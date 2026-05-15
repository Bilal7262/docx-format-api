<?php

namespace App\Formatters;

use App\Models\EssayJSON;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfBuilder
{
    // ── Public builders ───────────────────────────────────────────────────────

    public static function buildApa7(EssayJSON $essay, array $config): string
    {
        $bodyHtml = self::bodyHtml($essay->bodyMarkdown, $config, 'apa');
        $refsHtml = self::refsHtml($essay->references, $config, true);

        $html = self::page(
            self::apa7TitlePage($essay) .
            '<div style="page-break-after:always"></div>' .
            $bodyHtml .
            '<div style="page-break-after:always"></div>' .
            $refsHtml
        );

        // Title page = physical page 1 (hidden), body starts at 1 (offset -1)
        return self::toPdf($html, ['skipFirst' => true, 'offset' => -1]);
    }

    public static function buildMla9(EssayJSON $essay, array $config): string
    {
        $lastName = Base::extractLastName($essay->authorNamePlaceholder);
        $bodyHtml = self::bodyHtml($essay->bodyMarkdown, $config, 'mla');
        $refsHtml = self::refsHtml($essay->references, $config, true);

        $html = self::page(
            self::mla9FirstPage($essay) .
            $bodyHtml .
            '<div style="page-break-after:always"></div>' .
            $refsHtml
        );

        return self::toPdf($html, ['lastName' => $lastName]);
    }

    public static function buildChicago(EssayJSON $essay, array $config): string
    {
        $bodyHtml = self::bodyHtml($essay->bodyMarkdown, $config, 'chicago');
        $refsHtml = self::refsHtml($essay->references, $config, false);

        $html = self::page(
            self::chicagoTitlePage($essay) .
            '<div style="page-break-after:always"></div>' .
            $bodyHtml .
            '<div style="page-break-after:always"></div>' .
            $refsHtml
        );

        return self::toPdf($html, ['skipFirst' => true, 'offset' => -1]);
    }

    public static function buildHarvard(EssayJSON $essay, array $config): string
    {
        $bodyHtml = self::bodyHtml($essay->bodyMarkdown, $config, 'harvard');
        $refsHtml = self::refsHtml($essay->references, $config, true);

        $html = self::page(
            self::harvardTitlePage($essay) .
            '<div style="page-break-after:always"></div>' .
            $bodyHtml .
            '<div style="page-break-after:always"></div>' .
            $refsHtml
        );

        return self::toPdf($html);
    }

    public static function buildReport(string $title, string $bodyMarkdown, ?string $authorName, ?string $date): string
    {
        $config   = ['font_family' => 'Times New Roman', 'font_size' => 12];
        $bodyHtml = self::bodyHtml($bodyMarkdown, $config, 'report');

        $header  = '<p class="centered bold">' . htmlspecialchars($title) . '</p>';
        if ($authorName !== null && $authorName !== '') {
            $header .= '<p class="centered">' . htmlspecialchars($authorName) . '</p>';
        }
        if ($date !== null && $date !== '') {
            $header .= '<p class="centered">' . htmlspecialchars($date) . '</p>';
        }

        $html = self::page($header . $bodyHtml);

        return self::toPdf($html);
    }

    // ── Title pages ───────────────────────────────────────────────────────────

    private static function apa7TitlePage(EssayJSON $essay): string
    {
        $h  = '<div style="margin-top:2in;text-align:center">';
        $h .= '<p class="centered bold">'  . htmlspecialchars($essay->title)                  . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->authorNamePlaceholder)  . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->institutionPlaceholder) . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->coursePlaceholder)      . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->instructorPlaceholder)  . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->date)                   . '</p>';
        $h .= '</div>';
        return $h;
    }

    private static function mla9FirstPage(EssayJSON $essay): string
    {
        $h  = '<p class="left">' . htmlspecialchars($essay->authorNamePlaceholder)  . '</p>';
        $h .= '<p class="left">' . htmlspecialchars($essay->instructorPlaceholder)  . '</p>';
        $h .= '<p class="left">' . htmlspecialchars($essay->coursePlaceholder)      . '</p>';
        $h .= '<p class="left">' . htmlspecialchars($essay->date)                   . '</p>';
        return $h;
    }

    private static function chicagoTitlePage(EssayJSON $essay): string
    {
        $h  = '<div style="margin-top:2in;text-align:center">';
        $h .= '<p class="centered">' . htmlspecialchars($essay->title)                  . '</p>';
        $h .= '<p class="centered">' . htmlspecialchars($essay->authorNamePlaceholder)  . '</p>';
        $h .= '<p class="centered">' . htmlspecialchars($essay->coursePlaceholder)      . '</p>';
        $h .= '<p class="centered">' . htmlspecialchars($essay->date)                   . '</p>';
        $h .= '</div>';
        return $h;
    }

    private static function harvardTitlePage(EssayJSON $essay): string
    {
        $h  = '<div style="margin-top:2in;text-align:center">';
        $h .= '<p class="centered bold">'  . htmlspecialchars($essay->title)                  . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->authorNamePlaceholder)  . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->institutionPlaceholder) . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->coursePlaceholder)      . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->instructorPlaceholder)  . '</p>';
        $h .= '<p class="centered">'       . htmlspecialchars($essay->date)                   . '</p>';
        $h .= '</div>';
        return $h;
    }

    // ── Body HTML ─────────────────────────────────────────────────────────────

    private static function bodyHtml(string $markdown, array $config, string $style): string
    {
        $html = '';
        foreach (Base::parseBlocks($markdown) as [$type, $content]) {
            if ($type === 'paragraph') {
                $html .= '<p class="body-para">' . self::inlineHtml($content) . '</p>';
            } else {
                $level = (int) substr($type, 1);
                $html .= self::headingHtml($content, $level, $style);
            }
        }
        return $html;
    }

    private static function headingHtml(string $text, int $level, string $style): string
    {
        if ($level === 0) {
            $boldClass = in_array($style, ['apa', 'harvard'], true) ? ' bold' : '';
            $parts     = explode(': ', $text, 2);
            $h         = '<p class="centered' . $boldClass . '">' . htmlspecialchars($parts[0]) . '</p>';
            if (isset($parts[1])) {
                $h .= '<p class="centered">' . htmlspecialchars($parts[1]) . '</p>';
            }
            return $h;
        }

        $classes = self::headingClasses($level, $style);
        return '<p class="' . $classes . '">' . self::inlineHtml($text) . '</p>';
    }

    private static function headingClasses(int $level, string $style): string
    {
        if ($style === 'apa') {
            return match ($level) {
                1       => 'centered bold',
                2       => 'left bold',
                3       => 'left bold italic',
                4       => 'indented bold',
                5       => 'indented bold italic',
                default => 'indented bold italic',
            };
        }

        if ($style === 'mla') {
            return match ($level) {
                1       => 'left bold',
                2       => 'left italic',
                3       => 'centered bold',
                4       => 'centered italic',
                5       => 'left underline',
                default => 'left underline',
            };
        }

        // Chicago & Harvard (identical heading specs)
        return match ($level) {
            1       => 'centered bold',
            2       => 'left bold',
            3       => 'left italic',
            4       => 'centered italic',
            5       => 'left underline',
            default => 'left underline',
        };
    }

    // ── References ────────────────────────────────────────────────────────────

    private static function refsHtml(array $references, array $config, bool $doubleSpaced): string
    {
        $label      = $config['references_label'];
        $bold       = $config['references_bold'] ?? false;
        $labelClass = 'centered' . ($bold ? ' bold' : '');

        $h        = '<p class="' . $labelClass . '">' . htmlspecialchars($label) . '</p>';
        $refClass = $doubleSpaced ? 'hanging' : 'hanging-single';

        foreach ($references as $ref) {
            $h .= '<p class="' . $refClass . '">' . self::inlineHtml($ref->formatted) . '</p>';
        }
        return $h;
    }

    // ── Inline HTML ───────────────────────────────────────────────────────────

    private static function inlineHtml(string $text): string
    {
        $html = '';
        foreach (Base::parseInline($text) as [$segment, $bold, $italic]) {
            $escaped = htmlspecialchars($segment);
            if ($bold && $italic) {
                $html .= '<strong><em>' . $escaped . '</em></strong>';
            } elseif ($bold) {
                $html .= '<strong>' . $escaped . '</strong>';
            } elseif ($italic) {
                $html .= '<em>' . $escaped . '</em>';
            } else {
                $html .= $escaped;
            }
        }
        return $html;
    }

    // ── HTML wrapper ──────────────────────────────────────────────────────────

    private static function page(string $body): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' .
            'body{font-family:"Times New Roman",Times,serif;font-size:12pt;' .
            'margin:1in;padding:0;color:#000}' .
            'p{margin:0;padding:0;line-height:2}' .
            '.body-para{text-indent:0.5in}' .
            '.centered{text-align:center;text-indent:0}' .
            '.left{text-align:left;text-indent:0}' .
            '.bold{font-weight:bold}' .
            '.italic{font-style:italic}' .
            '.underline{text-decoration:underline}' .
            '.indented{text-align:left;padding-left:0.5in;text-indent:0}' .
            '.hanging{padding-left:0.5in;text-indent:-0.5in;line-height:2}' .
            '.hanging-single{padding-left:0.5in;text-indent:-0.5in;line-height:1;' .
            'margin-bottom:12pt}' .
            '</style></head><body>' . $body . '</body></html>';
    }

    // ── PDF conversion ────────────────────────────────────────────────────────

    private static function toPdf(string $html, array $pageNum = []): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Times New Roman');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('dpi', 150);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $skipFirst = $pageNum['skipFirst'] ?? false;
        $offset    = $pageNum['offset']    ?? 0;
        $lastName  = $pageNum['lastName']  ?? '';

        $canvas = $dompdf->getCanvas();
        $canvas->page_script(
            function (int $pageNumber, int $pageCount, $canvas, $fontMetrics)
            use ($skipFirst, $offset, $lastName): void {
                if ($skipFirst && $pageNumber === 1) {
                    return;
                }

                $displayNum = $pageNumber + $offset;
                if ($displayNum <= 0) {
                    return;
                }

                $text = $lastName !== '' ? ($lastName . ' ' . $displayNum) : (string) $displayNum;
                $font = $fontMetrics->getFont('Times New Roman', 'normal');
                $size = 12;

                $textWidth = $fontMetrics->getTextWidth($text, $font, $size);
                $x         = $canvas->get_width() - 72 - $textWidth;  // 1in from right
                $y         = 28;                                        // ~0.4in from top

                $canvas->text($x, $y, $text, $font, $size);
            }
        );

        return $dompdf->output();
    }
}
