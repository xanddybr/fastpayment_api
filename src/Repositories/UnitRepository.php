<?php
namespace App\Repositories;

use PDO;
use App\Contracts\Repositories\UnitRepositoryInterface;

class UnitRepository extends BaseRepository implements UnitRepositoryInterface
{
    public function getAll(): array
    {
        return $this->conn->query("SELECT * FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM units WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $name, string $slug): bool
    {
        $stmt = $this->conn->prepare("INSERT INTO units (name, slug) VALUES (:name, :slug)");
        return $stmt->execute([':name' => $name, ':slug' => $slug]);
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM units WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' || strpos($e->getMessage(), '1451') !== false) {
                throw new \Exception("Esse registro não pode ser excluído, pois ele está relacionado a 1 ou mais agendamentos");
            }
            throw $e;
        }
    }
}
