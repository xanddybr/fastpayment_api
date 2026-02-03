<?php
namespace App\Models;
use PDO;

class Person {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function findByEmail($email) {
        $sql = "SELECT p.*, tp.name as role 
                FROM persons p 
                JOIN types_person tp ON p.type_person_id = tp.id 
                WHERE p.email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
