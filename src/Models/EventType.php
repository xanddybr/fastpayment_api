<?php
namespace App\Models;

use PDO;

class EventType extends BaseModel {
    // O BaseModel já cuida do $this->conn via Singleton

    public function create($name, $slug) {
        $stmt = $this->conn->prepare("INSERT INTO event_types (name, slug) VALUES (:name, :slug)");
        return $stmt->execute([
            ":name" => $name,
            ":slug" => $slug
        ]);
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM event_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM event_types WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM event_types WHERE id = :id");
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}