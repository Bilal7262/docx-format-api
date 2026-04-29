<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Formatters\FormatterDispatcher;
use App\Models\FormatRequest;

$data = [
    'citation_style' => 'APA7',
    'title'          => 'The Impact of Climate Change',
    'body_markdown'  => implode("\n\n", [
        '## Introduction',
        'Climate change is one of the **most pressing** issues of our time.',
        '## Methods',
        'We analyzed *temperature data* from 1950 to 2023.',
    ]),
    'references' => [
        [
            'id'        => 'ref1',
            'formatted' => 'Smith, J. (2023). Climate patterns. Journal of Environmental Science, 45(2), 123–145.',
        ],
    ],
    'author_name' => 'John Doe',
    'institution' => 'University of Example',
    'course'      => 'ENV 101',
    'instructor'  => 'Dr. Jane Smith',
    'date'        => 'April 29, 2026',
];

$request    = new FormatRequest($data);
$essay      = $request->toEssayJson();
$docxBytes  = FormatterDispatcher::formatEssay($essay, $request->citationStyle);

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="essay.docx"');
header('Content-Length: ' . strlen($docxBytes));
echo $docxBytes;
