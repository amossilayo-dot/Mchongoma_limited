<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/CustomerCreditRepository.php';
require_once __DIR__ . '/../app/CustomerRepository.php';
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

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS customer_credit_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credit_id INT NOT NULL,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_method ENUM("Cash", "Mobile Money", "Card", "Bank Transfer") NOT NULL DEFAULT "Cash",
            reference VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

$customerId = (int) ($_GET['customer_id'] ?? 0);
if ($customerId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid customer_id.';
    exit;
}

try {
    $pdo = getDatabaseConnection();
    ensureCreditExportTables($pdo);

    $customerRepo = new CustomerRepository($pdo);
    $creditRepo = new CustomerCreditRepository($pdo);

    $customer = $customerRepo->getCustomer($customerId);
    if (!$customer) {
        throw new RuntimeException('Customer not found.');
    }

    $credits = $creditRepo->getCustomerCredits($customerId, 500, false);
    $payments = $creditRepo->getCustomerPayments($customerId, 500);

    $totalCredit = 0.0;
    $totalPaid = 0.0;
    $totalOutstanding = 0.0;
    foreach ($credits as $credit) {
        $totalCredit += (float) ($credit['total_amount'] ?? 0);
        $totalPaid += (float) ($credit['paid_amount'] ?? 0);
        $totalOutstanding += (float) ($credit['outstanding_amount'] ?? 0);
    }

    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        'Customer: ' . (string) ($customer['name'] ?? 'Unknown'),
        'Phone: ' . (string) (($customer['phone'] ?? '') !== '' ? $customer['phone'] : 'N/A'),
        str_repeat('-', 90),
        'Credit Summary',
        'Total Credit: Tsh ' . number_format($totalCredit, 0, '.', ','),
        'Total Paid: Tsh ' . number_format($totalPaid, 0, '.', ','),
        'Outstanding: Tsh ' . number_format($totalOutstanding, 0, '.', ','),
        str_repeat('-', 90),
        'Credit Sales',
    ];

    if (count($credits) === 0) {
        $lines[] = 'No credit sales found for this customer.';
    } else {
        foreach ($credits as $credit) {
            $saleRef = (string) ($credit['transaction_no'] ?? ('SALE-' . (string) ($credit['sale_id'] ?? '')));
            $lines[] = sprintf(
                '%s | Total: Tsh %s | Paid: Tsh %s | Outstanding: Tsh %s | Due: %s | %s',
                $saleRef,
                number_format((float) ($credit['total_amount'] ?? 0), 0, '.', ','),
                number_format((float) ($credit['paid_amount'] ?? 0), 0, '.', ','),
                number_format((float) ($credit['outstanding_amount'] ?? 0), 0, '.', ','),
                (string) ($credit['due_date'] ?? 'N/A'),
                (string) ($credit['status'] ?? 'Open')
            );
        }
    }

    $lines[] = str_repeat('-', 90);
    $lines[] = 'Payment History';

    if (count($payments) === 0) {
        $lines[] = 'No payments recorded yet.';
    } else {
        foreach ($payments as $payment) {
            $saleRef = (string) ($payment['transaction_no'] ?? ('SALE-' . (string) ($payment['sale_id'] ?? '')));
            $lines[] = sprintf(
                '%s | Tsh %s | %s | Ref: %s | %s',
                $saleRef,
                number_format((float) ($payment['amount'] ?? 0), 0, '.', ','),
                (string) ($payment['payment_method'] ?? 'Cash'),
                (string) (($payment['reference'] ?? '') !== '' ? $payment['reference'] : '-'),
                (string) ($payment['created_at'] ?? '')
            );
        }
    }

    $pdf = buildSimplePdf('Customer Statement', $lines);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="customer_statement_' . $customerId . '_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo $pdf;
    exit;
} catch (Throwable $exception) {
    error_log('[POS Customer Statement PDF] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate customer statement at this time.';
    exit;
}
