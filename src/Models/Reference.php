<?php

namespace App\Models;

class Reference
{
    public string $id;
    public string $formatted;

    public function __construct(string $id, string $formatted)
    {
        $this->id        = $id;
        $this->formatted = $formatted;
    }

    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['formatted']);
    }
}
