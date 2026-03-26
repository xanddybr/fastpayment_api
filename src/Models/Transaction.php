<?php
namespace App\Models;

use PDO;
use Exception;

class Transaction extends BaseModel {
    
    public function getEventDetailsBySchedule($scheduleId) {
        $sql = "SELECT e.name, e.price, et.name as type_name
                FROM schedules s 
                JOIN events e ON s.event_id = e.id 
                JOIN event_types et ON s.event_type_id = et.id
                WHERE s.id = :sid LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verifyPaidTransaction($email, $scheduleId) {
        $sql = "SELECT id FROM transactions 
                WHERE payer_email = :email AND schedule_id = :sid AND payment_status = 'approved' LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email, ':sid' => $scheduleId]);
        return (bool)$stmt->fetch();
    }

    // 1. Grava a transação inicial
public function createPendingTransaction($scheduleId, $personId, $email, $amount, $externalReference) {
    $sql = "INSERT INTO transactions (
                schedule_id, 
                person_id,
                payer_email, 
                external_reference, 
                amount, 
                payment_status
            ) VALUES (:sid, :pid, :email, :ref, :amount, 'pending')";

    $stmt = $this->conn->prepare($sql);
    return $stmt->execute([
        ':sid'    => $scheduleId,
        ':pid'    => $personId, // Se não tiver o ID do usuário agora, passe null
        ':email'  => $email,
        ':ref'    => $externalReference,
        ':amount' => $amount
    ]);
}

// 2. Atualiza o preference_id gerado pelo Mercado Pago
public function updatePreferenceId($externalReference, $preferenceId) {
    $sql = "UPDATE transactions SET preference_id = :pref WHERE external_reference = :ref";
    $stmt = $this->conn->prepare($sql);
    return $stmt->execute([':pref' => $preferenceId, ':ref' => $externalReference]);
}

// 3. O Webhook usa esta para confirmar o pagamento e baixar a vaga
public function confirmPayment($paymentId, $externalReference) {
    $sql = "UPDATE transactions 
            SET payment_status = 'approved', 
                payment_id = :pid 
            WHERE external_reference = :ref AND payment_status = 'pending'";

    $stmt = $this->conn->prepare($sql);
    $success = $stmt->execute([':pid' => $paymentId, ':ref' => $externalReference]);

    if ($success && $stmt->rowCount() > 0) {
        // Busca o schedule_id para decrementar a vaga
        $sqlInfo = "SELECT schedule_id FROM transactions WHERE external_reference = :ref";
        $stmtInfo = $this->conn->prepare($sqlInfo);
        $stmtInfo->execute([':ref' => $externalReference]);
        $res = $stmtInfo->fetch(\PDO::FETCH_ASSOC);

        if ($res) {
            $sqlVaga = "UPDATE schedules SET vacancies = vacancies - 1 WHERE id = :sid AND vacancies > 0";
            $stmtVaga = $this->conn->prepare($sqlVaga);
            return $stmtVaga->execute([':sid' => $res['schedule_id']]);
        }
    }
    return false;
}

    public function getPaidPendingRegistrations($email) {
        $sql = "SELECT t.payment_id, t.schedule_id, e.name as event_name, s.scheduled_at
                FROM transactions t
                JOIN schedules s ON t.schedule_id = s.id
                JOIN events e ON s.event_id = e.id
                WHERE t.payer_email = :email 
                AND t.payment_status = 'approved'
                AND s.scheduled_at >= NOW() -- TRAVA DE DATA/HORA
                AND t.payment_id NOT IN (SELECT COALESCE(payment_id, '') FROM subscribers)
                ORDER BY s.scheduled_at ASC";
                    
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function processWebhookNotification(string $paymentId): bool {
        // 1. Busca dados da transação local
        $sql = "SELECT schedule_id, payer_email, payment_status FROM transactions WHERE payment_id = :pid LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':pid' => $paymentId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction || $transaction['payment_status'] === 'approved') {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // 2. Aprova a transação
            $stmtUpdate = $this->conn->prepare("UPDATE transactions SET payment_status = 'approved', updated_at = NOW() WHERE payment_id = :pid");
            $stmtUpdate->execute([':pid' => $paymentId]);

            // 3. Abate a vaga
            $stmtVaga = $this->conn->prepare("UPDATE schedules SET vacancies = vacancies - 1 WHERE id = :sid AND vacancies > 0");
            $stmtVaga->execute([':sid' => $transaction['schedule_id']]);

            if ($stmtVaga->rowCount() === 0) {
                throw new Exception("Vagas esgotadas.");
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log("Erro no Webhook: " . $e->getMessage());
            return false;
        }
    }
}