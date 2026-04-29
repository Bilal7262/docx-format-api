<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * MLA 9th Edition formatter.
 *
 * Layout:
 *   - No separate title page.
 *   - First page starts with a left-aligned block (student name / instructor / course / date),
 *     then a centered plain-text title, then the essay body immediately follows.
 *   - Page numbers: "LastName #" in top-right header on every page.
 *   - Works Cited page: "Works Cited" centered (not bold), hanging indent, double-spaced.
 */
class Mla9
{
    public static function build(EssayJSON $essay, array $config): string
    {
        $phpWord = new PhpWord();
        Base::applyDocumentDefaults($phpWord, $config);

        $section = $phpWord->addSection();
        Base::setupSection($section, $config);

        $lastName = Base::extractLastName($essay->authorNamePlaceholder);
        Base::addPageNumbers($section, $config, $lastName);
        self::firstPageBlock($section, $essay, $config);
        Base::addBody($section, $essay->bodyMarkdown, $config, [self::class, 'heading']);
        $section->addPageBreak();
        Base::addReferencesPage($section, $essay->references, $config);

        return Base::docToBytes($phpWord);
    }

    // ── First-page header block ───────────────────────────────────────────────

    private static function firstPageBlock(Section $section, EssayJSON $essay, array $config): void
    {
        // Top-left four-line block: name, instructor, course, date
        foreach ([
            $essay->authorNamePlaceholder,
            $essay->instructorPlaceholder,
            $essay->coursePlaceholder,
            $essay->date,
        ] as $text) {
            $section->addText($text, Base::fontStyle($config), [
                'alignment'   => 'left',
                'lineHeight'  => 2.0,
                'spaceBefore' => 0,
                'spaceAfter'  => 0,
                'indentation' => ['firstLine' => 0],
            ]);
        }

        // Title: centered, plain text — no bold, no italic
        $section->addText($essay->title, Base::fontStyle($config), [
            'alignment'   => 'center',
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ]);
    }

    // ── Headings ──────────────────────────────────────────────────────────────

    public static function heading(Section $section, string $text, int $level, array $config): void
    {
        // MLA does not mandate a specific heading style — left-aligned, plain text
        $section->addText($text, Base::fontStyle($config), [
            'alignment'   => 'left',
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ]);
    }
}
