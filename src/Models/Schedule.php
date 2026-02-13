<?php
namespace App\Models;

use PDO;

class Schedule extends BaseModel {

    public function create($data) {
        $query = "INSERT INTO schedules (unit_id, event_id, event_type_id, vacancies, scheduled_at, status) 
                  VALUES (:unit, :event, :type, :vacancies, :scheduled_at, 'available')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ":unit"         => $data['unit_id'], 
            ":event"        => $data['event_id'], 
            ":type"         => $data['event_type_id'],
            ":vacancies"    => $data['vacancies'] ?? 1, 
            ":scheduled_at" => $data['scheduled_at']
        ]);
    }

    public function getAllAdmin() {
        $query = "SELECT s.id as schedule_id, e.name as event_name, e.price as event_price,
                         et.name as type_name, u.name as unit_name, s.scheduled_at, 
                         s.vacancies, s.status, e.slug
                  FROM schedules s
                  JOIN units u ON s.unit_id = u.id
                  JOIN events e ON s.event_id = e.id
                  JOIN event_types et ON s.event_type_id = et.id
                  ORDER BY s.scheduled_at DESC";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailable($eventSlug = null, $typeSlug = null) {
        $sql = "SELECT s.id as schedule_id, e.name as event_name, e.price as event_price,
                       et.name as type_name, u.name as unit_name, s.scheduled_at, 
                       s.vacancies, e.slug as event_slug, et.slug as type_slug
                FROM schedules s
                JOIN events e ON s.event_id = e.id
                JOIN units u ON s.unit_id = u.id
                JOIN event_types et ON s.event_type_id = et.id
                WHERE s.status = 'available'";

        if ($eventSlug) $sql .= " AND LOWER(e.slug) = :eventSlug";
        if ($typeSlug)  $sql .= " AND LOWER(et.slug) = :typeSlug";

        $stmt = $this->conn->prepare($sql);
        if ($eventSlug) $stmt->bindValue(':eventSlug', $eventSlug);
        if ($typeSlug)  $stmt->bindValue(':typeSlug', $typeSlug);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
 * Sincroniza o status da agenda:
 * 1. Fecha o que já passou ou está acontecendo (<= Agora)
 * 2. Abre o que é futuro (> 1 hora a partir de agora)
 */
    public function syncSchedulesStatus() {
        try {
            $this->conn->beginTransaction();

            // 1. Marcar como 'unavailable' tudo que já passou da hora de início
            // ou que está acontecendo exatamente agora.
            $sqlClose = "UPDATE schedules 
                        SET status = 'unavailable' 
                        WHERE scheduled_at <= NOW() 
                        AND status = 'available'";
            $this->conn->query($sqlClose)->execute();

            // 2. Marcar como 'available' tudo que está no futuro 
            // (com margem de segurança maior que 1 hora)
            $sqlOpen = "UPDATE schedules 
                        SET status = 'available' 
                        WHERE status = 'unavailable' 
                        AND scheduled_at > DATE_ADD(NOW(), INTERVAL 1 HOUR)";
            $this->conn->query($sqlOpen)->execute();

            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM schedules WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}