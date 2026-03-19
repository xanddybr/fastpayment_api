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
        // O BodyParsingMiddleware garante que isso seja um array
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;

        if (!$scheduleId || !$email) {
            return $this->jsonResponse($response, ["error" => "Incomplete data. Check email and schedule_id."], 400);
        }

        $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

        try {
            // 1. Busca detalhes do evento no Banco
            $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);

            if (!$event) {
                return $this->jsonResponse($response, ["error" => "Schedule not found in database."], 404);
            }

            $description = "Inscrição: " . $event['name'] . " (" . ($event['type_name'] ?? 'Geral') . ")";

            // 2. Setup de URLs dinâmicas
            $isLocal = ($_SERVER['HTTP_HOST'] === 'localhost:8080' || $_SERVER['REMOTE_ADDR'] === '127.0.0.1');
            
            $redirectBase = $isLocal 
                ? "http://localhost:5173" 
                : "https://misturadeluz.com/agenda";

            // Webhook precisa de URL acessível externamente (NGROK se for local)
            $webhookUrl = $isLocal 
                ? "https://SUA_URL_NGROK_AQUI.ngrok-free.app/api/webhook/mercadopago" 
                : "https://misturadeluz.com/agenda/api/public/webhook/mercadopago";

            // 3. Montagem da Preferência (CORRIGIDO PARA O MP)
           // No TransactionController.php, dentro de createPayment
            $preferenceData = [
                "items" => [
                    [
                        "title"       => "Inscricao Curso",
                        "quantity"    => 1,
                        "unit_price"  => (float)$event['price'],
                        "currency_id" => "BRL"
                    ]
                ],
                "payer" => [
                    "email" => (string)$email
                ],
                // ESTRUTURA COMPLETA E OBRIGATÓRIA
                "back_urls" => [
                    "success" => "https://misturadeluz.com/agenda/?status=success",
                    "failure" => "https://misturadeluz.com/agenda/?status=failure",
                    "pending" => "https://misturadeluz.com/agenda/?status=pending"
                ],
                "auto_return" => "approved",
                "external_reference" => "FP-" . time() . "-" . $scheduleId,
                "notification_url" => "https://misturadeluz.com/agenda/api/public/webhook/mercadopago",
                "binary_mode" => true // Força aprovação ou reprovação imediata, sem "pendente"
            ];

            // 4. Chamada para API do Mercado Pago
            $mpResponse = $this->callMercadoPagoAPI($accessToken, $preferenceData);

            if (isset($mpResponse['init_point'])) {
                return $this->jsonResponse($response, [
                    "init_point" => $mpResponse['init_point'],
                    "preference_id" => $mpResponse['id']
                ]);
            } 
            
            // Se o MP retornar erro, pegamos a mensagem detalhada
            $errorMsg = $mpResponse['message'] ?? ($mpResponse['error'] ?? 'Unknown MP Error');
            return $this->jsonResponse($response, ["error" => "Mercado Pago: " . $errorMsg], 400);

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
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
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;

        if (!$email || !$scheduleId) {
            return $this->jsonResponse($response, ["has_paid" => false, "error" => "Data missing"], 400);
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