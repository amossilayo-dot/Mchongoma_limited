<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/XlsxHelper.php';

ensureSecureSessionStarted();
requireAuthentication();

$range = strtolower(trim((string) ($_GET['range'] ?? 'month')));
$allowedRanges = ['day', 'today', 'week', 'month', 'year', 'all'];
if (!in_array($range, $allowedRanges, true)) {
    $range = 'month';
}

$rangeLabelMap = [
    'day' => 'Today',
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'year' => 'This Year',
    'all' => 'All Time',
];

$rangeStart = match ($range) {
    'day' => (new DateTimeImmutable('today'))->format('Y-m-d H:i:s'),
    'today' => (new DateTimeImmutable('today'))->format('Y-m-d H:i:s'),
    'week' => (new DateTimeImmutable('today'))->sub(new DateInterval('P6D'))->format('Y-m-d H:i:s'),
    'month' => (new DateTimeImmutable('today'))->sub(new DateInterval('P29D'))->format('Y-m-d H:i:s'),
    'year' => (new DateTimeImmutable('today'))->sub(new DateInterval('P364D'))->format('Y-m-d H:i:s'),
    default => null,
};

$whereClause = '';
$params = [];
if ($rangeStart !== null) {
    $whereClause = ' WHERE s.created_at >= :range_start';
    $params[':range_start'] = $rangeStart;
}

try {
    $pdo = getDatabaseConnection();

    $summaryStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(s.amount), 0) AS total_sales,
                COUNT(*) AS total_transactions,
                COALESCE(AVG(s.amount), 0) AS average_sale
         FROM sales s' . $whereClause
    );
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch() ?: [];

    $paymentStmt = $pdo->prepare(
        'SELECT COALESCE(s.payment_method, "Unknown") AS payment_method,
                COALESCE(SUM(s.amount), 0) AS total_amount
         FROM sales s' . $whereClause . '
         GROUP BY s.payment_method
         ORDER BY total_amount DESC'
    );
    foreach ($params as $key => $value) {
        $paymentStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $paymentStmt->execute();
    $paymentRows = $paymentStmt->fetchAll() ?: [];

    $salesStmt = $pdo->prepare(
        'SELECT s.transaction_no, s.amount, s.payment_method, s.created_at,
                c.name AS customer_name
         FROM sales s
         LEFT JOIN customers c ON c.id = s.customer_id' . $whereClause . '
         ORDER BY s.created_at DESC
         LIMIT 1000'
    );
    foreach ($params as $key => $value) {
        $salesStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $salesStmt->execute();
    $sales = $salesStmt->fetchAll() ?: [];

    $rows = [
        ['Mchongoma POS Report'],
        ['Generated At', date('Y-m-d H:i:s')],
        ['Range', $rangeLabelMap[$range] ?? strtoupper($range)],
        [],
        ['Summary'],
        ['Metric', 'Value'],
        ['Total Sales (TZS)', number_format((float) ($summary['total_sales'] ?? 0), 2, '.', '')],
        ['Total Transactions', (string) (int) ($summary['total_transactions'] ?? 0)],
        ['Average Sale (TZS)', number_format((float) ($summary['average_sale'] ?? 0), 2, '.', '')],
        [],
        ['Payment Breakdown'],
        ['Payment Method', 'Amount (TZS)'],
    ];

    foreach ($paymentRows as $row) {
        $rows[] = [
            (string) ($row['payment_method'] ?? 'Unknown'),
            number_format((float) ($row['total_amount'] ?? 0), 2, '.', ''),
        ];
    }

    $rows[] = [];
    $rows[] = ['Transactions'];
    $rows[] = ['Transaction No', 'Customer', 'Amount (TZS)', 'Payment Method', 'Created At'];

    foreach ($sales as $sale) {
        $rows[] = [
            (string) ($sale['transaction_no'] ?? '-'),
            (string) ($sale['customer_name'] ?? '-'),
            number_format((float) ($sale['amount'] ?? 0), 2, '.', ''),
            (string) ($sale['payment_method'] ?? '-'),
            (string) ($sale['created_at'] ?? '-'),
        ];
    }

    $tempFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'report_' . $range . '_' . date('Ymd_His') . '.xlsx';
    writeXlsxFile($tempFile, 'Report', $rows);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="report_' . $range . '_' . date('Ymd_His') . '.xlsx"');
    header('Content-Length: ' . (string) filesize($tempFile));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    readfile($tempFile);
    @unlink($tempFile);
    exit;
} catch (Throwable $exception) {
    error_log('[POS Export XLSX Report] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate XLSX report at this time.';
    exit;
}
