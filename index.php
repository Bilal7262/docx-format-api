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
</style>
</head>
<body>

<div class="topbar">docx-formatter <span>PHP API — Interactive Docs</span></div>

<div class="container">

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
      <option value="APA7">APA7 — APA 7th Edition</option>
      <option value="MLA9">MLA9 — MLA 9th Edition</option>
      <option value="CHICAGO">CHICAGO — Chicago Author-Date</option>
      <option value="HARVARD">HARVARD — Harvard</option>
    </select>

    <label>title</label>
    <input id="f-title" type="text" value="The Effects of Social Media on Mental Health">

    <label>body_markdown <span class="opt">— ## heading, ### subheading, **bold**, *italic*</span></label>
    <textarea id="f-body">## Introduction

Social media has become an integral part of modern life (Smith, 2023). Platforms such as Instagram and Twitter are used by **billions** of people daily.

## Literature Review

Research suggests that *excessive* use of social media is linked to anxiety and depression.

### Key Studies

Jones (2022) found a ***strong correlation*** between screen time and poor sleep quality.

## Conclusion

Further longitudinal studies are needed to establish causation.</textarea>

    <label>references</label>
    <div id="refs-container">
      <div class="ref-row">
        <input type="text" placeholder="id" value="smith2023">
        <input type="text" placeholder="formatted citation" value="Smith, J. A. (2023). The digital generation. Journal of Youth Studies, 45(2), 112–130.">
        <button onclick="removeRef(this)">✕</button>
      </div>
      <div class="ref-row">
        <input type="text" placeholder="id" value="jones2022">
        <input type="text" placeholder="formatted citation" value="Jones, M. (2022). Screen time and sleep. Health Psychology Review, 16(1), 45–60.">
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

    <div class="execute-row">
      <button class="btn-exec" id="exec-btn" onclick="executeFormat()">▶  Execute</button>
    </div>

    <div id="format-response" class="response-box"></div>

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

  const payload = {
    citation_style: document.getElementById('f-style').value,
    title:          document.getElementById('f-title').value,
    body_markdown:  document.getElementById('f-body').value,
    references:     getReferences(),
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
    const res = await fetch('/format', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (res.ok) {
      const blob = await res.blob();
      const style = payload.citation_style.toLowerCase();
      const filename = style + '_essay.docx';

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

async function executeGet(path, boxId) {
  const box = document.getElementById(boxId);
  box.className = 'response-box show';
  box.textContent = 'Sending request...';
  try {
    const res = await fetch(path);
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
