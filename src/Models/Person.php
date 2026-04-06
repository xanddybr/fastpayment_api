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

    public function create($data) {
        // Criptografa a senha para não salvar em texto puro
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        $sql = "INSERT INTO persons (full_name, email, password, created_at) 
                VALUES (:full_name, :email, :password, NOW())";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password', $hashedPassword);
            
            return $stmt->execute();
        } catch (\PDOException $e) {
            // Se o e-mail já existir e for UNIQUE no banco, vai cair aqui
            error_log("Erro ao criar pessoa: " . $e->getMessage());
            return false;
        }
    }

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

    public function saveCompleteRegistration($data) {
        try {
            $this->conn->beginTransaction();

            // 1. Tabela `persons`
            // Garante que o ID seja recuperado mesmo se o e-mail já existir
            $sqlPerson = "INSERT INTO persons (full_name, email, status, type_person_id) 
                        VALUES (?, ?, 'active', 2) 
                        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), full_name=VALUES(full_name)";
            $stmt = $this->conn->prepare($sqlPerson);
            $stmt->execute([
                $data['student_full_name'] ?? ($data['full_name'] ?? null),
                $data['student_email'] ?? ($data['email'] ?? null)
            ]);
            $personId = $this->conn->lastInsertId();

            // 2. Tabela `person_details`
            $sqlDetails = "INSERT INTO person_details (person_id, activity_professional, phone, neighborhood, city) 
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE activity_professional=VALUES(activity_professional), phone=VALUES(phone), neighborhood=VALUES(neighborhood), city=VALUES(city)";
            $stmt = $this->conn->prepare($sqlDetails);
            $stmt->execute([
                $personId,
                $data['activity_professional'] ?? null,
                $data['student_phone'] ?? ($data['phone'] ?? null),
                $data['neighborhood'] ?? null,
                $data['city'] ?? null
            ]);

            // 3. Tabela `events_subscribed`
            $sqlSub = "INSERT INTO events_subscribed (person_id, schedule_id, status) 
                    VALUES (?, ?, 'confirmed')";
            $stmt = $this->conn->prepare($sqlSub);
            $stmt->execute([$personId, $data['schedule_id']]);
            $subscribedId = $this->conn->lastInsertId();

            // 4. ATUALIZAÇÃO DE VAGAS (Lógica de Proteção)
            // IMPORTANTE: Só subtraímos a vaga se ela NÃO foi subtraída no Checkout.
            // Se 'is_pre_paid' for falso, significa que é uma inscrição que ainda não tirou a vaga.
            if (!isset($data['is_pre_paid']) || $data['is_pre_paid'] == 0) {
                $sqlUpdateVacancies = "UPDATE schedules SET vacancies = vacancies - 1 WHERE id = ? AND vacancies > 0";
                $stmtVac = $this->conn->prepare($sqlUpdateVacancies);
                $stmtVac->execute([$data['schedule_id']]);
                
                if ($stmtVac->rowCount() === 0) {
                    throw new Exception("Desculpe, as vagas para este evento acabaram de esgotar.");
                }
            }

            // 5. Tabela `anamnesis` (Ficha Técnica)
            $sqlAnamnesis = "INSERT INTO anamnesis 
                (subscribed_id, course_reason, expectations, who_recomend, is_medium, religion, religion_mention, is_tule_member, obs_motived, first_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtAna = $this->conn->prepare($sqlAnamnesis);
            
            // Tratamento de conversão para TinyInt (0 ou 1)
            $hasReligion = (!empty($data['religion_mention'])) ? 1 : 0;
            
            $stmtAna->execute([
                $subscribedId,
                $data['course_reason'] ?? null,
                $data['expectations'] ?? null,
                (isset($data['is_medium']) && ($data['is_medium'] == 1 || $data['is_medium'] == 'on')) ? 1 : 0,
                $hasReligion,
                $data['religion_mention'] ?? null,
                (isset($data['is_tule_member']) && ($data['is_tule_member'] == 1 || $data['is_tule_member'] == 'on')) ? 1 : 0,
                $data['obs_motived'] ?? null,
                (isset($data['first_time']) && ($data['first_time'] == 1 || $data['first_time'] == 'on')) ? 1 : 0
            ]);

            $this->conn->commit();
            return $subscribedId;

        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) { $this->conn->rollBack(); }
            throw $e;
        }
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
   
    public function getAdminEmails() {
        $sql = "SELECT email FROM persons WHERE type_person_id = 1 AND status = 'active'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
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
   // --- LÓGICA DE OTP (Corrigida conforme o schema.sql) ---
public function createValidationCode($email, $phone = null) {
    // Invalida códigos antigos
    $this->conn->prepare("UPDATE registered_codes SET status = 'expirado' WHERE email = :email AND status = 'pendente'")
               ->execute([':email' => $email]);

    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime("+5 minutes"));

    // Removidas as colunas 'phone' e 'validation_method' que não existem no SQL
    $sql = "INSERT INTO registered_codes (email, code, expires_at, status) 
            VALUES (:email, :code, :expiresAt, 'pendente')";
    
    $this->conn->prepare($sql)->execute([
        ':email'     => $email, 
        ':code'      => $code, 
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
    p.id AS person_id,
    p.full_name,
    p.email,
    pd.phone,
    pd.activity_professional,
    pd.neighborhood,
    pd.city,
    es.id AS subscribed_id,
    es.status AS enrollment_status,
    es.created_at AS data_inscricao,
    s.scheduled_at,
    e.name AS event_name,
    et.name AS type_name,
    u.name AS unit_name,
    e.price AS valor_evento,
    -- Busca o Status (com COLLATE para evitar erro #1267)
    COALESCE(
        (SELECT t.payment_status 
         FROM transactions t 
         WHERE t.schedule_id = s.id 
         AND t.payment_id COLLATE utf8mb4_unicode_ci = es.payment_id COLLATE utf8mb4_unicode_ci 
         ORDER BY (t.payment_status = 'approved') DESC, t.id DESC 
         LIMIT 1), 
    'pending') AS payment_status,
    -- Busca o E-mail do Pagador (vinculado à mesma lógica)
    (SELECT t.payer_email 
     FROM transactions t 
     WHERE t.schedule_id = s.id 
     AND t.payment_id COLLATE utf8mb4_unicode_ci = es.payment_id COLLATE utf8mb4_unicode_ci 
     ORDER BY (t.payment_status = 'approved') DESC, t.id DESC 
     LIMIT 1) AS payer_email,
    a.is_medium,
    a.first_time,
    a.is_tule_member,
    a.religion,
    a.religion_mention,
    a.course_reason,
    a.obs_motived,
    a.who_recomend,
    a.expectations
FROM persons p
INNER JOIN person_details pd ON p.id = pd.person_id
INNER JOIN events_subscribed es ON p.id = es.person_id
INNER JOIN schedules s ON es.schedule_id = s.id
INNER JOIN events e ON s.event_id = e.id
INNER JOIN event_types et ON s.event_type_id = et.id
INNER JOIN units u ON s.unit_id = u.id
LEFT JOIN anamnesis a ON es.id = a.subscribed_id
ORDER BY es.created_at DESC;";

        // IMPORTANTE: Usando $this->conn que vem do seu BaseModel Singleton
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
 * Verifica se o código é válido e o marca como 'validado' caso positivo.
 * Respeita a persistência e isola o SQL do Controller.
 */
public function validateOTP($email, $code) {
        // 1. Busca o código pendente e dentro do prazo de expiração
        $sql = "SELECT id FROM registered_codes 
                WHERE email = :email 
                AND code = :code 
                AND status = 'pendente' 
                AND expires_at > NOW() 
                LIMIT 1";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email, ':code' => $code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // 2. Persistência: Atualiza para 'validado' para evitar reuso
            $updateSql = "UPDATE registered_codes SET status = 'validado' WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute([':id' => $result['id']]);
            
            return true;
        }

        return false;
    }
   
}