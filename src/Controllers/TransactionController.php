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

    // Validação inicial básica
    if (!$scheduleId || !$email) {
        return $this->jsonResponse($response, [
            "error" => "Dados incompletos (E-mail ou ID).", 
            "recebido" => $data
        ], 400);
    }

    $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

    try {
        // 1. Busca detalhes do evento no banco para montar o item
        $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);
        if (!$event) {
            return $this->jsonResponse($response, ["error" => "Evento não encontrado no banco."], 404);
        }

        // 2. Prepara Títulos e Preço
        $fullTitle = ($event['type_name'] ?? 'Evento') . ": " . $event['name'];
        $eventDescription = $event['type_name'] ?? 'Inscrição FastPayment';
        $price = (float)number_format($event['price'], 2, '.', '');

        // 3. URLs do Ngrok (Atualizadas conforme seu teste)
        $ngrokUrl = "https://d121-2804-d41-ec16-4800-c1f0-9729-e644-d486.ngrok-free.app";

        $preferenceData = [
            "items" => [
                [
                    "title"       => mb_strcut((string)$fullTitle, 0, 250),
                    "description" => mb_strcut((string)$eventDescription, 0, 250),
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

        // 4. Chamada para API do Mercado Pago para gerar a preferência
        $mpResponse = $this->callMercadoPagoAPI($accessToken, $preferenceData);

        // --- VALIDAÇÃO E GRAVAÇÃO NO BANCO ---
        if (isset($mpResponse['id'])) {
            
            // ETAPA 1 DA NOITE: Gravar a transação como 'pending' no banco local
            // Passamos o ID da preferência do MP para vincular depois no webhook
            $this->transactionModel->createPendingTransaction(
                $scheduleId, 
                $email, 
                $mpResponse['id'], 
                $price
            );

            // Define o link de redirecionamento (Sandbox para o usuário de teste)
            $link = ($email === $emailTeste) ? $mpResponse['sandbox_init_point'] : $mpResponse['init_point'];

            return $this->jsonResponse($response, [
                "status" => "success",
                "init_point" => $link,
                "preference_id" => $mpResponse['id']
            ]);
        } 

        // Se o Mercado Pago retornar erro de validação (ex: items invalidos)
        return $this->jsonResponse($response, [
            "error" => "Mercado Pago Rejeitou a Requisição",
            "mp_response" => $mpResponse, 
            "sent_data" => $preferenceData
        ], 400);

    } catch (Exception $e) {
        return $this->jsonResponse($response, ["error" => "Erro interno: " . $e->getMessage()], 500);
    }
}

    public function webhook(Request $request, Response $response) {
        $data = $request->getParsedBody();
        
        // Captura os dados do Postman ou da URL
        $status = $data['status'] ?? 'approved';
        $paymentId = $data['data']['id'] ?? null;
        $externalReference = $data['external_reference'] ?? '';
        $payerEmail = $data['payer']['email'] ?? '';

        // Extrai o ID do final: "FP-timestamp-27" -> 27
        $parts = explode('-', $externalReference);
        $scheduleIdFromMP = end($parts); 

        // CHAMA O MODEL E PEGA O RESULTADO
        $success = $this->transactionModel->confirmPaymentAndReserveVacancy(
            $externalReference, 
            $status, 
            $paymentId, 
            $scheduleIdFromMP,
            $payerEmail
        );

        // RETORNA O RESULTADO PARA O POSTMAN VER
        return $this->jsonResponse($response, [
            "webhook_recebido" => true,
            "processado_no_model" => $success, // Se for FALSE, o erro está no Model
            "dados_identificados" => [
                "payment_id" => $paymentId,
                "schedule_id" => $scheduleIdFromMP,
                "status" => $status
            ]
        ], $success ? 200 : 400); // Se falhar, retorna erro 400
    }


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