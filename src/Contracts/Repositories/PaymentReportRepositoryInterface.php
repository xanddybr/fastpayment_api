<?php
namespace App\Contracts\Repositories;

interface PaymentReportRepositoryInterface
{
    public function getTotalRevenue(): float;
    public function getTransactionsReport(): array;
}
