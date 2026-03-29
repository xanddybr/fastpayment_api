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

    /**
     * Gera a preferência no Mercado Pago e grava transação pendente
     */
    public function createPayment(Request $request, Response $response) {
        $data = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true);
        
        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;
        $personId = $_SESSION['user_id'] ?? null; // Pega da sessão se existir

        $urlFront = "https://536b-2804-d41-ec16-4800-b3ef-38f2-330b-ed69.ngrok-free.app/agenda";

        // Referência única: FP + Timestamp + ID da Vaga
        $externalRef = "FP-" . time() . "-" . $scheduleId;

        try {
            $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);
            $price = (float)$event['price'];

            // GRAVA NO BANCO (Nomes de colunas agora batem com seu CREATE TABLE)
            $this->transactionModel->createPendingTransaction($scheduleId, $personId, $email, $price, $externalRef);

            $preferenceData = [
                "items" => [[
                    "title" => mb_strcut($event['type_name'] . ": " . $event['name'], 0, 250),
                    "quantity" => 1,
                    "unit_price" => $price,
                    "currency_id" => "BRL"
                ]],
                "payer" => ["email" => $email],
                "external_reference" => $externalRef,
                "notification_url" => "https://536b-2804-d41-ec16-4800-b3ef-38f2-330b-ed69.ngrok-free.app/api/webhook/mercadopago",
                "auto_return" => "approved",
                "back_urls" => [
                    "success" => $urlFront
                ]
            ];

            $mpResponse = $this->callMercadoPagoAPI($_ENV['MP_ACCESS_TOKEN'], $preferenceData);

            if (isset($mpResponse['id'])) {
                // ATUALIZA O PREFERENCE_ID
                $this->transactionModel->updatePreferenceId($externalRef, $mpResponse['id']);
                return $this->jsonResponse($response, ["init_point" => $mpResponse['init_point']]);
            }
            
            throw new \Exception("Erro ao gerar preferência no Mercado Pago.");

        } catch (\Exception $e) {
            error_log("ERRO CREATE_PAYMENT: " . $e->getMessage());
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

   
    public function webhook(Request $request, Response $response): Response {
        $payload = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true);
        
        // O MP manda o external_reference na consulta detalhada ou no payload se for merchant_order
        $paymentId = $payload['data']['id'] ?? ($payload['id'] ?? null);

        if ($paymentId) {
            // Buscamos a verdade no MP
            $paymentData = $this->getPaymentDetailsFromMP($paymentId);
            $extRef = $paymentData['external_reference'] ?? null;

            if ($extRef && $paymentData['status'] === 'approved') {
                // USAMOS A PONTE PARA DAR BAIXA
                $this->transactionModel->confirmPayment($paymentId, $extRef);
            }
        }

        return $response->withStatus(200);
    }

    /**
     * Verifica se um e-mail possui pagamentos aprovados sem inscrição vinculada
     */
    public function checkPayment(Request $request, Response $response) {
        $data = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true);
        $email = isset($data['email']) ? trim($data['email']) : null;

        if (!$email) {
            return $this->jsonResponse($response, ["has_paid" => false, "error" => "E-mail não fornecido"], 400);
        }

        try {
            // Busca no Model as pendências (considerando trava de data/hora do evento)
            $pendencias = $this->transactionModel->getPaidPendingRegistrations($email);

            return $this->jsonResponse($response, [
                "has_paid" => count($pendencias) > 0,
                "pendencias" => $pendencias
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    private function getPaymentDetailsFromMP($paymentId) {
        $token = $_ENV['MP_ACCESS_TOKEN'];
        $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $token
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    /**
     * Comunicação direta com a API do Mercado Pago
     */
    private function callMercadoPagoAPI($token, $payload) {
        $ch = curl_init("https://api.mercadopago.com/checkout/preferences");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json", 
                "Authorization: Bearer " . $token
            ],
            CURLOPT_SSL_VERIFYPEER => false // Em produção, altere para true se tiver certificados SSL
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    /**
     * Auxiliar para respostas JSON
     */
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}