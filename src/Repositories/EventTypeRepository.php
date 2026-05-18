<?php
namespace App\Repositories;

use PDO;
use App\Contracts\Repositories\EventTypeRepositoryInterface;

class EventTypeRepository extends BaseRepository implements EventTypeRepositoryInterface
{
    public function create(string $name, string $slug): bool
    {
        $stmt = $this->conn->prepare("INSERT INTO event_types (name, description, slug) VALUES (:name, :description, :slug)");
        return $stmt->execute([':name' => $name, ':description' => null, ':slug' => $slug]);
    }

    public function getAll(): array
    {
        return $this->conn->query("SELECT * FROM event_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM event_types WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM event_types WHERE id = :id");
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
