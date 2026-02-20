<?php
namespace App\Models;

use PDO;

class Unit extends BaseModel {
    // A conexão $this->conn já vem do BaseModel via Singleton

    public function getAll() {
        $sql = "SELECT * FROM units ORDER BY name ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM units WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $slug) {
        $stmt = $this->conn->prepare("INSERT INTO units (name, slug) VALUES (:name, :slug)");
        return $stmt->execute([
            ":name" => $name,
            ":slug" => $slug
        ]);
    }

    public function delete($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM units WHERE id = :id");
            $stmt->bindParam(":id", $id);
            
            if ($stmt->execute()) {
                return true; // Retorno explícito
            }
            return false;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' || strpos($e->getMessage(), '1451') !== false) {
                throw new \Exception("Esse registro não pode ser excluído, pois ele está relacionado a 1 ou mais agendamentos");
            }
           
        }
    }
}