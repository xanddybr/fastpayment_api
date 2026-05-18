<?php
namespace App\Contracts\Services;

interface EmailServiceInterface
{
    public function sendOTP(string $toEmail, string $toName, string $code): bool;
    public function sendPaymentConfirmation(string $payerEmail, array $eventData): bool;
}
