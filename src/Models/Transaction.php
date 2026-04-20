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
                JOIN events e      ON s.event_id      = e.id
                JOIN event_types et ON s.event_type_id = et.id
                WHERE s.id = :sid LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Grava transação pendente no momento do checkout.
     * person_id pode ser null — será preenchido após o formulário de inscrição.
     */
    public function createPendingTransaction($scheduleId, $personId, $email, $amount, $externalReference) {
        $sql = "INSERT INTO transactions
                    (schedule_id, person_id, payer_email, external_reference, amount, payment_status)
                VALUES
                    (:sid, :pid, :email, :ref, :amount, 'pending')";
        $stmt = $this->conn->prepare($sql);
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
        $sql = "UPDATE transactions SET preference_id = :pref WHERE external_reference = :ref";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':pref' => $preferenceId, ':ref' => $externalReference]);
    }

    /**
     * Confirmação atômica chamada pelo webhook do MP.
     *
     * 1. Aprova a transaction (pending → approved) e grava payment_id
     * 2. Insere em events_subscribed (person_id pode ser null aqui — será atualizado no form)
     * 3. Decrementa vaga em schedules
     *
     * Idempotente: se o webhook chegar duplicado o UPDATE não encontra 'pending' e retorna false.
     */
    public function confirmPayment($paymentId, $externalReference) {
        try {
            $this->conn->beginTransaction();

            // 1. Aprova a transação
            $stmt = $this->conn->prepare("
                UPDATE transactions
                SET payment_status = 'approved',
                    payment_id     = :pid,
                    updated_at     = NOW()
                WHERE external_reference = :ref
                  AND payment_status     = 'pending'
            ");
            $stmt->execute([':pid' => $paymentId, ':ref' => $externalReference]);

            if ($stmt->rowCount() === 0) {
                // Webhook duplicado ou já processado — não é erro
                $this->conn->rollBack();
                return false;
            }

            // Recupera person_id e schedule_id
            $info = $this->conn->prepare("
                SELECT person_id, schedule_id
                FROM transactions
                WHERE external_reference = :ref
                LIMIT 1
            ");
            $info->execute([':ref' => $externalReference]);
            $tx = $info->fetch(PDO::FETCH_ASSOC);

            if (!$tx) {
                throw new Exception("Transação não encontrada após update: {$externalReference}");
            }

            // 2. Insere em events_subscribed
            //    payment_id como VARCHAR bate com o schema (events_subscribed.payment_id varchar 100)
            $this->conn->prepare("
                INSERT INTO events_subscribed (person_id, schedule_id, payment_id, status)
                VALUES (:pid, :sid, :payid, 'confirmed')
            ")->execute([
                ':pid'   => $tx['person_id'],   // pode ser null — form ainda não foi preenchido
                ':sid'   => $tx['schedule_id'],
                ':payid' => $paymentId,
            ]);

            // 3. Decrementa vaga
            $stmtVaga = $this->conn->prepare("
                UPDATE schedules
                SET vacancies = vacancies - 1
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
     * Atualiza person_id na transaction e em events_subscribed após o form de inscrição.
     * Chamado por Registration::completeRegistration depois de criar/identificar a pessoa.
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
     * Usado no frontend pós-pagamento para exibir o formulário.
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
     * Verifica se já existe pagamento aprovado para email + schedule
     */
    public function verifyPaidTransaction($email, $scheduleId) {
        $sql = "SELECT id FROM transactions
                WHERE payer_email = :email
                  AND schedule_id = :sid
                  AND payment_status = 'approved'
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email, ':sid' => $scheduleId]);
        return (bool) $stmt->fetch();
    }
}