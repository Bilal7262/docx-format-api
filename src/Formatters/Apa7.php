<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * APA 7th Edition formatter.
 *
 * Layout:
 *   - Title page (page 1): title bold+centered in upper half, then author / institution /
 *     course / instructor / date — all centered. Page number "1" in top-right header.
 *   - Body: double-spaced, 0.5" first-line indent, headings per APA level rules.
 *   - References page: "References" bold+centered, hanging indent, double-spaced.
 */
class Apa7
{
    public static function build(EssayJSON $essay, array $config): string
    {
        $phpWord = new PhpWord();
        Base::applyDocumentDefaults($phpWord, $config);

        $section = $phpWord->addSection();
        Base::setupSection($section, $config);

        self::addPageNumbers($section, $config);
        self::titlePage($section, $essay, $config);
        $section->addPageBreak();
        Base::addBody($section, $essay->bodyMarkdown, $config, [self::class, 'heading']);
        $section->addPageBreak();
        Base::addReferencesPage($section, $essay->references, $config);

        return Base::docToBytes($phpWord);
    }

    // ── Page numbers (APA: hidden on title page, starts at 1 on body) ────────

    private static function addPageNumbers(Section $section, array $config): void
    {
        $alignment = str_contains($config['page_numbers']['position'], 'right') ? 'right' : 'center';
        $fontStyle  = Base::fontStyle($config);
        $paraStyle  = ['alignment' => $alignment];

        // Start at 0: title page = 0 (hidden), body page 1 = 1, page 2 = 2 …
        // Note: adding Header::FIRST automatically enables <w:titlePg/> (different first page)
        $section->getStyle()->setPageNumberingStart(0);

        // Empty first-page header — title page shows no page number
        $section->addHeader(\PhpOffice\PhpWord\Element\Header::FIRST);

        // Default header — shows on every page after the first
        $header = $section->addHeader();
        $header->addPreserveText('{PAGE}', $fontStyle, $paraStyle);
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

    // ── Headings (APA 7 levels) ───────────────────────────────────────────────

    public static function heading(Section $section, string $text, int $level, array $config): void
    {
        if ($level === 0) {
            Base::addDocumentTitle($section, $text, $config, true); // APA: title bold
            return;
        }

        $base = [
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ];

        // APA 7 heading levels (official spec):
        // L1 ##:    centered, bold
        // L2 ###:   left, bold
        // L3 ####:  left, bold italic
        // L4 #####: indented 0.5", bold
        // L5 ######: indented 0.5", bold italic
        $indent = \PhpOffice\PhpWord\Shared\Converter::inchToTwip(0.5);
        $specs = [
            1 => ['alignment' => 'center', 'bold' => true,  'italic' => false, 'indent' => 0],
            2 => ['alignment' => 'left',   'bold' => true,  'italic' => false, 'indent' => 0],
            3 => ['alignment' => 'left',   'bold' => true,  'italic' => true,  'indent' => 0],
            4 => ['alignment' => 'left',   'bold' => true,  'italic' => false, 'indent' => $indent],
            5 => ['alignment' => 'left',   'bold' => true,  'italic' => true,  'indent' => $indent],
        ];
        $s = $specs[$level] ?? $specs[5];

        $paraStyle = array_merge($base, ['alignment' => $s['alignment']]);
        if ($s['indent'] > 0) {
            $paraStyle['indentation'] = ['left' => $s['indent'], 'firstLine' => 0];
        }

        $textRun = $section->addTextRun($paraStyle);
        $textRun->addText($text, [
            'name'   => $config['font_family'],
            'size'   => $config['font_size'],
            'bold'   => $s['bold'],
            'italic' => $s['italic'],
        ]);
    }
}
