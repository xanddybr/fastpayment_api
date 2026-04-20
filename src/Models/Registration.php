<?php
namespace App\Models;

use PDO;
use Exception;

class Registration extends BaseModel {

    /**
     * Lista completa de inscritos para o painel admin.
     */
    public function getFullSubscribersList() {
        $sql = "SELECT
                    es.id               AS subscription_id,
                    p.full_name         AS client_name,
                    p.email             AS client_email,
                    pd.phone,
                    pd.activity_professional,
                    pd.city,
                    e.name              AS event_name,
                    et.name             AS event_type,
                    u.name              AS unit_name,
                    s.scheduled_at      AS event_date,
                    es.status           AS subscription_status,
                    a.first_time,
                    a.id                AS anamnesis_id
                FROM events_subscribed es
                JOIN persons p          ON es.person_id   = p.id
                LEFT JOIN person_details pd ON p.id       = pd.person_id
                JOIN schedules s         ON es.schedule_id = s.id
                JOIN events e            ON s.event_id     = e.id
                JOIN event_types et      ON s.event_type_id = et.id
                JOIN units u             ON s.unit_id       = u.id
                LEFT JOIN anamnesis a    ON es.id           = a.subscribed_id
                ORDER BY es.created_at DESC";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Histórico financeiro para o painel admin.
     * Usa a tabela transactions (fonte da verdade financeira).
     */
    public function getPaymentHistory() {
        $sql = "SELECT
                    t.payment_id,
                    t.payer_email,
                    t.amount,
                    t.payment_status,
                    t.created_at        AS payment_date,
                    e.name              AS event_name,
                    s.scheduled_at      AS event_date
                FROM transactions t
                LEFT JOIN schedules s ON t.schedule_id = s.id
                LEFT JOIN events e    ON s.event_id    = e.id
                WHERE t.payment_status = 'approved'
                ORDER BY t.created_at DESC";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Receita total acumulada (pagamentos aprovados).
     */
    public function getTotalRevenue() {
        $sql  = "SELECT SUM(amount) AS total FROM transactions WHERE payment_status = 'approved'";
        $result = $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * Relatório de transações para o painel admin.
     */
    public function getTransactionsReport() {
        $sql = "SELECT t.*, p.full_name, p.email
                FROM transactions t
                LEFT JOIN persons p ON t.person_id = p.id
                ORDER BY t.created_at DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}