<?php
namespace App\Config;

use PDO;
use PDOException;
use Exception;

class Database {
    private static $instance = null;

    public static function getConnection() {
        if (self::$instance === null) {
            try {
                // Carrega as variáveis de ambiente ou usa valores padrão
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $db   = $_ENV['DB_NAME'] ?? 'u967889760_fastpayment';
                $user = $_ENV['DB_USER'] ?? 'u967889760_fast';
                $pass = $_ENV['DB_PASS'] ?? 'Mistura#1';
                $port = $_ENV['DB_PORT'] ?? '3306';

                $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";
                
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => true // Importante para SaaS: mantém conexões abertas para reuso
                ];

                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new Exception("Erro de conexão: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}