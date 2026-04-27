"""
Dispatcher — routes format_essay() to the correct style module.
Adding a new style = create a new module + add one line here.
"""

from styles import STYLES
from . import apa7, chicago, harvard, mla9

_BUILDERS = {
    "APA7":    apa7.build,
    "MLA9":    mla9.build,
    "CHICAGO": chicago.build,
    "HARVARD": harvard.build,
}


def format_essay(essay, style_key: str) -> bytes:
    config = STYLES[style_key]
    builder = _BUILDERS[style_key]
    return builder(essay, config)
