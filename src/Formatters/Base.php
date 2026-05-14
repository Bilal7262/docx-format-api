<?php

namespace App\Formatters;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

class Base
{
    // ── Document setup ────────────────────────────────────────────────────────

    public static function applyDocumentDefaults(PhpWord $phpWord, array $config): void
    {
        $phpWord->setDefaultFontName($config['font_family']);
        $phpWord->setDefaultFontSize($config['font_size']);
    }

    public static function setupSection(Section $section, array $config): void
    {
        $margin = Converter::inchToTwip($config['margin_inches']);
        $style  = $section->getStyle();
        $style->setMarginTop($margin);
        $style->setMarginBottom($margin);
        $style->setMarginLeft($margin);
        $style->setMarginRight($margin);
    }

    // ── Page numbers ──────────────────────────────────────────────────────────

    public static function addPageNumbers(Section $section, array $config, string $lastName = ''): void
    {
        $fmt       = $config['page_numbers']['format'] ?? 'number_only';
        $alignment = str_contains($config['page_numbers']['position'], 'right') ? 'right' : 'center';

        $fontStyle      = self::fontStyle($config);
        $paragraphStyle = ['alignment' => $alignment];

        $header = $section->addHeader();

        if ($fmt === 'lastname_page' && $lastName !== '') {
            $header->addPreserveText($lastName . ' {PAGE}', $fontStyle, $paragraphStyle);
        } else {
            $header->addPreserveText('{PAGE}', $fontStyle, $paragraphStyle);
        }
    }

    // ── Body ──────────────────────────────────────────────────────────────────

    public static function addBody(Section $section, string $bodyMarkdown, array $config, callable $headingFn): void
    {
        $firstLineIndent = Converter::inchToTwip($config['body_first_line_indent_inches'] ?? 0.5);

        foreach (self::parseBlocks($bodyMarkdown) as [$blockType, $content]) {
            if (in_array($blockType, ['h0','h1','h2','h3','h4','h5'], true)) {
                $headingFn($section, $content, (int) substr($blockType, 1), $config);
            } else {
                $paragraphStyle = [
                    'lineHeight'  => 2.0,
                    'spaceBefore' => 0,
                    'spaceAfter'  => 0,
                    'indentation' => ['firstLine' => $firstLineIndent],
                ];
                $textRun = $section->addTextRun($paragraphStyle);
                self::addInlineRuns($textRun, $content, $config);
            }
        }
    }

    // ── References page ───────────────────────────────────────────────────────

    public static function addReferencesPage(Section $section, array $references, array $config): void
    {
        $hanging      = Converter::inchToTwip($config['hanging_indent_inches']);
        $doubleSpaced = $config['references_double_spaced'];

        $headingStyle = [
            'alignment'   => 'center',
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ];
        $headingRun = $section->addTextRun($headingStyle);
        $headingRun->addText($config['references_label'], [
            'name'  => $config['font_family'],
            'size'  => $config['font_size'],
            'bold'  => $config['references_bold'],
        ]);

        foreach ($references as $ref) {
            if ($doubleSpaced) {
                $paragraphStyle = [
                    'lineHeight'  => 2.0,
                    'spaceBefore' => 0,
                    'spaceAfter'  => 0,
                    'indentation' => ['left' => $hanging, 'hanging' => $hanging],
                ];
            } else {
                // Chicago: single-spaced entries, blank line between
                $paragraphStyle = [
                    'lineHeight'  => 1.0,
                    'spaceBefore' => 0,
                    'spaceAfter'  => Converter::pointToTwip(12),
                    'indentation' => ['left' => $hanging, 'hanging' => $hanging],
                ];
            }
            $textRun = $section->addTextRun($paragraphStyle);
            self::addInlineRuns($textRun, $ref->formatted, $config);
        }
    }

    // ── Markdown parsing ──────────────────────────────────────────────────────

