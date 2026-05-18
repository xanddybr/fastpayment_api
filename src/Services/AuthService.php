<?php
namespace App\Services;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\EmailServiceInterface;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private AuthRepositoryInterface $authRepo,
        private EmailServiceInterface $emailService
    ) {}

    public function login(string $email, string $password): ?array
    {
        return $this->authRepo->authenticate($email, $password);
    }

    public function generateValidationCode(string $email, string $phone, string $name): void
    {
        $code = $this->authRepo->createValidationCode($email, $phone);
        $this->emailService->sendOTP($email, $name, $code);
    }

    public function validateCode(string $email, string $code, ?string $name, ?string $phone): bool
    {
        $isValid = $this->authRepo->validateOTP($email, $code);

        if ($isValid && $name) {
            $this->authRepo->createTemporary($email, $name, $phone);
        }

        return $isValid;
    }

    public function createTempPerson(string $email, string $name): int
    {
        return $this->authRepo->createTemporary($email, $name, null);
    }

    public function cleanupCodes(): int
    {
        return $this->authRepo->deleteValidatedCodes();
    }
}
