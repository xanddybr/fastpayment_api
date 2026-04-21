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
     * Gera a preferência no MP e grava/reutiliza transação.
     *
     * Regras antes de criar:
     *  1. Sanitiza pendentes expirados automaticamente
     *  2. approved + subscription confirmed → bloqueia, pergunta se quer nova compra
     *  3. approved + subscription pending   → redireciona para o formulário
     *  4. pending existente                 → reutiliza a linha
     *  5. nada encontrado                   → cria normalmente
     *
     * force_new=true → ignora regras 2 e 3, cria nova compra diretamente
     */
    public function createPayment(Request $request, Response $response) {
        $data       = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $email      = $data['email']       ?? null;
        $scheduleId = $data['schedule_id'] ?? null;
        $personId   = $data['person_id']   ?? ($_SESSION['user_id'] ?? null);
        $forceNew   = !empty($data['force_new']);

        if (!$email || !$scheduleId) {
            return $this->jsonResponse($response, ['error' => 'email e schedule_id são obrigatórios'], 400);
        }

        $urlBase     = "https://fc7f-2804-d41-ec16-4800-d8cd-8cf1-3950-cb49.ngrok-free.app";
        $externalRef = 'FP-' . time() . '-' . $scheduleId;

        try {
            // 1. Sanitização automática
            $deleted = $this->transactionModel->deleteStalePendingTransactions();
            if ($deleted > 0) {
                error_log("SANITIZE: {$deleted} transação(ões) expirada(s) deletada(s)");
            }

            // 2 e 3. Verifica approved existente (ignora se force_new)
            if (!$forceNew) {
                $approved = $this->transactionModel->findApprovedByEmailAndSchedule($email, $scheduleId);

                if ($approved) {
                    $subStatus = $approved['subscription_status'] ?? null;

                    // approved + confirmed → já comprou e inscreveu
                   if ($subStatus === 'confirmed') {
    return $this->jsonResponse($response, [
        'error'                  => 'ja_inscrito',
        'mensagem'               => 'Você já realizou a compra e a inscrição para este evento. Deseja realizar outra?',
        'pode_comprar_novamente' => true,
    ], 409);
}

// approved + pending ou sem inscrição → redireciona para o formulário
if ($subStatus === 'pending' || $subStatus === 'no_subscription') {
    return $this->jsonResponse($response, [
        'error'       => 'inscricao_pendente',
        'mensagem'    => 'Você já realizou o pagamento. Por favor, conclua sua inscrição.',
        'payment_id'  => $approved['payment_id'],
        'schedule_id' => $scheduleId,
    ], 402);
}
                }
            }

            // 4. Pending existente → reutiliza
            $pending = $this->transactionModel->findPendingByEmailAndSchedule($email, $scheduleId);
            if ($pending) {
                $this->transactionModel->reuseExistingPendingTransaction(
                    $pending['external_reference'],
                    $externalRef
                );
                error_log("REUSE: pendente reutilizado | email={$email} schedule={$scheduleId}");
            }

            // 5. Busca dados do evento
            $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);
            if (!$event) {
                return $this->jsonResponse($response, ['error' => 'Agendamento não encontrado'], 404);
            }
            $price = (float) $event['price'];

            // Cria nova transação apenas se não havia pending
            if (!$pending) {
                $this->transactionModel->createPendingTransaction(
                    $scheduleId, $personId, $email, $price, $externalRef
                );
            }

            // Gera preferência no MP
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

            error_log("MP_PREFERENCE_REQUEST: " . json_encode($preferenceData));
            error_log("MP_PREFERENCE_RESPONSE: " . json_encode($mpResponse));

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
     * Webhook do MP — trata formato novo e antigo.
     *
     * Para qualquer status: atualiza payment_id e payment_status na transaction.
     * Se approved: insere events_subscribed com status 'pending' e decrementa vaga.
     */
    public function webhook(Request $request, Response $response): Response {
        $body        = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $queryParams = $request->getQueryParams();

        $paymentId = $body['data']['id']
                  ?? $queryParams['id']
                  ?? $body['resource']
                  ?? null;

        $topic = $queryParams['topic'] ?? $body['type'] ?? null;

        error_log("WEBHOOK | topic={$topic} | paymentId={$paymentId}");

        if ($paymentId && $topic !== 'merchant_order') {
            $paymentData = $this->getPaymentDetailsFromMP($paymentId);
            $extRef      = $paymentData['external_reference'] ?? null;
            $status      = $paymentData['status']             ?? null;

            error_log("WEBHOOK MP | status={$status} | extRef={$extRef}");

            if ($extRef && $status) {
                // Sempre atualiza payment_id e status — sem condição
                $this->transactionModel->updatePaymentStatus($paymentId, $extRef, $status);

                // Se approved: cria events_subscribed(pending) e decrementa vaga
                if ($status === 'approved') {
                    $result = $this->transactionModel->confirmPayment($paymentId, $extRef);
                    error_log("WEBHOOK confirmPayment | " . ($result ? 'OK' : 'JA_PROCESSADO'));
                }
            }
        }

        return $response->withStatus(200);
    }

    /**
     * GET /api/cron/transactions-cleanup
     */
    public function cleanupPendingTransactions(Request $request, Response $response) {
        try {
            $deleted = $this->transactionModel->deleteStalePendingTransactions();
            error_log("CRON CLEANUP: {$deleted} transação(ões) deletada(s)");
            return $this->jsonResponse($response, [
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} transação(ões) pendente(s) expirada(s) removida(s).",
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verifica se o e-mail tem pagamento aprovado sem inscrição finalizada.
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