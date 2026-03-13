<?php

namespace App\Models;

use PDO;
use Exception;

class Transaction extends BaseModel {
    
    /**
     * Passo 1: Busca detalhes ricos do evento para o Checkout (Item 3 do roteiro)
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
        
        // Retorna true se achar uma linha, false se não achar
        return (bool)$stmt->fetch();
    }

    /**
     * Passo 4: A função mestre que confirma o pagamento e RESERVA a vaga
     */
    public function confirmPaymentAndReserveVacancy($externalReference, $status, $paymentId, $scheduleId, $payerEmail) {
        try {
            // Iniciamos a transação para garantir que se um falhar, nada salva
            $this->conn->beginTransaction();

            // 1. Registra a transação na tabela transactions (conforme seu schema.sql)
            // Buscamos o preço atual do evento no momento da inserção
            $sqlInsert = "INSERT INTO transactions (schedule_id, payer_email, payment_status, amount, created_at, updated_at) 
                          VALUES (:sid, :email, :status, (SELECT e.price FROM schedules s JOIN events e ON s.event_id = e.id WHERE s.id = :sid2), NOW(), NOW())";
            
            $stmtInsert = $this->conn->prepare($sqlInsert);
            $stmtInsert->execute([
                ':sid'    => $scheduleId,
                ':sid2'   => $scheduleId,
                ':email'  => $payerEmail,
                ':status' => $status
            ]);

            // 2. A "TRAVA": Só baixa a vaga se vacancies for maior que zero
            $sqlUpdate = "UPDATE schedules 
                          SET vacancies = vacancies - 1 
                          WHERE id = :sid AND vacancies > 0";
            
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([':sid' => $scheduleId]);

            // Validação crucial: Se o UPDATE não afetou nenhuma linha, é porque as vagas acabaram
            if ($stmtUpdate->rowCount() === 0 && $status === 'approved') {
                throw new Exception("Vagas esgotadas no momento do processamento.");
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ERRO TRANSACTION MODEL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mantemos este para outros status (pendente, cancelado, etc) 
     * que não envolvem reserva de vaga imediata
     */
    public function updatePaymentStatus($externalReference, $status, $paymentId) {
        $parts = explode('-', $externalReference);
        $scheduleId = end($parts);

        $sql = "INSERT INTO transactions (schedule_id, payment_status, created_at) 
                VALUES (:sid, :status, NOW()) 
                ON DUPLICATE KEY UPDATE payment_status = :status2, updated_at = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':sid' => $scheduleId,
            ':status' => $status,
            ':status2' => $status
        ]);
    }
}