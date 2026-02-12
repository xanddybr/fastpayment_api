<?php

namespace App\Config;

use PDO;
use PDOException;
use Exception;

class Database {
    // A variável estática que guardará a conexão única
    private static $instance = null;

    public static function getConnection() {
        if (self::$instance === null) {
            try {
                // Tentamos ler do $_ENV. Se não existir, usamos o valor padrão (seu root local)
                $host = $_ENV['DB_HOST'];
                $db   = $_ENV['DB_NAME'];
                $user = $_ENV['DB_USER'];
                $pass = $_ENV['DB_PASS'];
                $port = $_ENV['DB_PORT']; 

                $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";
                
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => true
                ]);
            } catch (PDOException $e) {
                throw new Exception("Erro de conexão: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}