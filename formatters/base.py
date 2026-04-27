"""
Shared utilities used by every style formatter.
No style-specific logic lives here.
"""

import io
import re

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt


# ── Document setup ───────────────────────────────────────────────────────────

def apply_document_defaults(doc: Document, config: dict) -> None:
    normal = doc.styles["Normal"]
    normal.font.name = config["font_family"]
    normal.font.size = Pt(config["font_size"])
    pf = normal.paragraph_format
    pf.line_spacing_rule = WD_LINE_SPACING.DOUBLE
    pf.space_before = Pt(0)
    pf.space_after = Pt(0)


def setup_section(section, config: dict) -> None:
    m = Inches(config["margin_inches"])
    section.top_margin = m
    section.bottom_margin = m
    section.left_margin = m
    section.right_margin = m


# ── Page numbers ─────────────────────────────────────────────────────────────

def add_page_numbers(section, config: dict, last_name: str = "") -> None:
    """
    Writes a page-number field into the section's default header.
    Pass last_name for MLA "LastName #" format.
    For Chicago (suppress on title page), call this after setting
    section.different_first_page_header_footer = True — this only
    populates the default header, leaving the first-page header empty.
    """
    fmt = config["page_numbers"].get("format", "number_only")
    pos = config["page_numbers"]["position"]
    alignment = WD_ALIGN_PARAGRAPH.RIGHT if "right" in pos else WD_ALIGN_PARAGRAPH.CENTER

    header = section.header
    para = header.paragraphs[0] if header.paragraphs else header.add_paragraph()
    _clear_para_content(para)
    para.alignment = alignment

    if fmt == "lastname_page" and last_name:
        run = para.add_run(last_name + " ")  # non-breaking space before number
        set_run_font(run, config)

    _append_page_number_field(para, config)


def _append_page_number_field(paragraph, config: dict) -> None:
    run_begin = paragraph.add_run()
    fldChar1 = OxmlElement("w:fldChar")
    fldChar1.set(qn("w:fldCharType"), "begin")
    run_begin._r.append(fldChar1)
    set_run_font(run_begin, config)

    run_instr = paragraph.add_run()
    instrText = OxmlElement("w:instrText")
    instrText.set(qn("xml:space"), "preserve")
    instrText.text = " PAGE "
    run_instr._r.append(instrText)
    set_run_font(run_instr, config)

    run_end = paragraph.add_run()
    fldChar2 = OxmlElement("w:fldChar")
    fldChar2.set(qn("w:fldCharType"), "end")
    run_end._r.append(fldChar2)
    set_run_font(run_end, config)


# ── Body ─────────────────────────────────────────────────────────────────────

def add_body(doc: Document, body_markdown: str, config: dict, heading_fn) -> None:
    """
    Converts body_markdown blocks into Word paragraphs.
    heading_fn(doc, text, level, config) is supplied by each style module
    so heading alignment/bold/italic stays style-specific.
    """
    first_line_indent = Inches(config.get("body_first_line_indent_inches", 0.5))

    for block_type, content in parse_blocks(body_markdown):
        if block_type == "h1":
            heading_fn(doc, content, 1, config)
        elif block_type == "h2":
            heading_fn(doc, content, 2, config)
        else:
            p = doc.add_paragraph()
            p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.DOUBLE
            p.paragraph_format.space_before = Pt(0)
            p.paragraph_format.space_after = Pt(0)
            p.paragraph_format.first_line_indent = first_line_indent
            add_inline_runs(p, content, config)


# ── References page ──────────────────────────────────────────────────────────

