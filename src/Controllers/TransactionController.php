<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Transaction as TransactionModel;
use Exception;

class TransactionController {
    
    private $transactionModel;

    public function __construct() {
        $this->transactionModel = new TransactionModel();
    }

    public function createPayment(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;

        if (!$scheduleId || !$email) {
            return $this->jsonResponse($response, ["error" => "Dados insuficientes."], 400);
        }

        // Token do .env (Sempre use trim para evitar espaços em branco fatais)
        $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

        try {
            // 1. Busca dados via Model
            $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);

            if (!$event) {
                return $this->jsonResponse($response, ["error" => "Agendamento não encontrado."], 404);
            }

           $description = "Inscrição: " . $event['name'] . " (" . $event['type_name'] . ") - Unidade: " . $event['unit_name'];

                $preferenceData = [
                    "items" => [[
                        "title"       => $description,
                        "quantity"    => 1,
                        "unit_price"  => (float)$event['price'],
                        "currency_id" => "BRL"
                    ]],
                    "payer" => ["email" => (string)$email],
                    // External reference carrega o schedule_id para o Webhook saber qual vaga baixar
                    "external_reference" => "FP-" . time() . "-" . $scheduleId, 
                    "metadata" => [
                        "schedule_id" => $scheduleId,
                        "event_name"  => $event['name'],
                        "unit_name"   => $event['unit_name']
                    ],
                    "back_urls" => [
                        "success" => "https://misturadeluz.com/agenda/success", // Ajuste para sua URL real
                        "failure" => "https://misturadeluz.com/agenda/failure",
                        "pending" => "https://misturadeluz.com/agenda/pending"
                    ],
                    "auto_return" => "approved"
                ];

            // 3. CHAMADA À API (Centralizada)
            $mp = $this->callMercadoPagoAPI($accessToken, $preferenceData);

            if (isset($mp['init_point'])) {
                return $this->jsonResponse($response, ["init_point" => $mp['init_point']]);
            } 
            
            return $this->jsonResponse($response, ["error" => "MP: " . ($mp['message'] ?? 'Erro API')], 400);

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Encapsula o cURL para o código ficar limpo
     */
    private function callMercadoPagoAPI($token, $payload) {
        $ch = curl_init("https://api.mercadopago.com/checkout/preferences");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public function webhook(Request $request, Response $response) {
        // 1. Captura de parâmetros iniciais
        $params = $request->getQueryParams();
        $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

        // O Mercado Pago envia o ID da operação de formas diferentes conforme a versão
        $paymentId = $params['data_id'] ?? $params['id'] ?? null;
        $type = $params['type'] ?? ($params['topic'] ?? null);

        // Só processamos se for uma notificação de "payment"
        if ($paymentId && ($type === 'payment' || $params['topic'] === 'payment' || isset($params['data_id']))) {
            
            // 2. CONSULTA DE SEGURANÇA (Anti-Fraude)
            // Perguntamos diretamente à API do Mercado Pago se esse pagamento é real
            $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $accessToken
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $result = curl_exec($ch);
            $paymentData = json_decode($result, true);
            curl_close($ch);

            // 3. PROCESSAMENTO DOS DADOS RETORNADOS
            if (isset($paymentData['status'])) {
                $status = $paymentData['status'];
                $externalReference = $paymentData['external_reference'] ?? '';
                $payerEmail = $paymentData['payer']['email'] ?? '';
                
                // Extraímos o schedule_id do external_reference (FP-timestamp-ID)
                $parts = explode('-', $externalReference);
                $scheduleIdFromMP = end($parts); 

                if ($status === 'approved') {
                    // 4. RESERVA ESTRITA DA VAGA
                    // Chama o Model para gravar a transação e baixar a vaga (-1)
                    $success = $this->transactionModel->confirmPaymentAndReserveVacancy(
                        $externalReference, 
                        $status, 
                        $paymentId, 
                        $scheduleIdFromMP,
                        $payerEmail
                    );

                    if ($success) {
                        // 5. NOTIFICAÇÃO (Passo 4 do seu roteiro)
                        // Busca detalhes do evento (unidade, data, nome) para o e-mail
                        $eventData = $this->transactionModel->getEventDetailsBySchedule($scheduleIdFromMP);
                        
                        // Envia e-mails para Cliente e Vendedor
                        \App\Services\EmailService::sendPaymentConfirmation($payerEmail, $eventData);
                        
                        error_log("WEBHOOK: Vaga reservada e e-mails enviados para: " . $payerEmail);
                    }
                } else {
                    // Se o pagamento for negado, pendente ou cancelado
                    // Apenas atualizamos o log de status na tabela transactions
                    $this->transactionModel->updatePaymentStatus($externalReference, $status, $paymentId);
                    error_log("WEBHOOK: Status do pagamento " . $paymentId . " atualizado para: " . $status);
                }
            }
        }

        // O Mercado Pago exige retorno 200 ou 201 para confirmar o recebimento
        return $response->withStatus(200);
    }

    public function checkPayment(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;

        if (!$email || !$scheduleId) {
            return $this->jsonResponse($response, ["has_paid" => false, "error" => "Dados incompletos"], 400);
        }

        try {
            // Chamamos o Model para verificar na tabela transactions
            // Note: você precisará criar o método 'verifyPaidTransaction' no seu TransactionModel
            $hasPaid = $this->transactionModel->verifyPaidTransaction($email, $scheduleId);

            return $this->jsonResponse($response, [
                "has_paid" => $hasPaid,
                "message" => $hasPaid ? "Pagamento localizado!" : "Nenhum pagamento encontrado."
            ]);

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["has_paid" => false, "error" => $e->getMessage()], 500);
        }
    }
   
}