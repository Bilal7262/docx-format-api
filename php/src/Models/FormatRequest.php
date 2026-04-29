<?php

namespace App\Models;

class FormatRequest
{
    public readonly string  $citationStyle;
    public readonly string  $title;
    public readonly string  $bodyMarkdown;
    public readonly array   $references;
    public readonly ?string $authorName;
    public readonly ?string $institution;
    public readonly ?string $course;
    public readonly ?string $instructor;
    public readonly ?string $date;

    public function __construct(array $data)
    {
        $this->citationStyle = $this->validateCitationStyle($data['citation_style'] ?? '');
        $this->title         = $data['title'] ?? '';
        $this->bodyMarkdown  = $this->validateBodyMarkdown($data['body_markdown'] ?? '');
        $this->references    = $this->validateReferences($data['references'] ?? []);
        $this->authorName    = $data['author_name'] ?? null;
        $this->institution   = $data['institution'] ?? null;
        $this->course        = $data['course'] ?? null;
        $this->instructor    = $data['instructor'] ?? null;
        $this->date          = $data['date'] ?? null;
    }

    public function toEssayJson(): EssayJSON
    {
        return new EssayJSON(
            title:                  $this->title,
            bodyMarkdown:           $this->bodyMarkdown,
            references:             $this->references,
            authorNamePlaceholder:  $this->authorName   ?? '[Student Name]',
            coursePlaceholder:      $this->course       ?? '[Course]',
            instructorPlaceholder:  $this->instructor   ?? '[Instructor]',
            institutionPlaceholder: $this->institution  ?? '[University]',
            date:                   $this->date         ?? '',
        );
    }

    private function validateCitationStyle(string $style): string
    {
        $allowed = ['APA7', 'MLA9', 'CHICAGO', 'HARVARD'];
        if (!in_array($style, $allowed, true)) {
            throw new \InvalidArgumentException(
                'citation_style must be one of: ' . implode(', ', $allowed)
            );
        }
        return $style;
    }

    private function validateBodyMarkdown(string $body): string
    {
        if (trim($body) === '') {
            throw new \InvalidArgumentException('body_markdown cannot be empty');
        }
        return $body;
    }

    private function validateReferences(array $refs): array
    {
        if (empty($refs)) {
            throw new \InvalidArgumentException('references must contain at least one entry');
        }
        $references = [];
        foreach ($refs as $ref) {
            $reference = Reference::fromArray($ref);
            if (trim($reference->formatted) === '') {
                throw new \InvalidArgumentException(
                    "reference '{$reference->id}' has an empty formatted string"
                );
            }
            $references[] = $reference;
        }
        return $references;
    }
}
