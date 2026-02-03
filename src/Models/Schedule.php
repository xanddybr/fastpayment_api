<?php
namespace App\Models;
use PDO;

class Schedule {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function create($unit_id, $event_id, $type_id, $vacancies, $scheduled_at) {
        $query = "INSERT INTO schedules (unit_id, event_id, event_type_id, vacancies, scheduled_at, status) 
                  VALUES (:unit, :event, :type, :vacancies, :scheduled_at, 'available')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ":unit" => $unit_id, 
            ":event" => $event_id, 
            ":type" => $type_id,
            ":vacancies" => $vacancies, 
            ":scheduled_at" => $scheduled_at
        ]);
    }

    public function getAll() {
        // JOIN rigoroso para trazer nomes em vez de IDs
        $query = "SELECT s.*, u.name as unit_name, e.name as event_name, t.name as type_name 
                  FROM schedules s
                  JOIN units u ON s.unit_id = u.id
                  JOIN events e ON s.event_id = e.id
                  JOIN event_types t ON s.event_type_id = t.id
                  ORDER BY s.scheduled_at ASC";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($id) {
        $query = "DELETE FROM schedules WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}