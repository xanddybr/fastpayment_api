<?php

namespace App\Models;

use PDO;

class Transaction extends BaseModel {
    
    /**
     * Busca os dados do evento através do Schedule ID
     */
    public function getEventDetailsBySchedule($scheduleId) {
        $sql = "SELECT e.name, e.price 
                FROM schedules s 
                JOIN events e ON s.event_id = e.id 
                WHERE s.id = :sid LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['sid' => $scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Aqui você pode criar um método para salvar o log da transação
     * útil para conciliar pagamentos depois.
     */
    public function logTransaction($data) {
        // Implementar no futuro para salvar o ID do Mercado Pago vs Seu ID
    }

    /**
     * Atualiza o status do pagamento e vincula ao agendamento
     */
    public function updatePaymentStatus($externalReference, $status, $paymentId) {
        // Extrai o Schedule ID do external_reference (FP-timestamp-ID)
        $parts = explode('-', $externalReference);
        $scheduleId = end($parts);

        // 1. Atualiza a tabela de transações/pagamentos (se você tiver uma)
        // 2. Aqui você pode disparar a lógica de ocupar a vaga no agendamento
        $sql = "UPDATE schedules SET status = 'confirmed' WHERE id = :sid AND :status = 'approved'";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':sid' => $scheduleId,
            ':status' => $status
        ]);
    }
}