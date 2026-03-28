<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/PdfHelper.php';

ensureSecureSessionStarted();
requireAuthentication();

$invoiceId = (int) ($_GET['invoice_id'] ?? 0);
if ($invoiceId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid invoice_id.';
    exit;
}

try {
    $pdo = getDatabaseConnection();

    $invoiceStmt = $pdo->prepare(
        'SELECT i.id,
                i.invoice_no,
                i.customer_id,
                c.name AS customer_name,
                c.phone AS customer_phone,
                i.amount,
                i.status,
                i.created_at
         FROM invoices i
         LEFT JOIN customers c ON c.id = i.customer_id
         WHERE i.id = :id
         LIMIT 1'
    );
    $invoiceStmt->execute([':id' => $invoiceId]);
    $invoice = $invoiceStmt->fetch();

    if (!$invoice) {
        throw new RuntimeException('Invoice not found.');
    }

    $invoiceNo = (string) ($invoice['invoice_no'] ?? ('INV-' . $invoiceId));
    $customerLabel = trim((string) ($invoice['customer_name'] ?? ''));
    if ($customerLabel === '') {
        $customerLabel = 'Customer #' . (int) ($invoice['customer_id'] ?? 0);
    }

    $createdAtRaw = trim((string) ($invoice['created_at'] ?? ''));
    $createdAtLabel = $createdAtRaw;
    if ($createdAtRaw !== '') {
        $ts = strtotime($createdAtRaw);
        if ($ts !== false) {
            $createdAtLabel = date('Y-m-d H:i', $ts);
        }
    }

    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        str_repeat('-', 90),
        'Invoice No: ' . $invoiceNo,
        'Customer: ' . $customerLabel,
        'Customer Phone: ' . (string) ((trim((string) ($invoice['customer_phone'] ?? '')) !== '') ? $invoice['customer_phone'] : 'N/A'),
        'Status: ' . (string) ($invoice['status'] ?? 'Pending'),
        'Date: ' . $createdAtLabel,
        str_repeat('-', 90),
        'Amount Due: Tsh ' . number_format((float) ($invoice['amount'] ?? 0), 0, '.', ','),
    ];

    $pdf = buildSimplePdf('Invoice', $lines);

    $safeInvoiceNo = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoiceNo);
    if (!is_string($safeInvoiceNo) || $safeInvoiceNo === '') {
        $safeInvoiceNo = 'invoice_' . $invoiceId;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="invoice_' . $safeInvoiceNo . '_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo $pdf;
    exit;
} catch (Throwable $exception) {
    error_log('[POS Invoice Export PDF] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate invoice PDF at this time.';
    exit;
}
