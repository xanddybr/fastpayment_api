<?php
namespace App\Models;
use PDO;

class Unit {
    private $conn;

    public function __construct($db) { 
        $this->conn = $db; 
    }

    public function getAll() {
        // Mantendo o padrão de listar em ordem alfabética
        $query = "SELECT * FROM units ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, $slug) {
        // Inserindo nome e slug conforme o padrão que definimos para URLs amigáveis
        $stmt = $this->conn->prepare("INSERT INTO units (name, slug) VALUES (:name, :slug)");
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":slug", $slug);
        return $stmt->execute();
    }

    // Adicionando o método delete para fechar o CRUD do seu Modal
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM units WHERE id = :id");
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}