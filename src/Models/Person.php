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
            $this->conn->beginTransaction();

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

            $this->conn->commit();
            return $personId;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function updateUnified($id, $data) {
        try {
            $this->conn->beginTransaction();

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

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
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
}