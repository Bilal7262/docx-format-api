<?php
/**
 * CLI script — generates a sample .docx for every citation style.
 *
 * Usage:
 *   php scripts/generate.php               → generates all 4 styles
 *   php scripts/generate.php APA7          → generates one style
 *   php scripts/generate.php APA7 out.docx → custom output path
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Formatters\FormatterDispatcher;
use App\Models\FormatRequest;

$allStyles = ['APA7', 'MLA9', 'CHICAGO', 'HARVARD'];

$sampleData = [
    'title'         => 'The Effects of Social Media on Mental Health',
    'body_markdown' => implode("\n\n", [
        '## Introduction',
        'Social media has become an integral part of modern life (Smith, 2023). '
        . 'Platforms such as Instagram and Twitter are used by **billions** of people daily.',
        '## Literature Review',
        'Research suggests that *excessive* use of social media is linked to anxiety and depression.',
        '### Key Studies',
        'Jones (2022) found a ***strong correlation*** between screen time and poor sleep quality.',
        '## Conclusion',
        'Further longitudinal studies are needed to establish causation.',
    ]),
    'references' => [
        [
            'id'        => 'smith2023',
            'formatted' => 'Smith, J. A. (2023). The digital generation. Journal of Youth Studies, 45(2), 112–130.',
        ],
        [
            'id'        => 'jones2022',
            'formatted' => 'Jones, M. (2022). Screen time and sleep. Health Psychology Review, 16(1), 45–60.',
        ],
    ],
    'author_name' => 'Jane Smith',
    'institution' => 'University of London',
    'course'      => 'PSY 301',
    'instructor'  => 'Dr. Ahmed',
    'date'        => date('F j, Y'),
];

// Resolve which styles to generate
$requestedStyle = $argv[1] ?? null;
$outputPath     = $argv[2] ?? null;

if ($requestedStyle !== null && !in_array(strtoupper($requestedStyle), $allStyles, true)) {
    echo "Error: unknown style '{$requestedStyle}'. Valid options: " . implode(', ', $allStyles) . "\n";
    exit(1);
}

$stylesToRun = $requestedStyle ? [strtoupper($requestedStyle)] : $allStyles;

foreach ($stylesToRun as $style) {
    $data            = array_merge($sampleData, ['citation_style' => $style]);
    $request         = new FormatRequest($data);
    $essay           = $request->toEssayJson();
    $docxBytes       = FormatterDispatcher::formatEssay($essay, $style);

    $file = $outputPath ?? "output_{$style}.docx";
    file_put_contents($file, $docxBytes);
    echo "Generated: {$file}\n";
}
