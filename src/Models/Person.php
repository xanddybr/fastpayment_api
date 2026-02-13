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

            $personId = $this->conn->lastInsertId() ?: $this->findByEmail($data['email'])['id'];

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

    public function updateUnified($id, $data) {
        try {

            $sqlPerson = "UPDATE persons SET full_name = :name, type_person_id = :type_id WHERE id = :id";
            $stmtPerson = $this->conn->prepare($sqlPerson);
            $stmtPerson->execute([
                ':name'    => $data['full_name'],
                ':type_id' => $data['type_person_id'],
                ':id'      => $id
            ]);

            if (!empty($data['password'])) {
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmtPass = $this->conn->prepare("UPDATE persons SET password = :pass WHERE id = :id");
                $stmtPass->execute([':pass' => $hash, ':id' => $id]);
            }

            $details = $data['details'] ?? [];
            $sqlDetails = "UPDATE person_details SET 
                            activity_professional = :act, phone = :phone, street = :street, 
                            number = :num, neighborhood = :neigh, city = :city 
                           WHERE person_id = :id";

            $stmtDetails = $this->conn->prepare($sqlDetails);
            $stmtDetails->execute([
                ':act'    => $details['activity_professional'] ?? null,
                ':phone'  => $details['phone'] ?? null,
                ':street' => $details['street'] ?? null,
                ':num'    => $details['number'] ?? null,
                ':neigh'  => $details['neighborhood'] ?? null,
                ':city'   => $details['city'] ?? null,
                ':id'     => $id
            ]);

            return true;
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
                   ->execute(['email' => $email]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+5 minutes"));

        $sql = "INSERT INTO registered_codes (email, phone, validation_method, code, expires_at, status) 
                VALUES (:email, :phone, 'email', :code, :expiresAt, 'pendente')";
        
        $this->conn->prepare($sql)->execute([
            'email' => $email, 
            'phone' => $phone, 
            'code' => $code, 
            'expiresAt' => $expiresAt
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

    /**
     * Card 8.1: Update Unificado
     * Atualiza dados básicos, profissionais e a anamnese vinculada
     */
    public function updateFullProfile($data) {
        try {

            // 1. Atualiza Tabela persons
            $sqlPerson = "UPDATE persons SET full_name = :name, email = :email, status = :status 
                          WHERE id = :id";
            $this->conn->prepare($sqlPerson)->execute([
                ':name'   => $data['full_name'] ?? null,
                ':email'  => $data['email'] ?? null,
                ':status' => $data['person_status'] ?? $data['status'] ?? 'active', // Tenta as duas chaves
                ':id'     => $data['id']
            ]);

            // 2. Atualiza Tabela person_details
            $sqlDetails = "UPDATE person_details SET 
                            activity_professional = :prof, phone = :phone, street = :street, 
                            number = :num, neighborhood = :neigh, city = :city
                           WHERE person_id = :id";
            $this->conn->prepare($sqlDetails)->execute([
                ':prof'  => $data['activity_professional'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':street'=> $data['street'] ?? null,
                ':num'   => $data['number'] ?? null,
                ':neigh' => $data['neighborhood'] ?? null,
                ':city'  => $data['city'] ?? null,
                ':id'    => $data['id']
            ]);

            // 3. Atualiza Tabela anamnesis
            if (!empty($data['subscription_id'])) {
                $sqlAnam = "UPDATE anamnesis SET 
                                course_reason = :reason, expectations = :expect, 
                                who_recomend = :rec, is_medium = :med, 
                                religion = :rel, religion_mention = :rel_m, 
                                is_tule_member = :tule, obs_motived = :obs, first_time = :first
                            WHERE subscribed_id = :sid";
                $this->conn->prepare($sqlAnam)->execute([
                    ':reason' => $data['course_reason'] ?? null,
                    ':expect' => $data['expectations'] ?? null,
                    ':rec'    => $data['who_recomend'] ?? null,
                    ':med'    => $data['is_medium'] ?? 0,
                    ':rel'    => $data['religion'] ?? 0,
                    ':rel_m'  => $data['religion_mention'] ?? null,
                    ':tule'   => $data['is_tule_member'] ?? 0,
                    ':obs'    => $data['obs_motived'] ?? null,
                    ':first'  => $data['first_time'] ?? 1,
                    ':sid'    => $data['subscription_id']
                ]);
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}