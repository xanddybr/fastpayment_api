<?php
namespace App\Models;

class Unit extends BaseModel
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $slug,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:   (int) $data['id'],
            name:       $data['name'],
            slug:       $data['slug'],
        );
    }
}
