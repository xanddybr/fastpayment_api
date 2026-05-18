<?php
namespace App\Contracts\Services;

interface AuthServiceInterface
{
    public function login(string $email, string $password): ?array;
    public function generateValidationCode(string $email, string $phone, string $name): void;
    public function validateCode(string $email, string $code, ?string $name, ?string $phone): bool;
    public function createTempPerson(string $email, string $name): int;
    public function cleanupCodes(): int;
}
