<?php

namespace App\Models;
use PDO;

class Event {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function create($name, $price, $slug) {
    // Adicionamos a coluna slug na query SQL
    $stmt = $this->conn->prepare("INSERT INTO events (name, price, slug) VALUES (:name, :price, :slug)");
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":price", $price);
    $stmt->bindParam(":slug", $slug); // Novo campo
    return $stmt->execute();
}

    public function getAll() {
        return $this->conn->query("SELECT * FROM events")->fetchAll(PDO::FETCH_ASSOC);
    }
}