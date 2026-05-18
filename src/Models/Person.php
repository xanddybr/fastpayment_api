<?php
namespace App\Models;

class Person extends BaseModel
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $fullName,
        public readonly string  $email,
        public readonly string  $status,
        public readonly int     $typePersonId,
        public readonly ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:           (int) $data['id'],
            fullName:     $data['full_name'],
            email:        $data['email'],
            status:       $data['status'],
            typePersonId: (int) $data['type_person_id'],
            createdAt:    $data['created_at'] ?? null,
        );
    }
}
