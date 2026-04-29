<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use App\Styles;

/**
 * Dispatcher — routes formatEssay() to the correct style class.
 * Adding a new style = create a new class + add one line to $builders.
 */
class FormatterDispatcher
{
    private static array $builders = [
        'APA 7'              => [Apa7::class,    'build'],
        'MLA 9'              => [Mla9::class,    'build'],
        'Chicago Author-Date'=> [Chicago::class, 'build'],
        'Harvard'            => [Harvard::class, 'build'],
    ];

    public static function formatEssay(EssayJSON $essay, string $styleKey): string
    {
        $config  = Styles::STYLES[$styleKey];
        $builder = self::$builders[$styleKey];
        return call_user_func($builder, $essay, $config);
    }
}
