<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/CustomerCreditRepository.php';
require_once __DIR__ . '/../app/XlsxHelper.php';

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
    $credits = $creditRepo->getCredits(10000, false);

    $rows = [
        ['customer_name', 'sale_ref', 'total_amount', 'paid_amount', 'outstanding_amount', 'due_date', 'status', 'created_at'],
    ];

    foreach ($credits as $credit) {
        $rows[] = [
            (string) ($credit['customer_name'] ?? ''),
            (string) ($credit['transaction_no'] ?? ('SALE-' . (string) ($credit['sale_id'] ?? ''))),
            (string) ((float) ($credit['total_amount'] ?? 0)),
            (string) ((float) ($credit['paid_amount'] ?? 0)),
            (string) ((float) ($credit['outstanding_amount'] ?? 0)),
            (string) ($credit['due_date'] ?? ''),
            (string) ($credit['status'] ?? ''),
            (string) ($credit['created_at'] ?? ''),
        ];
    }

    $tempFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'customer_debts_' . date('Ymd_His') . '.xlsx';
    writeXlsxFile($tempFile, 'Customer Debts', $rows);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="customer_debts_' . date('Ymd_His') . '.xlsx"');
    header('Content-Length: ' . (string) filesize($tempFile));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    readfile($tempFile);
    @unlink($tempFile);
    exit;
} catch (Throwable $exception) {
    error_log('[POS Export Customer Debts XLSX] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate customer debt XLSX at this time.';
    exit;
}
