<?php
namespace App\Models;

use PDO;
use Exception;

class Transaction extends BaseModel {

    /**
     * Busca detalhes do evento para montar a preferência no MP
     */
    public function getEventDetailsBySchedule($scheduleId) {
        $sql = "SELECT e.name, e.price, et.name AS type_name
                FROM schedules s
                JOIN events e       ON s.event_id      = e.id
                JOIN event_types et ON s.event_type_id = et.id
                WHERE s.id = :sid LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca transação PENDING para email + schedule
     */
    public function findPendingByEmailAndSchedule($email, $scheduleId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM transactions
            WHERE payer_email    = :email
              AND schedule_id    = :sid
              AND payment_status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca transação APPROVED para email + schedule.
     * Retorna também o status da inscrição em events_subscribed.
     *
     * subscription_status possíveis:
     *   'pending'   → pagou mas ainda não preencheu o formulário
     *   'confirmed' → inscrição totalmente concluída
     *   null        → events_subscribed ainda não criado (não deveria acontecer)
     */
    public function findApprovedByEmailAndSchedule($email, $scheduleId) {
        $stmt = $this->conn->prepare("
            SELECT t.*, es.status AS subscription_status
            FROM transactions t
            LEFT JOIN events_subscribed es ON es.payment_id = t.payment_id
            WHERE t.payer_email    = :email
              AND t.schedule_id    = :sid
              AND t.payment_status = 'approved'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Reutiliza transação pending existente com novo external_reference
     */
    public function reuseExistingPendingTransaction($existingExternalRef, $newExternalRef) {
        $stmt = $this->conn->prepare("
            UPDATE transactions
            SET external_reference = :newRef,
                preference_id      = NULL,
                updated_at         = NOW()
            WHERE external_reference = :oldRef
              AND payment_status     = 'pending'
        ");
        return $stmt->execute([':newRef' => $newExternalRef, ':oldRef' => $existingExternalRef]);
    }

    /**
     * Grava transação pendente no momento do checkout.
     * person_id pode ser null — será preenchido após o formulário.
     */
    public function createPendingTransaction($scheduleId, $personId, $email, $amount, $externalReference) {
        $stmt = $this->conn->prepare("
            INSERT INTO transactions
                (schedule_id, person_id, payer_email, external_reference, amount, payment_status)
            VALUES
                (:sid, :pid, :email, :ref, :amount, 'pending')
        ");
        return $stmt->execute([
            ':sid'    => $scheduleId,
            ':pid'    => $personId ?: null,
            ':email'  => $email,
            ':ref'    => $externalReference,
            ':amount' => $amount,
        ]);
    }

    /**
     * Atualiza o preference_id retornado pelo Mercado Pago
     */
    public function updatePreferenceId($externalReference, $preferenceId) {
        $stmt = $this->conn->prepare("
            UPDATE transactions SET preference_id = :pref WHERE external_reference = :ref
        ");
        return $stmt->execute([':pref' => $preferenceId, ':ref' => $externalReference]);
    }

    /**
     * Atualiza payment_id e payment_status para qualquer status recebido do MP.
     * Sem condição de status — sempre grava independente do valor atual.
     */
    public function updatePaymentStatus($paymentId, $externalReference, $status): void {
        $this->conn->prepare("
            UPDATE transactions
            SET payment_id     = :pid,
                payment_status = :status,
                updated_at     = NOW()
            WHERE external_reference = :ref
        ")->execute([
            ':pid'    => $paymentId,
            ':status' => $status,
            ':ref'    => $externalReference,
        ]);
    }

    /**
     * Chamado pelo webhook quando status = approved.
     *
     * 1. Insere em events_subscribed com status 'pending' e payment_id preenchido
     * 2. Decrementa vaga em schedules
     *
     * Idempotente: se o payment_id já existir em events_subscribed não processa de novo.
     */
    public function confirmPayment($paymentId, $externalReference) {
        try {
            $this->conn->beginTransaction();

            // Verifica se já foi processado (idempotência)
            $exists = $this->conn->prepare("
                SELECT id FROM events_subscribed WHERE payment_id = :payid LIMIT 1
            ");
            $exists->execute([':payid' => $paymentId]);
            if ($exists->fetch()) {
                $this->conn->rollBack();
                return false;
            }

            // Recupera person_id e schedule_id
            $info = $this->conn->prepare("
                SELECT person_id, schedule_id FROM transactions
                WHERE external_reference = :ref LIMIT 1
            ");
            $info->execute([':ref' => $externalReference]);
            $tx = $info->fetch(PDO::FETCH_ASSOC);

            if (!$tx) {
                throw new Exception("Transação não encontrada: {$externalReference}");
            }

            // Insere em events_subscribed com status 'pending'
            // → será atualizado para 'confirmed' ao concluir o formulário
            $this->conn->prepare("
                INSERT INTO events_subscribed (person_id, schedule_id, payment_id, status)
                VALUES (:pid, :sid, :payid, 'pending')
            ")->execute([
                ':pid'   => $tx['person_id'],
                ':sid'   => $tx['schedule_id'],
                ':payid' => $paymentId,
            ]);

            // Decrementa vaga
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
            error_log("ERRO confirmPayment [{$externalReference}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza events_subscribed para 'confirmed' após formulário preenchido.
     * Chamado pelo RegistrationController::create.
     */
    public function confirmSubscription($paymentId): void {
        $this->conn->prepare("
            UPDATE events_subscribed
            SET status = 'confirmed'
            WHERE payment_id = :payid
        ")->execute([':payid' => $paymentId]);
    }

    /**
     * Atualiza person_id na transaction e em events_subscribed após o form.
     */
    public function linkPersonToPayment($paymentId, $personId) {
        $this->conn->prepare("
            UPDATE transactions SET person_id = :pid WHERE payment_id = :payid
        ")->execute([':pid' => $personId, ':payid' => $paymentId]);

        $this->conn->prepare("
            UPDATE events_subscribed SET person_id = :pid WHERE payment_id = :payid
        ")->execute([':pid' => $personId, ':payid' => $paymentId]);
    }

    /**
     * Retorna transações aprovadas sem inscrição finalizada para um e-mail.
     */
    public function getPaidPendingRegistrations($email) {
        $sql = "SELECT t.payment_id, t.schedule_id, t.person_id,
                       e.name AS event_name, s.scheduled_at
                FROM transactions t
                JOIN schedules s ON t.schedule_id = s.id
                JOIN events e    ON s.event_id    = e.id
                WHERE t.payer_email    = :email
                  AND t.payment_status = 'approved'
                  AND s.scheduled_at  >= NOW()
                  AND NOT EXISTS (
                      SELECT 1 FROM anamnesis a
                      JOIN events_subscribed es ON a.subscribed_id = es.id
                      WHERE es.payment_id = t.payment_id
                  )
                ORDER BY s.scheduled_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sanitização: deleta transações 'pending' expiradas.
     * Tempo configurável via PENDING_EXPIRY_MINUTES no .env (padrão 60min).
     */
    public function deleteStalePendingTransactions(): int {
        $stmt = $this->conn->prepare("
            DELETE FROM transactions
            WHERE payment_status <> 'approved'
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}