    public static function parseBlocks(string $markdown): array
    {
        $markdown = str_replace('\n', "\n", $markdown);
        $markdown = str_replace('\/', '/', $markdown);
        $markdown = preg_replace('/<br\s*\/?>/i', "\n\n", $markdown);

        $blocks       = [];
        $currentLines = [];

        foreach (explode("\n", $markdown) as $line) {
            $stripped = trim($line);
            if (str_starts_with($stripped, '###### ')) {
                if ($currentLines) { $blocks[] = ['paragraph', implode(' ', $currentLines)]; $currentLines = []; }
                $blocks[] = ['h5', trim(substr($stripped, 7))];
            } elseif (str_starts_with($stripped, '##### ')) {
                if ($currentLines) { $blocks[] = ['paragraph', implode(' ', $currentLines)]; $currentLines = []; }
                $blocks[] = ['h4', trim(substr($stripped, 6))];
            } elseif (str_starts_with($stripped, '#### ')) {
                if ($currentLines) { $blocks[] = ['paragraph', implode(' ', $currentLines)]; $currentLines = []; }
                $blocks[] = ['h3', trim(substr($stripped, 5))];
            } elseif (str_starts_with($stripped, '### ')) {
                if ($currentLines) { $blocks[] = ['paragraph', implode(' ', $currentLines)]; $currentLines = []; }
                $blocks[] = ['h2', trim(substr($stripped, 4))];
            } elseif (str_starts_with($stripped, '## ')) {
                if ($currentLines) { $blocks[] = ['paragraph', implode(' ', $currentLines)]; $currentLines = []; }
                $blocks[] = ['h1', trim(substr($stripped, 3))];
            } elseif (str_starts_with($stripped, '# ')) {
                if ($currentLines) { $blocks[] = ['paragraph', implode(' ', $currentLines)]; $currentLines = []; }
                $blocks[] = ['h0', trim(substr($stripped, 2))];
            } elseif ($stripped === '') {
                if ($currentLines) {
                    $blocks[] = ['paragraph', implode(' ', $currentLines)];
                    $currentLines = [];
                }
            } else {
                $currentLines[] = $stripped;
            }
        }

        if ($currentLines) {
            $blocks[] = ['paragraph', implode(' ', $currentLines)];
        }

        return $blocks;
    }

    public static function parseInline(string $text): array
    {
        $result  = [];
        $pattern = '/\*\*\*(.+?)\*\*\*|\*\*(.+?)\*\*|\*(.+?)\*/s';
        $lastEnd = 0;

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $matchStart = $match[0][1];
            $matchEnd   = $matchStart + strlen($match[0][0]);

            if ($matchStart > $lastEnd) {
                $result[] = [substr($text, $lastEnd, $matchStart - $lastEnd), false, false];
            }

            if ($match[1][0] !== '') {
                $result[] = [$match[1][0], true, true];   // bold + italic
            } elseif ($match[2][0] !== '') {
                $result[] = [$match[2][0], true, false];  // bold only
            } elseif ($match[3][0] !== '') {
                $result[] = [$match[3][0], false, true];  // italic only
            }

            $lastEnd = $matchEnd;
        }

        if ($lastEnd < strlen($text)) {
            $result[] = [substr($text, $lastEnd), false, false];
        }

        return $result ?: [[$text, false, false]];
    }

    public static function addInlineRuns($textRun, string $text, array $config): void
    {
        foreach (self::parseInline($text) as [$segment, $bold, $italic]) {
            $textRun->addText($segment, [
                'name'   => $config['font_family'],
                'size'   => $config['font_size'],
                'bold'   => $bold,
                'italic' => $italic,
            ]);
        }
    }

    // ── Shared paragraph helpers ──────────────────────────────────────────────

    // Render a # title (and optional subtitle after ':') centered in the document body
    public static function addDocumentTitle(Section $section, string $content, array $config, bool $bold): void
    {
        $base = [
            'alignment'   => 'center',
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ];

        $parts    = explode(': ', $content, 2);
        $title    = $parts[0];
        $subtitle = $parts[1] ?? null;

        $titleRun = $section->addTextRun($base);
        $titleRun->addText($title, [
            'name' => $config['font_family'],
            'size' => $config['font_size'],
            'bold' => $bold,
        ]);

        if ($subtitle !== null) {
            $subRun = $section->addTextRun($base);
            $subRun->addText($subtitle, [
                'name' => $config['font_family'],
                'size' => $config['font_size'],
            ]);
        }
    }

    public static function centeredLine(Section $section, string $text, array $config, bool $bold = false): void
    {
        $section->addText($text, [
            'name' => $config['font_family'],
            'size' => $config['font_size'],
            'bold' => $bold,
        ], [
            'alignment'   => 'center',
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ]);
    }

    public static function fontStyle(array $config): array
    {
        return [
            'name' => $config['font_family'],
            'size' => $config['font_size'],
        ];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    public static function extractLastName(string $fullName): string
    {
        if (str_starts_with($fullName, '[')) {
            return '[LastName]';
        }
        $parts = explode(' ', trim($fullName));
        return end($parts) ?: '[LastName]';
    }

    public static function docToBytes(PhpWord $phpWord): string
    {
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $tempFile  = tempnam(sys_get_temp_dir(), 'docx');
        $objWriter->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        return $content;
    }
}
