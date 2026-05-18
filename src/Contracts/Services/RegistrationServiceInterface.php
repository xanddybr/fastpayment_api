<?php
namespace App\Contracts\Services;

interface RegistrationServiceInterface
{
    public function register(array $data): array;
    public function getAllSubscribers(): array;
    public function getTotalRevenue(): float;
    public function getPaymentHistory(): array;
}
