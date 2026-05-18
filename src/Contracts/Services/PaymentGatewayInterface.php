<?php
namespace App\Contracts\Services;

interface PaymentGatewayInterface
{
    public function createPreference(array $data): array;
    public function getPaymentDetails(string $paymentId): array;
}
