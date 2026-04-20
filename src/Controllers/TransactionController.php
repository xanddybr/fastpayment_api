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
     * Gera a preferência no Mercado Pago e grava transação pendente.
     * O frontend envia: email, schedule_id, person_id (opcional — ainda pode ser null)
     */
    public function createPayment(Request $request, Response $response) {
        $data       = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $email      = $data['email']       ?? null;
        $scheduleId = $data['schedule_id'] ?? null;
        $personId   = $data['person_id']   ?? ($_SESSION['user_id'] ?? null);

        if (!$email || !$scheduleId) {
            return $this->jsonResponse($response, ['error' => 'email e schedule_id são obrigatórios'], 400);
        }

        $urlBase     = 'https://fc7f-2804-d41-ec16-4800-d8cd-8cf1-3950-cb49.ngrok-free.app';
        $externalRef = 'FP-' . time() . '-' . $scheduleId;

        try {
            $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);
            if (!$event) {
                return $this->jsonResponse($response, ['error' => 'Agendamento não encontrado'], 404);
            }
            $price = (float) $event['price'];

            $this->transactionModel->createPendingTransaction(
                $scheduleId, $personId, $email, $price, $externalRef
            );

            $preferenceData = [
                'items' => [[
                    'title'       => mb_strcut($event['type_name'] . ': ' . $event['name'], 0, 250),
                    'quantity'    => 1,
                    'unit_price'  => $price,
                    'currency_id' => 'BRL',
                ]],
                'payer'              => ['email' => $email],
                'external_reference' => $externalRef,
                'notification_url'   => $urlBase . '/api/payment/webhook',
                'auto_return'        => 'approved',
                'back_urls'          => ['success' => $urlBase . '/beta'],
            ];

            $mpResponse = $this->callMercadoPagoAPI($_ENV['MP_ACCESS_TOKEN'], $preferenceData);

            if (!isset($mpResponse['id'])) {
                throw new Exception('Erro ao gerar preferência no Mercado Pago.');
            }

            $this->transactionModel->updatePreferenceId($externalRef, $mpResponse['id']);
            return $this->jsonResponse($response, ['init_point' => $mpResponse['init_point']]);

        } catch (Exception $e) {
            error_log('ERRO CREATE_PAYMENT: ' . $e->getMessage());
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook do Mercado Pago — chamada server-to-server, sem sessão.
     * Apenas confirma o pagamento no model. Sempre retorna 200 para o MP.
     */
    public function webhook(Request $request, Response $response): Response {
    $body        = $request->getParsedBody() 
                ?? json_decode(file_get_contents('php://input'), true) 
                ?? [];
    $queryParams = $request->getQueryParams();

    // Trata formato novo (data.id no body) e formato antigo (?id= query param)
    $paymentId = $body['data']['id']   // formato novo
              ?? $queryParams['id']    // formato antigo query param ✅
              ?? $body['resource']     // formato antigo body
              ?? null;

    $topic = $queryParams['topic'] ?? $body['type'] ?? null;

    error_log("WEBHOOK | topic={$topic} | paymentId={$paymentId}");

    // Ignora merchant_order — só processa payment
    if ($paymentId && $topic !== 'merchant_order') {
        $paymentData = $this->getPaymentDetailsFromMP($paymentId);
        $extRef      = $paymentData['external_reference'] ?? null;
        $status      = $paymentData['status']             ?? null;

        error_log("WEBHOOK MP | status={$status} | extRef={$extRef}");

        if ($extRef && $status === 'approved') {
            $result = $this->transactionModel->confirmPayment($paymentId, $extRef);
            error_log("WEBHOOK confirmPayment | " . ($result ? 'OK' : 'JA_PROCESSADO'));
        }
    }

    return $response->withStatus(200);
}

    /**
     * Verifica se o e-mail tem pagamento aprovado sem inscrição finalizada.
     * Usado no frontend pós-pagamento para decidir se exibe o formulário.
     */
    public function checkPayment(Request $request, Response $response) {
        $data  = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->jsonResponse($response, ['has_paid' => false, 'error' => 'E-mail não fornecido'], 400);
        }

        try {
            $pendencias = $this->transactionModel->getPaidPendingRegistrations($email);
            return $this->jsonResponse($response, [
                'has_paid'   => count($pendencias) > 0,
                'pendencias' => $pendencias,
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function getPaymentDetailsFromMP($paymentId): array {
        $ch = curl_init('https://api.mercadopago.com/v1/payments/' . $paymentId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $_ENV['MP_ACCESS_TOKEN']],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true) ?? [];
    }

    private function callMercadoPagoAPI($token, $payload): array {
        $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true) ?? [];
    }

    private function jsonResponse(Response $response, $data, $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}