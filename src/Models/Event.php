<?php

namespace App\Models;

use PDO;

class Event extends BaseModel {
    // Não precisa de construtor, o BaseModel já fornece $this->conn via Singleton

    public function create($name, $price, $slug) {
        $sql = "INSERT INTO events (name, price, slug) VALUES (:name, :price, :slug)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ":name"  => $name,
            ":price" => $price,
            ":slug"  => $slug
        ]);
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM events ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM events WHERE id = :id");
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}