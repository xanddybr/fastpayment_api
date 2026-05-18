<?php
namespace App\Contracts\Repositories;

interface ScheduleRepositoryInterface
{
    public function create(array $data): bool;
    public function getAllAdmin(): array;
    public function getAvailable(?string $eventSlug, ?string $typeSlug): array;
    public function closeExpired(): void;
    public function delete(int $id): bool;
}
