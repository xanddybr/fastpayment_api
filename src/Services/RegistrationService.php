<?php
namespace App\Services;

use Exception;
use PDO;
use App\Contracts\Repositories\PersonRepositoryInterface;
use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Contracts\Services\RegistrationServiceInterface;

class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        private PersonRepositoryInterface $personRepo,
        private TransactionRepositoryInterface $transactionRepo,
        private PDO $conn
    ) {}

    public function register(array $data): array
    {
        if (empty($data['schedule_id']) || empty($data['payment_id'])) {
            throw new \InvalidArgumentException('schedule_id e payment_id são obrigatórios.');
        }

        $this->conn->beginTransaction();
        try {
            $personId = $this->personRepo->saveCompleteRegistration($data);
            $this->transactionRepo->linkPersonToPayment($data['payment_id'], $personId);

            $stmt = $this->conn->prepare("
                SELECT id FROM events_subscribed WHERE payment_id = :payid LIMIT 1
            ");
            $stmt->execute([':payid' => $data['payment_id']]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscription) {
                throw new Exception('Inscrição não encontrada. O pagamento pode ainda estar sendo processado.');
            }

            $this->personRepo->createAnamnesis($subscription['id'], $data);
            $this->transactionRepo->confirmSubscription($data['payment_id']);

            $this->conn->commit();
            return ['subscribed_id' => $subscription['id']];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function getAllSubscribers(): array
    {
        return $this->personRepo->getAllSubscribers();
    }

    public function getTotalRevenue(): float
    {
        return $this->transactionRepo->getTotalRevenue();
    }

    public function getPaymentHistory(): array
    {
        return $this->transactionRepo->getTransactionsReport();
    }
}
