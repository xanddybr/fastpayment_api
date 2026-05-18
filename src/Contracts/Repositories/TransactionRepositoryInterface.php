<?php
namespace App\Contracts\Repositories;

interface TransactionRepositoryInterface
{
    public function getEventDetailsBySchedule(int $scheduleId): ?array;
    public function findPendingByEmailAndSchedule(string $email, int $scheduleId): ?array;
    public function findApprovedByEmailAndSchedule(string $email, int $scheduleId): ?array;
    public function reuseExistingPending(string $existingRef, string $newRef, ?int $personId): bool;
    public function createPending(int $scheduleId, ?int $personId, float $amount, string $externalRef): bool;
    public function updatePreferenceId(string $externalRef, string $preferenceId): bool;
    public function updatePaymentStatus(string $paymentId, string $externalRef, string $status): void;
    public function confirmPayment(string $paymentId, string $externalRef): bool;
    public function confirmSubscription(string $paymentId): void;
    public function linkPersonToPayment(string $paymentId, int $personId): void;
    public function findPersonIdByEmail(string $email): ?int;
    public function getPaidPendingRegistrations(string $email): array;
    public function validatePaymentById(string $paymentId): ?array;
    public function deleteStalePending(): int;
}
