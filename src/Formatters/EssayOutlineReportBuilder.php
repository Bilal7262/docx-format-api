<?php

namespace App\Formatters;

use Dompdf\Dompdf;
use Dompdf\Options;

class EssayOutlineReportBuilder
{
    private const BG_PAGE    = '#0D0D14';
    private const BG_CARD    = '#13131A';
    private const BG_CARD2   = '#1A1A26';
    private const PURPLE     = '#6B3FA0';
    private const PURPLE_DIM = '#2A1A45';
    private const PURPLE_BDR = '#4F2DB8';

    public static function build(string $essayHtml): string
    {
        // Convert <br/> / <br /> to newlines for easier parsing
        $text = preg_replace('/<br\s*\/?>/', "\n", $essayHtml);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = array_map('trim', explode("\n", $text));
        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));

        $thesis   = '';
        $sections = [];

        $currentSection = null;

        foreach ($lines as $line) {
            // Thesis Statement line
            if ($thesis === '' && preg_match('/^Thesis\s+Statement\s*[:\-]?\s*(.*)/i', $line, $m)) {
                $thesis = trim($m[1]);
                continue;
            }

            // Roman numeral heading: I. Title  or  I Introduction
            if (preg_match('/^(M{0,4}(?:CM|CD|D?C{0,3})(?:XC|XL|L?X{0,3})(?:IX|IV|V?I{0,3}))\.\s+(.+)$/i', $line, $m)) {
                if ($currentSection !== null) {
                    $sections[] = $currentSection;
                }
                $currentSection = ['title' => trim($m[2]), 'bullets' => []];
                continue;
            }

            // Bullet point
            if (preg_match('/^[-–•*]\s+(.+)$/', $line, $m)) {
                if ($currentSection !== null) {
                    $currentSection['bullets'][] = trim($m[1]);
                }
                continue;
            }

            // Continuation line with no section yet — could be thesis continuation
            if ($currentSection === null && $thesis !== '') {
                $thesis .= ' ' . $line;
            }
        }

        if ($currentSection !== null) {
            $sections[] = $currentSection;
        }

        return self::toPdf(self::buildHtml($thesis, $sections));
    }

    private static function buildHtml(string $thesis, array $sections): string
    {
        $bgPage   = self::BG_PAGE;
        $bgCard   = self::BG_CARD;
        $bgCard2  = self::BG_CARD2;
        $purple   = self::PURPLE;
        $purpleDim = self::PURPLE_DIM;
        $purpleBdr = self::PURPLE_BDR;

        // Thesis block
        $thesisHtml = '';
        if ($thesis !== '') {
            $t = htmlspecialchars($thesis);
            $thesisHtml = <<<HTML
<div style="background:{$purpleDim};border:1.5px solid {$purpleBdr};border-radius:10px;
            padding:16px 18px;margin-bottom:14px">
  <div style="color:{$purpleBdr};font-size:7.5pt;font-weight:bold;letter-spacing:1.2px;
              text-transform:uppercase;margin-bottom:8px">+ Thesis Statement</div>
  <p style="color:#ddd;font-size:10pt;font-style:italic;line-height:1.65;margin:0">{$t}</p>
</div>
HTML;
        }

        // Section cards
        $sectionCards = '';
        foreach ($sections as $i => $sec) {
            $num   = $i + 1;
            $title = htmlspecialchars($sec['title']);

            $bulletRows = '';
            foreach ($sec['bullets'] as $b) {
                $bt = htmlspecialchars($b);
                $bulletRows .= <<<HTML
<tr>
  <td style="width:10px;padding:3px 8px 3px 0;vertical-align:top;color:{$purpleBdr};font-size:9pt">|</td>
  <td style="padding:3px 0;color:#aaa;font-size:9.5pt;line-height:1.5">{$bt}</td>
</tr>
HTML;
            }

            $bulletsBlock = $bulletRows !== '' ? <<<HTML
<table width="100%" cellspacing="0" cellpadding="0" style="margin-top:10px;padding-left:4px">
  {$bulletRows}
</table>
HTML : '';

            $sectionCards .= <<<HTML
<div style="background:{$bgCard};border:1px solid #1E1E2E;border-radius:10px;
            padding:14px 16px;margin-bottom:10px">
  <table width="100%" cellspacing="0" cellpadding="0"><tr valign="middle">
    <td style="width:32px">
      <div style="background:{$purpleBdr};color:#fff;font-size:9.5pt;font-weight:bold;
                  width:26px;height:26px;border-radius:6px;text-align:center;
                  padding-top:5px">{$num}</div>
    </td>
    <td style="padding-left:10px">
      <span style="color:#fff;font-size:10.5pt;font-weight:bold">{$title}</span>
    </td>
  </tr></table>
  {$bulletsBlock}
</div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing:border-box; margin:0; padding:0 }
  body { font-family:Arial,Helvetica,sans-serif; background:{$bgPage}; color:#fff; padding:22px }
  table { border-collapse:collapse }
</style>
</head>
<body>
{$thesisHtml}
{$sectionCards}
</body>
</html>
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
