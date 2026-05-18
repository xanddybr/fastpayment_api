<?php
namespace App\Services;

use App\Contracts\Repositories\PersonRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\EmailServiceInterface;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private PersonRepositoryInterface $personRepo,
        private EmailServiceInterface $emailService
    ) {}

    public function login(string $email, string $password): ?array
    {
        return $this->personRepo->authenticate($email, $password);
    }

    public function generateValidationCode(string $email, string $phone): string
    {
        $code = $this->personRepo->createValidationCode($email, $phone);
        return $code;
    }

    public function validateCode(string $email, string $code, ?string $name, ?string $phone): bool
    {
        $isValid = $this->personRepo->validateOTP($email, $code);

        if ($isValid && $name) {
            $this->personRepo->createTemporary($email, $name, $phone);
        }

        return $isValid;
    }

    public function createTempPerson(string $email, string $name): int
    {
        return $this->personRepo->createTemporary($email, $name, null);
    }

    public function cleanupCodes(): int
    {
        return $this->personRepo->deleteValidatedCodes();
    }
}
