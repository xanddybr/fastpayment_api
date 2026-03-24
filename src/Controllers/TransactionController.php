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
     * Creates a payment preference in Mercado Pago
     */
    public function createPayment(Request $request, Response $response) {
    $data = $request->getParsedBody();
    
    // Fallback caso o body não venha parseado
    if (empty($data)) {
        $data = json_decode(file_get_contents('php://input'), true);
    }

    $email = isset($data['email']) ? trim($data['email']) : null;
    $scheduleId = $data['schedule_id'] ?? null;
    $emailTeste = "teste_user_2904943887590020914@testeuser.com";

    if (!$scheduleId || !$email) {
        return $this->jsonResponse($response, ["error" => "Dados incompletos (E-mail ou ID).", "recebido" => $data], 400);
    }

    $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

    try {
        // 1. Busca detalhes do evento
        $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);
        if (!$event) {
            return $this->jsonResponse($response, ["error" => "Evento não encontrado no banco."], 404);
        }

        // 2. Prepara Títulos e Preço (Garantindo Float)
        $fullTitle = ($event['type_name'] ?? 'Evento') . ": " . $event['name'];
        $eventDescription = $event['type_name'] ?? 'Inscrição FastPayment';
        $price = (float)number_format($event['price'], 2, '.', '');

        // 3. URLs FIXAS PARA TESTE (Substitua se o Ngrok mudar!)
        // Como você quer validar o fluxo, vamos travar na URL que você confirmou que funciona
        $ngrokUrl = "https://8b38-2804-d41-ec16-4800-1ee-ebef-d1d-54b1.ngrok-free.app";

        $preferenceData = [
            "items" => [
                [
                    "title"       => mb_strcut($fullTitle, 0, 250),
                    "description" => mb_strcut($eventDescription, 0, 250),
                    "quantity"    => 1,
                    "unit_price"  => $price,
                    "currency_id" => "BRL"
                ]
            ],
            "payer" => [
                "email" => $email
            ],
            "back_urls" => [
                "success" => $ngrokUrl . "/api/success",
                "failure" => $ngrokUrl . "/api/failure",
                "pending" => $ngrokUrl . "/api/success"
            ],
            "auto_return" => "approved",
            "external_reference" => "FP-" . time() . "-" . $scheduleId,
            "notification_url" => $ngrokUrl . "/api/webhook/mercadopago",
            "binary_mode" => true
        ];

        // 4. Chamada para API do Mercado Pago
        $mpResponse = $this->callMercadoPagoAPI($accessToken, $preferenceData);

        // --- VALIDAÇÃO ROBUSTA DA RESPOSTA ---
        if (isset($mpResponse['id'])) {
            // Se for e-mail de teste, usa sandbox_init_point, senão init_point
            $link = ($email === $emailTeste) ? $mpResponse['sandbox_init_point'] : $mpResponse['init_point'];

            return $this->jsonResponse($response, [
                "status" => "success",
                "init_point" => $link,
                "preference_id" => $mpResponse['id']
            ]);
        } 

        // Se chegou aqui, o MP recusou algo. Vamos retornar o erro real para o F12
        return $this->jsonResponse($response, [
            "error" => "Mercado Pago Rejeitou a Requisição",
            "mp_response" => $mpResponse, 
            "sent_data" => $preferenceData
        ], 400);

    } catch (Exception $e) {
        return $this->jsonResponse($response, ["error" => "Erro interno: " . $e->getMessage()], 500);
    }
}

    /**
     * Process Mercado Pago Webhook Notifications
     */
    public function webhook(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

        // IPN/Webhook logic: get payment ID
        $paymentId = $params['data_id'] ?? $params['id'] ?? null;
        $type = $params['type'] ?? ($params['topic'] ?? null);

        if ($paymentId && ($type === 'payment' || $params['topic'] === 'payment')) {
            
            // SECURITY CHECK: Verify payment with Mercado Pago API
            $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $accessToken]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $result = curl_exec($ch);
            $paymentData = json_decode($result, true);
            curl_close($ch);

            if (isset($paymentData['status'])) {
                $status = $paymentData['status'];
                $externalReference = $paymentData['external_reference'] ?? '';
                $payerEmail = $paymentData['payer']['email'] ?? '';
                
                // Extract schedule_id from external_reference (FP-timestamp-ID)
                $parts = explode('-', $externalReference);
                $scheduleIdFromMP = end($parts); 

                if ($status === 'approved') {
                    // RESERVE VACANCY AND SAVE TRANSACTION
                    $success = $this->transactionModel->confirmPaymentAndReserveVacancy(
                        $externalReference, 
                        $status, 
                        $paymentId, 
                        $scheduleIdFromMP,
                        $payerEmail
                    );

                    if ($success) {
                        // SEND CONFIRMATION EMAILS
                        $eventData = $this->transactionModel->getEventDetailsBySchedule($scheduleIdFromMP);
                        \App\Services\EmailService::sendPaymentConfirmation($payerEmail, $eventData);
                        error_log("WEBHOOK SUCCESS: Vacancy reserved for " . $payerEmail);
                    }
                } else {
                    $this->transactionModel->updatePaymentStatus($externalReference, $status, $paymentId);
                    error_log("WEBHOOK UPDATE: Payment " . $paymentId . " status: " . $status);
                }
            }
        }

        return $response->withStatus(200);
    }

    /**
     * Checks if a user has a pre-paid approved transaction
     */
    public function checkPayment(Request $request, Response $response) {
        // Tenta pegar o corpo já parseado ou lê o raw input
        $data = $request->getParsedBody();
        
        if (empty($data)) {
            $data = json_decode(file_get_contents('php://input'), true);
        }

        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;

        // Se ainda assim estiver vazio, retornamos o erro com o que foi recebido para debug
        if (!$email || !$scheduleId) {
            return $this->jsonResponse($response, [
                "error" => "Incomplete data",
                "received" => $data // Ajuda a ver o que chegou no F12
            ], 400);
        }

        try {
            $hasPaid = $this->transactionModel->verifyPaidTransaction($email, $scheduleId);
            return $this->jsonResponse($response, [
                "has_paid" => (bool)$hasPaid,
                "message" => $hasPaid ? "Payment confirmed." : "Waiting payment."
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["has_paid" => false, "error" => $e->getMessage()], 500);
        }
    }

    /**
     * CURL Helper for Mercado Pago API
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
}