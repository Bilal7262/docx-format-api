import io

from fastapi import FastAPI, HTTPException
from fastapi.responses import StreamingResponse

from formatters import format_essay
from models import FormatRequest
from styles import STYLES

app = FastAPI(
    title="MyEssayWriter — Formatted .docx API",
    description="POST /format — receive full essay content, return a formatted .docx file.",
)

VALID_STYLES = set(STYLES.keys())


# ── Utility ──────────────────────────────────────────────────────────────────

def _docx_response(docx_bytes: bytes, title: str, style: str) -> StreamingResponse:
    safe_title = "".join(
        c for c in title[:60] if c.isalnum() or c in " -_"
    ).strip()
    filename = f"{safe_title or 'essay'}_{style.lower()}.docx"

    return StreamingResponse(
        io.BytesIO(docx_bytes),
        media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )


# ── Health / options ─────────────────────────────────────────────────────────

@app.get("/health")
def health():
    return {"status": "ok"}


@app.get("/options")
def options():
    """Valid values for the citation_style field."""
    return {
        "citation_styles": sorted(VALID_STYLES),
    }


# ── Primary endpoint ─────────────────────────────────────────────────────────

@app.post("/format")
async def format_endpoint(request: FormatRequest):
    """
    Receive fully-formed essay content and return a formatted .docx file.

    Payload:
    {
        "citation_style": "APA7",           -- APA7 | MLA9 | CHICAGO | HARVARD
        "title":          "Essay title",
        "body_markdown":  "## Introduction\\n\\nText with (Smith, 2023) citations...",
        "references": [
            { "id": "ref1", "formatted": "Smith, J. (2023). Title. Publisher." }
        ],
        "author_name":  "Jane Smith",       -- optional
        "institution":  "MIT",              -- optional
        "course":       "ENG 201",          -- optional
        "instructor":   "Dr. Brown",        -- optional
        "date":         "April 27, 2026"    -- optional
    }
    """
    try:
        essay = request.to_essay_json()
        docx_bytes = format_essay(essay, request.citation_style)
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Formatting failed: {e}")

    return _docx_response(docx_bytes, request.title, request.citation_style)


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
