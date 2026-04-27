# docx-formatter

A FastAPI service that converts essay content into properly formatted `.docx` files supporting APA7, MLA9, Chicago, and Harvard citation styles.

---

## Features

- **4 citation styles** — APA 7th Edition, MLA 9th Edition, Chicago Author-Date, Harvard
- **Style-accurate formatting** — title pages, page numbers, heading levels, hanging indents, line spacing per style rules
- **Single endpoint** — send content, get a download-ready `.docx` back
- **No dependencies on external APIs** — pure formatting service

---

## Project Structure

```
docx-formatter/
├── main.py              # FastAPI app
├── models.py            # Request/response schemas
├── styles.py            # Citation style configs
├── requirements.txt
└── formatters/
    ├── __init__.py      # Dispatcher
    ├── base.py          # Shared utilities
    ├── apa7.py          # APA 7th Edition
    ├── mla9.py          # MLA 9th Edition
    ├── chicago.py       # Chicago Author-Date
    └── harvard.py       # Harvard
```

---

## Setup

```bash
# 1. Create virtual environment
python -m venv venv

# 2. Activate
venv\Scripts\activate          # Windows
source venv/bin/activate       # Mac/Linux

# 3. Install dependencies
pip install -r requirements.txt

# 4. Run
python main.py
```

Server starts at `http://localhost:8000`

---

## API

### `POST /format`

Receives essay content and returns a formatted `.docx` file as a download.

**Request body:**

```json
{
    "citation_style": "APA7",
    "title": "The Effects of Social Media on Mental Health",
    "body_markdown": "## Introduction\n\nIn recent years (Smith, 2023)...",
    "references": [
        {
            "id": "ref1",
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
| `citation_style` | Yes | `APA7` / `MLA9` / `CHICAGO` / `HARVARD` |
| `title` | Yes | Essay title |
| `body_markdown` | Yes | Essay body — use `##` for headings, `###` for subheadings |
| `references` | Yes | Array of `{ id, formatted }` objects |
| `author_name` | No | Defaults to `[Student Name]` |
| `institution` | No | Defaults to `[University]` |
| `course` | No | Defaults to `[Course]` |
| `instructor` | No | Defaults to `[Instructor]` |
| `date` | No | Defaults to empty |

**Response:** `.docx` file download

---

### `GET /options`

Returns valid values for the `citation_style` field.

```json
{
    "citation_styles": ["APA7", "CHICAGO", "HARVARD", "MLA9"]
}
```

### `GET /health`

```json
{ "status": "ok" }
```

---

## Citation Style Reference

| Style | Title Page | Page Numbers | References Heading |
|---|---|---|---|
| APA7 | Yes (student format) | Top-right, all pages | "References" (bold, centered) |
| MLA9 | No (first-page block) | "LastName #" top-right | "Works Cited" (centered) |
| Chicago | Yes, no number on it | Top-right, content pages | "References" (centered) |
| Harvard | Yes | Top-right, all pages | "Reference List" (centered) |

---

## Interactive Docs

```
http://localhost:8000/docs
```
