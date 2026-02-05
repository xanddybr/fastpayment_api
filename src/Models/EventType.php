<?php
namespace App\Models;
use PDO;

class EventType {
    private $conn;
    
    public function __construct($db) { $this->conn = $db; }

    public function create($name,$slug) {
        $stmt = $this->conn->prepare("INSERT INTO event_types (name,slug) VALUES (:name, :slug)");
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":slug", $slug);
        return $stmt->execute();
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM event_types")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM event_types WHERE id = :id");
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
}