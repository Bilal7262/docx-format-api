<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Formatters\FormatterDispatcher;
use App\Models\FormatRequest;
use App\Styles;

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = rtrim($path, '/') ?: '/';

header('Content-Type: application/json');

// ── GET /health ───────────────────────────────────────────────────────────────

if ($method === 'GET' && $path === '/health') {
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── GET /options ──────────────────────────────────────────────────────────────

if ($method === 'GET' && $path === '/options') {
    $styles = array_keys(Styles::STYLES);
    sort($styles);
    echo json_encode(['citation_styles' => $styles]);
    exit;
}

// ── POST /format ──────────────────────────────────────────────────────────────

if ($method === 'POST' && $path === '/format') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    try {
        $request   = new FormatRequest($data);
        $essay     = $request->toEssayJson();
        $docxBytes = FormatterDispatcher::formatEssay($essay, $request->citationStyle);
    } catch (\InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }

    $filename = strtolower($request->citationStyle) . '_essay.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($docxBytes));
    echo $docxBytes;
    exit;
}

// ── GET /docs ─────────────────────────────────────────────────────────────────

if ($method === 'GET' && $path === '/docs') {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>docx-formatter API</title>
<style>
  body { font-family: sans-serif; max-width: 860px; margin: 40px auto; padding: 0 20px; color: #222; }
  h1 { color: #1a1a2e; }
  h2 { margin-top: 2rem; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; }
  .method { display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; color: #fff; margin-right: 8px; }
  .post { background: #49cc90; }
  .get  { background: #61affe; }
  .route { font-size: 1.1rem; font-weight: bold; font-family: monospace; }
  pre { background: #f4f4f4; padding: 16px; border-radius: 6px; overflow-x: auto; font-size: 0.88rem; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
  th { background: #f0f0f0; }
  code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold; }
  .req  { background: #ffeeba; color: #856404; }
  .opt  { background: #d4edda; color: #155724; }
</style>
</head>
<body>

<h1>docx-formatter API</h1>
<p>Converts essay content into properly formatted <code>.docx</code> files.</p>

<h2><span class="method post">POST</span> <span class="route">/format</span></h2>
<p>Send essay content as JSON, receive a formatted <code>.docx</code> file download.</p>

<h3>Request Body</h3>
<pre>{
    "citation_style": "APA7",
    "title":          "The Effects of Social Media on Mental Health",
    "body_markdown":  "## Introduction\n\nIn recent years (Smith, 2023)...",
    "references": [
        {
            "id":        "ref1",
            "formatted": "Smith, J. A. (2023). The digital generation. Journal of Youth Studies, 45(2), 112-130."
        }
    ],
    "author_name": "Jane Smith",
    "institution": "University of London",
    "course":      "PSY 301",
    "instructor":  "Dr. Ahmed",
    "date":        "April 27, 2026"
}</pre>

<h3>Fields</h3>
<table>
<tr><th>Field</th><th>Required</th><th>Description</th></tr>
<tr><td><code>citation_style</code></td><td><span class="badge req">Required</span></td><td><code>APA7</code> / <code>MLA9</code> / <code>CHICAGO</code> / <code>HARVARD</code></td></tr>
<tr><td><code>title</code></td><td><span class="badge req">Required</span></td><td>Essay title</td></tr>
<tr><td><code>body_markdown</code></td><td><span class="badge req">Required</span></td><td>Essay body. Use <code>##</code> headings, <code>###</code> subheadings, <code>**bold**</code>, <code>*italic*</code></td></tr>
<tr><td><code>references</code></td><td><span class="badge req">Required</span></td><td>Array of <code>{ id, formatted }</code> objects</td></tr>
<tr><td><code>author_name</code></td><td><span class="badge opt">Optional</span></td><td>Defaults to <code>[Student Name]</code></td></tr>
<tr><td><code>institution</code></td><td><span class="badge opt">Optional</span></td><td>Defaults to <code>[University]</code></td></tr>
<tr><td><code>course</code></td><td><span class="badge opt">Optional</span></td><td>Defaults to <code>[Course]</code></td></tr>
<tr><td><code>instructor</code></td><td><span class="badge opt">Optional</span></td><td>Defaults to <code>[Instructor]</code></td></tr>
<tr><td><code>date</code></td><td><span class="badge opt">Optional</span></td><td>Defaults to empty</td></tr>
</table>

<h3>curl Example</h3>
<pre>curl -X POST http://localhost:8080/format \\
  -H "Content-Type: application/json" \\
  -d '{"citation_style":"APA7","title":"My Essay","body_markdown":"## Intro\\n\\nHello world.","references":[{"id":"r1","formatted":"Smith, J. (2023). Example. Journal, 1(1), 1-10."}]}' \\
  -o essay.docx</pre>

<h2><span class="method get">GET</span> <span class="route">/options</span></h2>
<p>Returns all valid <code>citation_style</code> values.</p>
<pre>{ "citation_styles": ["APA7", "CHICAGO", "HARVARD", "MLA9"] }</pre>

<h2><span class="method get">GET</span> <span class="route">/health</span></h2>
<pre>{ "status": "ok" }</pre>

<h2>Citation Style Reference</h2>
<table>
<tr><th>Style</th><th>Title Page</th><th>Page Numbers</th><th>References Heading</th></tr>
<tr><td>APA7</td><td>Yes (student format)</td><td>Top-right, all pages</td><td>"References" (bold, centered)</td></tr>
<tr><td>MLA9</td><td>No (first-page block)</td><td>"LastName #" top-right</td><td>"Works Cited" (centered)</td></tr>
<tr><td>Chicago</td><td>Yes, no number on it</td><td>Top-right, content pages</td><td>"References" (centered)</td></tr>
<tr><td>Harvard</td><td>Yes</td><td>Top-right, all pages</td><td>"Reference List" (centered)</td></tr>
</table>

</body>
</html>
HTML;
    exit;
}

// ── 404 ───────────────────────────────────────────────────────────────────────

http_response_code(404);
echo json_encode(['error' => 'Not found']);
