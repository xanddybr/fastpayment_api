<?php
namespace App\Models;

use App\Config\Database;

abstract class BaseModel {
    protected $conn;

    public function __construct() {
        // Todos os filhos já "nascem" com a conexão pronta
        $this->conn = Database::getConnection();
    }

    public function getConnection() {
        return $this->conn;
    }
}