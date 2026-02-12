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
                $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
                $db   = $_ENV['DB_NAME'] ?? 'u967889760_fastpayment';
                $user = $_ENV['DB_USER'] ?? 'root';
                $pass = $_ENV['DB_PASS'] ?? 'Mistura#1'; // Senha vazia para o seu root local
                $port = $_ENV['DB_PORT'] ?? '3306';

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