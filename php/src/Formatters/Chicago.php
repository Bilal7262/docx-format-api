<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Chicago Author-Date formatter.
 *
 * Layout:
 *   - Title page: title centered ~1/3 down the page, then author / course / date.
 *     NO page number on the title page (differentFirstPage = true).
 *   - Body: double-spaced, 0.5" first-line indent, centered bold headings.
 *   - References page: "References" centered (not bold), single-spaced entries
 *     with a blank line between each, hanging indent.
 */
class Chicago
{
    public static function build(EssayJSON $essay, array $config): string
    {
        $phpWord = new PhpWord();
        Base::applyDocumentDefaults($phpWord, $config);

        $section = $phpWord->addSection();
        Base::setupSection($section, $config);

        // Suppress page number on the title page; default header shows it on all other pages
        $section->getStyle()->setDifferentFirstPage(true);
        $section->addHeader('first'); // empty first-page header
        Base::addPageNumbers($section, $config);

        self::titlePage($section, $essay, $config);
        $section->addPageBreak();
        Base::addBody($section, $essay->bodyMarkdown, $config, [self::class, 'heading']);
        $section->addPageBreak();
        Base::addReferencesPage($section, $essay->references, $config);

        return Base::docToBytes($phpWord);
    }

    // ── Title page ────────────────────────────────────────────────────────────

    private static function titlePage(Section $section, EssayJSON $essay, array $config): void
    {
        // Title centered roughly one-third down the page, plain text (not bold)
        $titleRun = $section->addTextRun([
            'alignment'   => 'center',
            'spaceBefore' => Converter::inchToTwip(3),
            'spaceAfter'  => 0,
            'lineHeight'  => 2.0,
            'indentation' => ['firstLine' => 0],
        ]);
        $titleRun->addText($essay->title, [
            'name' => $config['font_family'],
            'size' => $config['font_size'],
        ]);

        foreach ([
            $essay->authorNamePlaceholder,
            $essay->coursePlaceholder,
            $essay->date,
        ] as $text) {
            Base::centeredLine($section, $text, $config);
        }
    }

    // ── Headings ──────────────────────────────────────────────────────────────

    public static function heading(Section $section, string $text, int $level, array $config): void
    {
        // Centered bold for level 1, left-aligned bold for level 2
        $alignment = $level === 1 ? 'center' : 'left';

        $textRun = $section->addTextRun([
            'alignment'   => $alignment,
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ]);
        $textRun->addText($text, [
            'name' => $config['font_family'],
            'size' => $config['font_size'],
            'bold' => true,
        ]);
    }
}
