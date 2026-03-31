<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/DashboardRepository.php';
require_once __DIR__ . '/../app/InventoryRepository.php';
require_once __DIR__ . '/../app/CustomerRepository.php';
require_once __DIR__ . '/../app/SalesRepository.php';
require_once __DIR__ . '/../app/PdfHelper.php';

ensureSecureSessionStarted();
requireAuthentication();

$type = strtolower(trim((string) ($_GET['type'] ?? 'daily')));
$allowedTypes = ['daily', 'weekly', 'monthly', 'inventory', 'customers', 'profit'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'daily';
}

$range = strtolower(trim((string) ($_GET['range'] ?? '')));
$allowedRanges = ['day', 'today', 'week', 'month', 'year', 'all'];
if (!in_array($range, $allowedRanges, true)) {
    $range = '';
}

$disposition = strtolower(trim((string) ($_GET['disposition'] ?? 'attachment')));
if (!in_array($disposition, ['attachment', 'inline'], true)) {
    $disposition = 'attachment';
}

try {
    $pdo = getDatabaseConnection();
    $dashboardRepo = new DashboardRepository($pdo);
    $inventoryRepo = new InventoryRepository($pdo);
    $customerRepo = new CustomerRepository($pdo);
    $salesRepo = new SalesRepository($pdo);

    $title = 'Mchongoma POS Report';
    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        'Type: ' . strtoupper($type),
        str_repeat('-', 90),
    ];

    if ($range !== '') {
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
             LIMIT 120'
        );
        foreach ($params as $key => $value) {
            $salesStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $salesStmt->execute();
        $sales = $salesStmt->fetchAll() ?: [];

        $title = 'Sales Report - ' . ($rangeLabelMap[$range] ?? 'Custom Range');
        $lines = [
            'Generated: ' . date('Y-m-d H:i:s'),
            'Range: ' . ($rangeLabelMap[$range] ?? strtoupper($range)),
            str_repeat('-', 90),
            'Total Sales: Tsh ' . number_format((float) ($summary['total_sales'] ?? 0), 0, '.', ','),
            'Total Transactions: ' . (int) ($summary['total_transactions'] ?? 0),
            'Average Sale: Tsh ' . number_format((float) ($summary['average_sale'] ?? 0), 0, '.', ','),
        ];

        if (count($paymentRows) > 0) {
            $lines[] = str_repeat('-', 90);
            $lines[] = 'Payment Breakdown';
            foreach ($paymentRows as $paymentRow) {
                $lines[] = sprintf(
                    '%s: Tsh %s',
                    (string) ($paymentRow['payment_method'] ?? 'Unknown'),
                    number_format((float) ($paymentRow['total_amount'] ?? 0), 0, '.', ',')
                );
            }
        }

        $lines[] = str_repeat('-', 90);
        $lines[] = 'Recent Transactions in Range (up to 120)';

        if (count($sales) === 0) {
            $lines[] = 'No sales found for this range.';
        } else {
            foreach ($sales as $sale) {
                $timestamp = strtotime((string) ($sale['created_at'] ?? ''));
                $dateLabel = $timestamp !== false ? date('Y-m-d H:i', $timestamp) : '-';
                $lines[] = sprintf(
                    '%s | %s | Tsh %s | %s | %s',
                    (string) ($sale['transaction_no'] ?? '-'),
                    (string) ($sale['customer_name'] ?? '-'),
                    number_format((float) ($sale['amount'] ?? 0), 0, '.', ','),
                    (string) ($sale['payment_method'] ?? '-'),
                    $dateLabel
                );
            }
        }
    } elseif ($type === 'daily') {
        $title = 'Daily Sales Report';
        $sales = $salesRepo->getTodaysSales();
        $total = 0.0;
        foreach ($sales as $sale) {
            $amount = (float) ($sale['amount'] ?? 0);
            $total += $amount;
            $lines[] = sprintf(
                '%s | %s | Tsh %s | %s',
                (string) ($sale['transaction_no'] ?? '-'),
                (string) ($sale['customer_name'] ?? '-'),
                number_format($amount, 0, '.', ','),
                (string) ($sale['payment_method'] ?? '-')
            );
        }
        $lines[] = str_repeat('-', 90);
        $lines[] = 'Total Sales Today: Tsh ' . number_format($total, 0, '.', ',');
        $lines[] = 'Total Transactions: ' . count($sales);
    } elseif ($type === 'weekly') {
        $title = 'Weekly Sales Report';
        $weekly = $dashboardRepo->getWeeklySales();
        $labels = $weekly['labels'] ?? [];
        $values = $weekly['values'] ?? [];
        $total = 0.0;
        foreach ($labels as $i => $label) {
            $amount = (float) ($values[$i] ?? 0);
            $total += $amount;
            $lines[] = 'Day ' . $label . ': Tsh ' . number_format($amount, 0, '.', ',');
        }
        $lines[] = str_repeat('-', 90);
        $lines[] = 'Weekly Total: Tsh ' . number_format($total, 0, '.', ',');
    } elseif ($type === 'monthly') {
        $title = 'Monthly Sales Trend Report';
        $monthly = $salesRepo->getMonthlySales();
        $labels = $monthly['labels'] ?? [];
        $values = $monthly['values'] ?? [];
        $total = 0.0;
        foreach ($labels as $i => $label) {
            $amount = (float) ($values[$i] ?? 0);
            $total += $amount;
            $lines[] = 'Day ' . $label . ': Tsh ' . number_format($amount, 0, '.', ',');
        }
        $lines[] = str_repeat('-', 90);
        $lines[] = '30-Day Total: Tsh ' . number_format($total, 0, '.', ',');
    } elseif ($type === 'inventory') {
        $title = 'Inventory Report';
        $products = $inventoryRepo->getProducts(200);
        $lowStock = 0;
        foreach ($products as $product) {
            $qty = (int) ($product['stock_qty'] ?? 0);
            $reorder = (int) ($product['reorder_level'] ?? 0);
            if ($qty <= $reorder) {
                $lowStock++;
            }
            $lines[] = sprintf(
                '%s | SKU: %s | Qty: %d | Reorder: %d | Tsh %s',
                (string) ($product['name'] ?? '-'),
                (string) ($product['sku'] ?? '-'),
                $qty,
                $reorder,
                number_format((float) ($product['unit_price'] ?? 0), 0, '.', ',')
            );
        }
        $lines[] = str_repeat('-', 90);
        $lines[] = 'Products Listed: ' . count($products);
        $lines[] = 'Low Stock Items: ' . $lowStock;
    } elseif ($type === 'customers') {
        $title = 'Customer Report';
        $customers = $customerRepo->getCustomers(200);
        foreach ($customers as $customer) {
            $lines[] = sprintf(
                '%s | Phone: %s | Orders: %d | Spent: Tsh %s',
                (string) ($customer['name'] ?? '-'),
                (string) ($customer['phone'] ?? 'N/A'),
                (int) ($customer['total_orders'] ?? 0),
                number_format((float) ($customer['total_spent'] ?? 0), 0, '.', ',')
            );
        }
        $lines[] = str_repeat('-', 90);
        $lines[] = 'Customers Listed: ' . count($customers);
    } else {
        $title = 'Profit and Loss Report';
        $salesSummary = $salesRepo->getSalesSummary();
        $totalSales = (float) ($salesSummary['total_sales'] ?? 0);

        $expenseStmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM expenses');
        $expenseTotal = (float) (($expenseStmt ? $expenseStmt->fetch()['total_expenses'] : 0) ?? 0);
        $profit = $totalSales - $expenseTotal;

        $lines[] = 'Total Sales: Tsh ' . number_format($totalSales, 0, '.', ',');
        $lines[] = 'Total Expenses: Tsh ' . number_format($expenseTotal, 0, '.', ',');
        $lines[] = 'Estimated Profit: Tsh ' . number_format($profit, 0, '.', ',');
        $lines[] = 'Total Transactions: ' . (int) ($salesSummary['total_transactions'] ?? 0);
    }

    $pdf = buildSimplePdf($title, $lines);

    $fileSuffix = $range !== '' ? 'range_' . $range : $type;

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="report_' . $fileSuffix . '_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo $pdf;
    exit;
} catch (Throwable $exception) {
    error_log('[POS Export PDF] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate PDF report at this time.';
    exit;
}
