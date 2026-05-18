<?php
namespace App\Repositories;

use PDO;
use Exception;
use App\Contracts\Repositories\TransactionRepositoryInterface;

class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    public function getEventDetailsBySchedule(int $scheduleId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT e.name, e.price, et.name AS type_name
            FROM schedules s
            JOIN events e       ON s.event_id      = e.id
            JOIN event_types et ON s.event_type_id = et.id
            WHERE s.id = :sid LIMIT 1
        ");
        $stmt->execute([':sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findPendingByEmailAndSchedule(string $email, int $scheduleId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT t.* FROM transactions t
            INNER JOIN persons p ON t.person_id = p.id
            WHERE p.email = :email AND t.schedule_id = :sid AND t.payment_status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findApprovedByEmailAndSchedule(string $email, int $scheduleId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT t.*, COALESCE(es.status, 'no_subscription') AS subscription_status
            FROM transactions t
            INNER JOIN persons p ON t.person_id = p.id
            LEFT JOIN events_subscribed es
                ON es.payment_id = t.payment_id COLLATE utf8mb4_unicode_ci
            WHERE p.email = :email AND t.schedule_id = :sid AND t.payment_status = 'approved'
            ORDER BY t.updated_at DESC LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function reuseExistingPending(string $existingRef, string $newRef, ?int $personId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE transactions
            SET external_reference = :newRef, person_id = :pid, preference_id = NULL, updated_at = NOW()
            WHERE external_reference = :oldRef AND payment_status = 'pending'
        ");
        return $stmt->execute([':newRef' => $newRef, ':oldRef' => $existingRef, ':pid' => $personId]);
    }

    public function createPending(int $scheduleId, ?int $personId, float $amount, string $externalRef): bool
    {
        $stmt = $this->conn->prepare("
            INSERT INTO transactions (schedule_id, person_id, external_reference, amount, payment_status)
            VALUES (:sid, :pid, :ref, :amount, 'pending')
        ");
        return $stmt->execute([
            ':sid'    => $scheduleId,
            ':pid'    => $personId,
            ':ref'    => $externalRef,
            ':amount' => $amount,
        ]);
    }

    public function updatePreferenceId(string $externalRef, string $preferenceId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE transactions SET preference_id = :pref WHERE external_reference = :ref
        ");
        return $stmt->execute([':pref' => $preferenceId, ':ref' => $externalRef]);
    }

    public function updatePaymentStatus(string $paymentId, string $externalRef, string $status): void
    {
        $this->conn->prepare("
            UPDATE transactions
            SET payment_id = :pid, payment_status = :status, updated_at = NOW()
            WHERE external_reference = :ref
        ")->execute([':pid' => $paymentId, ':status' => $status, ':ref' => $externalRef]);
    }

    public function confirmPayment(string $paymentId, string $externalRef): bool
    {
        try {
            $this->conn->beginTransaction();

            $exists = $this->conn->prepare("
                SELECT id FROM events_subscribed WHERE payment_id = :payid LIMIT 1
            ");
            $exists->execute([':payid' => $paymentId]);
            if ($exists->fetch()) {
                $this->conn->rollBack();
                return false;
            }

            $info = $this->conn->prepare("
                SELECT person_id, schedule_id FROM transactions
                WHERE external_reference = :ref LIMIT 1
            ");
            $info->execute([':ref' => $externalRef]);
            $tx = $info->fetch(PDO::FETCH_ASSOC);

            if (!$tx) {
                throw new Exception("Transação não encontrada: {$externalRef}");
            }

            $this->conn->prepare("
                INSERT INTO events_subscribed (person_id, schedule_id, payment_id, status)
                VALUES (:pid, :sid, :payid, 'pending')
            ")->execute([
                ':pid'   => $tx['person_id'],
                ':sid'   => $tx['schedule_id'],
                ':payid' => $paymentId,
            ]);

            $stmtVaga = $this->conn->prepare("
                UPDATE schedules SET vacancies = vacancies - 1
                WHERE id = :sid AND vacancies > 0
            ");
            $stmtVaga->execute([':sid' => $tx['schedule_id']]);

            if ($stmtVaga->rowCount() === 0) {
                throw new Exception("Sem vagas para schedule_id={$tx['schedule_id']}");
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("ERRO confirmPayment [{$externalRef}]: " . $e->getMessage());
            return false;
        }
    }

    public function confirmSubscription(string $paymentId): void
    {
        $this->conn->prepare("
            UPDATE events_subscribed SET status = 'confirmed' WHERE payment_id = :payid
        ")->execute([':payid' => $paymentId]);
    }

    public function linkPersonToPayment(string $paymentId, int $personId): void
    {
        $this->conn->prepare("
            UPDATE transactions SET person_id = :pid WHERE payment_id = :payid
        ")->execute([':pid' => $personId, ':payid' => $paymentId]);

        $this->conn->prepare("
            UPDATE events_subscribed SET person_id = :pid WHERE payment_id = :payid
        ")->execute([':pid' => $personId, ':payid' => $paymentId]);
    }

    public function findPersonIdByEmail(string $email): ?int
    {
        $stmt = $this->conn->prepare("SELECT id FROM persons WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }

    public function getPaidPendingRegistrations(string $email): array
    {
        $stmt = $this->conn->prepare("
            SELECT t.payment_id, t.schedule_id, t.person_id,
                e.name AS event_name, s.scheduled_at
            FROM transactions t
            INNER JOIN persons p          ON t.person_id   = p.id
            JOIN schedules s              ON t.schedule_id = s.id
            JOIN events e                 ON s.event_id    = e.id
            JOIN events_subscribed es     ON es.payment_id = t.payment_id
            WHERE p.email          = :email
            AND t.payment_status = 'approved'
            AND s.scheduled_at  >= NOW()
            AND es.status        = 'pending'
            AND NOT EXISTS (SELECT 1 FROM anamnesis a WHERE a.subscribed_id = es.id)
            ORDER BY s.scheduled_at ASC
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validatePaymentById(string $paymentId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                t.payment_id, t.payment_status, t.schedule_id,
                s.scheduled_at, s.duration_minutes,
                e.name AS event_name, e.price AS event_price,
                et.name AS type_name, u.name AS unit_name,
                COALESCE(es.status, 'no_subscription') AS subscription_status,
                es.id AS subscribed_id
            FROM transactions t
            INNER JOIN schedules s    ON t.schedule_id   = s.id
            INNER JOIN events e       ON s.event_id      = e.id
            INNER JOIN event_types et ON s.event_type_id = et.id
            INNER JOIN units u        ON s.unit_id       = u.id
            LEFT JOIN events_subscribed es
                ON es.payment_id = t.payment_id COLLATE utf8mb4_unicode_ci
            WHERE t.payment_id = :payid AND t.payment_status = 'approved'
            LIMIT 1
        ");
        $stmt->execute([':payid' => $paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteStalePending(): int
    {
        $minutes = 60;

        $stmt = $this->conn->prepare("
            DELETE FROM transactions
            WHERE payment_status = 'pending'
            AND created_at <= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->execute([':minutes' => $minutes]);
        $deleted = $stmt->rowCount();

        $this->conn->prepare("
            DELETE FROM persons
            WHERE status = 'pending'
            AND created_at <= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            AND id NOT IN (SELECT DISTINCT person_id FROM transactions WHERE person_id IS NOT NULL)
        ")->execute([':minutes' => $minutes]);

        return $deleted;
    }

    public function getTotalRevenue(): float
    {
        $result = $this->conn->query(
            "SELECT SUM(amount) AS total FROM transactions WHERE payment_status = 'approved'"
        )->fetch(PDO::FETCH_ASSOC);
        return (float) ($result['total'] ?? 0);
    }

    public function getTransactionsReport(): array
    {
        return $this->conn->query("
            SELECT t.*, p.full_name, p.email
            FROM transactions t
            LEFT JOIN persons p ON t.person_id = p.id
            ORDER BY t.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
