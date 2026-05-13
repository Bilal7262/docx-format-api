<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Chicago 17 formatter.
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

        // Start at 0: title page = 0 (hidden by empty first header), body page 1 = 1
        $section->getStyle()->setPageNumberingStart(0);
        $section->addHeader('first'); // empty — no page number on title page
        Base::addPageNumbers($section, $config); // page number on all other pages

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
        if ($level === 0) {
            Base::addDocumentTitle($section, $text, $config, false); // Chicago: title NOT bold
            return;
        }

        $base = [
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ];

        // Chicago heading levels:
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