def add_references_page(doc: Document, references: list, config: dict) -> None:
    """
    Fully driven by config so no style_key branching is needed here.
    Chicago sets references_double_spaced=False in its config.
    """
    hanging = Inches(config["hanging_indent_inches"])
    double_spaced = config["references_double_spaced"]

    heading = doc.add_paragraph()
    heading.alignment = WD_ALIGN_PARAGRAPH.CENTER
    heading.paragraph_format.line_spacing_rule = WD_LINE_SPACING.DOUBLE
    heading.paragraph_format.space_before = Pt(0)
    heading.paragraph_format.space_after = Pt(0)
    heading.paragraph_format.first_line_indent = Inches(0)
    run = heading.add_run(config["references_label"])
    run.bold = config["references_bold"]
    set_run_font(run, config)

    for ref in references:
        p = doc.add_paragraph()
        p.paragraph_format.left_indent = hanging
        p.paragraph_format.first_line_indent = -hanging
        p.paragraph_format.space_before = Pt(0)
        if double_spaced:
            p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.DOUBLE
            p.paragraph_format.space_after = Pt(0)
        else:
            # Chicago: single-spaced entry, blank line between entries
            p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
            p.paragraph_format.space_after = Pt(12)
        run = p.add_run(ref.formatted)
        set_run_font(run, config)


# ── Markdown parsing ─────────────────────────────────────────────────────────

def parse_blocks(markdown: str) -> list:
    """Split markdown into [(block_type, content)] — h1, h2, paragraph."""
    blocks = []
    current_lines = []

    for line in markdown.split("\n"):
        stripped = line.strip()
        if stripped.startswith("### "):
            if current_lines:
                blocks.append(("paragraph", " ".join(current_lines)))
                current_lines = []
            blocks.append(("h2", stripped[4:].strip()))
        elif stripped.startswith("## "):
            if current_lines:
                blocks.append(("paragraph", " ".join(current_lines)))
                current_lines = []
            blocks.append(("h1", stripped[3:].strip()))
        elif stripped == "":
            if current_lines:
                blocks.append(("paragraph", " ".join(current_lines)))
                current_lines = []
        else:
            current_lines.append(stripped)

    if current_lines:
        blocks.append(("paragraph", " ".join(current_lines)))

    return blocks


def parse_inline(text: str) -> list:
    """Returns [(text, bold, italic)] tuples for inline markdown."""
    result = []
    pattern = re.compile(r"\*\*\*(.+?)\*\*\*|\*\*(.+?)\*\*|\*(.+?)\*", re.DOTALL)
    last_end = 0

    for match in pattern.finditer(text):
        if match.start() > last_end:
            result.append((text[last_end:match.start()], False, False))
        if match.group(1):
            result.append((match.group(1), True, True))
        elif match.group(2):
            result.append((match.group(2), True, False))
        elif match.group(3):
            result.append((match.group(3), False, True))
        last_end = match.end()

    if last_end < len(text):
        result.append((text[last_end:], False, False))

    return result or [(text, False, False)]


def add_inline_runs(paragraph, text: str, config: dict) -> None:
    for segment, bold, italic in parse_inline(text):
        run = paragraph.add_run(segment)
        run.bold = bold
        run.italic = italic
        set_run_font(run, config)


# ── Shared paragraph helpers ─────────────────────────────────────────────────

def centered_line(doc: Document, text: str, config: dict, bold: bool = False):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.DOUBLE
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after = Pt(0)
    p.paragraph_format.first_line_indent = Inches(0)
    run = p.add_run(text)
    run.bold = bold
    set_run_font(run, config)
    return p


def set_run_font(run, config: dict) -> None:
    run.font.name = config["font_family"]
    run.font.size = Pt(config["font_size"])


# ── Internal helpers ─────────────────────────────────────────────────────────

def _clear_para_content(para) -> None:
    """Remove all run content from a paragraph, keeping paragraph properties."""
    p = para._p
    for child in list(p):
        if child.tag != qn("w:pPr"):
            p.remove(child)


def extract_last_name(full_name: str) -> str:
    if full_name.startswith("["):
        return "[LastName]"
    parts = full_name.strip().split()
    return parts[-1] if parts else "[LastName]"


def doc_to_bytes(doc: Document) -> bytes:
    buf = io.BytesIO()
    doc.save(buf)
    buf.seek(0)
    return buf.getvalue()
