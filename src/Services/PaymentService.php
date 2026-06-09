<?php
namespace App\Services;

use Exception;
use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\PaymentServiceInterface;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepo,
        private PaymentGatewayInterface $gateway,
        private string $appUrl
    ) {}

    public function createPayment(string $email, int $scheduleId, ?int $personId, bool $forceNew): array
    {
        $deleted = $this->transactionRepo->deleteStalePending();
        if ($deleted > 0) {
            error_log("SANITIZE: {$deleted} transação(ões) expirada(s) deletada(s)");
        }

        if (!$forceNew) {
            $approved = $this->transactionRepo->findApprovedByEmailAndSchedule($email, $scheduleId);
            if ($approved) {
                $subStatus = $approved['subscription_status'] ?? null;
                if ($subStatus === 'confirmed') {
                    throw new \DomainException('ja_inscrito');
                }
                if ($subStatus === 'pending') {
                    throw new \DomainException('inscricao_pendente:' . $approved['payment_id']);
                }
            }
        }

        if (!$personId) {
            $personId = $this->transactionRepo->findPersonIdByEmail($email);
        }

        if (!$personId) {
            throw new \DomainException('pessoa_nao_encontrada');
        }

        $externalRef = 'FP-' . time() . '-' . $scheduleId;
        $urlBase     = rtrim($this->appUrl, '/');

        $pending = $this->transactionRepo->findPendingByEmailAndSchedule($email, $scheduleId);
        if ($pending) {
            $this->transactionRepo->reuseExistingPending($pending['external_reference'], $externalRef, $personId);
        }

        $event = $this->transactionRepo->getEventDetailsBySchedule($scheduleId);
        if (!$event) {
            throw new Exception('Agendamento não encontrado');
        }
        $price = (float) $event['price'];

        if (!$pending) {
            $this->transactionRepo->createPending($scheduleId, $personId, $price, $externalRef);
        }

        $isLocalDev = !empty($_ENV['FRONT_URL']);

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
            'back_urls'          => ['success' => $urlBase . '/'],
        ];

        if (!$isLocalDev) {
            $preferenceData['auto_return'] = 'approved';
        }

        $mpResponse = $this->gateway->createPreference($preferenceData);

        if (!isset($mpResponse['id'])) {
            throw new Exception('Erro ao gerar preferência no Mercado Pago.');
        }

        $this->transactionRepo->updatePreferenceId($externalRef, $mpResponse['id']);
        return ['init_point' => $mpResponse['init_point']];
    }

    public function processWebhook(string $paymentId, string $topic): void
    {
        if ($topic === 'merchant_order') {
            return;
        }

        $paymentData = $this->gateway->getPaymentDetails($paymentId);
        $extRef      = $paymentData['external_reference'] ?? null;
        $status      = $paymentData['status']             ?? null;
        $payerEmail  = $paymentData['payer']['email']     ?? null;

        error_log("WEBHOOK MP | status={$status} | extRef={$extRef} | payer={$payerEmail}");

        if (!$extRef || !$status) {
            return;
        }

        $this->transactionRepo->updatePaymentStatus($paymentId, $extRef, $status);

        if ($status === 'approved') {
            $result = $this->transactionRepo->confirmPayment($paymentId, $extRef, $payerEmail);
            error_log("WEBHOOK confirmPayment | " . ($result ? 'OK' : 'JA_PROCESSADO'));
        }
    }

    public function cleanupPending(): int
    {
        return $this->transactionRepo->deleteStalePending();
    }

    public function checkPayment(string $email): array
    {
        return $this->transactionRepo->getPaidPendingRegistrations($email);
    }

    public function checkRejected(string $email, int $scheduleId): ?array
    {
        return $this->transactionRepo->findRejectedByEmailAndSchedule($email, $scheduleId);
    }

    public function validatePayment(string $paymentId): ?array
    {
        $row = $this->transactionRepo->validatePaymentById($paymentId);

        if (!$row) {
            // Webhook pode ainda não ter chegado — consulta o MP diretamente
            $mpData = $this->gateway->getPaymentDetails($paymentId);
            $status = $mpData['status'] ?? null;
            $extRef = $mpData['external_reference'] ?? null;

            error_log("validatePayment sync | paymentId={$paymentId} | mp_status={$status} | extRef={$extRef}");

            if ($status === 'approved' && $extRef) {
                $payerEmail = $mpData['payer']['email'] ?? null;
                $this->transactionRepo->updatePaymentStatus($paymentId, $extRef, $status);
                $this->transactionRepo->confirmPayment($paymentId, $extRef, $payerEmail);
                $row = $this->transactionRepo->validatePaymentById($paymentId);
            }
        }

        return $row;
    }
}
