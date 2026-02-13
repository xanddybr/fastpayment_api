<?php
namespace App\Models;

use PDO;

class Registration extends BaseModel {

    /**
     * Consulta mestre para o Menu Inscritos
     * Une dados pessoais, profissionais, o evento escolhido e o status da anamnese
     */
    public function getFullSubscribersList() {
        $sql = "SELECT 
                    es.id as subscription_id,
                    p.full_name as client_name,
                    p.email as client_email,
                    pd.phone,
                    pd.activity_professional,
                    pd.city,
                    e.name as event_name,
                    et.name as event_type,
                    u.name as unit_name,
                    s.scheduled_at as event_date,
                    es.status as subscription_status,
                    a.first_time,
                    a.id as anamnesis_id
                FROM events_subscribed es
                JOIN persons p ON es.person_id = p.id
                LEFT JOIN person_details pd ON p.id = pd.person_id
                JOIN schedules s ON es.schedule_id = s.id
                JOIN events e ON s.event_id = e.id
                JOIN event_types et ON s.event_type_id = et.id
                JOIN units u ON s.unit_id = u.id
                LEFT JOIN anamnesis a ON es.id = a.subscribed_id
                ORDER BY es.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Card 11.2: Extrato de movimentação financeira (Histórico)
     */
    public function getPaymentHistory() {
        $sql = "SELECT 
                    pay.id as payment_id,
                    pay.payer_email,
                    pay.amount,
                    pay.status as payment_status,
                    pay.created_at as payment_date,
                    e.name as event_name,
                    s.scheduled_at as event_date
                FROM payments pay
                LEFT JOIN events_subscribed es ON pay.id = es.payment_id
                LEFT JOIN schedules s ON es.schedule_id = s.id
                LEFT JOIN events e ON s.event_id = e.id
                ORDER BY pay.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Card 11.3: Efetiva a inscrição, reduz vaga e cria registro de anamnese
     */
    public function completeSubscription($personId, $scheduleId, $paymentId) {
        try {
            $this->conn->beginTransaction();

            // 1. Criar o registro oficial em events_subscribed
            $sqlSub = "INSERT INTO events_subscribed (person_id, schedule_id, payment_id, status) 
                       VALUES (:pid, :sid, :payid, 'confirmed')
                       ON DUPLICATE KEY UPDATE status = 'confirmed'";
            $stmtSub = $this->conn->prepare($sqlSub);
            $stmtSub->execute([
                ':pid'   => $personId,
                ':sid'   => $scheduleId,
                ':payid' => $paymentId
            ]);

            // Captura o ID da inscrição para a anamnese
            $subscriptionId = $this->conn->lastInsertId() ?: null;

            // 2. Reduzir uma vaga no agendamento (Apenas se houver vagas)
            $sqlVac = "UPDATE schedules SET vacancies = vacancies - 1 
                       WHERE id = :sid AND vacancies > 0";
            $stmtVac = $this->conn->prepare($sqlVac);
            $stmtVac->execute([':sid' => $scheduleId]);

            if ($stmtVac->rowCount() === 0) {
                // Opcional: Aqui poderíamos lançar exceção se as vagas acabaram no milissegundo do pagamento
            }

            // 3. Gerar registro inicial de anamnese (vazio)
            if ($subscriptionId) {
                $sqlAnam = "INSERT IGNORE INTO anamnesis (subscribed_id, first_time) 
                            VALUES (:subid, 1)";
                $this->conn->prepare($sqlAnam)->execute([':subid' => $subscriptionId]);
            }

            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}