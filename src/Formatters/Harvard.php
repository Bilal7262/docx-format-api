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
        if ($level === 0) {
            Base::addDocumentTitle($section, $text, $config, true); // Harvard: title bold
            return;
        }

        $base = [
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ];

        // Harvard heading levels:
        // L1 ##:    centered, bold
        // L2 ###:   left, bold
        // L3 ####:  left, italic
        // L4 #####: centered, italic
        // L5 ######: underlined, left
        $specs = [
            1 => ['alignment' => 'center', 'bold' => true,  'italic' => false, 'underline' => false],
            2 => ['alignment' => 'left',   'bold' => true,  'italic' => false, 'underline' => false],
            3 => ['alignment' => 'left',   'bold' => false, 'italic' => true,  'underline' => false],
            4 => ['alignment' => 'center', 'bold' => false, 'italic' => true,  'underline' => false],
            5 => ['alignment' => 'left',   'bold' => false, 'italic' => false, 'underline' => true],
        ];
        $s = $specs[$level] ?? $specs[5];

        $textRun = $section->addTextRun(array_merge($base, ['alignment' => $s['alignment']]));
        $textRun->addText($text, [
            'name'      => $config['font_family'],
            'size'      => $config['font_size'],
            'bold'      => $s['bold'],
            'italic'    => $s['italic'],
            'underline' => $s['underline'] ? 'single' : 'none',
        ]);
    }
}
