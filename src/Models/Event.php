<?php
namespace App\Models;

class Event extends BaseModel
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly float  $price,
        public readonly string $slug,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:    (int)   $data['id'],
            name:          $data['name'],
            price: (float) $data['price'],
            slug:          $data['slug'],
        );
    }
}
