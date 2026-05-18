<?php
namespace App\Repositories;

use PDO;

abstract class BaseRepository
{
    protected PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getConnection(): PDO
    {
        return $this->conn;
    }
}
