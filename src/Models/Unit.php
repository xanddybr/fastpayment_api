<?php
namespace App\Models;
use PDO;

class Unit {
    private $conn;
    private $table = "units";

    public function __construct($db) { $this->conn = $db; }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name) {
        $query = "INSERT INTO " . $this->table . " (name) VALUES (:name)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $name);
        return $stmt->execute();
    }
}