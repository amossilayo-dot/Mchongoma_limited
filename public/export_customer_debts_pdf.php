<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/CustomerCreditRepository.php';
require_once __DIR__ . '/../app/PdfHelper.php';

ensureSecureSessionStarted();
requireAuthentication();

function ensureCreditExportTables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS customer_credits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            customer_id INT NOT NULL,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            outstanding_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM("Open", "Partial", "Paid") NOT NULL DEFAULT "Open",
            due_date DATE NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $check = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "customer_credits"
           AND column_name = "due_date"'
    );
    $check->execute();
    if ((int) ($check->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE customer_credits ADD COLUMN due_date DATE NULL AFTER status');
    }
}

try {
    $pdo = getDatabaseConnection();
    ensureCreditExportTables($pdo);

    $creditRepo = new CustomerCreditRepository($pdo);
    $credits = $creditRepo->getCredits(800, false);

    $totalAmount = 0.0;
    $totalPaid = 0.0;
    $totalOutstanding = 0.0;
    foreach ($credits as $credit) {
        $totalAmount += (float) ($credit['total_amount'] ?? 0);
        $totalPaid += (float) ($credit['paid_amount'] ?? 0);
        $totalOutstanding += (float) ($credit['outstanding_amount'] ?? 0);
    }

    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        'Total Credit Amount: Tsh ' . number_format($totalAmount, 0, '.', ','),
        'Total Paid Amount: Tsh ' . number_format($totalPaid, 0, '.', ','),
        'Total Outstanding: Tsh ' . number_format($totalOutstanding, 0, '.', ','),
        'Open Credits: ' . count(array_filter($credits, static fn(array $c): bool => (float) ($c['outstanding_amount'] ?? 0) > 0)),
        str_repeat('-', 90),
    ];

    foreach ($credits as $credit) {
        $dueDate = (string) ($credit['due_date'] ?? 'N/A');
        $saleRef = (string) ($credit['transaction_no'] ?? ('SALE-' . (string) ($credit['sale_id'] ?? '')));
        $status = (string) ($credit['status'] ?? 'Open');
        $lines[] = sprintf(
            '%s | %s | Total: Tsh %s | Paid: Tsh %s | Outstanding: Tsh %s | Due: %s | %s',
            (string) ($credit['customer_name'] ?? '-'),
            $saleRef,
            number_format((float) ($credit['total_amount'] ?? 0), 0, '.', ','),
            number_format((float) ($credit['paid_amount'] ?? 0), 0, '.', ','),
            number_format((float) ($credit['outstanding_amount'] ?? 0), 0, '.', ','),
            $dueDate,
            $status
        );
    }

    $pdf = buildSimplePdf('Customer Debt Report', $lines);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="customer_debts_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo $pdf;
    exit;
} catch (Throwable $exception) {
    error_log('[POS Export Customer Debts PDF] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate customer debt PDF at this time.';
    exit;
}
