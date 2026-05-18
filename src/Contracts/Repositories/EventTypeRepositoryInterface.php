<?php
namespace App\Contracts\Repositories;

interface EventTypeRepositoryInterface
{
    public function create(string $name, string $slug): bool;
    public function getAll(): array;
    public function findById(int $id): ?array;
    public function delete(int $id): bool;
    public function getConnection(): \PDO;
}
