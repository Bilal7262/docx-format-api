<?php

namespace App\Formatters;

use Dompdf\Dompdf;
use Dompdf\Options;

class AiDetectorReportBuilder
{
    private const BG_PAGE     = '#0A0A0F';
    private const BG_CARD     = '#13131A';
    private const BG_CARD2    = '#1A1A26';
    private const PURPLE_MID  = '#4F2DB8';

    public static function build(array $data): string
    {
        $overallScore = (int)    ($data['overall_score']     ?? 0);
        $riskLevel    = strtolower($data['risk_level']       ?? 'low');
        $verdict      = (string) ($data['verdict']           ?? '');
        $modelAttrib  = (array)  ($data['model_attribution'] ?? []);
        $paragraphs   = (array)  ($data['paragraphs']        ?? []);
        $sentences    = (array)  ($data['sentences']         ?? []);
        $findings     = (array)  ($data['findings']          ?? []);

        $totalSentences = count($sentences);
        $aiSentences    = count(array_filter($sentences, fn($s) => ($s['type'] ?? '') === 'ai'));
        $humanSentences = count(array_filter($sentences, fn($s) => ($s['type'] ?? '') === 'hum'));
        $totalWords     = 0;
        foreach ($sentences as $s) {
            $totalWords += count(explode(' ', trim($s['text'] ?? '')));
        }

        return self::toPdf(self::buildHtml(
            $overallScore, $riskLevel, $verdict,
            $totalWords, $totalSentences, $aiSentences, $humanSentences,
            $modelAttrib, $findings, $sentences, $paragraphs
        ));
    }

