<?php

namespace App\Models;

use PDO;
use Exception;

class Registration extends BaseModel {

    /**
     * Busca dados do usuário e seu último evento aprovado
     */
    public function getUserStatusForFinalization($email) {
        $sql = "SELECT p.id as person_id_found, e.name as last_event 
                FROM persons p
                LEFT JOIN transactions t ON p.id = t.person_id AND t.payment_status = 'approved'
                LEFT JOIN schedules s ON t.schedule_id = s.id
                LEFT JOIN events e ON s.event_id = e.id
                WHERE p.email = :email
                ORDER BY t.created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a lista completa para o Dashboard Admin
     */
    public function getDashboardReport() {
        $sql = "SELECT 
                    r.id as registration_id, p.full_name as client_name, p.email as client_email,
                    e.name as event_name, s.scheduled_at as event_date,
                    pay.status as payment_status, pay.amount as paid_amount,
                    r.created_at as registration_date
                FROM registrations r
                JOIN persons p ON r.person_id = p.id
                JOIN schedules s ON r.schedule_id = s.id
                JOIN events e ON s.event_id = e.id
                LEFT JOIN payments pay ON r.payment_id = pay.id
                ORDER BY r.created_at DESC";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}