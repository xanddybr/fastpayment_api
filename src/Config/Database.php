<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    public static function getConnection() {
        // Variáveis locais para método estático (Correto!)
        $host = "localhost";
        $db_name = "u967889760_fastpayment";
        $username = "u967889760_fast";
        $password = "Mistura#1";

        try {
            // Conexão PDO
            $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
            
            // 1. Configura fuso horário no MySQL (Importante para o NOW() funcionar com horários de Brasília)
            $conn->exec("SET time_zone='-03:00';");
            
            // 2. Garante que o PHP capture erros do SQL como Exceções (Crítico para Transações)
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 3. Opcional: Desativa emulação de prepares para maior segurança
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            return $conn;
        } catch(PDOException $e) {
            // Lança a exceção para ser tratada pelo Controller
            throw new \Exception("Erro na conexão: " . $e->getMessage());
        }
    }
}