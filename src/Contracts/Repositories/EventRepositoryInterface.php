<?php
namespace App\Contracts\Repositories;

interface EventRepositoryInterface
{
    public function create(string $name, float $price, string $slug): bool;
    public function getAll(): array;
    public function findById(int $id): ?array;
    public function delete(int $id): bool;
    public function getConnection(): \PDO;
}
