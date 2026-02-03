<?php
namespace App\Models;
use PDO;

class EventType {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function create($name) {
        $stmt = $this->conn->prepare("INSERT INTO event_types (name) VALUES (:name)");
        $stmt->bindParam(":name", $name);
        return $stmt->execute();
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM event_types")->fetchAll(PDO::FETCH_ASSOC);
    }
}