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

    public function createPendingTransaction($scheduleId, $email, $preferenceId, $amount) {
    $sql = "INSERT INTO transactions (schedule_id, payer_email, payment_id, amount, payment_status, created_at) 
            VALUES (:sid, :email, :pid, :amount, 'pending', NOW())";
    
    $stmt = $this->conn->prepare($sql);
    return $stmt->execute([
        ':sid' => $scheduleId,
        ':email' => $email,
        ':pid' => $preferenceId,
        ':amount' => $amount
    ]);
}

    /**
     * Step 4: Master function to confirm payment and RESERVE vacancy
     */
   // Dentro do seu Transaction.php (Model)

public function confirmPaymentAndReserveVacancy($externalReference, $status, $paymentId, $scheduleId, $payerEmail) {

    error_log("--- INICIO WEBHOOK DEBUG ---");
    error_log("Procurando Payment ID: " . $paymentId);
    error_log("Schedule ID enviado: " . $scheduleId);

try {
        $this->conn->beginTransaction();

        // 1. Buscamos a transação existente que criamos no checkout (Etapa Pending)
        $checkSql = "SELECT id, payment_status FROM transactions WHERE payment_id = :pid LIMIT 1";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->execute([':pid' => $paymentId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Se a transação não existe ou já foi aprovada, não fazemos nada
        if (!$existing) {
            error_log("WEBHOOK: Transação $paymentId não encontrada no banco.");
            $this->conn->rollBack();
            return false; 
        }

        if ($existing['payment_status'] === 'approved') {
            error_log("WEBHOOK: Transação $paymentId já estava aprovada.");
            $this->conn->rollBack();
            return true; 
        }

        // 2. ATUALIZAMOS a transação existente para 'approved'
        $sqlUpdateTrans = "UPDATE transactions 
                           SET payment_status = :status, updated_at = NOW() 
                           WHERE payment_id = :pid";
        
        $stmtUpdateTrans = $this->conn->prepare($sqlUpdateTrans);
        $stmtUpdateTrans->execute([
            ':status' => $status,
            ':pid'    => $paymentId
        ]);

        // 3. BAIXA DA VAGA (Apenas se o status vindo for 'approved')
        if ($status === 'approved') {
            // Conferindo nomes do seu schema: tabela 'schedules', coluna 'vacancies'
            $sqlUpdateVaga = "UPDATE schedules 
                              SET vacancies = vacancies - 1 
                              WHERE id = :sid AND vacancies > 0";
            
            $stmtUpdateVaga = $this->conn->prepare($sqlUpdateVaga);
            $stmtUpdateVaga->execute([':sid' => $scheduleId]);

            // Se rowCount for 0, ou o ID tá errado ou acabou a vaga
            if ($stmtUpdateVaga->rowCount() === 0) {
                error_log("ERRO: Não foi possível subtrair vaga para schedule $scheduleId");
                throw new Exception("Falha ao baixar vaga: Curso esgotado ou ID inválido.");
            }
        }

        $this->conn->commit();
        error_log("SUCESSO: Status atualizado e vaga baixada para schedule $scheduleId");
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