<?php
namespace App\Contracts\Repositories;

interface AuthRepositoryInterface
{
    public function authenticate(string $email, string $password): ?array;
    public function createTemporary(string $email, string $fullName, ?string $phone): int;
    public function createValidationCode(string $email, string $phone): string;
    public function validateOTP(string $email, string $code): bool;
    public function deleteValidatedCodes(): int;
}
