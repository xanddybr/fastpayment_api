<?php
namespace App\Models;

class Registration extends BaseModel
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $personId,
        public readonly int     $scheduleId,
        public readonly string  $paymentId,
        public readonly string  $status,
        public readonly ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:         (int) $data['id'],
            personId:   (int) $data['person_id'],
            scheduleId: (int) $data['schedule_id'],
            paymentId:        $data['payment_id'],
            status:           $data['status'],
            createdAt:        $data['created_at'] ?? null,
        );
    }
}
