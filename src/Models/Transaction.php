<?php
namespace App\Models;

class Transaction extends BaseModel
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $scheduleId,
        public readonly float   $amount,
        public readonly string  $externalReference,
        public readonly string  $paymentStatus,
        public readonly ?int    $personId       = null,
        public readonly ?string $paymentId      = null,
        public readonly ?string $preferenceId   = null,
        public readonly ?string $createdAt      = null,
        public readonly ?string $updatedAt      = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:                (int)   $data['id'],
            scheduleId:        (int)   $data['schedule_id'],
            amount:            (float) $data['amount'],
            externalReference:         $data['external_reference'],
            paymentStatus:             $data['payment_status'],
            personId:          isset($data['person_id'])    ? (int) $data['person_id'] : null,
            paymentId:                 $data['payment_id']    ?? null,
            preferenceId:              $data['preference_id'] ?? null,
            createdAt:                 $data['created_at']    ?? null,
            updatedAt:                 $data['updated_at']    ?? null,
        );
    }
}
