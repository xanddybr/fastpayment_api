<?php
namespace App\Repositories;

use PDO;
use Exception;
use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Repositories\PersonRepositoryInterface;
use App\Contracts\Repositories\SubscriberRepositoryInterface;

class PersonRepository extends BaseRepository implements PersonRepositoryInterface, AuthRepositoryInterface, SubscriberRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM persons WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, tp.name AS role
            FROM persons p
            JOIN types_person tp ON p.type_person_id = tp.id
            WHERE p.email = :email LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(): array
    {
        return $this->conn->query("
            SELECT DISTINCT
                p.id, p.full_name, p.email, p.status, p.type_person_id, p.created_at,
                pd.activity_professional, pd.phone, pd.city,
                tp.name AS role
            FROM persons p
            LEFT JOIN person_details pd ON p.id = pd.person_id
            LEFT JOIN types_person tp   ON p.type_person_id = tp.id
            ORDER BY p.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): bool
    {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO persons (full_name, email, password, created_at)
                VALUES (:full_name, :email, :password, NOW())
            ");
            return $stmt->execute([
                ':full_name' => $data['full_name'],
                ':email'     => $data['email'],
                ':password'  => $hash,
            ]);
        } catch (\PDOException $e) {
            error_log('Erro ao criar pessoa: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM persons WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function updatePasswordByEmail(string $email, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("UPDATE persons SET password = :password WHERE email = :email");
        $stmt->execute([':password' => $hash, ':email' => $email]);
        return $stmt->rowCount() > 0;
    }

    public function getAdminEmails(): array
    {
        $stmt = $this->conn->prepare("
            SELECT email FROM persons WHERE type_person_id = 1 AND status = 'active'
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function authenticate(string $email, string $password): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, full_name, email, password
            FROM persons
            WHERE email = :email AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }
        return null;
    }

    public function createTemporary(string $email, string $fullName, ?string $phone): int
    {
        $this->conn->prepare("
            INSERT INTO persons (full_name, email, status, type_person_id)
            VALUES (:name, :email, 'pending', 2)
            ON DUPLICATE KEY UPDATE
                id        = LAST_INSERT_ID(id),
                full_name = VALUES(full_name)
        ")->execute([':name' => $fullName, ':email' => $email]);

        $personId = (int) $this->conn->lastInsertId();

        if ($phone) {
            $this->conn->prepare("
                INSERT INTO person_details (person_id, phone)
                VALUES (:pid, :phone)
                ON DUPLICATE KEY UPDATE phone = VALUES(phone)
            ")->execute([':pid' => $personId, ':phone' => $phone]);
        }

        return $personId;
    }

    public function saveCompleteRegistration(array $data): int
    {
        $fullName = $data['student_full_name'] ?? ($data['full_name'] ?? null);
        $phone    = $data['student_phone']     ?? ($data['phone']     ?? null);

        if (!$fullName) {
            throw new Exception('Nome é obrigatório.');
        }
        if (empty($data['payment_id'])) {
            throw new Exception('payment_id é obrigatório para vincular a pessoa.');
        }

        $stmt = $this->conn->prepare("
            SELECT p.email
            FROM transactions t
            INNER JOIN persons p ON t.person_id = p.id
            WHERE t.payment_id = :payid
            LIMIT 1
        ");
        $stmt->execute([':payid' => $data['payment_id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new Exception('Pagamento não vinculado a um cliente.');
        }

        $email = $existing['email'];

        $this->conn->prepare("
            INSERT INTO persons (full_name, email, status, type_person_id)
            VALUES (:name, :email, 'active', 2)
            ON DUPLICATE KEY UPDATE
                id        = LAST_INSERT_ID(id),
                full_name = VALUES(full_name),
                status    = 'active'
        ")->execute([':name' => $fullName, ':email' => $email]);

        $personId = (int) $this->conn->lastInsertId();

        $this->conn->prepare("
            INSERT INTO person_details (person_id, activity_professional, phone, neighborhood, city)
            VALUES (:pid, :prof, :phone, :neighborhood, :city)
            ON DUPLICATE KEY UPDATE
                activity_professional = VALUES(activity_professional),
                phone                 = VALUES(phone),
                neighborhood          = VALUES(neighborhood),
                city                  = VALUES(city)
        ")->execute([
            ':pid'          => $personId,
            ':prof'         => $data['activity_professional'] ?? null,
            ':phone'        => $phone,
            ':neighborhood' => $data['neighborhood'] ?? null,
            ':city'         => $data['city']         ?? null,
        ]);

        return $personId;
    }

    public function createAnamnesis(int $subscribedId, array $data): void
    {
        $this->conn->prepare("
            INSERT INTO anamnesis
                (subscribed_id, course_reason, who_recomended, is_medium,
                 religion_mention, is_tule_member, first_time)
            VALUES
                (:subid, :reason, :who, :medium, :rel_mention, :tule, :first)
        ")->execute([
            ':subid'       => $subscribedId,
            ':reason'      => $data['course_reason']      ?? null,
            ':who'         => $data['who_recomended']     ?? null,
            ':medium'      => $this->toBool($data['is_medium']      ?? 0),
            ':rel_mention' => $data['religion_mention']   ?? null,
            ':tule'        => $this->toBool($data['is_tule_member'] ?? 0),
            ':first'       => $this->toBool($data['first_time']     ?? 0),
        ]);
    }

    public function getAllSubscribers(): array
    {
        return $this->conn->query("
            SELECT
                p.id AS person_id,
                COALESCE(p.full_name, 'Aguardando inscrição') AS full_name,
                COALESCE(p.email, '-') AS email,
                pd.phone, pd.activity_professional, pd.city, pd.neighborhood,
                es.id AS subscribed_id, es.created_at,
                es.status AS enrollment_status,
                e.name AS event_name, e.price AS valor_evento,
                et.name AS type_name, u.name AS unit_name,
                s.scheduled_at AS event_date,
                es.payment_id AS transacao_gateway,
                p.email AS payer_email,
                t.payment_status, t.created_at AS createdPay,
                a.course_reason, a.who_recomended, a.religion_mention,
                a.is_medium, a.is_tule_member, a.first_time
            FROM events_subscribed es
            LEFT JOIN persons p         ON es.person_id    = p.id
            LEFT JOIN person_details pd ON pd.person_id    = p.id
            INNER JOIN schedules s      ON es.schedule_id  = s.id
            INNER JOIN events e         ON s.event_id      = e.id
            INNER JOIN event_types et   ON s.event_type_id = et.id
            INNER JOIN units u          ON s.unit_id       = u.id
            LEFT JOIN anamnesis a       ON es.id           = a.subscribed_id
            LEFT JOIN (
                SELECT payment_id, payment_status, updated_at, created_at
                FROM transactions
                WHERE (payment_id, updated_at) IN (
                    SELECT payment_id, MAX(updated_at) FROM transactions GROUP BY payment_id
                )
            ) t ON es.payment_id = t.payment_id COLLATE utf8mb4_unicode_ci
            WHERE es.person_id IS NOT NULL
            ORDER BY es.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createValidationCode(string $email, string $phone): string
    {
        $this->conn->prepare("
            UPDATE registered_codes SET status = 'expirado'
            WHERE email = :email AND status = 'pendente'
        ")->execute([':email' => $email]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->conn->prepare("
            INSERT INTO registered_codes (email, code, status) VALUES (:email, :code, 'pendente')
        ")->execute([':email' => $email, ':code' => $code]);

        return $code;
    }

    public function validateOTP(string $email, string $code): bool
    {
        $stmt = $this->conn->prepare("
            SELECT id FROM registered_codes
            WHERE email = :email AND code = :code AND status = 'pendente'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':code' => $code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $this->conn->prepare("
                UPDATE registered_codes SET status = 'expirado' WHERE id = :id
            ")->execute([':id' => $result['id']]);
            return true;
        }
        return false;
    }

    public function deleteValidatedCodes(): int
    {
        $stmt = $this->conn->prepare("DELETE FROM registered_codes WHERE status = 'expirado'");
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function toBool($value): int
    {
        return ($value === 1 || $value === '1' || $value === 'on' || $value === true) ? 1 : 0;
    }
}
