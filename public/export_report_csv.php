<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';

ensureSecureSessionStarted();
requireAuthentication();

$range = strtolower(trim((string) ($_GET['range'] ?? 'month')));
$lang = strtolower(trim((string) ($_GET['lang'] ?? 'en')));
if (!in_array($lang, ['en', 'sw'], true)) {
    $lang = 'en';
}

$t = static fn(string $en, string $sw): string => $lang === 'sw' ? $sw : $en;

$allowedRanges = ['day', 'today', 'week', 'month', 'year', 'all'];
if (!in_array($range, $allowedRanges, true)) {
    $range = 'month';
}

$rangeLabelMap = [
    'day' => $t('Today', 'Leo'),
    'today' => $t('Today', 'Leo'),
    'week' => $t('This Week', 'Wiki Hii'),
    'month' => $t('This Month', 'Mwezi Huu'),
    'year' => $t('This Year', 'Mwaka Huu'),
    'all' => $t('All Time', 'Muda Wote'),
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

    $fileName = 'report_' . $range . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        throw new RuntimeException('Unable to open output stream.');
    }

    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Mchongoma POS Report']);
    fputcsv($out, [$t('Generated At', 'Imetengenezwa'), date('Y-m-d H:i:s')]);
    fputcsv($out, [$t('Range', 'Kipindi'), $rangeLabelMap[$range] ?? strtoupper($range)]);
    fputcsv($out, []);

    fputcsv($out, [$t('Summary', 'Muhtasari')]);
    fputcsv($out, [$t('Total Sales (TZS)', 'Jumla ya Mauzo (TZS)'), number_format((float) ($summary['total_sales'] ?? 0), 2, '.', '')]);
    fputcsv($out, [$t('Total Transactions', 'Jumla ya Miamala'), (string) (int) ($summary['total_transactions'] ?? 0)]);
    fputcsv($out, [$t('Average Sale (TZS)', 'Wastani wa Uuzaji (TZS)'), number_format((float) ($summary['average_sale'] ?? 0), 2, '.', '')]);
    fputcsv($out, []);

    fputcsv($out, [$t('Payment Breakdown', 'Mchanganuo wa Malipo')]);
    fputcsv($out, [$t('Payment Method', 'Njia ya Malipo'), $t('Amount (TZS)', 'Kiasi (TZS)')]);
    foreach ($paymentRows as $row) {
        $paymentMethod = (string) ($row['payment_method'] ?? 'Unknown');
        if ($lang === 'sw') {
            $paymentMethodMap = [
                'Cash' => 'Taslimu',
                'Mobile Money' => 'Pesa Mtandao',
                'Card' => 'Kadi',
                'Bank Transfer' => 'Uhamisho wa Benki',
                'Unknown' => 'Haijulikani',
            ];
            $paymentMethod = $paymentMethodMap[$paymentMethod] ?? $paymentMethod;
        }

        fputcsv($out, [
            $paymentMethod,
            number_format((float) ($row['total_amount'] ?? 0), 2, '.', ''),
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, [$t('Transactions', 'Miamala')]);
    fputcsv($out, [$t('Transaction No', 'Namba ya Muamala'), $t('Customer', 'Mteja'), $t('Amount (TZS)', 'Kiasi (TZS)'), $t('Payment Method', 'Njia ya Malipo'), $t('Created At', 'Tarehe ya Kutengenezwa')]);
    foreach ($sales as $sale) {
        $paymentMethod = (string) ($sale['payment_method'] ?? '-');
        if ($lang === 'sw') {
            $paymentMethodMap = [
                'Cash' => 'Taslimu',
                'Mobile Money' => 'Pesa Mtandao',
                'Card' => 'Kadi',
                'Bank Transfer' => 'Uhamisho wa Benki',
            ];
            $paymentMethod = $paymentMethodMap[$paymentMethod] ?? $paymentMethod;
        }

        fputcsv($out, [
            (string) ($sale['transaction_no'] ?? '-'),
            (string) ($sale['customer_name'] ?? '-'),
            number_format((float) ($sale['amount'] ?? 0), 2, '.', ''),
            $paymentMethod,
            (string) ($sale['created_at'] ?? '-'),
        ]);
    }

    fclose($out);
    exit;
} catch (Throwable $exception) {
    error_log('[POS Export CSV] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $lang === 'sw'
        ? 'Imeshindikana kutengeneza ripoti ya CSV kwa sasa.'
        : 'Could not generate CSV report at this time.';
    exit;
}
