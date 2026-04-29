<?php

namespace App\Models;

class Reference
{
    public function __construct(
        public readonly string $id,
        public readonly string $formatted,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            formatted: $data['formatted'],
        );
    }
}
