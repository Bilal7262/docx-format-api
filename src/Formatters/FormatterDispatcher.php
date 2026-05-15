<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use App\Styles;
use PhpOffice\PhpWord\Settings;

/**
 * Dispatcher — routes formatEssay() to the correct style class.
 * Adding a new style = create a new class + add one line to $builders.
 */
class FormatterDispatcher
{
    private static array $builders = [
        'APA 7'      => [Apa7::class,    'build'],
        'MLA 9'      => [Mla9::class,    'build'],
        'Chicago 17' => [Chicago::class, 'build'],
        'Harvard'    => [Harvard::class, 'build'],
    ];

    private static array $pdfBuilders = [
        'APA 7'      => [PdfBuilder::class, 'buildApa7'],
        'MLA 9'      => [PdfBuilder::class, 'buildMla9'],
        'Chicago 17' => [PdfBuilder::class, 'buildChicago'],
        'Harvard'    => [PdfBuilder::class, 'buildHarvard'],
    ];

    public static function formatEssay(EssayJSON $essay, string $styleKey, string $format = 'docx'): string
    {
        Settings::setOutputEscapingEnabled(true);

        $config   = Styles::STYLES[$styleKey];
        $builders = $format === 'pdf' ? self::$pdfBuilders : self::$builders;
        return call_user_func($builders[$styleKey], $essay, $config);
    }
}
