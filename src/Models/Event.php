<?php
namespace App\Models;
use PDO;

class Event {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function create($name, $price) {
        $stmt = $this->conn->prepare("INSERT INTO events (name, price) VALUES (:name, :price)");
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":price", $price);
        return $stmt->execute();
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM events")->fetchAll(PDO::FETCH_ASSOC);
    }
}