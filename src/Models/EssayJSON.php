<?php

namespace App\Models;

class EssayJSON
{
    public string $title;
    public string $bodyMarkdown;
    public array  $references;
    public string $authorNamePlaceholder;
    public string $coursePlaceholder;
    public string $instructorPlaceholder;
    public string $institutionPlaceholder;
    public string $date;

    public function __construct(
        string $title,
        string $bodyMarkdown,
        array  $references,
        string $authorNamePlaceholder  = '[Student Name]',
        string $coursePlaceholder      = '[Course]',
        string $instructorPlaceholder  = '[Instructor]',
        string $institutionPlaceholder = '[University]',
        string $date                   = ''
    ) {
        $this->title                  = $title;
        $this->bodyMarkdown           = $bodyMarkdown;
        $this->references             = $references;
        $this->authorNamePlaceholder  = $authorNamePlaceholder;
        $this->coursePlaceholder      = $coursePlaceholder;
        $this->instructorPlaceholder  = $instructorPlaceholder;
        $this->institutionPlaceholder = $institutionPlaceholder;
        $this->date                   = $date;
    }
}
