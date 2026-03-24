<?php

namespace App\Models;

use PDO;
use Exception;

class Transaction extends BaseModel {
    
    /**
     * Step 1: Fetch rich event details for Checkout
     */
    public function getEventDetailsBySchedule($scheduleId) {
        $sql = "SELECT e.name, e.price, ut.name as unit_name, et.name as type_name, s.scheduled_at
                FROM schedules s 
                JOIN events e ON s.event_id = e.id 
                JOIN units ut ON s.unit_id = ut.id
                JOIN event_types et ON s.event_type_id = et.id
                WHERE s.id = :sid LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Checks if an approved transaction already exists for this user/event
     */
    public function verifyPaidTransaction($email, $scheduleId) {
        $sql = "SELECT id FROM transactions 
                WHERE payer_email = :email 
                AND schedule_id = :sid 
                AND payment_status = 'approved' 
                LIMIT 1";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':email' => $email,
            ':sid'   => $scheduleId
        ]);
        
        return (bool)$stmt->fetch();
    }

    /**
     * Step 4: Master function to confirm payment and RESERVE vacancy
     */
   // Dentro do seu Transaction.php (Model)

public function confirmPaymentAndReserveVacancy($externalReference, $status, $paymentId, $scheduleId, $payerEmail) {
    try {
        $this->conn->beginTransaction();

        // 1. Verificamos se essa transação já foi registrada (evita duplicidade no Webhook)
        $checkSql = "SELECT id FROM transactions WHERE payment_id = :pid LIMIT 1";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->execute([':pid' => $paymentId]);
        
        if ($checkStmt->fetch()) {
            $this->conn->rollBack();
            return true; // Já processado, apenas ignoramos
        }

        // 2. Inserir o registro da transação
        $sqlInsert = "INSERT INTO transactions (schedule_id, payer_email, payment_status, payment_id, amount, created_at, updated_at) 
                      SELECT :sid, :email, :status, :pid, e.price, NOW(), NOW()
                      FROM schedules s 
                      JOIN events e ON s.event_id = e.id 
                      WHERE s.id = :sid2";
        
        $stmtInsert = $this->conn->prepare($sqlInsert);
        $stmtInsert->execute([
            ':sid'    => $scheduleId,
            ':sid2'   => $scheduleId,
            ':email'  => $payerEmail,
            ':status' => $status,
            ':pid'    => $paymentId
        ]);

        // 3. BAIXA DA VAGA (Só se aprovado)
        if ($status === 'approved') {
            $sqlUpdate = "UPDATE schedules 
                          SET vacancies = vacancies - 1 
                          WHERE id = :sid AND vacancies > 0";
            
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([':sid' => $scheduleId]);

            if ($stmtUpdate->rowCount() === 0) {
                throw new Exception("Falha ao baixar vaga: Curso esgotado ou ID inválido.");
            }
        }

        $this->conn->commit();
        error_log("SUCESSO: Vaga baixada para o schedule $scheduleId");
        return true;

    } catch (Exception $e) {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        error_log("ERRO NO MODEL: " . $e->getMessage());
        return false;
    }
}

    /**
     * Updates payment status for other cases (pending, cancelled, etc)
     */
    public function updatePaymentStatus($externalReference, $status, $paymentId) {
        $parts = explode('-', $externalReference);
        $scheduleId = end($parts);

        $sql = "UPDATE transactions 
                SET payment_status = :status, updated_at = NOW() 
                WHERE schedule_id = :sid AND payer_email = (SELECT payer_email FROM (SELECT payer_email FROM transactions WHERE schedule_id = :sid2 ORDER BY id DESC LIMIT 1) as t)";
        
        // Simple fallback to keep track of statuses
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':sid' => $scheduleId,
            ':sid2' => $scheduleId,
            ':status' => $status
        ]);
    }
}