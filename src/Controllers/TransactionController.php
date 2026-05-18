<?php
namespace App\Controllers;

use App\Contracts\Services\PaymentServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionController
{
    public function __construct(private PaymentServiceInterface $paymentService) {}

    public function createPayment(Request $request, Response $response): Response
    {
        $data       = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $email      = $data['email']       ?? null;
        $scheduleId = (int) ($data['schedule_id'] ?? 0);
        $personId   = $data['person_id']   ?? ($_SESSION['user_id'] ?? null);
        $forceNew   = !empty($data['force_new']);

        if (!$email || !$scheduleId) {
            return $this->json($response, ['error' => 'email e schedule_id são obrigatórios'], 400);
        }

        try {
            $result = $this->paymentService->createPayment($email, $scheduleId, $personId ? (int) $personId : null, $forceNew);
            return $this->json($response, $result);

        } catch (\DomainException $e) {
            $parts = explode(':', $e->getMessage(), 2);
            $code  = $parts[0];

            if ($code === 'ja_inscrito') {
                return $this->json($response, [
                    'error'    => 'ja_inscrito',
                    'mensagem' => 'Você já esta inscrito para este evento!',
                ], 409);
            }
            if ($code === 'inscricao_pendente') {
                return $this->json($response, [
                    'error'      => 'inscricao_pendente',
                    'mensagem'   => 'Você já realizou o pagamento. Por favor, conclua sua inscrição.',
                    'payment_id' => $parts[1] ?? null,
                    'schedule_id' => $scheduleId,
                ], 402);
            }
            return $this->json($response, ['error' => $e->getMessage()], 400);

        } catch (\Exception $e) {
            error_log('ERRO CREATE_PAYMENT: ' . $e->getMessage());
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function validatePayment(Request $request, Response $response): Response
    {
        $data      = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $paymentId = $data['payment_id'] ?? null;

        if (!$paymentId) {
            return $this->json($response, ['error' => 'payment_id é obrigatório'], 400);
        }

        try {
            $row = $this->paymentService->validatePayment($paymentId);
            if (!$row) {
                return $this->json($response, ['error' => 'Pagamento não encontrado ou não aprovado'], 404);
            }
            return $this->json($response, [
                'valid'               => true,
                'subscription_status' => $row['subscription_status'],
                'event'               => [
                    'schedule_id'      => $row['schedule_id'],
                    'event_name'       => $row['event_name'],
                    'event_price'      => $row['event_price'],
                    'type_name'        => $row['type_name'],
                    'unit_name'        => $row['unit_name'],
                    'scheduled_at'     => $row['scheduled_at'],
                    'duration_minutes' => $row['duration_minutes'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'Erro ao validar pagamento'], 500);
        }
    }

    public function webhook(Request $request, Response $response): Response
    {
        $body        = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $queryParams = $request->getQueryParams();

        $paymentId = $body['data']['id'] ?? $queryParams['id'] ?? $body['resource'] ?? null;
        $topic     = $queryParams['topic'] ?? $body['type'] ?? null;

        error_log("WEBHOOK | topic={$topic} | paymentId={$paymentId}");

        if ($paymentId) {
            $this->paymentService->processWebhook($paymentId, (string) $topic);
        }

        return $response->withStatus(200);
    }

    public function cleanupPendingTransactions(Request $request, Response $response): Response
    {
        try {
            $deleted = $this->paymentService->cleanupPending();
            return $this->json($response, [
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} transação(ões) pendente(s) expirada(s) removida(s).",
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function checkPayment(Request $request, Response $response): Response
    {
        $data  = $request->getParsedBody() ?? json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->json($response, ['has_paid' => false, 'error' => 'E-mail não fornecido'], 400);
        }

        try {
            $pendencias = $this->paymentService->checkPayment($email);
            return $this->json($response, [
                'has_paid'   => count($pendencias) > 0,
                'pendencias' => $pendencias,
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
