<?php
namespace App\Contracts\Services;

interface PaymentServiceInterface
{
    public function createPayment(string $email, int $scheduleId, ?int $personId, bool $forceNew): array;
    public function processWebhook(string $paymentId, string $topic): void;
    public function cleanupPending(): int;
    public function checkPayment(string $email): array;
    public function validatePayment(string $paymentId): ?array;
}
