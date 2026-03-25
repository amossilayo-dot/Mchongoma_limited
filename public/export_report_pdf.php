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

    if ($type === 'daily') {
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

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Ymd_His') . '.pdf"');
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
