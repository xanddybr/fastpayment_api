<?php
namespace App\Exceptions;

class PaymentBusinessException extends \DomainException
{
    public function __construct(
        public readonly string $businessCode,
        public readonly array $context = []
    ) {
        parent::__construct($businessCode);
    }
}
