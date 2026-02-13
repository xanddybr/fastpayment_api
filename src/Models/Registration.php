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
     * Card 11.3 + 10.0: Efetiva a inscrição, garante a vaga e registra o financeiro.
     * Esta função é atômica (Transaction) e protegida contra duplicidade.
     */
   /**
     * Card 11.3: Efetiva a inscrição com base no schema.sql fornecido.
     * Ajustado para as colunas reais das tabelas transactions e anamnesis.
     */
    public function completeSubscription($personId, $scheduleId, $paymentId) {
        try {
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
            }

            // 1. REGRA DE NEGÓCIO: Evitar duplicidade
            $checkSql = "SELECT id FROM events_subscribed WHERE person_id = :p AND schedule_id = :s";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->execute([':p' => $personId, ':s' => $scheduleId]);

            if ($checkStmt->fetch()) {
                throw new \Exception("Inscrição Negada: Aluno já inscrito neste horário.");
            }

            // 2. VERIFICAR E BAIXAR VAGAS
            $updateVagas = "UPDATE schedules SET vacancies = vacancies - 1 WHERE id = :id AND vacancies > 0";
            $stmtVagas = $this->conn->prepare($updateVagas);
            $stmtVagas->execute([':id' => $scheduleId]);

            if ($stmtVagas->rowCount() === 0) {
                throw new \Exception("Inscrição Negada: Não há mais vagas disponíveis.");
            }

            // 3. BUSCAR VALOR DO EVENTO
            $sqlPrice = "SELECT e.price FROM schedules s JOIN events e ON s.event_id = e.id WHERE s.id = :sid";
            $stmtPrice = $this->conn->prepare($sqlPrice);
            $stmtPrice->execute([':sid' => $scheduleId]);
            $eventData = $stmtPrice->fetch(\PDO::FETCH_ASSOC);
            $amount = $eventData['price'] ?? 0;

            // 4. CRIAR REGISTRO FINANCEIRO (Ajustado para o seu schema)
            $sqlTrans = "INSERT INTO transactions (schedule_id, person_id, payment_status, amount, created_at) 
                        VALUES (:sid, :pid, 'approved', :amount, NOW())";
            $this->conn->prepare($sqlTrans)->execute([
                ':sid'    => $scheduleId,
                ':pid'    => $personId,
                ':amount' => $amount
            ]);

            // 5. CRIAR INSCRIÇÃO
            $sqlSub = "INSERT INTO events_subscribed (person_id, schedule_id, status, created_at) 
                    VALUES (:pid, :sid, 'confirmed', NOW())";
            $this->conn->prepare($sqlSub)->execute([':pid' => $personId, ':sid' => $scheduleId]);
            $subscriptionId = $this->conn->lastInsertId();

            // 6. CRIAR FICHA DE ANAMNESE (Ajustado para 'subscribed_id')
            $sqlAna = "INSERT INTO anamnesis (subscribed_id, first_time, created_at) VALUES (:subid, 1, NOW())";
            $this->conn->prepare($sqlAna)->execute([':subid' => $subscriptionId]);

            $this->conn->commit();
            return true;

        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }
    
    public function getTransactionsReport() {
        $sql = "SELECT t.*, p.full_name, p.email 
                FROM transactions t
                JOIN persons p ON t.person_id = p.id
                ORDER BY t.created_at DESC";
        return $this->conn->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Card 11.2: Extrato de Movimentação por e-mail/ID
     */
    public function getFinancialStatement() {
        $sql = "SELECT 
                    t.id as trans_id, t.amount, t.type as trans_type, t.created_at,
                    p.full_name, p.email,
                    pay.status as payment_status, pay.id as payment_id
                FROM transactions t
                JOIN persons p ON t.person_id = p.id
                LEFT JOIN payments pay ON t.payment_id = pay.id
                ORDER BY t.created_at DESC";
        
        return $this->conn->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a receita total acumulada na plataforma
     */
    /**
     * Retorna a receita total acumulada (Apenas pagamentos aprovados)
     * Ajustado conforme schema.sql: utiliza 'payment_status' e 'amount'
     */
    public function getTotalRevenue() {
        // No seu banco a coluna é payment_status e não existe a coluna 'type'
        $sql = "SELECT SUM(amount) as total 
                FROM transactions 
                WHERE payment_status = 'approved'";
        
        $stmt = $this->conn->query($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result['total'] ?? 0;
    }
}