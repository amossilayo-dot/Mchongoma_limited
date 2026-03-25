<?php

declare(strict_types=1);

/**
 * Create a simple, dependency-free PDF from text lines.
 */
function buildSimplePdf(string $title, array $lines): string
{
    $lines = array_values(array_filter(array_map(static fn($line) => trim((string) $line), $lines), static fn($line) => $line !== ''));

    $streamLines = [];
    $streamLines[] = 'BT';
    $streamLines[] = '/F1 16 Tf';
    $streamLines[] = '50 780 Td';
    $streamLines[] = '(' . pdfEscape($title) . ') Tj';
    $streamLines[] = '/F1 10 Tf';
    $streamLines[] = '0 -24 Td';

    $lineCount = 0;
    foreach ($lines as $line) {
        if ($lineCount > 65) {
            break;
        }

        foreach (wrapPdfLine($line, 100) as $wrapped) {
            $streamLines[] = '(' . pdfEscape($wrapped) . ') Tj';
            $streamLines[] = 'T*';
            $lineCount++;
            if ($lineCount > 65) {
                break 2;
            }
        }
    }

    $streamLines[] = 'ET';
    $contentStream = implode("\n", $streamLines) . "\n";

    $objects = [];
    $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
    $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
    $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
    $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
    $objects[] = '5 0 obj << /Length ' . strlen($contentStream) . ' >> stream' . "\n" . $contentStream . 'endstream endobj';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= 'xref' . "\n";
    $pdf .= '0 ' . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
    $pdf .= 'startxref' . "\n";
    $pdf .= $xrefOffset . "\n";
    $pdf .= '%%EOF';

    return $pdf;
}

function pdfEscape(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    return str_replace(')', '\\)', $text);
}

function wrapPdfLine(string $line, int $maxChars): array
{
    if (strlen($line) <= $maxChars) {
        return [$line];
    }

    $wrapped = [];
    $words = preg_split('/\s+/', $line) ?: [];
    $buffer = '';

    foreach ($words as $word) {
        $candidate = $buffer === '' ? $word : $buffer . ' ' . $word;
        if (strlen($candidate) <= $maxChars) {
            $buffer = $candidate;
            continue;
        }

        if ($buffer !== '') {
            $wrapped[] = $buffer;
            $buffer = $word;
        } else {
            $wrapped[] = substr($word, 0, $maxChars);
            $buffer = substr($word, $maxChars);
        }
    }

    if ($buffer !== '') {
        $wrapped[] = $buffer;
    }

    return $wrapped;
}
