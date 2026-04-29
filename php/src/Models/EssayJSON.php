<?php

namespace App\Models;

class EssayJSON
{
    public function __construct(
        public readonly string $title,
        public readonly string $bodyMarkdown,
        public readonly array  $references,
        public readonly string $authorNamePlaceholder = '[Student Name]',
        public readonly string $coursePlaceholder = '[Course]',
        public readonly string $instructorPlaceholder = '[Instructor]',
        public readonly string $institutionPlaceholder = '[University]',
        public readonly string $date = '',
    ) {}
}
