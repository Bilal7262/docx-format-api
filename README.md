# docx-formatter (PHP)

A PHP API that converts essay content into properly formatted `.docx` files supporting APA 7, MLA 9, Chicago Author-Date, and Harvard citation styles.

---

## Requirements

- **PHP >= 8.0** — [windows.php.net/download](https://windows.php.net/download) (Non Thread Safe x64 ZIP → extract to `C:\php` → add `C:\php` to system PATH)
- **Composer** — [getcomposer.org/Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe)

Verify:
```bash
php --version
composer --version
```

---

## Project Structure

```
docx-formatter/
├── composer.json
├── index.php                  # API entry point + /docs interactive UI
├── scripts/
│   └── generate.php           # CLI script to generate .docx files
└── src/
    ├── Styles.php             # Citation style configs
    ├── Models/
    │   ├── Reference.php
    │   ├── EssayJSON.php
    │   └── FormatRequest.php  # Input validation
    └── Formatters/
        ├── Base.php           # Shared utilities
        ├── Apa7.php           # APA 7th Edition
        ├── Mla9.php           # MLA 9th Edition
        ├── Chicago.php        # Chicago Author-Date
        ├── Harvard.php        # Harvard
        └── FormatterDispatcher.php
```

---

## Setup

```bash
composer install
```

---

## Run Scripts

### Start web server

```bash
composer serve
# or:
php -S localhost:8080 index.php
```

Server starts at `http://localhost:8080`

---

### Generate `.docx` from command line

```bash
# Generate all 4 styles
composer generate

# Single style (use exact style name with quotes)
php scripts/generate.php "APA 7"
php scripts/generate.php "MLA 9"
php scripts/generate.php "Chicago Author-Date"
php scripts/generate.php "Harvard"

# Custom output filename
php scripts/generate.php "APA 7" my_essay.docx
```

---

## API Endpoints

### `GET /docs`

Interactive API documentation page — test all endpoints directly in the browser, including file download.

```
http://localhost:8080/docs
```

---

### `POST /format`

Send essay content, receive a formatted `.docx` file download.

**Request body (JSON):**

```json
{
    "citation_style": "APA 7",
    "title": "The Effects of Social Media on Mental Health",
    "body_markdown": "## Introduction\n\nIn recent years (Smith, 2023)...",
    "references": [
        {
            "id": "smith2023",
            "formatted": "Smith, J. A. (2023). The digital generation. Journal of Youth Studies, 45(2), 112–130."
        }
    ],
    "author_name":  "Jane Smith",
    "institution":  "University of London",
    "course":       "PSY 301",
    "instructor":   "Dr. Ahmed",
    "date":         "April 27, 2026"
}
```

| Field | Required | Description |
|---|---|---|
| `citation_style` | Yes | `APA 7` / `MLA 9` / `Chicago Author-Date` / `Harvard` |
| `title` | Yes | Essay title |
| `body_markdown` | Yes | Body — `##` headings, `###` subheadings, `**bold**`, `*italic*` |
| `references` | Yes | Array of `{ id, formatted }` |
| `author_name` | No | Defaults to `[Student Name]` |
| `institution` | No | Defaults to `[University]` |
| `course` | No | Defaults to `[Course]` |
| `instructor` | No | Defaults to `[Instructor]` |
| `date` | No | Defaults to empty |

**Response:** `.docx` file download

**Example (curl):**
```bash
curl -X POST http://localhost:8080/format \
  -H "Content-Type: application/json" \
  -d '{"citation_style":"APA 7","title":"My Essay","body_markdown":"## Intro\n\nHello world.","references":[{"id":"r1","formatted":"Smith, J. (2023). Example. Journal, 1(1), 1-10."}]}' \
  -o essay.docx
```

---

### `GET /options`

Returns valid `citation_style` values.

```json
{ "citation_styles": ["APA 7", "Chicago Author-Date", "Harvard", "MLA 9"] }
```

### `GET /health`

```json
{ "status": "ok" }
```

---

## Citation Style Reference

| Style | Title Page | Page Numbers | References Heading |
|---|---|---|---|
| APA 7 | Yes (student format) | Top-right, all pages | "References" (bold, centered) |
| MLA 9 | No (first-page block) | "LastName #" top-right | "Works Cited" (centered) |
| Chicago Author-Date | Yes, no number on it | Top-right, content pages | "References" (centered) |
| Harvard | Yes | Top-right, all pages | "Reference List" (centered) |

---

## Dependencies

| Library | Purpose |
|---|---|
| `phpoffice/phpword ^1.2` | `.docx` file generation |
