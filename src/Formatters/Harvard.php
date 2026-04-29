<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Harvard formatter.
 *
 * Layout:
 *   - Title page: title bold+centered in upper half, then author / institution /
 *     course / instructor / date — all centered. Page number "1" in top-right header.
 *   - Body: double-spaced, 0.5" first-line indent, centered bold headings.
 *   - Reference List page: "Reference List" centered (not bold), hanging indent, double-spaced.
 */
class Harvard
{
    public static function build(EssayJSON $essay, array $config): string
    {
        $phpWord = new PhpWord();
        Base::applyDocumentDefaults($phpWord, $config);

        $section = $phpWord->addSection();
        Base::setupSection($section, $config);

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
        // Title: bold, centered, pushed to upper half with spaceBefore
        $titleRun = $section->addTextRun([
            'alignment'   => 'center',
            'spaceBefore' => Converter::inchToTwip(2),
            'spaceAfter'  => 0,
            'lineHeight'  => 2.0,
            'indentation' => ['firstLine' => 0],
        ]);
        $titleRun->addText($essay->title, [
            'name' => $config['font_family'],
            'size' => $config['font_size'],
            'bold' => true,
        ]);

        foreach ([
            $essay->authorNamePlaceholder,
            $essay->institutionPlaceholder,
            $essay->coursePlaceholder,
            $essay->instructorPlaceholder,
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