    private static function buildHtml(
        int    $score,
        string $riskLevel,
        string $verdict,
        int    $totalWords,
        int    $totalSentences,
        int    $aiSentences,
        int    $humanSentences,
        array  $modelAttrib,
        array  $findings,
        array  $sentences,
        array  $paragraphs
    ): string {
        $riskColor = match ($riskLevel) {
            'high'   => '#e74c3c',
            'medium' => '#f39c12',
            default  => '#27ae60',
        };
        $riskLabel  = ucfirst($riskLevel);
        $bgPage     = self::BG_PAGE;
        $bgCard     = self::BG_CARD;
        $bgCard2    = self::BG_CARD2;
        $purpleMid  = self::PURPLE_MID;

        // ── Score ring: HTML-only (dompdf has poor SVG support) ────────────────
        // Outer ring = colored border on a round div, inner = dark bg with text.
        // We fake a "progress" look by splitting the border into colored vs dark
        // using a rotated half-mask — but dompdf doesn't support transforms either.
        // Best reliable approach: solid colored outer circle + dark inner circle.
        $scoreRing = <<<HTML
<div style="width:200px;height:200px;border-radius:50%;background:{$riskColor};text-align:center;padding:9px 6px 6px">
  <div style="border-radius:50%;background:#0A0A0F;margin:0 auto;padding: 59px 11px;">
    <div style="color:{$riskColor};font-size:25pt;font-weight:bold;line-height:1">{$score}%</div>
    <div style="color:#888;font-size:8pt;margin-top:6px;letter-spacing:1px">AI DETECTED</div>
  </div>
</div>
HTML;

        // ── Stat boxes (table row) ────────────────────────────────────────────
        $statBoxes = self::statBox($totalWords, 'WORDS', '#ffffff')
            . self::statBox($totalSentences, 'SENTENCES', '#ffffff')
            . self::statBox($aiSentences, 'AI FLAGGED', '#e74c3c')
            . self::statBox($humanSentences, 'CLEAN', '#27ae60');

        // ── Model attribution bars ────────────────────────────────────────────
        $modelRows = '';
        $modelMap  = [
            'gpt4'   => 'GPT-4 / ChatGPT',
            'gemini' => 'Google Gemini',
            'claude' => 'Claude',
        ];
        foreach ($modelMap as $key => $name) {
            $pct = (int) ($modelAttrib[$key] ?? 0);
            if ($pct <= 0) {
                continue;
            }
            $barW = $pct;
            $modelRows .= <<<HTML
<tr>
  <td style="padding:4px 0 2px;color:#ccc;font-size:10pt">{$name}</td>
  <td style="padding:4px 0 2px;text-align:right;color:#fff;font-size:10pt;font-weight:bold;width:36px">{$pct}%</td>
</tr>
<tr>
  <td colspan="2" style="padding:0 0 10px">
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
      <td style="background:{$purpleMid};height:7px;width:{$barW}%;border-radius:3px 0 0 3px"></td>
      <td style="background:#1E1E2E;height:7px;border-radius:0 3px 3px 0"></td>
    </tr></table>
  </td>
</tr>
HTML;
        }

        // ── Key findings ──────────────────────────────────────────────────────
        $findingRows = '';
        foreach ($findings as $f) {
            $type = $f['type'] ?? 'ok';
            $text = htmlspecialchars($f['text'] ?? '');
            [$dot, $color] = match ($type) {
                'bad'   => ['x', '#e74c3c'],
                'warn'  => ['!', '#f39c12'],
                default => ['+', '#27ae60'],
            };
            $findingRows .= <<<HTML
<tr>
  <td style="width:20px;padding:4px 7px 4px 0;vertical-align:middle">
    <span style="background:{$color};color:#fff;font-size:7.5pt;font-weight:bold;
                 padding:1px 5px;border-radius:8px">{$dot}</span>
  </td>
  <td style="color:#ccc;font-size:10pt;padding:4px 0">{$text}</td>
</tr>
HTML;
        }

        // ── Highlighted sentences ─────────────────────────────────────────────
        $sentenceBlocks = '';
        foreach ($sentences as $s) {
            $type       = $s['type']       ?? 'hum';
            $confidence = (int) ($s['confidence'] ?? 0);
            $text       = htmlspecialchars($s['text'] ?? '');
            [$bg, $border, $badge, $badgeColor] = match ($type) {
                'ai'        => ['#2A1010', '#e74c3c', $confidence . '% AI',       '#e74c3c'],
                'uncertain' => ['#2A2010', '#f39c12', $confidence . '% Uncertain', '#f39c12'],
                default     => ['#0A1F0A', '#27ae60', 'Human',                     '#27ae60'],
            };
            $sentenceBlocks .= <<<HTML
<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:6px">
<tr>
  <td width="4" style="background:{$border};border-radius:3px 0 0 3px">&nbsp;</td>
  <td style="background:{$bg};padding:8px 10px;border-radius:0 3px 3px 0">
    <span style="font-size:9.5pt;color:#e8e8e8">{$text}</span>
    &nbsp;<span style="background:{$badgeColor};color:#fff;font-size:7.5pt;
                padding:2px 7px;border-radius:8px">{$badge}</span>
  </td>
</tr>
</table>
HTML;
        }

        // ── Paragraph analysis rows ───────────────────────────────────────────
        $paraRows = '';
        foreach ($paragraphs as $p) {
            $idx     = (int)    ($p['index']       ?? 0) + 1;
            $aiScore = (int)    ($p['ai_score']    ?? 0);
            $risk    = strtolower($p['risk_level'] ?? 'low');
            $model   = htmlspecialchars(strtoupper($p['likely_model'] ?? ''));
            $excerpt = htmlspecialchars(mb_strimwidth($p['excerpt'] ?? '', 0, 55, '...'));
            $rColor  = match ($risk) {
                'high'   => '#e74c3c',
                'medium' => '#f39c12',
                default  => '#27ae60',
            };
            $rLabel2 = ucfirst($risk);
            $barFill = min(100, $aiScore);
            $barEmpty = 100 - $barFill;
            $paraRows .= <<<HTML
<tr>
  <td style="padding:9px 8px;color:#fff;font-weight:bold;text-align:center;
             border-bottom:1px solid #1E1E2E;width:30px">{$idx}</td>
  <td style="padding:9px 8px;color:#bbb;font-size:9pt;border-bottom:1px solid #1E1E2E">{$excerpt}</td>
  <td style="padding:9px 8px;border-bottom:1px solid #1E1E2E;width:130px">
    <table width="100%" cellspacing="0" cellpadding="0"><tr>
      <td style="width:60px;vertical-align:middle;padding-right:6px">
        <table width="100%" cellspacing="0" cellpadding="0"><tr>
          <td style="background:{$rColor};height:6px;width:{$barFill}%;border-radius:2px 0 0 2px"></td>
          <td style="background:#1E1E2E;height:6px;border-radius:0 2px 2px 0"></td>
        </tr></table>
      </td>
      <td style="color:{$rColor};font-weight:bold;font-size:9.5pt;white-space:nowrap">{$aiScore}%</td>
    </tr></table>
  </td>
  <td style="padding:9px 8px;text-align:center;border-bottom:1px solid #1E1E2E;width:70px">
    <span style="background:{$rColor};color:#fff;font-size:7.5pt;
                 padding:2px 8px;border-radius:8px">{$rLabel2}</span>
  </td>
  <td style="padding:9px 8px;text-align:center;color:{$purpleMid};font-size:9pt;
             font-weight:bold;border-bottom:1px solid #1E1E2E;width:60px">{$model}</td>
</tr>
HTML;
        }

        $verdictEsc = htmlspecialchars($verdict);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing:border-box; margin:0; padding:0 }
  body { font-family:Arial,Helvetica,sans-serif; background:{$bgPage}; color:#fff; padding:22px }
  .card { background:{$bgCard}; border:1px solid #1E1E2E; border-radius:10px;
          padding:16px; margin-bottom:14px }
  .sec { font-size:8.5pt; font-weight:bold; color:#555; text-transform:uppercase;
         letter-spacing:1.2px; margin-bottom:11px }
  table { border-collapse:collapse }
</style>
</head>
<body>

<!-- ① Header card -->
<div class="card">
  <table width="100%" cellspacing="0" cellpadding="0">
  <tr>
    <td width="215" valign="middle" style="padding-right:12px">{$scoreRing}</td>
    <td valign="middle" style="padding-left:18px">

      <!-- risk badge -->
      <table cellspacing="0" cellpadding="0" style="margin-bottom:9px"><tr>
        <td style="background:{$bgCard2};border:1px solid {$riskColor};color:{$riskColor};
                   font-size:8.5pt;padding:3px 12px;border-radius:11px;font-weight:bold">
          * Professor risk: {$riskLabel}
        </td>
      </tr></table>

      <!-- verdict -->
      <p style="font-size:9.5pt;color:#ccc;margin-bottom:13px;line-height:1.55">{$verdictEsc}</p>

      <!-- stat boxes -->
      <table cellspacing="0" cellpadding="0"><tr>
        {$statBoxes}
      </tr></table>

    </td>
  </tr>
  </table>
</div>

<!-- ② Model + Findings side by side -->
<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:14px">
<tr valign="top">

  <td width="49%" style="background:{$bgCard};border:1px solid #1E1E2E;
                          border-radius:10px;padding:16px">
    <div class="sec">Likely AI Model</div>
    <table width="100%" cellspacing="0" cellpadding="0">
      {$modelRows}
    </table>
  </td>

  <td width="2%"></td>

  <td width="49%" style="background:{$bgCard};border:1px solid #1E1E2E;
                          border-radius:10px;padding:16px">
    <div class="sec">Key Findings</div>
    <table cellspacing="0" cellpadding="0">
      {$findingRows}
    </table>
  </td>

</tr>
</table>

<!-- ③ Highlighted Essay -->
<div class="card">
  <div class="sec">Highlighted Essay</div>
  <table cellspacing="0" cellpadding="0" style="margin-bottom:10px"><tr>
    <td style="padding-right:14px">
      <span style="background:#e74c3c;color:#fff;font-size:7pt;padding:1px 6px;border-radius:4px">AI</span>
      <span style="color:#aaa;font-size:8.5pt"> AI-flagged</span>
    </td>
    <td style="padding-right:14px">
      <span style="background:#f39c12;color:#fff;font-size:7pt;padding:1px 6px;border-radius:4px">?</span>
      <span style="color:#aaa;font-size:8.5pt"> Uncertain</span>
    </td>
    <td>
      <span style="background:#27ae60;color:#fff;font-size:7pt;padding:1px 6px;border-radius:4px">H</span>
      <span style="color:#aaa;font-size:8.5pt"> Human</span>
    </td>
  </tr></table>
  {$sentenceBlocks}
</div>

<!-- ④ Paragraph Analysis -->
<div class="card">
  <div class="sec">Paragraph Analysis</div>
  <table width="100%" cellspacing="0" cellpadding="0">
    <tr style="background:{$bgCard2}">
      <th style="padding:8px;color:#555;font-size:8pt;text-align:center;
                 border-bottom:2px solid #1E1E2E;width:30px">#</th>
      <th style="padding:8px;color:#555;font-size:8pt;text-align:left;
                 border-bottom:2px solid #1E1E2E">EXCERPT</th>
      <th style="padding:8px;color:#555;font-size:8pt;text-align:left;
                 border-bottom:2px solid #1E1E2E;width:130px">AI SCORE</th>
      <th style="padding:8px;color:#555;font-size:8pt;text-align:center;
                 border-bottom:2px solid #1E1E2E;width:70px">RISK</th>
      <th style="padding:8px;color:#555;font-size:8pt;text-align:center;
                 border-bottom:2px solid #1E1E2E;width:60px">MODEL</th>
    </tr>
    {$paraRows}
  </table>
</div>

</body>
</html>
HTML;
    }

    private static function statBox(int $value, string $label, string $color): string
    {
        $bg2 = self::BG_CARD2;
        return <<<HTML
<td style="padding-right:8px">
  <table cellspacing="0" cellpadding="0">
  <tr><td style="background:{$bg2};border:1px solid #1E1E2E;border-radius:7px;
                 padding:7px 13px;text-align:center;min-width:62px">
    <div style="color:{$color};font-weight:bold;font-size:14pt">{$value}</div>
    <div style="color:#555;font-size:6.5pt;margin-top:2px;letter-spacing:.5px">{$label}</div>
  </td></tr>
  </table>
</td>
HTML;
    }

    private static function toPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('dpi', 150);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}