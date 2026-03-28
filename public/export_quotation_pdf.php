<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/PdfHelper.php';

ensureSecureSessionStarted();
requireAuthentication();

$quotationId = (int) ($_GET['quotation_id'] ?? 0);
if ($quotationId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid quotation_id.';
    exit;
}

try {
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare(
        'SELECT q.id,
                q.quotation_no,
                q.customer_id,
                c.name AS customer_name,
                q.amount,
                q.status,
                q.created_at
         FROM quotations q
         LEFT JOIN customers c ON c.id = q.customer_id
         WHERE q.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();

    if (!$quotation) {
        throw new RuntimeException('Quotation not found.');
    }

    $customerName = trim((string) ($quotation['customer_name'] ?? ''));
    if ($customerName === '') {
        $customerName = 'Customer #' . (int) ($quotation['customer_id'] ?? 0);
    }

    $status = trim((string) ($quotation['status'] ?? ''));
    if ($status === '') {
        $status = 'Pending';
    }

    $createdAtRaw = trim((string) ($quotation['created_at'] ?? ''));
    $createdAtLabel = $createdAtRaw;
    if ($createdAtRaw !== '') {
        $timestamp = strtotime($createdAtRaw);
        if ($timestamp !== false) {
            $createdAtLabel = date('Y-m-d H:i', $timestamp);
        }
    }

    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        'Quotation No: ' . (string) ($quotation['quotation_no'] ?? '-'),
        'Customer: ' . $customerName,
        'Status: ' . $status,
        'Date: ' . $createdAtLabel,
        str_repeat('-', 90),
        'Amount: Tsh ' . number_format((float) ($quotation['amount'] ?? 0), 0, '.', ','),
    ];

    $pdf = buildSimplePdf('Quotation', $lines);

    $safeQuotationNo = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) ($quotation['quotation_no'] ?? ('QT_' . $quotationId)));
    if (!is_string($safeQuotationNo) || $safeQuotationNo === '') {
        $safeQuotationNo = 'QT_' . $quotationId;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="quotation_' . $safeQuotationNo . '_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo $pdf;
    exit;
} catch (Throwable $exception) {
    error_log('[POS Quotation Export PDF] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate quotation PDF at this time.';
    exit;
}
