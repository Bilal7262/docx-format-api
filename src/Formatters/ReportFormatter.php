<?php

namespace App\Formatters;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

class ReportFormatter
{
    private const CONFIG = [
        'font_family'                   => 'Times New Roman',
        'font_size'                     => 12,
        'margin_inches'                 => 1.0,
        'body_first_line_indent_inches' => 0.5,
    ];

    public static function build(string $title, string $bodyMarkdown, ?string $authorName = null, ?string $date = null): string
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        Base::applyDocumentDefaults($phpWord, self::CONFIG);

        $section = $phpWord->addSection();
        Base::setupSection($section, self::CONFIG);

        self::addPageNumbers($section);
        self::addTitle($section, $title, $authorName, $date);
        Base::addBody($section, $bodyMarkdown, self::CONFIG, [self::class, 'heading']);

        return Base::docToBytes($phpWord);
    }

    private static function addPageNumbers(Section $section): void
    {
        $header = $section->addHeader();
        $header->addPreserveText('{PAGE}', Base::fontStyle(self::CONFIG), ['alignment' => 'right']);
    }

    private static function addTitle(Section $section, string $title, ?string $authorName, ?string $date): void
    {
        $centeredPara = [
            'alignment'   => 'center',
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ];

        $section->addText($title, [
            'name' => self::CONFIG['font_family'],
            'size' => self::CONFIG['font_size'],
            'bold' => true,
        ], $centeredPara);

        if ($authorName !== null && $authorName !== '') {
            $section->addText($authorName, Base::fontStyle(self::CONFIG), $centeredPara);
        }

        if ($date !== null && $date !== '') {
            $section->addText($date, Base::fontStyle(self::CONFIG), $centeredPara);
        }
    }

    public static function heading(Section $section, string $content, int $level, array $config): void
    {
        $styles = [
            0 => ['bold' => true,  'italic' => false, 'alignment' => 'center'],
            1 => ['bold' => true,  'italic' => false, 'alignment' => 'center'],
            2 => ['bold' => true,  'italic' => false, 'alignment' => 'left'],
            3 => ['bold' => false, 'italic' => true,  'alignment' => 'left'],
            4 => ['bold' => true,  'italic' => true,  'alignment' => 'left'],
            5 => ['bold' => false, 'italic' => false, 'alignment' => 'left'],
        ];

        $s = $styles[$level] ?? $styles[5];

        $section->addText($content, [
            'name'   => $config['font_family'],
            'size'   => $config['font_size'],
            'bold'   => $s['bold'],
            'italic' => $s['italic'],
        ], [
            'alignment'   => $s['alignment'],
            'lineHeight'  => 2.0,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
            'indentation' => ['firstLine' => 0],
        ]);
    }
}
