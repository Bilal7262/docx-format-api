from pydantic import BaseModel, field_validator
from typing import List, Literal, Optional


class Reference(BaseModel):
    id: str
    formatted: str


class EssayJSON(BaseModel):
    title: str
    author_name_placeholder: str = "[Student Name]"
    course_placeholder: str = "[Course]"
    instructor_placeholder: str = "[Instructor]"
    institution_placeholder: str = "[University]"
    date: str = ""
    body_markdown: str
    references: List[Reference]


# ── Primary payload: everything comes from the caller ────────────────────────

class FormatRequest(BaseModel):
    # Required
    citation_style: Literal["APA7", "MLA9", "CHICAGO", "HARVARD"]
    title: str
    body_markdown: str
    references: List[Reference]

    # User / cover-page info — optional, defaults to visible placeholders
    author_name: Optional[str] = None
    institution: Optional[str] = None
    course: Optional[str] = None
    instructor: Optional[str] = None
    date: Optional[str] = None

    @field_validator("references")
    @classmethod
    def references_not_empty(cls, v):
        if not v:
            raise ValueError("references must contain at least one entry")
        for ref in v:
            if not ref.formatted.strip():
                raise ValueError(f"reference '{ref.id}' has an empty formatted string")
        return v

    @field_validator("body_markdown")
    @classmethod
    def body_not_empty(cls, v):
        if not v.strip():
            raise ValueError("body_markdown cannot be empty")
        return v

    def to_essay_json(self) -> EssayJSON:
        """Map this request directly to the internal EssayJSON format."""
        return EssayJSON(
            title=self.title,
            author_name_placeholder=self.author_name or "[Student Name]",
            institution_placeholder=self.institution or "[University]",
            course_placeholder=self.course or "[Course]",
            instructor_placeholder=self.instructor or "[Instructor]",
            date=self.date or "",
            body_markdown=self.body_markdown,
            references=self.references,
        )

