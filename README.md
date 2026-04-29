# docx-formatter (PHP)

A PHP service that converts essay content into properly formatted `.docx` files supporting APA7, MLA9, Chicago, and Harvard citation styles.

---

## Features

- **4 citation styles** — APA 7th Edition, MLA 9th Edition, Chicago Author-Date, Harvard
- **Style-accurate formatting** — title pages, page numbers, heading levels, hanging indents, line spacing per style rules
- **Inline markdown** — `**bold**`, `*italic*`, `***bold+italic***` in body text
- **No external APIs** — pure formatting service, powered by `phpoffice/phpword`

---

## Project Structure

```
php/
├── composer.json        # phpoffice/phpword dependency
├── index.php            # Example entry point
└── src/
    ├── Styles.php       # Citation style configs
    ├── Models/
    │   ├── Reference.php
    │   ├── EssayJSON.php
    │   └── FormatRequest.php   # Input validation
    └── Formatters/
        ├── Base.php            # Shared utilities
        ├── Apa7.php            # APA 7th Edition
        ├── Mla9.php            # MLA 9th Edition
        ├── Chicago.php         # Chicago Author-Date
        ├── Harvard.php         # Harvard
        └── FormatterDispatcher.php
```

---

## Setup

```bash
# 1. Go into the php directory
cd php

# 2. Install dependencies
composer install

# 3. Run (built-in server)
php -S localhost:8080 index.php
```

---

## Usage

Call `FormatterDispatcher::formatEssay()` with a validated `EssayJSON` object:

```php
use App\Formatters\FormatterDispatcher;
use App\Models\FormatRequest;

$data = [
    'citation_style' => 'APA7',
    'title'          => 'The Effects of Social Media on Mental Health',
    'body_markdown'  => "## Introduction\n\nIn recent years (Smith, 2023)...",
    'references'     => [
        [
            'id'        => 'ref1',
            'formatted' => 'Smith, J. A. (2023). The digital generation. Journal of Youth Studies, 45(2), 112–130.',
        ],
    ],
    'author_name' => 'Jane Smith',
    'institution' => 'University of London',
    'course'      => 'PSY 301',
    'instructor'  => 'Dr. Ahmed',
    'date'        => 'April 27, 2026',
];

$request   = new FormatRequest($data);   // validates input
$essay     = $request->toEssayJson();
$docxBytes = FormatterDispatcher::formatEssay($essay, $request->citationStyle);

// Stream as download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="essay.docx"');
echo $docxBytes;
```

### Input Fields

| Field | Required | Description |
|---|---|---|
| `citation_style` | Yes | `APA7` / `MLA9` / `CHICAGO` / `HARVARD` |
| `title` | Yes | Essay title |
| `body_markdown` | Yes | Essay body — use `##` for headings, `###` for subheadings |
| `references` | Yes | Array of `{ id, formatted }` objects |
| `author_name` | No | Defaults to `[Student Name]` |
| `institution` | No | Defaults to `[University]` |
| `course` | No | Defaults to `[Course]` |
| `instructor` | No | Defaults to `[Instructor]` |
| `date` | No | Defaults to empty |

---

## Citation Style Reference

| Style | Title Page | Page Numbers | References Heading |
|---|---|---|---|
| APA7 | Yes (student format) | Top-right, all pages | "References" (bold, centered) |
| MLA9 | No (first-page block) | "LastName #" top-right | "Works Cited" (centered) |
| Chicago | Yes, no number on it | Top-right, content pages | "References" (centered) |
| Harvard | Yes | Top-right, all pages | "Reference List" (centered) |

---

## Dependencies

| Library | Purpose |
|---|---|
| `phpoffice/phpword ^1.2` | `.docx` file generation |
