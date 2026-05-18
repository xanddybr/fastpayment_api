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
    public function createTemporary(string $email, string $fullName, ?string $phone): int;
    public function saveCompleteRegistration(array $data): int;
    public function createAnamnesis(int $subscribedId, array $data): void;
    public function getAllSubscribers(): array;
    public function authenticate(string $email, string $password): ?array;
    public function createValidationCode(string $email, string $phone): string;
    public function validateOTP(string $email, string $code): bool;
    public function deleteValidatedCodes(): int;
}
