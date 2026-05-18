<?php
namespace App\Repositories;

use PDO;
use App\Contracts\Repositories\EventRepositoryInterface;

class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    public function create(string $name, float $price, string $slug): bool
    {
        $stmt = $this->conn->prepare("INSERT INTO events (name, price, slug) VALUES (:name, :price, :slug)");
        return $stmt->execute([':name' => $name, ':price' => $price, ':slug' => $slug]);
    }

    public function getAll(): array
    {
        return $this->conn->query("SELECT * FROM events ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM events WHERE id = :id");
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
