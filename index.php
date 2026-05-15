<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Formatters\FormatterDispatcher;
use App\Formatters\PdfBuilder;
use App\Formatters\ReportFormatter;
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

    $outputFormat = strtolower($data['output_format'] ?? 'docx');
    if (!in_array($outputFormat, ['docx', 'pdf'], true)) {
        $outputFormat = 'docx';
    }

    try {
        $request = new FormatRequest($data);
        $essay   = $request->toEssayJson();
        $bytes   = FormatterDispatcher::formatEssay($essay, $request->citationStyle, $outputFormat);
    } catch (\InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Generation failed: ' . $e->getMessage()]);
        exit;
    }

    $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower($request->citationStyle));
    $filename = $slug . '_essay.' . $outputFormat;

    while (ob_get_level()) {
        ob_end_clean();
    }

    $contentType = $outputFormat === 'pdf'
        ? 'application/pdf'
        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

// ── POST /report ─────────────────────────────────────────────────────────────

if ($method === 'POST' && $path === '/report') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    $title        = trim($data['title'] ?? '');
    $bodyMarkdown = trim($data['body_markdown'] ?? '');
    $authorName   = isset($data['author_name']) ? trim($data['author_name']) : null;
    $date         = isset($data['date'])        ? trim($data['date'])        : null;

    if ($title === '') {
        http_response_code(422);
        echo json_encode(['error' => 'title is required']);
        exit;
    }

    if ($bodyMarkdown === '') {
        http_response_code(422);
        echo json_encode(['error' => 'body_markdown is required']);
        exit;
    }

    $outputFormat = strtolower($data['output_format'] ?? 'docx');
    if (!in_array($outputFormat, ['docx', 'pdf'], true)) {
        $outputFormat = 'docx';
    }

    try {
        $bytes = $outputFormat === 'pdf'
            ? PdfBuilder::buildReport($title, $bodyMarkdown, $authorName, $date)
            : ReportFormatter::build($title, $bodyMarkdown, $authorName, $date);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Generation failed: ' . $e->getMessage()]);
        exit;
    }

    $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower($title));
    $filename = substr($slug, 0, 40) . '_report.' . $outputFormat;

    while (ob_get_level()) ob_end_clean();

    $contentType = $outputFormat === 'pdf'
        ? 'application/pdf'
        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

// ── GET /essay/{id} ──────────────────────────────────────────────────────────

if ($method === 'GET' && preg_match('#^/essay/(\d+)$#', $path, $m)) {
    $id    = $m[1];
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://backend.skyscrapersnow.com/aiEssayWriterApi/generate-essay-document/' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: ' . $token,
        ],
    ]);
    $body        = curl_exec($ch);
    $status      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err         = curl_error($ch);
    curl_close($ch);

    if ($err) {
        http_response_code(502);
        echo json_encode(['error' => 'cURL error: ' . $err]);
        exit;
    }

    while (ob_get_level()) ob_end_clean();

    http_response_code($status);
    if (str_starts_with($body, 'PK')) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="essay_' . $id . '.docx"');
        header('Content-Length: ' . strlen($body));
    } else {
        header('Content-Type: ' . ($contentType ?: 'application/json'));
    }
    echo $body;
    exit;
}

// ── GET /docs ─────────────────────────────────────────────────────────────────

