<?php
namespace App\Contracts\Repositories;

interface PersonRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function findAll(): array;
    public function create(array $data): bool;
    public function delete(int $id): bool;
    public function updatePasswordByEmail(string $email, string $newPassword): bool;
    public function getAdminEmails(): array;
}
