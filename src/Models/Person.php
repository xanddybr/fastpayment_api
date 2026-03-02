<?php
namespace App\Models;

use PDO;
use Exception;

/**
 * Person Model
 * Herda de BaseModel para obter automaticamente a ligação $this->conn via Singleton
 */
class Person extends BaseModel {

    // Nota: O construtor não é necessário, pois o BaseModel já faz o trabalho.

    public function findByEmail($email) {
        $sql = "SELECT p.*, tp.name as role 
                FROM persons p 
                JOIN types_person tp ON p.type_person_id = tp.id 
                WHERE p.email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findAll() {
        // O DISTINCT garante que se a linha inteira for repetida, ele mostre apenas uma
        $sql = "SELECT DISTINCT 
                    p.id, p.full_name, p.email, p.status, p.type_person_id, p.created_at,
                    pd.activity_professional, pd.phone, pd.city, 
                    tp.name as role 
                FROM persons p 
                LEFT JOIN person_details pd ON p.id = pd.person_id 
                LEFT JOIN types_person tp ON p.type_person_id = tp.id 
                ORDER BY p.id DESC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $sql = "SELECT p.*, pd.activity_professional, pd.phone, pd.street, pd.number, pd.neighborhood, pd.city
                FROM persons p 
                LEFT JOIN person_details pd ON p.id = pd.person_id 
                WHERE p.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete($id) {
        $sql = "DELETE FROM persons WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    /**
     * Guarda ou Atualiza Pessoa e Detalhes (Transacional)
     */
    public function saveUnified($data) {
        try {

            $sqlPerson = "INSERT INTO persons (full_name, email, password, type_person_id, status) 
                          VALUES (:name, :email, :pass, :type_id, 'active')
                          ON DUPLICATE KEY UPDATE full_name = :name, type_person_id = :type_id";
            
            $stmtPerson = $this->conn->prepare($sqlPerson);
            
            $password = (isset($data['password']) && password_get_info($data['password'])['algo']) 
                        ? $data['password'] 
                        : password_hash($data['password'] ?? '123456', PASSWORD_DEFAULT);

            $stmtPerson->execute([
                ':name'    => $data['full_name'],
                ':email'   => $data['email'],
                ':pass'    => $password,
                ':type_id' => $data['type_person_id'] ?? 1
            ]);

            $personId = $this->conn->lastInsertId();
            if (!$personId) {
                $existingPerson = $this->findByEmail($data['email']);
                // Se não encontrar a pessoa, lança exceção para evitar erro de array offset null
                if (!$existingPerson) throw new Exception("Erro ao recuperar ID da pessoa após salvar.");
                $personId = $existingPerson['id'];
            }

            $details = $data['details'] ?? [];
            $sqlDetails = "INSERT INTO person_details (person_id, activity_professional, phone, street, number, neighborhood, city)
                           VALUES (:p_id, :act, :phone, :street, :num, :neigh, :city)
                           ON DUPLICATE KEY UPDATE 
                           activity_professional = :act, phone = :phone, street = :street, 
                           number = :num, neighborhood = :neigh, city = :city";

            $stmtDetails = $this->conn->prepare($sqlDetails);
            $stmtDetails->execute([
                ':p_id'   => $personId,
                ':act'    => $details['activity_professional'] ?? null,
                ':phone'  => $details['phone'] ?? null,
                ':street' => $details['street'] ?? null,
                ':num'    => $details['number'] ?? null,
                ':neigh'  => $details['neighborhood'] ?? null,
                ':city'   => $details['city'] ?? null
            ]);

            return $personId;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function updatePasswordByEmail($email, $newPassword) {
        try {
            // Gera o hash seguro conforme os padrões do seu sistema
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $sql = "UPDATE persons SET password = :password WHERE email = :email";
            $stmt = $this->conn->prepare($sql);
            
            $stmt->execute([
                ':password' => $hashedPassword,
                ':email'    => $email
            ]);

            // rowCount indica se o registro foi de fato alterado
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function authenticate($email, $password) {
        $sql = "SELECT id, full_name, email, password FROM persons WHERE email = :email AND status = 'active' LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']); // Segurança: remove o hash antes de retornar
            return $user;
        }
        return false;
    }

    // --- LÓGICA DE OTP (Vinculada à Pessoa) ---
    public function createValidationCode($email, $phone) {
        // Invalida códigos antigos
        $this->conn->prepare("UPDATE registered_codes SET status = 'substituido' WHERE email = :email AND status = 'pendente'")
                   ->execute([':email' => $email]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+5 minutes"));

        $sql = "INSERT INTO registered_codes (email, phone, validation_method, code, expires_at, status) 
                VALUES (:email, :phone, 'email', :code, :expiresAt, 'pendente')";
        
        $this->conn->prepare($sql)->execute([
            ':email' => $email, 
            ':phone' => $phone, 
            ':code' => $code, 
            ':expiresAt' => $expiresAt
        ]);

        return $code;
    }

    /**
     * Card 8.1 / 11.1: Ficha Definitiva do Aluno
     * Traz Person, Details, e todas as Inscrições com Anamnese completa
     */
    public function getFullProfile($id) {
        $sql = "SELECT 
                    p.id, p.full_name, p.email, p.status as person_status,
                    pd.activity_professional, pd.phone, pd.street, pd.number, pd.neighborhood, pd.city,
                    es.id as subscription_id, es.status as subscription_status,
                    e.name as course_name, s.scheduled_at as course_date,
                    -- Campos da Anamnese (Excluindo id e created_at)
                    a.course_reason, a.expectations, a.who_recomend, a.is_medium, 
                    a.religion, a.religion_mention, a.is_tule_member, a.obs_motived, a.first_time
                FROM persons p
                LEFT JOIN person_details pd ON p.id = pd.person_id
                LEFT JOIN events_subscribed es ON p.id = es.person_id
                LEFT JOIN schedules s ON es.schedule_id = s.id
                LEFT JOIN events e ON s.event_id = e.id
                LEFT JOIN anamnesis a ON es.id = a.subscribed_id
                WHERE p.id = :id
                ORDER BY es.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC); 
    }

    public function getAllSubscribers() {
        $sql = "SELECT 
                    p.id as person_id,
                    p.full_name, 
                    p.email,
                    pd.phone, 
                    pd.activity_professional, 
                    pd.neighborhood, 
                    pd.city,
                    es.id as subscribed_id,
                    es.status as subscription_status,
                    es.created_at as data_inscricao,
                    t.payer_email,
                    t.amount as valor_pago,
                    t.payment_status,
                    t.updated_at as data_pagamento,
                    a.course_reason, 
                    a.expectations, 
                    a.who_recomend, 
                    a.is_medium, 
                    a.religion, 
                    a.religion_mention, 
                    a.is_tule_member, 
                    a.obs_motived, 
                    a.first_time
                FROM persons p
                INNER JOIN person_details pd ON p.id = pd.person_id
                INNER JOIN events_subscribed es ON p.id = es.person_id
                LEFT JOIN transactions t ON (p.id = t.person_id AND es.schedule_id = t.schedule_id)
                LEFT JOIN anamnesis a ON es.id = a.subscribed_id
                WHERE p.type_person_id = 2 
                ORDER BY es.created_at DESC";

        // IMPORTANTE: Usando $this->conn que vem do seu BaseModel Singleton
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
   
}