if ($method === 'GET' && $path === '/docs') {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>docx-formatter API Docs</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#fafafa;color:#222}
.topbar{background:#1a1a2e;padding:14px 24px;color:#fff;font-size:1.2rem;font-weight:700;letter-spacing:.5px}
.topbar span{opacity:.6;font-weight:400;font-size:.95rem;margin-left:12px}
.container{max-width:900px;margin:32px auto;padding:0 20px}
.endpoint{background:#fff;border:1px solid #e0e0e0;border-radius:8px;margin-bottom:16px;overflow:hidden}
.ep-header{display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;user-select:none;transition:background .15s}
.ep-header:hover{background:#f5f5f5}
.badge{display:inline-block;padding:4px 12px;border-radius:4px;font-weight:700;font-size:.8rem;color:#fff;min-width:58px;text-align:center}
.post{background:#49cc90}.get{background:#61affe}
.ep-path{font-family:monospace;font-size:1rem;font-weight:600}
.ep-desc{color:#666;font-size:.9rem;margin-left:auto}
.ep-body{border-top:1px solid #e8e8e8;padding:20px 20px 24px;display:none}
.ep-body.open{display:block}
label{display:block;font-size:.85rem;font-weight:600;color:#444;margin-bottom:4px;margin-top:14px}
label .opt{font-weight:400;color:#888;font-size:.8rem;margin-left:4px}
input,select,textarea{width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:5px;font-size:.9rem;font-family:inherit;background:#fafafa}
input:focus,select:focus,textarea:focus{outline:none;border-color:#61affe;background:#fff}
textarea{resize:vertical;min-height:120px}
.ref-row{display:flex;gap:8px;margin-bottom:8px;align-items:flex-start}
.ref-row input:first-child{max-width:110px}
.ref-row button{background:#e74c3c;color:#fff;border:none;border-radius:4px;padding:8px 10px;cursor:pointer;font-size:.85rem;white-space:nowrap;flex-shrink:0}
.btn-add-ref{background:#fff;border:1px dashed #aaa;color:#555;border-radius:5px;padding:7px 14px;cursor:pointer;font-size:.85rem;margin-top:4px;width:100%}
.btn-add-ref:hover{border-color:#61affe;color:#61affe}
.execute-row{display:flex;gap:10px;margin-top:20px;align-items:center}
.btn-exec{padding:9px 24px;background:#49cc90;color:#fff;border:none;border-radius:5px;font-size:.95rem;font-weight:600;cursor:pointer;transition:background .15s}
.btn-exec:hover{background:#3aaf7e}
.btn-exec:disabled{background:#aaa;cursor:not-allowed}
.btn-get{padding:9px 24px;background:#61affe;color:#fff;border:none;border-radius:5px;font-size:.95rem;font-weight:600;cursor:pointer;transition:background .15s}
.btn-get:hover{background:#4a8fe8}
.response-box{margin-top:16px;background:#1e1e1e;color:#d4d4d4;border-radius:6px;padding:14px 16px;font-family:monospace;font-size:.85rem;white-space:pre-wrap;word-break:break-all;display:none}
.response-box.show{display:block}
.response-box.success{border-left:4px solid #49cc90}
.response-box.error{border-left:4px solid #e74c3c}
.response-box.downloading{border-left:4px solid #61affe}
.spinner{display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}
.section-label{font-size:.75rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;margin-top:24px}
.dl-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;background:#1a1a2e;color:#fff;border-radius:5px;font-size:.88rem;font-weight:600;cursor:pointer;border:none;margin-top:10px;text-decoration:none}
.dl-btn:hover{background:#2d2d4e}
.auth-bar{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px}
.auth-bar label{font-size:.85rem;font-weight:700;color:#444;white-space:nowrap;margin:0}
.auth-bar input{flex:1;padding:7px 10px;border:1px solid #ccc;border-radius:5px;font-size:.82rem;font-family:monospace;background:#fafafa}
.auth-bar input:focus{outline:none;border-color:#61affe;background:#fff}
</style>
</head>
<body>

<div class="topbar">docx-formatter <span>PHP API — Interactive Docs</span></div>

<div class="container">

<div class="auth-bar">
  <label>Authorization</label>
  <input id="auth-token" type="text" value="Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiZDNiNDY0MmI4Nzc1MTRjZWUxYjg4MWQ4M2I1ZDFjYTZjMTQxNDM5YmE2OGE2NDRiNTk2NDczMWU1Yjg3NDU3ZmZlYjA0ODk4MGFiNWNiYmYiLCJpYXQiOjE3Nzc1MzIyMTcuOTY0MzQwOTI1MjE2Njc0ODA0Njg3NSwibmJmIjoxNzc3NTMyMjE3Ljk2NDM0ODA3Nzc3NDA0Nzg1MTU2MjUsImV4cCI6MTc4MjcxNjIxNy45NTg1Mjg5OTU1MTM5MTYwMTU2MjUsInN1YiI6IjU0OTIzMCIsInNjb3BlcyI6W119.AyTAje8bv8QNEyaLUKsYF76hwe8TBg48lWbJU8KleoDSHjQeOW0148xH-O6FLM3PmumXjKgSpQnJdxz56OeSGxOd5qrHi1odNT2MeHDCx2KoktLemZUt2XWhI7Yfpc4_T4rClZ44qPonq5f7_AWGBVZ0M_WaUvSv8baN2ce5VbUrU078w5-2No1ZiqKseAcZdWifFO2Hu2SgMwq-aSwVBhCYwkM0VFy4oS2G_rFhkBxtg_aFazYbnqihnSALIV-yP-QYLEmNSSwwGtj6fQUfyQmU21n95JITcEMwgS4e_liuNBEoe1hn11Lo7PMdBJipBjWn32ySNSCEAW3m6SyCgzHM-BX-Cc_9Q1sBECyV4iUyrV2sYJ9elndzUmUR6GAnXTWrSvinPaMDO3NZGel0vgoz6SIBM_4D3_OXnJD8tyChjhWuuf5o5joT1nmfp8iEpI0QRYJh-dE8yb4g-rLo08FT8dyUgJ5v38SxcKiUemzqcnW0pSN8I3pIdw3TPiJKodCHG01ykrtoFqsCc3MqML5GWfsenlJvtutHq1J9z2vkb0p0AYobHXLWDVNkpUG4j5Dj5La5MHmZFbc641Vobkdqbg-5I-UpyWqnGuOJDYDnPX_8M9ao8q0vE05O--g28akWwBa2iIMpMifKvA_Y3b3icedESfLUGmhITB444hI">
</div>

<!-- POST /format -->
<div class="endpoint">
  <div class="ep-header" onclick="toggle(this)">
    <span class="badge post">POST</span>
    <span class="ep-path">/format</span>
    <span class="ep-desc">Generate a formatted .docx file</span>
  </div>
  <div class="ep-body">

    <div class="section-label">Parameters</div>

    <label>citation_style</label>
    <select id="f-style">
      <option value="APA 7">APA 7</option>
      <option value="MLA 9">MLA 9</option>
      <option value="Chicago 17">Chicago 17</option>
      <option value="Harvard">Harvard</option>
    </select>

    <label>title</label>
    <input id="f-title" type="text" value="The Effects of Social Media on Mental Health">

    <label>body_markdown</label>
    <div style="background:#f0f4ff;border:1px solid #d0d8f0;border-radius:5px;padding:10px 14px;margin-bottom:8px;font-size:.82rem;line-height:1.7">
      <strong style="display:block;margin-bottom:4px;color:#444">Markdown syntax:</strong>
      <table style="border-collapse:collapse;width:100%">
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e;white-space:nowrap">#&nbsp;Title:&nbsp;Subtitle</td><td style="color:#555">Document title (subtitle after <code>:</code> is optional)</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e;white-space:nowrap">##&nbsp;Heading</td><td style="color:#555">Level 1 heading</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e;white-space:nowrap">###&nbsp;Heading</td><td style="color:#555">Level 2 heading</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e;white-space:nowrap">####&nbsp;Heading</td><td style="color:#555">Level 3 heading</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e;white-space:nowrap">#####&nbsp;Heading</td><td style="color:#555">Level 4 heading</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e;white-space:nowrap">######&nbsp;Heading</td><td style="color:#555">Level 5 heading</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e">**bold**</td><td style="color:#555">Bold text</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e">*italic*</td><td style="color:#555">Italic text</td></tr>
        <tr><td style="padding:1px 10px 1px 0;font-family:monospace;color:#1a1a2e">***bold italic***</td><td style="color:#555">Bold + italic text</td></tr>
      </table>
      <div style="margin-top:6px;border-top:1px solid #d0d8f0;padding-top:6px;color:#666">
        <strong>Heading styles per citation:</strong><br>
        <span style="color:#555">
          <b>MLA 9</b> — L1: bold left &nbsp;·&nbsp; L2: italic left &nbsp;·&nbsp; L3: centered bold &nbsp;·&nbsp; L4: centered italic &nbsp;·&nbsp; L5: underlined left<br>
          <b>APA 7</b> — L1: centered bold &nbsp;·&nbsp; L2: left bold &nbsp;·&nbsp; L3: left bold+italic &nbsp;·&nbsp; L4: indented bold &nbsp;·&nbsp; L5: indented bold+italic<br>
          <b>Chicago / Harvard</b> — L1: centered bold &nbsp;·&nbsp; L2: left bold &nbsp;·&nbsp; L3: left italic &nbsp;·&nbsp; L4: centered italic &nbsp;·&nbsp; L5: underlined left
        </span>
      </div>
    </div>
    <textarea id="f-body"># The Effects of Social Media on Mental Health: A Review of Current Evidence

## Introduction

Social media has become an integral part of modern life (Smith, 2023). Platforms such as Instagram and Twitter are used by **billions** of people daily.

## Literature Review

Research suggests that *excessive* use of social media is linked to anxiety and depression.

### Key Studies

Jones (2022) found a ***strong correlation*** between screen time and poor sleep quality.

#### Methodology

Studies used both *quantitative* and **qualitative** approaches to measure social media exposure and mental health outcomes.

##### Sample Demographics

Participants ranged from ages 13 to 35, with data collected across **12 countries** over a three-year period.

###### Data Collection Tools

Researchers employed validated instruments such as the *PHQ-9* and the ***Social Media Use Integration Scale***.

## Conclusion

Further longitudinal studies are needed to establish causation.</textarea>

    <label>references</label>
    <div id="refs-container">
      <div class="ref-row">
        <input type="text" placeholder="id" value="smith2023">
        <input type="text" placeholder="formatted citation" value="Smith, J. A. (2023). The digital generation. *Journal of Youth Studies*, 45(2), 112–130.">
        <button onclick="removeRef(this)">✕</button>
      </div>
      <div class="ref-row">
        <input type="text" placeholder="id" value="jones2022">
        <input type="text" placeholder="formatted citation" value="Jones, M. (2022). Screen time and sleep. *Health Psychology Review*, 16(1), 45–60.">
        <button onclick="removeRef(this)">✕</button>
      </div>
    </div>
    <button class="btn-add-ref" onclick="addRef()">+ Add Reference</button>

    <label>author_name <span class="opt">optional</span></label>
    <input id="f-author" type="text" value="Jane Smith">

    <label>institution <span class="opt">optional</span></label>
    <input id="f-inst" type="text" value="University of London">

    <label>course <span class="opt">optional</span></label>
    <input id="f-course" type="text" value="PSY 301">

    <label>instructor <span class="opt">optional</span></label>
    <input id="f-instr" type="text" value="Dr. Ahmed">

    <label>date <span class="opt">optional</span></label>
    <input id="f-date" type="text" value="April 27, 2026">

    <label>output_format</label>
    <select id="f-output-format">
      <option value="docx">docx</option>
      <option value="pdf">pdf</option>
    </select>

    <div class="execute-row">
      <button class="btn-exec" id="exec-btn" onclick="executeFormat()">▶  Execute</button>
    </div>

    <div id="format-response" class="response-box"></div>

  </div>
</div>

<!-- POST /report -->
<div class="endpoint">
  <div class="ep-header" onclick="toggle(this)">
    <span class="badge post">POST</span>
    <span class="ep-path">/report</span>
    <span class="ep-desc">Generate a plain formatted .docx report</span>
  </div>
  <div class="ep-body">

    <div class="section-label">Parameters</div>

    <label>title</label>
    <input id="r-title" type="text" value="Quarterly Business Report">

    <label>body_markdown</label>
    <textarea id="r-body">## Executive Summary

This report provides an overview of **Q1 2026** performance across all departments.

## Findings

Revenue increased by *15%* compared to the previous quarter.

### Key Metrics

- Total revenue: ***$2.4M***
- New customers: **320**
- Churn rate: *4.2%*

## Recommendations

Further investment in **digital marketing** channels is advised.

## Conclusion

Overall performance was *strong* and targets were met across all divisions.</textarea>

    <label>output_format</label>
    <select id="r-output-format">
      <option value="docx">docx</option>
      <option value="pdf">pdf</option>
    </select>

    <div class="execute-row">
      <button class="btn-exec" id="report-btn" onclick="executeReport()">▶  Execute</button>
    </div>

    <div id="report-response" class="response-box"></div>

  </div>
</div>

<!-- GET /options -->
<div class="endpoint">
  <div class="ep-header" onclick="toggle(this)">
    <span class="badge get">GET</span>
    <span class="ep-path">/options</span>
    <span class="ep-desc">List available citation styles</span>
  </div>
  <div class="ep-body">
    <div class="execute-row">
      <button class="btn-get" onclick="executeGet('/options','options-response')">▶  Execute</button>
    </div>
    <div id="options-response" class="response-box"></div>
  </div>
</div>

<!-- GET /essay/{id} -->
<div class="endpoint">
  <div class="ep-header" onclick="toggle(this)">
    <span class="badge get">GET</span>
    <span class="ep-path">/essay/{id}</span>
    <span class="ep-desc">Fetch essay data from backend</span>
  </div>
  <div class="ep-body">
    <div class="section-label">Parameters</div>
    <label>Document ID</label>
    <input id="essay-id" type="text" value="1626632" style="max-width:220px">
    <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
      <span style="font-size:.78rem;color:#888;align-self:center">Quick fill:</span>
      <button onclick="document.getElementById('essay-id').value='1626632'" style="background:#f0f4ff;border:1px solid #c8d4f0;color:#1a1a2e;border-radius:4px;padding:4px 10px;font-size:.78rem;cursor:pointer">1626632 — MLA</button>
      <button onclick="document.getElementById('essay-id').value='1626652'" style="background:#f0f4ff;border:1px solid #c8d4f0;color:#1a1a2e;border-radius:4px;padding:4px 10px;font-size:.78rem;cursor:pointer">1626652 — APA</button>
      <button onclick="document.getElementById('essay-id').value='1626654'" style="background:#f0f4ff;border:1px solid #c8d4f0;color:#1a1a2e;border-radius:4px;padding:4px 10px;font-size:.78rem;cursor:pointer">1626654 — Chicago</button>
      <button onclick="document.getElementById('essay-id').value='1626662'" style="background:#f0f4ff;border:1px solid #c8d4f0;color:#1a1a2e;border-radius:4px;padding:4px 10px;font-size:.78rem;cursor:pointer">1626662 — Harvard</button>
    </div>
    <div class="execute-row">
      <button class="btn-get" onclick="executeEssayFetch()">▶  Execute</button>
    </div>
    <div id="essay-response" class="response-box"></div>
  </div>
</div>

<!-- GET /health -->
<div class="endpoint">
  <div class="ep-header" onclick="toggle(this)">
    <span class="badge get">GET</span>
    <span class="ep-path">/health</span>
    <span class="ep-desc">Check server status</span>
  </div>
  <div class="ep-body">
    <div class="execute-row">
      <button class="btn-get" onclick="executeGet('/health','health-response')">▶  Execute</button>
    </div>
    <div id="health-response" class="response-box"></div>
  </div>
</div>

</div><!-- /container -->

<script>
function toggle(header) {
  const body = header.nextElementSibling;
  body.classList.toggle('open');
}

// Open POST /format by default
document.querySelector('.ep-body').classList.add('open');

function addRef() {
  const container = document.getElementById('refs-container');
  const row = document.createElement('div');
  row.className = 'ref-row';
  row.innerHTML = '<input type="text" placeholder="id"><input type="text" placeholder="formatted citation"><button onclick="removeRef(this)">✕</button>';
  container.appendChild(row);
}

function removeRef(btn) {
  const rows = document.querySelectorAll('.ref-row');
  if (rows.length > 1) btn.closest('.ref-row').remove();
}

function getReferences() {
  return Array.from(document.querySelectorAll('.ref-row')).map((row, i) => {
    const inputs = row.querySelectorAll('input');
    return { id: inputs[0].value || ('ref' + (i+1)), formatted: inputs[1].value };
  }).filter(r => r.formatted.trim() !== '');
}

async function executeFormat() {
  const btn = document.getElementById('exec-btn');
  const box = document.getElementById('format-response');

  const outputFormat = document.getElementById('f-output-format').value;
  const payload = {
    citation_style: document.getElementById('f-style').value,
    title:          document.getElementById('f-title').value,
    body_markdown:  document.getElementById('f-body').value,
    references:     getReferences(),
    output_format:  outputFormat,
  };
  const optional = {
    author_name: document.getElementById('f-author').value,
    institution: document.getElementById('f-inst').value,
    course:      document.getElementById('f-course').value,
    instructor:  document.getElementById('f-instr').value,
    date:        document.getElementById('f-date').value,
  };
  for (const [k, v] of Object.entries(optional)) {
    if (v.trim()) payload[k] = v;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Generating...';
  box.className = 'response-box show';
  box.textContent = 'Sending request...';

  try {
    const token = document.getElementById('auth-token').value.trim();
    const res = await fetch('/format', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(token && { 'Authorization': token }) },
      body: JSON.stringify(payload),
    });

    if (res.ok) {
      const blob = await res.blob();
      const style = payload.citation_style.toLowerCase();
      const filename = style.replace(/[^a-z0-9]+/g,'_') + '_essay.' + outputFormat;

      // Auto-download
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      a.click();
      URL.revokeObjectURL(url);

      box.className = 'response-box show downloading';
      box.innerHTML = '✅  Success — file downloaded: ' + filename + '\n\nSize: ' + (blob.size / 1024).toFixed(1) + ' KB\nContent-Type: ' + res.headers.get('Content-Type');
    } else {
      const data = await res.json();
      box.className = 'response-box show error';
      box.textContent = '❌  Error ' + res.status + '\n\n' + JSON.stringify(data, null, 2);
    }
  } catch (e) {
    box.className = 'response-box show error';
    box.textContent = '❌  Network error: ' + e.message;
  }

  btn.disabled = false;
  btn.innerHTML = '▶  Execute';
}

async function executeReport() {
  const btn = document.getElementById('report-btn');
  const box = document.getElementById('report-response');

  const rOutputFormat = document.getElementById('r-output-format').value;
  const payload = {
    title:         document.getElementById('r-title').value,
    body_markdown: document.getElementById('r-body').value,
    output_format: rOutputFormat,
  };

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Generating...';
  box.className = 'response-box show';
  box.textContent = 'Sending request...';

  try {
    const token = document.getElementById('auth-token').value.trim();
    const res = await fetch('/report', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(token && { 'Authorization': token }) },
      body: JSON.stringify(payload),
    });

    if (res.ok) {
      const blob = await res.blob();
      const slug = payload.title.toLowerCase().replace(/[^a-z0-9]+/g, '_').substring(0, 40);
      const filename = slug + '_report.' + rOutputFormat;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = filename; a.click();
      URL.revokeObjectURL(url);
      box.className = 'response-box show downloading';
      box.innerHTML = '✅  Success — file downloaded: ' + filename + '\n\nSize: ' + (blob.size / 1024).toFixed(1) + ' KB\nContent-Type: ' + res.headers.get('Content-Type');
    } else {
      const data = await res.json();
      box.className = 'response-box show error';
      box.textContent = '❌  Error ' + res.status + '\n\n' + JSON.stringify(data, null, 2);
    }
  } catch (e) {
    box.className = 'response-box show error';
    box.textContent = '❌  Network error: ' + e.message;
  }

  btn.disabled = false;
  btn.innerHTML = '▶  Execute';
}

async function executeEssayFetch() {
  const id  = document.getElementById('essay-id').value.trim();
  const box = document.getElementById('essay-response');
  if (!id) { box.className = 'response-box show error'; box.textContent = '❌  Document ID required'; return; }

  box.className = 'response-box show';
  box.textContent = 'Fetching...';
  const token = document.getElementById('auth-token').value.trim();
  try {
    const res = await fetch('/essay/' + id, token ? { headers: { 'Authorization': token } } : {});
    const ct  = res.headers.get('Content-Type') || '';

    if (ct.includes('wordprocessingml') || ct.includes('octet-stream')) {
      const blob = await res.blob();
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href = url; a.download = 'essay_' + id + '.docx'; a.click();
      URL.revokeObjectURL(url);
      box.className = 'response-box show downloading';
      box.textContent = '✅  Downloaded: essay_' + id + '.docx\n\nSize: ' + (blob.size / 1024).toFixed(1) + ' KB';
    } else {
      const data = await res.json();
      box.className = 'response-box show ' + (res.ok ? 'success' : 'error');
      box.textContent = 'HTTP ' + res.status + '\n\n' + JSON.stringify(data, null, 2);
    }
  } catch (e) {
    box.className = 'response-box show error';
    box.textContent = '❌  ' + e.message;
  }
}

async function executeGet(path, boxId) {
  const box = document.getElementById(boxId);
  box.className = 'response-box show';
  box.textContent = 'Sending request...';
  try {
    const token = document.getElementById('auth-token').value.trim();
    const res = await fetch(path, token ? { headers: { 'Authorization': token } } : {});
    const data = await res.json();
    box.className = 'response-box show success';
    box.textContent = 'HTTP ' + res.status + '\n\n' + JSON.stringify(data, null, 2);
  } catch (e) {
    box.className = 'response-box show error';
    box.textContent = '❌  ' + e.message;
  }
}
</script>
</body>
</html>
HTML;
    exit;
}

// ── 404 ───────────────────────────────────────────────────────────────────────

http_response_code(404);
echo json_encode(['error' => 'Not found']);
