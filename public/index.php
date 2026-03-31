<?php

declare(strict_types=1);

const IMPORT_MAX_ROWS = 20000;
const IMPORT_MAX_FILE_SIZE_BYTES = 25 * 1024 * 1024;
const MAX_SALE_ITEMS = 100;
const MAX_SALE_LINE_QTY = 1000;

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

require_once __DIR__ . '/../app/Auth.php';
ensureSecureSessionStarted();
requireAuthentication();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/PageController.php';
require_once __DIR__ . '/../app/DashboardRepository.php';
require_once __DIR__ . '/../app/InventoryRepository.php';
require_once __DIR__ . '/../app/CustomerRepository.php';
require_once __DIR__ . '/../app/SalesRepository.php';
require_once __DIR__ . '/../app/SuppliersRepository.php';
require_once __DIR__ . '/../app/EmployeesRepository.php';
require_once __DIR__ . '/../app/ExpensesRepository.php';
require_once __DIR__ . '/../app/InvoicesRepository.php';
require_once __DIR__ . '/../app/DeliveriesRepository.php';
require_once __DIR__ . '/../app/ReceivingRepository.php';
require_once __DIR__ . '/../app/QuotationsRepository.php';
require_once __DIR__ . '/../app/PurchaseOrdersRepository.php';
require_once __DIR__ . '/../app/ReturnsRepository.php';
require_once __DIR__ . '/../app/AppointmentsRepository.php';
require_once __DIR__ . '/../app/LocationsRepository.php';
require_once __DIR__ . '/../app/MessagesRepository.php';
require_once __DIR__ . '/../app/XlsxHelper.php';
require_once __DIR__ . '/../app/MobileMoneyGateway.php';
require_once __DIR__ . '/../app/MobileMoneyRepository.php';
require_once __DIR__ . '/../app/CustomerCreditRepository.php';

$pageController = new PageController();
$currentPage = $pageController->getCurrentPage();
$authUser = currentUser();
$userName = (string) ($authUser['name'] ?? 'User');
$userRole = (string) ($authUser['role'] ?? 'Staff');
$isDemoSession = (bool) ($authUser['is_demo'] ?? false);
$letters = preg_replace('/[^A-Za-z]/', '', $userName);
$userInitials = strtoupper(substr($letters !== null && $letters !== '' ? $letters : 'US', 0, 2));
$flashFeedback = $_SESSION['flash_feedback'] ?? null;
$flashReceipt = $_SESSION['flash_receipt'] ?? null;
unset($_SESSION['flash_feedback']);
unset($_SESSION['flash_receipt']);

$accessDeniedMessage = null;
if (!canAccessPage($currentPage, $userRole)) {
    $accessDeniedMessage = 'You do not have permission to open that page.';
    $currentPage = 'dashboard';
}

$pages = $pageController->getPages();
$pageTitle = $pages[$currentPage]['title'] ?? 'Dashboard';

$usingDemoData = false;
$errorMessage = null;
$pdo = null;
$importFeedback = null;
$setupStatus = [
    'pdoMysql' => extension_loaded('pdo_mysql'),
    'dbConnected' => false,
    'schemaReady' => false,
];

// Demo data fallbacks
$totals = [
    'totalSales' => 1035400,
    'totalCustomers' => 2,
    'totalProducts' => 4,
    'totalStockUnits' => 720,
    'totalItems' => 4,
    'transactionsToday' => 1,
];
$weeklySales = [
    'labels' => ['18', '19', '20', '21', '22', '23', '24'],
    'values' => [6000, 0, 8000, 90000, 0, 600000, 1035400],
];
$monthlySales = [
    'labels' => array_map(fn($i) => (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $i . 'D'))->format('d'), range(29, 0)),
    'values' => array_fill(0, 30, 0),
];
$lowStock = ['count' => 0, 'items' => [], 'message' => 'All products are well stocked.'];
$recentSales = [
    ['customer_name' => 'Walk-in Customer', 'transaction_no' => 'TXN-20260324-141851', 'amount' => 1035400, 'payment_method' => 'Cash', 'created_at' => '2026-03-24 22:18:51'],
    ['customer_name' => 'Walk-in Customer', 'transaction_no' => 'TXN-20260323-055039', 'amount' => 89900, 'payment_method' => 'Cash', 'created_at' => '2026-03-23 17:50:39'],
    ['customer_name' => 'Mchina', 'transaction_no' => 'TXN-20260323-053456', 'amount' => 51000, 'payment_method' => 'Cash', 'created_at' => '2026-03-23 17:34:56'],
    ['customer_name' => 'Mchina', 'transaction_no' => 'TXN-20260321-0904', 'amount' => 5500, 'payment_method' => 'Cash', 'created_at' => '2026-03-21 09:04:00'],
];
$products = [
    ['id' => 1, 'name' => 'Sugar 1kg', 'sku' => 'SKU-SUG-001', 'stock_qty' => 120, 'reorder_level' => 15, 'unit_price' => 3800],
    ['id' => 2, 'name' => 'Rice 1kg', 'sku' => 'SKU-RIC-001', 'stock_qty' => 240, 'reorder_level' => 20, 'unit_price' => 3500],
    ['id' => 3, 'name' => 'Soap Bar', 'sku' => 'SKU-SOP-001', 'stock_qty' => 200, 'reorder_level' => 25, 'unit_price' => 1200],
    ['id' => 4, 'name' => 'Milk 500ml', 'sku' => 'SKU-MLK-001', 'stock_qty' => 160, 'reorder_level' => 20, 'unit_price' => 1800],
];
$customers = [
    ['id' => 1, 'name' => 'Walk-in Customer', 'phone' => null, 'total_orders' => 4, 'total_spent' => 1139300, 'created_at' => '2026-03-18'],
    ['id' => 2, 'name' => 'Mchina', 'phone' => '255700000111', 'total_orders' => 2, 'total_spent' => 56500, 'created_at' => '2026-03-18'],
];
$saleProductOptions = [
    ['id' => 1, 'name' => 'Sugar 1kg', 'unit_price' => 3800],
    ['id' => 2, 'name' => 'Rice 1kg', 'unit_price' => 3500],
    ['id' => 3, 'name' => 'Soap Bar', 'unit_price' => 1200],
    ['id' => 4, 'name' => 'Milk 500ml', 'unit_price' => 1800],
];
$poProductOptions = $products;
$returnProductOptions = $products;
$saleCustomerOptions = [
    ['id' => 1, 'name' => 'Walk-in Customer'],
    ['id' => 2, 'name' => 'Mchina'],
];
$allSales = $recentSales;
$invoicesRecords = [];
$quotationsRecords = [];
$suppliers = [];
$purchaseOrdersRecords = [];
$purchaseOrderItemsByOrder = [];
$receivingRecords = [];
$receivingItemsByRecord = [];
$receivingPurchaseOrderOptions = [];
$returnsRecords = [];
$employees = [];
$appointments = [];
$systemUsers = [];
$userPermissionOverridesByUser = [];
$dashboardEodSummary = [
    'totalSales' => 1035400.0,
    'transactions' => 1,
    'cash' => 1035400.0,
    'mobileMoney' => 0.0,
];
$storeSettings = [
    'store_name' => 'Mchongoma Limited',
    'store_email' => 'info@mchongoma.com',
    'store_phone' => '',
    'store_address' => 'Dar es Salaam',
    'default_city' => 'Dar es Salaam',
    'starting_amount' => '50',
    'cash_denominations' => '50,100,200,500,1000,2000,5000,10000',
    'mobile_money_mode' => 'mock',
    'mobile_money_timeout' => '15',
    'mobile_money_callback_secret' => '',
    'mobile_money_mpesa_url' => '',
    'mobile_money_mpesa_token' => '',
    'mobile_money_mpesa_business_id' => '',
    'mobile_money_mpesa_command' => 'customer_paybill',
    'mobile_money_tigo_url' => '',
    'mobile_money_tigo_token' => '',
    'mobile_money_airtel_url' => '',
    'mobile_money_airtel_token' => '',
];
$configuredLocations = [];
$mobileMoneyTransactions = [];
$customerCredits = [];
$customerOutstandingTotals = [];
$securityAuditLogs = [];
$securityLogsStatusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($securityLogsStatusFilter, ['all', 'success', 'failed', 'blocked', 'denied', 'error'], true)) {
    $securityLogsStatusFilter = 'all';
}
$securityLogsSearch = trim((string) ($_GET['q'] ?? ''));
$inventoryProductCount = count($products);
$inventoryTotalStockUnits = array_reduce(
    $products,
    static fn(int $carry, array $item): int => $carry + (int) ($item['stock_qty'] ?? 0),
    0
);

try {
    $pdo = getDatabaseConnection();
    $setupStatus['dbConnected'] = true;

    $schemaCheckStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN ('sales', 'customers', 'products')"
    );
    $schemaCheckStmt->execute();
    $setupStatus['schemaReady'] = ((int) $schemaCheckStmt->fetch()['total']) === 3;

    $dashboardRepo = new DashboardRepository($pdo);
    $inventoryRepo = new InventoryRepository($pdo);
    $customerRepo = new CustomerRepository($pdo);
    $salesRepo = new SalesRepository($pdo);
    $mobileMoneyRepo = new MobileMoneyRepository($pdo);
    $locationsRepo = new LocationsRepository($pdo);
    $customerCreditRepo = new CustomerCreditRepository($pdo);

    ensureCustomerCreditTables($pdo);
    ensureReceivingItemsTable($pdo);
    ensureReturnsTableSchema($pdo);
    ensurePurchaseOrdersSchema($pdo);
    ensureQuotationsSchema($pdo);
    ensureUserAccountManagementSchema($pdo);

    if (isStoreConfigSaveRequest()) {
        $_SESSION['flash_feedback'] = handleStoreConfigSave($pdo, $userRole);
        header('Location: ?page=settings');
        exit;
    }

    if (isEntityCreateRequest()) {
        $_SESSION['flash_feedback'] = handleEntityCreate($pdo, $currentPage, $userName, $userRole);
        header('Location: ?page=' . urlencode($currentPage));
        exit;
    }

    if (isEntityUpdateRequest()) {
        $_SESSION['flash_feedback'] = handleEntityUpdate($pdo, $currentPage, $userRole);
        header('Location: ?page=' . urlencode($currentPage));
        exit;
    }

    if (isInventoryImportRequest()) {
        $importFeedback = handleProductImport($inventoryRepo, $userRole);
    }

    $totals = $dashboardRepo->getTotals();
    $weeklySales = $dashboardRepo->getWeeklySales();
    $monthlySales = $salesRepo->getMonthlySales();
    $lowStockItems = $inventoryRepo->getLowStockProducts();
    $lowStock = [
        'count' => count($lowStockItems),
        'items' => $lowStockItems,
        'message' => count($lowStockItems) > 0 ? count($lowStockItems) . ' products need restocking.' : 'All products are well stocked.',
    ];
    $recentSales = $dashboardRepo->getRecentSales(5);
    $inventoryProductLimit = $currentPage === 'inventory'
        ? max(200, $inventoryRepo->getTotalCount())
        : 200;
    $products = $inventoryRepo->getProducts($inventoryProductLimit);
    $inventoryProductCount = count($products);
    $inventoryTotalStockUnits = array_reduce(
        $products,
        static fn(int $carry, array $item): int => $carry + (int) ($item['stock_qty'] ?? 0),
        0
    );
    $customers = $customerRepo->getCustomers(50);
    $saleProductLimit = max(500, $inventoryRepo->getTotalCount());
    $saleProductOptions = array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'category' => (string) ($item['category'] ?? ''),
        'stock_qty' => (int) ($item['stock_qty'] ?? 0),
        'unit_price' => (float) ($item['unit_price'] ?? 0),
    ], $inventoryRepo->getProducts($saleProductLimit));
    $poProductOptions = $inventoryRepo->getProducts(max(500, $inventoryRepo->getTotalCount()));
    $returnProductOptions = $inventoryRepo->getProducts(max(500, $inventoryRepo->getTotalCount()));
    $saleCustomerOptions = array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
    ], $customerRepo->getCustomers(500));
    $allSales = $salesRepo->getSales(50);
    if (canAccessPage('invoices', $userRole)) {
        $invoicesRecords = (new InvoicesRepository($pdo))->getInvoices(300);
    }
    if (canAccessPage('deliveries', $userRole)) {
        $deliveriesRecords = (new DeliveriesRepository($pdo))->getDeliveries(300);
    }
    if (canAccessPage('quotations', $userRole)) {
        $quotationsRecords = (new QuotationsRepository($pdo))->getQuotations(300);
    }
    $suppliers = (new SuppliersRepository($pdo))->getSuppliers(200);
    if (canAccessPage('purchase-orders', $userRole)) {
        $purchaseOrdersRepo = new PurchaseOrdersRepository($pdo);
        $purchaseOrdersRecords = $purchaseOrdersRepo->getOrders(300);
        $purchaseOrderIds = array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $purchaseOrdersRecords);
        $purchaseOrderItemsByOrder = $purchaseOrdersRepo->getOrderItemsByOrderIds($purchaseOrderIds);
    }
    if (canAccessPage('receiving', $userRole)) {
        $receivingRepo = new ReceivingRepository($pdo);
        $receivingRecords = $receivingRepo->getReceivings(300);
        $receivingIds = array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $receivingRecords);
        $receivingItemsByRecord = $receivingRepo->getReceivingItemsByReceivingIds($receivingIds);

        $receivingPurchaseOrders = (new PurchaseOrdersRepository($pdo))->getOrders(500);
        $receivingPurchaseOrderOptions = array_values(array_filter(
            $receivingPurchaseOrders,
            static function (array $row): bool {
                $status = trim((string) ($row['status'] ?? 'Pending'));
                return in_array($status, ['Pending', 'Approved'], true);
            }
        ));
    }
    if (canAccessPage('employees', $userRole)) {
        $employees = (new EmployeesRepository($pdo))->getEmployees(300);
    }
    if (canAccessPage('appointments', $userRole)) {
        $appointments = (new AppointmentsRepository($pdo))->getAppointments(300);
    }
    if (canAccessPage('returns', $userRole)) {
        $returnsRecords = (new ReturnsRepository($pdo))->getReturns(300);
    }
    if (canAccessPage('users', $userRole) && hasUsersTable($pdo)) {
        $usersStmt = $pdo->query(
            'SELECT id, name, email, role, is_active, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT 300'
        );
        $systemUsers = $usersStmt ? ($usersStmt->fetchAll() ?: []) : [];

        $permissionRowsStmt = $pdo->query(
            'SELECT user_id, page_key, is_allowed
             FROM user_page_permissions'
        );
        $permissionRows = $permissionRowsStmt ? ($permissionRowsStmt->fetchAll() ?: []) : [];
        foreach ($permissionRows as $permissionRow) {
            $userId = (int) ($permissionRow['user_id'] ?? 0);
            $pageKey = strtolower(trim((string) ($permissionRow['page_key'] ?? '')));
            if ($userId <= 0 || $pageKey === '') {
                continue;
            }

            if (!isset($userPermissionOverridesByUser[$userId]) || !is_array($userPermissionOverridesByUser[$userId])) {
                $userPermissionOverridesByUser[$userId] = [];
            }
            $userPermissionOverridesByUser[$userId][$pageKey] = ((int) ($permissionRow['is_allowed'] ?? 0)) === 1;
        }
    }

    $todaySummary = $salesRepo->getTodaySummary();
    $eodByMethodStmt = $pdo->prepare(
        'SELECT payment_method, COALESCE(SUM(amount), 0) AS total
         FROM sales
         WHERE DATE(created_at) = CURDATE()
         GROUP BY payment_method'
    );
    $eodByMethodStmt->execute();
    $todayPaymentRows = $eodByMethodStmt->fetchAll() ?: [];

    $cashTotal = 0.0;
    $mobileMoneyTotal = 0.0;
    foreach ($todayPaymentRows as $row) {
        $method = strtolower(trim((string) ($row['payment_method'] ?? '')));
        $rowTotal = (float) ($row['total'] ?? 0);
        if ($method === 'cash') {
            $cashTotal += $rowTotal;
        }
        if ($method === 'mobile money') {
            $mobileMoneyTotal += $rowTotal;
        }
    }

    $dashboardEodSummary = [
        'totalSales' => (float) ($todaySummary['total_sales'] ?? 0),
        'transactions' => (int) ($todaySummary['total_transactions'] ?? 0),
        'cash' => $cashTotal,
        'mobileMoney' => $mobileMoneyTotal,
    ];

    $configuredLocations = $locationsRepo->getLocations(200);
    $mobileMoneyTransactions = $mobileMoneyRepo->getRecentTransactions(50);
    $customerCredits = $customerCreditRepo->getCredits(300, true);
    $customerOutstandingTotals = $customerCreditRepo->getCustomerOutstandingTotals();
    $storeSettings = getStoreSettings($pdo, $storeSettings);

    if ($currentPage === 'security-logs' && canAccessPage('security-logs', $userRole)) {
        ensureAuthSecurityTables($pdo);

        $sql = 'SELECT id, event_type, event_status, login_identifier, user_id, ip_address, meta_json, created_at
                FROM security_audit_logs
                WHERE 1=1';
        $params = [];

        if ($securityLogsStatusFilter !== 'all') {
            $sql .= ' AND event_status = :event_status';
            $params[':event_status'] = $securityLogsStatusFilter;
        }

        if ($securityLogsSearch !== '') {
            $sql .= ' AND (event_type LIKE :search OR login_identifier LIKE :search OR ip_address LIKE :search)';
            $params[':search'] = '%' . $securityLogsSearch . '%';
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 300';

        $securityStmt = $pdo->prepare($sql);
        $securityStmt->execute($params);
        $securityAuditLogs = $securityStmt->fetchAll() ?: [];
    }
} catch (Throwable $exception) {
    $usingDemoData = true;
    error_log('[POS Dashboard] ' . $exception->getMessage());
    if ($isDemoSession) {
        $errorMessage = 'Offline demo mode is active. Data changes are temporary until database connection is restored.';
    } else {
        $errorMessage = isDebugMode()
            ? $exception->getMessage()
            : 'Database is unavailable. Showing demo data until connection is restored.';
    }

    if (isInventoryImportRequest()) {
        $importFeedback = [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Import failed: ' . $exception->getMessage()
                : 'Import failed. Please check your file and try again.',
        ];
    }
}

// Reports page data with range filter support
$allowedReportRanges = ['today', 'week', 'month', 'year', 'all'];
$reportRange = strtolower(trim((string) ($_GET['range'] ?? 'month')));
if (!in_array($reportRange, $allowedReportRanges, true)) {
    $reportRange = 'month';
}

$reportRangeButtons = [
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'year' => 'This Year',
    'all' => 'All Time',
];

$reportRangeToExport = [
    'today' => 'day',
    'week' => 'week',
    'month' => 'month',
    'year' => 'year',
    'all' => 'all',
];
$reportExportRange = $reportRangeToExport[$reportRange] ?? 'month';

$todayStart = new DateTimeImmutable('today');
$rangeStart = match ($reportRange) {
    'today' => $todayStart,
    'week' => $todayStart->sub(new DateInterval('P6D')),
    'month' => $todayStart->sub(new DateInterval('P29D')),
    'year' => $todayStart->sub(new DateInterval('P364D')),
    default => null,
};

$reportSalesWindow = [];
foreach ($allSales as $sale) {
    $createdAtRaw = (string) ($sale['created_at'] ?? '');
    $timestamp = strtotime($createdAtRaw);
    if ($timestamp === false) {
        continue;
    }

    if ($rangeStart instanceof DateTimeImmutable && $timestamp < $rangeStart->getTimestamp()) {
        continue;
    }

    $sale['_ts'] = $timestamp;
    $reportSalesWindow[] = $sale;
}

$reportRevenueTotal = array_reduce(
    $reportSalesWindow,
    static fn(float $carry, array $sale): float => $carry + (float) ($sale['amount'] ?? 0),
    0.0
);
$reportTransactionsTotal = count($reportSalesWindow);
$reportProfitMargin = 11.3;
$reportGrossProfit = $reportRevenueTotal * ($reportProfitMargin / 100);

$daySeriesMap = [];
foreach ($reportSalesWindow as $sale) {
    $timestamp = (int) ($sale['_ts'] ?? 0);
    if ($timestamp <= 0) {
        continue;
    }

    $dayKey = ($reportRange === 'year' || $reportRange === 'all')
        ? date('Y-m', $timestamp)
        : date('Y-m-d', $timestamp);

    if (!isset($daySeriesMap[$dayKey])) {
        $daySeriesMap[$dayKey] = ['revenue' => 0.0, 'profit' => 0.0];
    }

    $amount = (float) ($sale['amount'] ?? 0);
    $daySeriesMap[$dayKey]['revenue'] += $amount;
    $daySeriesMap[$dayKey]['profit'] += $amount * ($reportProfitMargin / 100);
}

$reportDaySeries = [];
if ($reportRange === 'year' || $reportRange === 'all') {
    for ($i = 11; $i >= 0; $i--) {
        $month = $todayStart->modify('first day of this month')->sub(new DateInterval('P' . $i . 'M'));
        $key = $month->format('Y-m');
        $reportDaySeries[] = [
            'label' => $month->format('M y'),
            'revenue' => (float) ($daySeriesMap[$key]['revenue'] ?? 0),
            'profit' => (float) ($daySeriesMap[$key]['profit'] ?? 0),
        ];
    }
} else {
    $daysBack = match ($reportRange) {
        'today' => 0,
        'week' => 6,
        default => 29,
    };

    for ($i = $daysBack; $i >= 0; $i--) {
        $day = $todayStart->sub(new DateInterval('P' . $i . 'D'));
        $key = $day->format('Y-m-d');
        $reportDaySeries[] = [
            'label' => $day->format('j M'),
            'revenue' => (float) ($daySeriesMap[$key]['revenue'] ?? 0),
            'profit' => (float) ($daySeriesMap[$key]['profit'] ?? 0),
        ];
    }
}

$hourlySeriesMap = [];
foreach ($reportSalesWindow as $sale) {
    $timestamp = (int) ($sale['_ts'] ?? 0);
    if ($timestamp <= 0) {
        continue;
    }

    $hourKey = date('H', $timestamp);
    if (!isset($hourlySeriesMap[$hourKey])) {
        $hourlySeriesMap[$hourKey] = 0.0;
    }
    $hourlySeriesMap[$hourKey] += (float) ($sale['amount'] ?? 0);
}

$reportHourlySeries = [];
if (count($hourlySeriesMap) > 0) {
    ksort($hourlySeriesMap);
    foreach ($hourlySeriesMap as $hour => $value) {
        $reportHourlySeries[] = [
            'label' => date('gA', strtotime($hour . ':00')),
            'value' => (float) $value,
        ];
    }
} else {
    $reportHourlySeries = [
        ['label' => '6AM', 'value' => 0],
        ['label' => '9AM', 'value' => 0],
        ['label' => '11AM', 'value' => 0],
        ['label' => '2PM', 'value' => 0],
        ['label' => '8PM', 'value' => 0],
    ];
}

$paymentMap = [];
foreach ($reportSalesWindow as $sale) {
    $method = trim((string) ($sale['payment_method'] ?? 'Cash'));
    if ($method === '') {
        $method = 'Cash';
    }
    if (!isset($paymentMap[$method])) {
        $paymentMap[$method] = 0.0;
    }
    $paymentMap[$method] += (float) ($sale['amount'] ?? 0);
}

$reportPaymentBreakdown = [];
foreach ($paymentMap as $method => $amount) {
    $percentage = $reportRevenueTotal > 0 ? (($amount / $reportRevenueTotal) * 100) : 0;
    $reportPaymentBreakdown[] = [
        'method' => $method,
        'amount' => $amount,
        'percentage' => $percentage,
    ];
}
usort($reportPaymentBreakdown, static fn(array $a, array $b): int => $b['amount'] <=> $a['amount']);

$reportRangeLabel = (string) ($reportRangeButtons[$reportRange] ?? ucfirst($reportRange));
$reportAverageTicket = $reportTransactionsTotal > 0
    ? ($reportRevenueTotal / $reportTransactionsTotal)
    : 0.0;
$reportTopPaymentMethod = count($reportPaymentBreakdown) > 0
    ? (string) ($reportPaymentBreakdown[0]['method'] ?? 'N/A')
    : 'N/A';
$reportTopPaymentShare = count($reportPaymentBreakdown) > 0
    ? (float) ($reportPaymentBreakdown[0]['percentage'] ?? 0)
    : 0.0;
$reportGeneratedAt = date('M d, Y H:i');

$reportCashierPerformance = [[
    'name' => $userName,
    'sales_count' => $reportTransactionsTotal,
    'revenue' => $reportRevenueTotal,
]];

$reportTopProducts = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query(
            'SELECT p.name AS product_name,
                    SUM(oi.quantity) AS sold_qty,
                    SUM(oi.subtotal) AS revenue
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             GROUP BY oi.product_id, p.name
             ORDER BY sold_qty DESC, revenue DESC
             LIMIT 10'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
        foreach ($rows as $row) {
            $reportTopProducts[] = [
                'name' => (string) ($row['product_name'] ?? ''),
                'sold_qty' => (int) ($row['sold_qty'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
            ];
        }
    } catch (Throwable $exception) {
        // Fallback below if order_items schema is unavailable.
    }
}

if (count($reportTopProducts) === 0) {
    $fallback = $products;
    usort($fallback, static fn(array $a, array $b): int => ((float) ($b['unit_price'] ?? 0)) <=> ((float) ($a['unit_price'] ?? 0)));
    $fallback = array_slice($fallback, 0, 10);

    foreach ($fallback as $index => $product) {
        $soldQty = max(1, 10 - $index);
        $revenue = $soldQty * (float) ($product['unit_price'] ?? 0);
        $reportTopProducts[] = [
            'name' => (string) ($product['name'] ?? 'Product'),
            'sold_qty' => $soldQty,
            'revenue' => $revenue,
        ];
    }
}

function moneyFormat(int|float|string|null $amount): string
{
    if (is_string($amount)) {
        $amount = trim(str_replace(',', '', $amount));
    }

    if (!is_numeric($amount)) {
        $amount = 0;
    }

    return number_format((float) $amount, 0, '.', ',');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function isDebugMode(): bool
{
    $value = strtolower(trim((string) (getenv('APP_DEBUG') ?: '0')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function hasValidCsrfToken(): bool
{
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    $requestToken = (string) ($_POST['csrf_token'] ?? '');

    return $sessionToken !== '' && $requestToken !== '' && hash_equals($sessionToken, $requestToken);
}

function isInventoryImportRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && ($_GET['page'] ?? 'dashboard') === 'inventory'
        && ($_POST['action'] ?? '') === 'import_products';
}

function isEntityCreateRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && ($_POST['action'] ?? '') === 'create_entity';
}

function isEntityUpdateRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && ($_POST['action'] ?? '') === 'update_entity';
}

function isStoreConfigSaveRequest(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && ($_GET['page'] ?? 'dashboard') === 'settings'
        && ($_POST['action'] ?? '') === 'save_store_config';
}

function resolveEntityPageKey(string $entity): ?string
{
    $map = [
        'sale' => 'sales',
        'product' => 'inventory',
        'customer' => 'customers',
        'customer_delete' => 'customers',
        'customer_payment' => 'customers',
        'supplier' => 'suppliers',
        'supplier_delete' => 'suppliers',
        'employee' => 'employees',
        'user' => 'users',
        'user_status' => 'users',
        'user_password_reset' => 'users',
        'user_permission_override' => 'users',
        'expense' => 'expenses',
        'invoice' => 'invoices',
        'invoice_status' => 'invoices',
        'delivery' => 'deliveries',
        'delivery_status' => 'deliveries',
        'receiving' => 'receiving',
        'receiving_status' => 'receiving',
        'quotation' => 'quotations',
        'quotation_status' => 'quotations',
        'quotation_delete' => 'quotations',
        'purchase_order' => 'purchase-orders',
        'purchase_order_status' => 'purchase-orders',
        'return' => 'returns',
        'return_status' => 'returns',
        'appointment' => 'appointments',
        'appointment_delete' => 'appointments',
        'appointment_status' => 'appointments',
        'location' => 'locations',
        'message' => 'messages',
    ];

    return $map[$entity] ?? null;
}

function canManageEntityRequest(string $entity, string $userRole): bool
{
    $pageKey = resolveEntityPageKey($entity);
    if ($pageKey === null) {
        return false;
    }

    return canAccessPage($pageKey, $userRole);
}

function normalizeUserRoleInput(string $rawRole): ?string
{
    $normalized = strtolower(trim($rawRole));
    $roles = [
        'admin' => 'Admin',
        'manager' => 'Manager',
        'staff' => 'Staff',
        'cashier' => 'Cashier',
    ];

    return $roles[$normalized] ?? null;
}

function normalizePermissionModeInput(string $rawMode): ?string
{
    $mode = strtolower(trim($rawMode));
    return in_array($mode, ['allow', 'deny', 'default'], true) ? $mode : null;
}

function ensureCustomerCreditTables(PDO $pdo): void
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customer_credit_customer (customer_id),
            INDEX idx_customer_credit_status (status),
            INDEX idx_customer_credit_sale (sale_id),
            CONSTRAINT fk_customer_credit_sale
                FOREIGN KEY (sale_id) REFERENCES sales(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_customer_credit_customer
                FOREIGN KEY (customer_id) REFERENCES customers(id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $dueDateColumnCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "customer_credits"
           AND column_name = "due_date"'
    );
    $dueDateColumnCheck->execute();
    if ((int) ($dueDateColumnCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE customer_credits ADD COLUMN due_date DATE NULL AFTER status');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS customer_credit_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credit_id INT NOT NULL,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_method ENUM("Cash", "Mobile Money", "Card", "Bank Transfer") NOT NULL DEFAULT "Cash",
            reference VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_credit_payment_credit (credit_id),
            INDEX idx_credit_payment_customer (customer_id),
            CONSTRAINT fk_credit_payment_credit
                FOREIGN KEY (credit_id) REFERENCES customer_credits(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_credit_payment_customer
                FOREIGN KEY (customer_id) REFERENCES customers(id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensureReceivingItemsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS receiving_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receiving_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity_received INT NOT NULL DEFAULT 0,
            quantity_rejected INT NOT NULL DEFAULT 0,
            unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receiving_item_receiving (receiving_id),
            INDEX idx_receiving_item_product (product_id),
            CONSTRAINT fk_receiving_item_receiving
                FOREIGN KEY (receiving_id) REFERENCES receiving(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_receiving_item_product
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $stockAppliedColumnCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "receiving"
           AND column_name = "stock_applied"'
    );
    $stockAppliedColumnCheck->execute();
    if ((int) ($stockAppliedColumnCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE receiving ADD COLUMN stock_applied TINYINT(1) NOT NULL DEFAULT 0 AFTER amount');
    }

    $purchaseOrderColumnCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "receiving"
           AND column_name = "purchase_order_id"'
    );
    $purchaseOrderColumnCheck->execute();
    if ((int) ($purchaseOrderColumnCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE receiving ADD COLUMN purchase_order_id INT NULL AFTER supplier_id');
    }

    $poIndexCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = "receiving"
           AND index_name = "idx_receiving_purchase_order"'
    );
    $poIndexCheck->execute();
    if ((int) ($poIndexCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE receiving ADD INDEX idx_receiving_purchase_order (purchase_order_id)');
    }

    $poForeignKeyCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.key_column_usage
         WHERE table_schema = DATABASE()
           AND table_name = "receiving"
           AND column_name = "purchase_order_id"
           AND referenced_table_name = "purchase_orders"'
    );
    $poForeignKeyCheck->execute();
    if ((int) ($poForeignKeyCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec(
            'ALTER TABLE receiving
             ADD CONSTRAINT fk_receiving_purchase_order
             FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
             ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }
}

function ensureReturnsTableSchema(PDO $pdo): void
{
    $isExpiredColumnCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "returns"
           AND column_name = "is_expired"'
    );
    $isExpiredColumnCheck->execute();
    if ((int) ($isExpiredColumnCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE returns ADD COLUMN is_expired TINYINT(1) NOT NULL DEFAULT 0 AFTER reason');
    }
}

function ensurePurchaseOrdersSchema(PDO $pdo): void
{
    $expectedDateColumnCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "purchase_orders"
           AND column_name = "expected_delivery_date"'
    );
    $expectedDateColumnCheck->execute();
    if ((int) ($expectedDateColumnCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN expected_delivery_date DATE NULL AFTER status');
    }

    $notesColumnCheck = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "purchase_orders"
           AND column_name = "notes"'
    );
    $notesColumnCheck->execute();
    if ((int) ($notesColumnCheck->fetch()['total'] ?? 0) === 0) {
        $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN notes TEXT NULL AFTER expected_delivery_date');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_po_item_po (purchase_order_id),
            INDEX idx_po_item_product (product_id),
            CONSTRAINT fk_po_item_po
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_po_item_product
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensureQuotationsSchema(PDO $pdo): void
{
    $tableCheckStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = "quotations"'
    );
    $tableCheckStmt->execute();
    if ((int) ($tableCheckStmt->fetch()['total'] ?? 0) === 0) {
        return;
    }

    $statusColumnStmt = $pdo->prepare(
        'SELECT column_type
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "quotations"
           AND column_name = "status"
         LIMIT 1'
    );
    $statusColumnStmt->execute();
    $statusColumn = $statusColumnStmt->fetch();
    if (!$statusColumn) {
        return;
    }

    $columnType = strtolower((string) ($statusColumn['column_type'] ?? ''));
    $isLegacyEnum = str_contains($columnType, 'draft')
        || str_contains($columnType, 'sent')
        || str_contains($columnType, 'accepted');

    if ($isLegacyEnum) {
        // Expand enum first so both legacy and new values are valid during migration.
        $pdo->exec(
            'ALTER TABLE quotations
             MODIFY status ENUM("Draft","Sent","Accepted","Rejected","Pending","Approved","Expired")
             NOT NULL DEFAULT "Pending"'
        );

        $pdo->exec(
            'UPDATE quotations
             SET status = CASE
                WHEN status IN ("Draft", "Sent", "") THEN "Pending"
                WHEN status = "Accepted" THEN "Approved"
                WHEN status = "Rejected" THEN "Rejected"
                ELSE status
             END'
        );

        $pdo->exec(
            'ALTER TABLE quotations
             MODIFY status ENUM("Pending","Approved","Rejected","Expired")
             NOT NULL DEFAULT "Pending"'
        );
    }

    // Normalize blanks introduced by old invalid enum coercions.
    $pdo->exec('UPDATE quotations SET status = "Pending" WHERE status = "" OR status IS NULL');
}

function ensureStoreSettingsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function getStoreSettings(PDO $pdo, array $defaults): array
{
    ensureStoreSettingsTable($pdo);
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM store_settings');
    $rows = $stmt ? $stmt->fetchAll() : [];

    foreach ($rows as $row) {
        $key = (string) ($row['setting_key'] ?? '');
        if ($key === '' || !array_key_exists($key, $defaults)) {
            continue;
        }
        $defaults[$key] = (string) ($row['setting_value'] ?? '');
    }

    return $defaults;
}

function upsertStoreSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([
        ':setting_key' => $key,
        ':setting_value' => $value,
    ]);
}

function locationAlreadyExists(PDO $pdo, string $name, string $address, string $city): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM locations
         WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
           AND LOWER(TRIM(address)) = LOWER(TRIM(:address))
           AND LOWER(TRIM(city)) = LOWER(TRIM(:city))'
    );
    $stmt->execute([
        ':name' => $name,
        ':address' => $address,
        ':city' => $city,
    ]);

    return ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
}

function handleStoreConfigSave(PDO $pdo, string $userRole): array
{
    if (!canAccessPage('settings', $userRole)) {
        logSecurityAuditEvent('settings_update_denied', 'denied', '', ['role' => $userRole], $pdo);
        return [
            'type' => 'error',
            'message' => 'You are not allowed to change store settings.',
        ];
    }

    if (!hasValidCsrfToken()) {
        return [
            'type' => 'error',
            'message' => 'Request validation failed. Refresh the page and try again.',
        ];
    }

    $storeName = trim((string) ($_POST['store_name'] ?? 'Mchongoma Limited'));
    $storeEmail = trim((string) ($_POST['store_email'] ?? ''));
    $storePhone = trim((string) ($_POST['store_phone'] ?? ''));
    $storeAddress = trim((string) ($_POST['store_address'] ?? 'Dar es Salaam'));
    $defaultCity = trim((string) ($_POST['default_city'] ?? 'Dar es Salaam'));
    $startingAmountRaw = trim((string) ($_POST['starting_amount'] ?? '50'));
    $mobileMoneyModeRaw = strtolower(trim((string) ($_POST['mobile_money_mode'] ?? 'mock')));
    $mobileMoneyTimeoutRaw = trim((string) ($_POST['mobile_money_timeout'] ?? '15'));
    $mobileMoneyCallbackSecret = trim((string) ($_POST['mobile_money_callback_secret'] ?? ''));
    $mobileMoneyMpesaUrl = trim((string) ($_POST['mobile_money_mpesa_url'] ?? ''));
    $mobileMoneyMpesaToken = trim((string) ($_POST['mobile_money_mpesa_token'] ?? ''));
    $mobileMoneyMpesaBusinessId = trim((string) ($_POST['mobile_money_mpesa_business_id'] ?? ''));
    $mobileMoneyMpesaCommandRaw = strtolower(trim((string) ($_POST['mobile_money_mpesa_command'] ?? 'customer_paybill')));
    $mobileMoneyTigoUrl = trim((string) ($_POST['mobile_money_tigo_url'] ?? ''));
    $mobileMoneyTigoToken = trim((string) ($_POST['mobile_money_tigo_token'] ?? ''));
    $mobileMoneyAirtelUrl = trim((string) ($_POST['mobile_money_airtel_url'] ?? ''));
    $mobileMoneyAirtelToken = trim((string) ($_POST['mobile_money_airtel_token'] ?? ''));

    if ($storeName === '') {
        return ['type' => 'error', 'message' => 'Store name is required.'];
    }

    if ($storeAddress === '') {
        return ['type' => 'error', 'message' => 'Store address is required.'];
    }

    if ($defaultCity === '') {
        $defaultCity = 'Dar es Salaam';
    }

    if (!is_numeric($startingAmountRaw)) {
        return ['type' => 'error', 'message' => 'Starting amount must be a valid number.'];
    }

    $startingAmount = (float) $startingAmountRaw;
    if ($startingAmount < 50) {
        return ['type' => 'error', 'message' => 'Starting amount must be at least 50 Tsh.'];
    }

    if ($storeEmail !== '' && !filter_var($storeEmail, FILTER_VALIDATE_EMAIL)) {
        return ['type' => 'error', 'message' => 'Store email format is invalid.'];
    }

    $allowedModes = ['mock', 'live'];
    $mobileMoneyMode = in_array($mobileMoneyModeRaw, $allowedModes, true) ? $mobileMoneyModeRaw : 'mock';

    if (!is_numeric($mobileMoneyTimeoutRaw)) {
        return ['type' => 'error', 'message' => 'Gateway timeout must be a valid number.'];
    }

    $mobileMoneyTimeout = (int) $mobileMoneyTimeoutRaw;
    if ($mobileMoneyTimeout < 5 || $mobileMoneyTimeout > 60) {
        return ['type' => 'error', 'message' => 'Gateway timeout must be between 5 and 60 seconds.'];
    }

    $allowedMpesaCommands = ['customer_paybill', 'customer_buygoods', 'disburse'];
    $mobileMoneyMpesaCommand = in_array($mobileMoneyMpesaCommandRaw, $allowedMpesaCommands, true)
        ? $mobileMoneyMpesaCommandRaw
        : 'customer_paybill';

    if ($mobileMoneyMode === 'live') {
        if (strlen($mobileMoneyCallbackSecret) < 16) {
            return ['type' => 'error', 'message' => 'Callback secret must be at least 16 characters in live mode.'];
        }
        if ($mobileMoneyMpesaUrl !== '' && !filter_var($mobileMoneyMpesaUrl, FILTER_VALIDATE_URL)) {
            return ['type' => 'error', 'message' => 'M-Pesa URL is invalid.'];
        }
        if ($mobileMoneyTigoUrl !== '' && !filter_var($mobileMoneyTigoUrl, FILTER_VALIDATE_URL)) {
            return ['type' => 'error', 'message' => 'Tigo Pesa URL is invalid.'];
        }
        if ($mobileMoneyAirtelUrl !== '' && !filter_var($mobileMoneyAirtelUrl, FILTER_VALIDATE_URL)) {
            return ['type' => 'error', 'message' => 'Airtel Money URL is invalid.'];
        }
    }

    $locationNames = $_POST['location_name'] ?? [];
    $locationAddresses = $_POST['location_address'] ?? [];
    $locationCities = $_POST['location_city'] ?? [];
    $locationPhones = $_POST['location_phone'] ?? [];
    $denominationsInput = $_POST['cash_denominations'] ?? [];

    if (!is_array($locationNames)) {
        $locationNames = [];
    }
    if (!is_array($locationAddresses)) {
        $locationAddresses = [];
    }
    if (!is_array($locationCities)) {
        $locationCities = [];
    }
    if (!is_array($locationPhones)) {
        $locationPhones = [];
    }
    if (!is_array($denominationsInput)) {
        $denominationsInput = [];
    }

    $validDenominations = [50, 100, 200, 500, 1000, 2000, 5000, 10000];
    $selectedDenominations = [];
    foreach ($denominationsInput as $item) {
        $value = (int) $item;
        if (in_array($value, $validDenominations, true)) {
            $selectedDenominations[$value] = true;
        }
    }
    $selectedDenominations = array_keys($selectedDenominations);
    sort($selectedDenominations);

    if (count($selectedDenominations) === 0) {
        $selectedDenominations = $validDenominations;
    }

    try {
        $pdo->beginTransaction();

        ensureStoreSettingsTable($pdo);
        upsertStoreSetting($pdo, 'store_name', $storeName);
        upsertStoreSetting($pdo, 'store_email', $storeEmail);
        upsertStoreSetting($pdo, 'store_phone', $storePhone);
        upsertStoreSetting($pdo, 'store_address', $storeAddress);
        upsertStoreSetting($pdo, 'default_city', $defaultCity);
        upsertStoreSetting($pdo, 'starting_amount', (string) ((int) round($startingAmount)));
        upsertStoreSetting($pdo, 'cash_denominations', implode(',', array_map('strval', $selectedDenominations)));
        upsertStoreSetting($pdo, 'mobile_money_mode', $mobileMoneyMode);
        upsertStoreSetting($pdo, 'mobile_money_timeout', (string) $mobileMoneyTimeout);
        upsertStoreSetting($pdo, 'mobile_money_callback_secret', $mobileMoneyCallbackSecret);
        upsertStoreSetting($pdo, 'mobile_money_mpesa_url', $mobileMoneyMpesaUrl);
        upsertStoreSetting($pdo, 'mobile_money_mpesa_token', $mobileMoneyMpesaToken);
        upsertStoreSetting($pdo, 'mobile_money_mpesa_business_id', $mobileMoneyMpesaBusinessId);
        upsertStoreSetting($pdo, 'mobile_money_mpesa_command', $mobileMoneyMpesaCommand);
        upsertStoreSetting($pdo, 'mobile_money_tigo_url', $mobileMoneyTigoUrl);
        upsertStoreSetting($pdo, 'mobile_money_tigo_token', $mobileMoneyTigoToken);
        upsertStoreSetting($pdo, 'mobile_money_airtel_url', $mobileMoneyAirtelUrl);
        upsertStoreSetting($pdo, 'mobile_money_airtel_token', $mobileMoneyAirtelToken);

        $locationsRepo = new LocationsRepository($pdo);
        $addedLocations = 0;
        $skippedLocations = 0;
        $max = max(count($locationNames), count($locationAddresses), count($locationCities), count($locationPhones));

        for ($i = 0; $i < $max; $i++) {
            $name = trim((string) ($locationNames[$i] ?? ''));
            $address = trim((string) ($locationAddresses[$i] ?? ''));
            $city = trim((string) ($locationCities[$i] ?? $defaultCity));
            $phone = trim((string) ($locationPhones[$i] ?? ''));

            if ($name === '' && $address === '' && $city === '' && $phone === '') {
                continue;
            }

            if ($name === '' || $address === '') {
                $skippedLocations++;
                continue;
            }

            if ($city === '') {
                $city = $defaultCity;
            }

            if (locationAlreadyExists($pdo, $name, $address, $city)) {
                $skippedLocations++;
                continue;
            }

            $locationsRepo->createLocation([
                'name' => $name,
                'address' => $address,
                'city' => $city,
                'phone' => $phone,
                'status' => 'Active',
            ]);
            $addedLocations++;
        }

        $pdo->commit();

        $message = 'Store configuration saved. Starting amount set to Tsh ' . number_format($startingAmount, 0, '.', ',') . '.';
        if ($addedLocations > 0 || $skippedLocations > 0) {
            $message .= ' Locations: ' . $addedLocations . ' added';
            if ($skippedLocations > 0) {
                $message .= ', ' . $skippedLocations . ' skipped';
            }
            $message .= '.';
        }

        return ['type' => 'success', 'message' => $message];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[POS Settings] ' . $exception->getMessage());
        return [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Could not save settings: ' . $exception->getMessage()
                : 'Could not save store configuration. Please try again.',
        ];
    }
}

function getConfiguredDenominations(array $storeSettings): array
{
    $default = [50, 100, 200, 500, 1000, 2000, 5000, 10000];
    $raw = (string) ($storeSettings['cash_denominations'] ?? '');
    if ($raw === '') {
        return $default;
    }

    $values = [];
    foreach (explode(',', $raw) as $part) {
        $num = (int) trim($part);
        if ($num > 0) {
            $values[$num] = true;
        }
    }

    $list = array_keys($values);
    sort($list);

    return count($list) > 0 ? $list : $default;
}

function getAvailableCheckoutPaymentOptions(array $storeSettings): array
{
    $options = [
        ['key' => 'cash', 'label' => 'Cash', 'type' => 'cash'],
        ['key' => 'card', 'label' => 'Card', 'type' => 'card'],
        ['key' => 'bank_transfer', 'label' => 'Bank Transfer', 'type' => 'bank'],
    ];

    $mobileMode = strtolower(trim((string) ($storeSettings['mobile_money_mode'] ?? 'mock')));
    $supportsAllMobileInMock = $mobileMode !== 'live';

    $providers = [
        'mpesa' => [
            'label' => 'M-Pesa',
            'url' => trim((string) ($storeSettings['mobile_money_mpesa_url'] ?? '')),
            'token' => trim((string) ($storeSettings['mobile_money_mpesa_token'] ?? '')),
        ],
        'airtel_money' => [
            'label' => 'Airtel Money',
            'url' => trim((string) ($storeSettings['mobile_money_airtel_url'] ?? '')),
            'token' => trim((string) ($storeSettings['mobile_money_airtel_token'] ?? '')),
        ],
        'tigo_pesa' => [
            'label' => 'Tigo Pesa',
            'url' => trim((string) ($storeSettings['mobile_money_tigo_url'] ?? '')),
            'token' => trim((string) ($storeSettings['mobile_money_tigo_token'] ?? '')),
        ],
    ];

    foreach ($providers as $key => $provider) {
        $isAvailable = $supportsAllMobileInMock || ($provider['url'] !== '' && $provider['token'] !== '');
        if (!$isAvailable) {
            continue;
        }

        $options[] = [
            'key' => $key,
            'label' => $provider['label'],
            'type' => 'mobile',
            'provider' => $key,
        ];
    }

    return $options;
}

function handleEntityCreate(PDO $pdo, string $currentPage, string $userName, string $userRole): array
{
    if (!hasValidCsrfToken()) {
        return [
            'type' => 'error',
            'message' => 'Request validation failed. Refresh the page and try again.',
        ];
    }

    $entity = (string) ($_POST['entity'] ?? '');

    if (!canManageEntityRequest($entity, $userRole)) {
        logSecurityAuditEvent('entity_create_denied', 'denied', '', ['role' => $userRole, 'entity' => $entity], $pdo);
        return [
            'type' => 'error',
            'message' => 'You are not allowed to perform this action.',
        ];
    }

    try {
        switch ($entity) {
            case 'sale':
                $selectedGateway = strtolower(trim((string) ($_POST['payment_gateway'] ?? 'cash')));
                $allowedGateways = ['cash', 'card', 'bank_transfer', 'mpesa', 'airtel_money', 'tigo_pesa', 'pay_later'];
                if (!in_array($selectedGateway, $allowedGateways, true)) {
                    throw new RuntimeException('Unsupported payment gateway selected.');
                }

                $isCreditSale = $selectedGateway === 'pay_later' || (int) ($_POST['is_credit_sale'] ?? 0) === 1;
                $creditNote = trim((string) ($_POST['credit_note'] ?? ''));
                $creditDueDateInput = trim((string) ($_POST['credit_due_date'] ?? ''));
                $creditDueDate = $creditDueDateInput !== ''
                    ? $creditDueDateInput
                    : date('Y-m-d', strtotime('+30 days'));

                $customerId = (int) ($_POST['customer_id'] ?? 0);
                $productId = (int) ($_POST['product_id'] ?? 0);
                $quantity = (int) ($_POST['quantity'] ?? 0);
                $amount = (float) ($_POST['amount'] ?? 0);
                $discountAmount = max(0, (float) ($_POST['discount_amount'] ?? 0));

                $gatewayToPaymentMethod = [
                    'cash' => 'Cash',
                    'card' => 'Card',
                    'bank_transfer' => 'Bank Transfer',
                    'mpesa' => 'Mobile Money',
                    'airtel_money' => 'Mobile Money',
                    'tigo_pesa' => 'Mobile Money',
                    'pay_later' => 'Cash',
                ];

                $paymentMethod = $gatewayToPaymentMethod[$selectedGateway] ?? '';
                if ($paymentMethod === '') {
                    throw new RuntimeException('Payment method is invalid.');
                }

                $mobileProvider = (string) ($_POST['mobile_money_provider'] ?? '');
                if ($paymentMethod === 'Mobile Money' && $mobileProvider === '' && isset($gatewayToPaymentMethod[$selectedGateway])) {
                    $mobileProvider = $selectedGateway;
                }

                if ($paymentMethod === 'Mobile Money' && !in_array($mobileProvider, ['mpesa', 'airtel_money', 'tigo_pesa'], true)) {
                    throw new RuntimeException('Mobile money provider is invalid.');
                }

                $cartItemsInput = [];
                $cartQuantityByProduct = [];
                $cartJson = trim((string) ($_POST['cart_json'] ?? ''));
                if ($cartJson !== '') {
                    $decoded = json_decode($cartJson, true);
                    if (!is_array($decoded)) {
                        throw new RuntimeException('Invalid checkout cart payload.');
                    }

                    foreach ($decoded as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        $rowProductId = (int) ($row['product_id'] ?? 0);
                        $rowQuantity = (int) ($row['quantity'] ?? 0);
                        if ($rowProductId <= 0 || $rowQuantity <= 0) {
                            continue;
                        }

                        if ($rowQuantity > MAX_SALE_LINE_QTY) {
                            throw new RuntimeException('Item quantity exceeds the allowed limit per line.');
                        }

                        $cartQuantityByProduct[$rowProductId] = (int) ($cartQuantityByProduct[$rowProductId] ?? 0) + $rowQuantity;
                    }
                }

                if (count($cartQuantityByProduct) === 0 && $productId > 0 && $quantity > 0) {
                    if ($quantity > MAX_SALE_LINE_QTY) {
                        throw new RuntimeException('Item quantity exceeds the allowed limit per line.');
                    }

                    $cartQuantityByProduct[$productId] = (int) ($cartQuantityByProduct[$productId] ?? 0) + $quantity;
                }

                foreach ($cartQuantityByProduct as $lineProductId => $lineQuantity) {
                    if ($lineQuantity <= 0) {
                        continue;
                    }

                    if ($lineQuantity > MAX_SALE_LINE_QTY) {
                        throw new RuntimeException('Combined quantity for one product is too high.');
                    }

                    $cartItemsInput[] = [
                        'product_id' => (int) $lineProductId,
                        'quantity' => (int) $lineQuantity,
                    ];
                }

                if ($customerId <= 0) {
                    throw new RuntimeException('Customer is required for a sale.');
                }
                if (count($cartItemsInput) === 0) {
                    throw new RuntimeException('Select at least one product for checkout.');
                }
                if (count($cartItemsInput) > MAX_SALE_ITEMS) {
                    throw new RuntimeException('Checkout has too many items. Please split into smaller sales.');
                }

                $mobileResult = null;
                $receiptItems = [];
                $subtotalAmount = 0.0;
                $paymentLabel = $paymentMethod;

                $pdo->beginTransaction();
                try {
                    $inventoryRepo = new InventoryRepository($pdo);

                    foreach ($cartItemsInput as $item) {
                        $lineProductId = (int) $item['product_id'];
                        $lineQuantity = (int) $item['quantity'];

                        $product = $inventoryRepo->getProduct($lineProductId);
                        if (!is_array($product)) {
                            throw new RuntimeException('One or more selected products were not found.');
                        }

                        $unitPrice = isset($product['unit_price'])
                            ? (float) $product['unit_price']
                            : (float) ($product['price'] ?? 0);
                        $lineTotal = $unitPrice * $lineQuantity;

                        $receiptItems[] = [
                            'product_id' => $lineProductId,
                            'name' => (string) ($product['name'] ?? $product['product_name'] ?? 'Product'),
                            'quantity' => $lineQuantity,
                            'unit_price' => $unitPrice,
                            'line_total' => $lineTotal,
                        ];

                        $subtotalAmount += $lineTotal;
                    }

                    if ($subtotalAmount <= 0) {
                        throw new RuntimeException('Checkout total must be greater than zero.');
                    }

                    if ($discountAmount > $subtotalAmount) {
                        $discountAmount = $subtotalAmount;
                    }

                    $amount = $subtotalAmount - $discountAmount;

                    if ($paymentMethod === 'Mobile Money' && !$isCreditSale) {
                        $gateway = new MobileMoneyGateway($pdo);
                        $mobileResult = $gateway->initiate([
                            'provider' => $mobileProvider,
                            'phone' => (string) ($_POST['mobile_money_phone'] ?? ''),
                            'amount' => $amount,
                            'currency' => 'TZS',
                            'reference' => (string) ($_POST['mobile_money_reference'] ?? ''),
                        ]);

                        if (!($mobileResult['success'] ?? false)) {
                            throw new RuntimeException((string) ($mobileResult['message'] ?? 'Mobile money payment failed.'));
                        }

                        $paymentLabel = (string) ($mobileResult['provider_label'] ?? 'Mobile Money');
                    } elseif ($isCreditSale) {
                        $paymentLabel = 'Pay Later';
                    }

                    foreach ($receiptItems as $item) {
                        $inventoryRepo->deductStock((int) $item['product_id'], (int) $item['quantity']);
                    }

                    $salesRepo = new SalesRepository($pdo);
                    $saleId = $salesRepo->createSale([
                        'customer_id' => $customerId,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                    ]);

                    if ($isCreditSale) {
                        $customerCreditRepo = new CustomerCreditRepository($pdo);
                        $customerCreditRepo->createCreditForSale(
                            $saleId,
                            $customerId,
                            $amount,
                            $creditNote,
                            $creditDueDate
                        );
                    }

                    $createdSale = $salesRepo->getSale($saleId);

                    if (
                        is_array($mobileResult)
                        && isset($mobileResult['transaction_id'])
                        && isset($gateway)
                    ) {
                        $gateway->attachSaleId((int) $mobileResult['transaction_id'], $saleId);
                    }

                    $_SESSION['flash_receipt'] = [
                        'transaction_no' => (string) ($createdSale['transaction_no'] ?? ''),
                        'customer_name' => (string) ($createdSale['customer_name'] ?? 'Walk-in Customer'),
                        'cashier_name' => $userName,
                        'created_at' => (string) ($createdSale['created_at'] ?? date('Y-m-d H:i:s')),
                        'items' => array_map(static fn(array $line): array => [
                            'name' => (string) ($line['name'] ?? ''),
                            'quantity' => (int) ($line['quantity'] ?? 0),
                            'unit_price' => (float) ($line['unit_price'] ?? 0),
                            'line_total' => (float) ($line['line_total'] ?? 0),
                        ], $receiptItems),
                        'subtotal' => $subtotalAmount,
                        'tax' => 0,
                        'discount' => $discountAmount,
                        'total' => $amount,
                        'payment_method' => $paymentLabel,
                    ];

                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }

                $totalQty = array_reduce(
                    $receiptItems,
                    static fn(int $carry, array $line): int => $carry + (int) ($line['quantity'] ?? 0),
                    0
                );

                $message = 'Sale created successfully. Stock deducted for ' . count($receiptItems) . ' items (qty ' . $totalQty . ').';
                if (is_array($mobileResult)) {
                    $message .= ' ' . (string) ($mobileResult['message'] ?? '');
                    if (!empty($mobileResult['external_reference'])) {
                        $message .= ' Ref: ' . (string) $mobileResult['external_reference'] . '.';
                    }
                }

                if ($isCreditSale) {
                    $message .= ' Customer debt recorded as pay later.';
                }

                return ['type' => 'success', 'message' => trim($message)];

            case 'customer_payment':
                if ($currentPage !== 'customers') {
                    throw new RuntimeException('Customer payment updates are only allowed from the customers page.');
                }

                $creditId = (int) ($_POST['credit_id'] ?? 0);
                $paymentAmount = (float) ($_POST['payment_amount'] ?? 0);
                $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'Cash'));
                $paymentReference = trim((string) ($_POST['payment_reference'] ?? ''));

                $creditRepo = new CustomerCreditRepository($pdo);
                $paymentResult = $creditRepo->recordPayment(
                    $creditId,
                    $paymentAmount,
                    $paymentMethod,
                    $paymentReference
                );

                return [
                    'type' => 'success',
                    'message' => sprintf(
                        'Payment recorded. Remaining debt: Tsh %s (%s).',
                        moneyFormat((float) ($paymentResult['new_outstanding_amount'] ?? 0)),
                        (string) ($paymentResult['status'] ?? 'Updated')
                    ),
                ];

            case 'product':
                (new InventoryRepository($pdo))->createProduct([
                    'name' => (string) ($_POST['name'] ?? ''),
                    'sku' => (string) ($_POST['sku'] ?? ''),
                    'category' => (string) ($_POST['category'] ?? ''),
                    'unit_price' => (float) ($_POST['unit_price'] ?? 0),
                    'stock_qty' => (int) ($_POST['stock_qty'] ?? 0),
                    'reorder_level' => (int) ($_POST['reorder_level'] ?? 5),
                ]);
                return ['type' => 'success', 'message' => 'Product created successfully.'];

            case 'customer':
                (new CustomerRepository($pdo))->createCustomer([
                    'name' => (string) ($_POST['name'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Customer created successfully.'];

            case 'supplier':
                (new SuppliersRepository($pdo))->createSupplier([
                    'name' => (string) ($_POST['name'] ?? ''),
                    'contact_person' => (string) ($_POST['contact_person'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'address' => (string) ($_POST['address'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Supplier created successfully.'];

            case 'employee':
                (new EmployeesRepository($pdo))->createEmployee([
                    'name' => (string) ($_POST['name'] ?? ''),
                    'position' => (string) ($_POST['position'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'salary' => (float) ($_POST['salary'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Employee created successfully.'];

            case 'user':
                if (strtolower(trim($userRole)) !== 'admin') {
                    throw new RuntimeException('Only administrators can create users.');
                }

                ensureUserAccountManagementSchema($pdo);

                $name = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['password'] ?? '');
                $role = normalizeUserRoleInput((string) ($_POST['role'] ?? ''));

                if ($name === '' || $email === '' || $password === '' || $role === null) {
                    throw new RuntimeException('Name, email, password, and role are required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please provide a valid user email address.');
                }
                if (strlen($password) < 8) {
                    throw new RuntimeException('Password must be at least 8 characters long.');
                }

                $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $existsStmt->execute([':email' => $email]);
                if ($existsStmt->fetch()) {
                    throw new RuntimeException('A user with this email already exists.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    throw new RuntimeException('Could not secure the user password.');
                }

                $createStmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, is_active)
                     VALUES (:name, :email, :password, :role, 1)'
                );
                $createStmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password' => $passwordHash,
                    ':role' => $role,
                ]);

                return ['type' => 'success', 'message' => 'User created and role assigned successfully.'];

            case 'expense':
                (new ExpensesRepository($pdo))->createExpense([
                    'description' => (string) ($_POST['description'] ?? ''),
                    'category' => (string) ($_POST['category'] ?? ''),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Expense saved successfully.'];

            case 'invoice':
                (new InvoicesRepository($pdo))->createInvoice([
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                    'status' => (string) ($_POST['status'] ?? 'Pending'),
                ]);
                return ['type' => 'success', 'message' => 'Invoice created successfully.'];

            case 'delivery':
                (new DeliveriesRepository($pdo))->createDelivery([
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Delivery created successfully.'];

            case 'receiving':
                $itemsPayload = json_decode((string) ($_POST['items_json'] ?? '[]'), true);
                $receivingItems = is_array($itemsPayload) ? $itemsPayload : [];
                (new ReceivingRepository($pdo))->createReceiving([
                    'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
                    'purchase_order_id' => (int) ($_POST['purchase_order_id'] ?? 0),
                    'status' => (string) ($_POST['status'] ?? 'Pending'),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                    'items' => $receivingItems,
                ]);
                return ['type' => 'success', 'message' => 'Receiving record created successfully.'];

            case 'quotation':
                (new QuotationsRepository($pdo))->createQuotation([
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Quotation created successfully.'];

            case 'purchase_order':
                $purchaseOrderItemsPayload = json_decode((string) ($_POST['items_json'] ?? '[]'), true);
                $purchaseOrderItems = is_array($purchaseOrderItemsPayload) ? $purchaseOrderItemsPayload : [];
                (new PurchaseOrdersRepository($pdo))->createOrder([
                    'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                    'expected_delivery_date' => (string) ($_POST['expected_delivery_date'] ?? ''),
                    'notes' => (string) ($_POST['notes'] ?? ''),
                    'items' => $purchaseOrderItems,
                ]);
                return ['type' => 'success', 'message' => 'Purchase order created successfully.'];

            case 'return':
                (new ReturnsRepository($pdo))->createReturn([
                    'product_id' => (int) ($_POST['product_id'] ?? 0),
                    'quantity' => (int) ($_POST['quantity'] ?? 0),
                    'reason' => (string) ($_POST['reason'] ?? ''),
                    'is_expired' => ($_POST['is_expired'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Return created successfully.'];

            case 'appointment':
                (new AppointmentsRepository($pdo))->createAppointment([
                    'title' => (string) ($_POST['title'] ?? ''),
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'appointment_date' => (string) ($_POST['appointment_date'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Appointment created successfully.'];

            case 'appointment_delete':
                if ($currentPage !== 'appointments') {
                    throw new RuntimeException('Appointment deletion is only allowed from the appointments page.');
                }

                $appointmentId = (int) ($_POST['id'] ?? 0);
                if ($appointmentId <= 0) {
                    throw new RuntimeException('Invalid appointment ID.');
                }

                $deleted = (new AppointmentsRepository($pdo))->deleteAppointment($appointmentId);
                if (!$deleted) {
                    throw new RuntimeException('Could not delete appointment.');
                }

                return ['type' => 'success', 'message' => 'Appointment deleted successfully.'];

            case 'location':
                (new LocationsRepository($pdo))->createLocation([
                    'name' => (string) ($_POST['name'] ?? ''),
                    'address' => (string) ($_POST['address'] ?? ''),
                    'city' => (string) ($_POST['city'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Location created successfully.'];

            case 'message':
                (new MessagesRepository($pdo))->createMessage([
                    'sender' => $userName,
                    'recipient' => (string) ($_POST['recipient'] ?? ''),
                    'subject' => (string) ($_POST['subject'] ?? ''),
                    'message' => (string) ($_POST['message'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Message sent successfully.'];
        }

        return [
            'type' => 'warning',
            'message' => 'Unknown action requested. No changes were made.',
        ];
    } catch (Throwable $exception) {
        error_log('[POS Create] ' . $exception->getMessage());

        $safeMessage = 'Could not save the record. Please check your input and try again.';
        if (!($exception instanceof PDOException)) {
            $candidate = trim((string) $exception->getMessage());
            if ($candidate !== '') {
                $safeMessage = $candidate;
            }
        }

        return [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Save failed: ' . $exception->getMessage()
                : $safeMessage,
        ];
    }
}

function handleEntityUpdate(PDO $pdo, string $currentPage, string $userRole): array
{
    if (!hasValidCsrfToken()) {
        return [
            'type' => 'error',
            'message' => 'Request validation failed. Refresh the page and try again.',
        ];
    }

    $entity = (string) ($_POST['entity'] ?? '');

    if (!canManageEntityRequest($entity, $userRole)) {
        logSecurityAuditEvent('entity_update_denied', 'denied', '', ['role' => $userRole, 'entity' => $entity], $pdo);
        return [
            'type' => 'error',
            'message' => 'You are not allowed to perform this update.',
        ];
    }

    try {
        switch ($entity) {
            case 'product':
                if ($currentPage !== 'inventory') {
                    throw new RuntimeException('Product updates are only allowed from the inventory page.');
                }

                $productId = (int) ($_POST['id'] ?? 0);
                if ($productId <= 0) {
                    throw new RuntimeException('Invalid product ID.');
                }

                (new InventoryRepository($pdo))->updateProduct($productId, [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'sku' => (string) ($_POST['sku'] ?? ''),
                    'category' => (string) ($_POST['category'] ?? ''),
                    'unit_price' => (float) ($_POST['unit_price'] ?? 0),
                    'stock_qty' => (int) ($_POST['stock_qty'] ?? 0),
                    'reorder_level' => (int) ($_POST['reorder_level'] ?? 5),
                ]);

                return ['type' => 'success', 'message' => 'Product updated successfully.'];

            case 'customer':
                if ($currentPage !== 'customers') {
                    throw new RuntimeException('Customer updates are only allowed from the customers page.');
                }

                $customerId = (int) ($_POST['id'] ?? 0);
                if ($customerId <= 0) {
                    throw new RuntimeException('Invalid customer ID.');
                }

                (new CustomerRepository($pdo))->updateCustomer($customerId, [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                ]);

                return ['type' => 'success', 'message' => 'Customer updated successfully.'];

            case 'customer_delete':
                if ($currentPage !== 'customers') {
                    throw new RuntimeException('Customer deletions are only allowed from the customers page.');
                }

                $customerId = (int) ($_POST['id'] ?? 0);
                if ($customerId <= 0) {
                    throw new RuntimeException('Invalid customer ID.');
                }

                $customerRepo = new CustomerRepository($pdo);
                $customer = $customerRepo->getCustomer($customerId);
                if ($customer === null) {
                    throw new RuntimeException('Customer not found.');
                }

                $customerName = strtolower(trim((string) ($customer['name'] ?? '')));
                if ($customerName === 'walk-in customer') {
                    throw new RuntimeException('Walk-in Customer cannot be deleted.');
                }

                $deleted = $customerRepo->deleteCustomer($customerId);
                if (!$deleted) {
                    throw new RuntimeException('Could not delete customer.');
                }

                return ['type' => 'success', 'message' => 'Customer deleted successfully.'];

            case 'supplier':
                if ($currentPage !== 'suppliers') {
                    throw new RuntimeException('Supplier updates are only allowed from the suppliers page.');
                }

                $supplierId = (int) ($_POST['id'] ?? 0);
                if ($supplierId <= 0) {
                    throw new RuntimeException('Invalid supplier ID.');
                }

                (new SuppliersRepository($pdo))->updateSupplier($supplierId, [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'contact_person' => (string) ($_POST['contact_person'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'address' => (string) ($_POST['address'] ?? ''),
                    'status' => (string) ($_POST['status'] ?? 'Active'),
                ]);

                return ['type' => 'success', 'message' => 'Supplier updated successfully.'];

            case 'supplier_delete':
                if ($currentPage !== 'suppliers') {
                    throw new RuntimeException('Supplier deletions are only allowed from the suppliers page.');
                }

                $supplierId = (int) ($_POST['id'] ?? 0);
                if ($supplierId <= 0) {
                    throw new RuntimeException('Invalid supplier ID.');
                }

                $deleted = (new SuppliersRepository($pdo))->deleteSupplier($supplierId);
                if (!$deleted) {
                    throw new RuntimeException('Could not delete supplier.');
                }

                return ['type' => 'success', 'message' => 'Supplier deleted successfully.'];

            case 'user':
                if ($currentPage !== 'users') {
                    throw new RuntimeException('User role updates are only allowed from the users page.');
                }
                if (strtolower(trim($userRole)) !== 'admin') {
                    throw new RuntimeException('Only administrators can update user privileges.');
                }

                ensureUserAccountManagementSchema($pdo);

                $targetUserId = (int) ($_POST['id'] ?? 0);
                $newRole = normalizeUserRoleInput((string) ($_POST['role'] ?? ''));
                if ($targetUserId <= 0 || $newRole === null) {
                    throw new RuntimeException('Invalid user update request.');
                }

                $authUser = currentUser();
                $currentUserId = (int) (($authUser['id'] ?? 0) ?: 0);
                if ($currentUserId > 0 && $currentUserId === $targetUserId && $newRole !== 'Admin') {
                    throw new RuntimeException('You cannot remove your own admin privilege.');
                }

                $updateStmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                $updateStmt->execute([
                    ':role' => $newRole,
                    ':id' => $targetUserId,
                ]);

                if ($currentUserId > 0 && $currentUserId === $targetUserId && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                    $_SESSION['user']['role'] = $newRole;
                }

                return ['type' => 'success', 'message' => 'User privileges updated successfully.'];

            case 'user_status':
                if ($currentPage !== 'users') {
                    throw new RuntimeException('User status updates are only allowed from the users page.');
                }
                if (strtolower(trim($userRole)) !== 'admin') {
                    throw new RuntimeException('Only administrators can update user status.');
                }

                ensureUserAccountManagementSchema($pdo);

                $targetUserId = (int) ($_POST['id'] ?? 0);
                $statusRaw = strtolower(trim((string) ($_POST['status'] ?? 'active')));
                $isActive = $statusRaw === 'active' ? 1 : 0;
                if ($targetUserId <= 0) {
                    throw new RuntimeException('Invalid user status update request.');
                }

                $authUser = currentUser();
                $currentUserId = (int) (($authUser['id'] ?? 0) ?: 0);
                if ($currentUserId > 0 && $currentUserId === $targetUserId && $isActive === 0) {
                    throw new RuntimeException('You cannot deactivate your own account.');
                }

                $statusStmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
                $statusStmt->execute([
                    ':is_active' => $isActive,
                    ':id' => $targetUserId,
                ]);

                return [
                    'type' => 'success',
                    'message' => $isActive === 1
                        ? 'User account activated successfully.'
                        : 'User account deactivated successfully.',
                ];

            case 'user_password_reset':
                if ($currentPage !== 'users') {
                    throw new RuntimeException('Password resets are only allowed from the users page.');
                }
                if (strtolower(trim($userRole)) !== 'admin') {
                    throw new RuntimeException('Only administrators can reset user passwords.');
                }

                $targetUserId = (int) ($_POST['id'] ?? 0);
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if ($targetUserId <= 0) {
                    throw new RuntimeException('Invalid user password reset request.');
                }
                if (strlen($newPassword) < 8) {
                    throw new RuntimeException('New password must be at least 8 characters long.');
                }
                if (!hash_equals($newPassword, $confirmPassword)) {
                    throw new RuntimeException('Password confirmation does not match.');
                }

                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($newHash === false) {
                    throw new RuntimeException('Could not secure the new password.');
                }

                $resetStmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $resetStmt->execute([
                    ':password' => $newHash,
                    ':id' => $targetUserId,
                ]);

                return ['type' => 'success', 'message' => 'User password reset successfully.'];

            case 'user_permission_override':
                if ($currentPage !== 'users') {
                    throw new RuntimeException('Permission overrides are only allowed from the users page.');
                }
                if (strtolower(trim($userRole)) !== 'admin') {
                    throw new RuntimeException('Only administrators can assign custom permissions.');
                }

                ensureUserAccountManagementSchema($pdo);

                $targetUserId = (int) ($_POST['id'] ?? 0);
                if ($targetUserId <= 0) {
                    throw new RuntimeException('Invalid permission override request.');
                }

                $allowedPageKeys = array_keys((new PageController())->getPages());
                $requestedModes = $_POST['permission_mode'] ?? [];
                if (!is_array($requestedModes)) {
                    throw new RuntimeException('Invalid permission override payload.');
                }

                $sessionOverrideUpdates = [];
                foreach ($requestedModes as $rawPageKey => $rawMode) {
                    $pageKey = strtolower(trim((string) $rawPageKey));
                    $mode = normalizePermissionModeInput((string) $rawMode);
                    if ($pageKey === '' || $mode === null) {
                        continue;
                    }
                    if (!in_array($pageKey, $allowedPageKeys, true)) {
                        continue;
                    }

                    if ($mode === 'default') {
                        $deleteStmt = $pdo->prepare('DELETE FROM user_page_permissions WHERE user_id = :user_id AND page_key = :page_key');
                        $deleteStmt->execute([
                            ':user_id' => $targetUserId,
                            ':page_key' => $pageKey,
                        ]);
                        $sessionOverrideUpdates[$pageKey] = null;
                        continue;
                    }

                    $isAllowed = $mode === 'allow' ? 1 : 0;
                    $upsertStmt = $pdo->prepare(
                        'INSERT INTO user_page_permissions (user_id, page_key, is_allowed)
                         VALUES (:user_id, :page_key, :is_allowed)
                         ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)'
                    );
                    $upsertStmt->execute([
                        ':user_id' => $targetUserId,
                        ':page_key' => $pageKey,
                        ':is_allowed' => $isAllowed,
                    ]);
                    $sessionOverrideUpdates[$pageKey] = ($isAllowed === 1);
                }

                $authUser = currentUser();
                $currentUserId = (int) (($authUser['id'] ?? 0) ?: 0);
                if ($currentUserId > 0 && $currentUserId === $targetUserId && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                    if (!isset($_SESSION['user']['permission_overrides']) || !is_array($_SESSION['user']['permission_overrides'])) {
                        $_SESSION['user']['permission_overrides'] = [];
                    }

                    foreach ($sessionOverrideUpdates as $pageKey => $overrideValue) {
                        if ($overrideValue === null) {
                            unset($_SESSION['user']['permission_overrides'][$pageKey]);
                            continue;
                        }
                        $_SESSION['user']['permission_overrides'][$pageKey] = (bool) $overrideValue;
                    }
                }

                return ['type' => 'success', 'message' => 'User permission override saved successfully.'];

            case 'appointment_status':
                if ($currentPage !== 'appointments') {
                    throw new RuntimeException('Appointment status updates are only allowed from the appointments page.');
                }

                $appointmentId = (int) ($_POST['id'] ?? 0);
                $requestedStatus = trim((string) ($_POST['status'] ?? ''));
                if ($appointmentId <= 0) {
                    throw new RuntimeException('Invalid appointment ID.');
                }

                $updated = (new AppointmentsRepository($pdo))->updateAppointmentStatus($appointmentId, $requestedStatus);
                if (!$updated) {
                    throw new RuntimeException('Could not update appointment status.');
                }

                return ['type' => 'success', 'message' => 'Appointment status updated successfully.'];

            case 'receiving_status':
                if ($currentPage !== 'receiving') {
                    throw new RuntimeException('Receiving status updates are only allowed from the receiving page.');
                }

                $receivingId = (int) ($_POST['id'] ?? 0);
                $requestedStatus = trim((string) ($_POST['status'] ?? ''));
                if ($receivingId <= 0) {
                    throw new RuntimeException('Invalid receiving ID.');
                }

                $updated = (new ReceivingRepository($pdo))->updateReceivingStatus($receivingId, $requestedStatus);
                if (!$updated) {
                    throw new RuntimeException('Could not update receiving status.');
                }

                $message = 'Receiving status updated successfully.';
                if (strtolower($requestedStatus) === 'completed') {
                    $poLookupStmt = $pdo->prepare(
                        'SELECT po.id, po.po_no, po.status
                         FROM receiving r
                         LEFT JOIN purchase_orders po ON po.id = r.purchase_order_id
                         WHERE r.id = :id
                         LIMIT 1'
                    );
                    $poLookupStmt->execute([':id' => $receivingId]);
                    $poRow = $poLookupStmt->fetch() ?: null;

                    $poId = (int) ($poRow['id'] ?? 0);
                    $poNo = trim((string) ($poRow['po_no'] ?? ''));
                    $poStatus = trim((string) ($poRow['status'] ?? ''));

                    if ($poId > 0 && strtolower($poStatus) === 'received') {
                        $poLabel = $poNo !== '' ? $poNo : ('PO #' . $poId);
                        $message = 'Receiving completed and linked purchase order ' . $poLabel . ' was marked as Received.';
                    }
                }

                return ['type' => 'success', 'message' => $message];

            case 'invoice_status':
                if ($currentPage !== 'invoices') {
                    throw new RuntimeException('Invoice status updates are only allowed from the invoices page.');
                }

                $invoiceId = (int) ($_POST['id'] ?? 0);
                $requestedStatus = trim((string) ($_POST['status'] ?? ''));
                if ($invoiceId <= 0) {
                    throw new RuntimeException('Invalid invoice ID.');
                }

                $updated = (new InvoicesRepository($pdo))->updateInvoiceStatus($invoiceId, $requestedStatus);
                if (!$updated) {
                    throw new RuntimeException('Could not update invoice status.');
                }

                return ['type' => 'success', 'message' => 'Invoice updated to ' . $requestedStatus . '.'];

            case 'delivery_status':
                if ($currentPage !== 'deliveries') {
                    throw new RuntimeException('Delivery status updates are only allowed from the deliveries page.');
                }

                $deliveryId = (int) ($_POST['id'] ?? 0);
                $requestedStatus = trim((string) ($_POST['status'] ?? ''));
                if ($deliveryId <= 0) {
                    throw new RuntimeException('Invalid delivery ID.');
                }

                $updated = (new DeliveriesRepository($pdo))->updateDeliveryStatus($deliveryId, $requestedStatus);
                if (!$updated) {
                    throw new RuntimeException('Delivery status can only follow Pending -> In Transit/Cancelled, or In Transit -> Delivered/Cancelled.');
                }

                return ['type' => 'success', 'message' => 'Delivery updated to ' . $requestedStatus . '.'];

            case 'quotation':
                if ($currentPage !== 'quotations') {
                    throw new RuntimeException('Quotation updates are only allowed from the quotations page.');
                }

                $normalizedRole = strtolower(trim($userRole));
                if (!in_array($normalizedRole, ['admin', 'manager'], true)) {
                    throw new RuntimeException('Only Admin or Manager can edit quotations.');
                }

                $quotationId = (int) ($_POST['id'] ?? 0);
                if ($quotationId <= 0) {
                    throw new RuntimeException('Invalid quotation ID.');
                }

                $updated = (new QuotationsRepository($pdo))->updateQuotation($quotationId, [
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);

                if (!$updated) {
                    throw new RuntimeException('No quotation changes were applied.');
                }

                return ['type' => 'success', 'message' => 'Quotation updated successfully.'];

            case 'quotation_status':
                if ($currentPage !== 'quotations') {
                    throw new RuntimeException('Quotation status updates are only allowed from the quotations page.');
                }

                $normalizedRole = strtolower(trim($userRole));
                if (!in_array($normalizedRole, ['admin', 'manager'], true)) {
                    throw new RuntimeException('Only Admin or Manager can update quotation status.');
                }

                $quotationId = (int) ($_POST['id'] ?? 0);
                $requestedStatus = trim((string) ($_POST['status'] ?? ''));
                if ($quotationId <= 0) {
                    throw new RuntimeException('Invalid quotation ID.');
                }

                $updated = (new QuotationsRepository($pdo))->updateQuotationStatus($quotationId, $requestedStatus);
                if (!$updated) {
                    throw new RuntimeException('Quotation status can only follow Pending -> Approved/Rejected/Expired, or Approved -> Expired.');
                }

                return ['type' => 'success', 'message' => 'Quotation updated to ' . $requestedStatus . '.'];

            case 'quotation_delete':
                if ($currentPage !== 'quotations') {
                    throw new RuntimeException('Quotation deletions are only allowed from the quotations page.');
                }

                $normalizedRole = strtolower(trim($userRole));
                if (!in_array($normalizedRole, ['admin', 'manager'], true)) {
                    throw new RuntimeException('Only Admin or Manager can delete quotations.');
                }

                $quotationId = (int) ($_POST['id'] ?? 0);
                if ($quotationId <= 0) {
                    throw new RuntimeException('Invalid quotation ID.');
                }

                $deleted = (new QuotationsRepository($pdo))->deleteQuotation($quotationId);
                if (!$deleted) {
                    throw new RuntimeException('Could not delete quotation.');
                }

                return ['type' => 'success', 'message' => 'Quotation deleted successfully.'];

            case 'return_status':
                if ($currentPage !== 'returns') {
                    throw new RuntimeException('Return status updates are only allowed from the returns page.');
                }

                $returnId = (int) ($_POST['id'] ?? 0);
                $requestedStatus = trim((string) ($_POST['status'] ?? ''));
                if ($returnId <= 0) {
                    throw new RuntimeException('Invalid return ID.');
                }

                $result = (new ReturnsRepository($pdo))->updateReturnStatus($returnId, $requestedStatus);
                if (!(bool) ($result['updated'] ?? false)) {
                    throw new RuntimeException('Return status can only be updated once while Pending.');
                }

                if (strtolower($requestedStatus) === 'approved') {
                    if ((bool) ($result['stock_applied'] ?? false)) {
                        return ['type' => 'success', 'message' => 'Return approved and stock added to inventory.'];
                    }

                    if ((bool) ($result['skipped_expired'] ?? false)) {
                        return ['type' => 'success', 'message' => 'Return approved. Stock was not added because item is expired.'];
                    }

                    return ['type' => 'success', 'message' => 'Return approved.'];
                }

                return ['type' => 'success', 'message' => 'Return rejected. Stock was not changed.'];

            case 'purchase_order_status':
                if ($currentPage !== 'purchase-orders') {
                    throw new RuntimeException('Purchase order status updates are only allowed from the purchase orders page.');
                }

                $purchaseOrderId = (int) ($_POST['id'] ?? 0);
                $requestedStatus = trim((string) ($_POST['status'] ?? ''));
                if ($purchaseOrderId <= 0) {
                    throw new RuntimeException('Invalid purchase order ID.');
                }

                $updated = (new PurchaseOrdersRepository($pdo))->updateOrderStatus($purchaseOrderId, $requestedStatus);
                if (!$updated) {
                    throw new RuntimeException('Purchase order status can only follow Pending -> Approved -> Received, or be Cancelled before Received.');
                }

                return ['type' => 'success', 'message' => 'Purchase order updated to ' . $requestedStatus . '.'];
        }

        return [
            'type' => 'warning',
            'message' => 'Unknown update action requested. No changes were made.',
        ];
    } catch (Throwable $exception) {
        error_log('[POS Update] ' . $exception->getMessage());

        $safeMessage = 'Could not update the record. Please check your input and try again.';
        if (!($exception instanceof PDOException)) {
            $candidate = trim((string) $exception->getMessage());
            if ($candidate !== '') {
                $safeMessage = $candidate;
            }
        }

        return [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Update failed: ' . $exception->getMessage()
                : $safeMessage,
        ];
    }
}

function normalizeImportHeader(string $header): string
{
    $header = str_replace("\xEF\xBB\xBF", '', $header);
    $header = strtolower(trim($header));
    return str_replace([' ', '-'], '_', $header);
}

function parseIntValue(string $value, ?int $default = null): ?int
{
    $value = trim($value);
    if ($value === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $parsed = (int) round((float) $value);
    return $parsed < 0 ? null : $parsed;
}

function parseFloatValue(string $value, ?float $default = null): ?float
{
    $value = trim(str_replace(',', '', $value));
    if ($value === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $parsed = (float) $value;
    return $parsed < 0 ? null : $parsed;
}

function generateImportSku(string $name, string $category, int $lineNumber): string
{
    $namePart = strtoupper(trim($name));
    $categoryPart = strtoupper(trim($category));

    $namePart = $namePart !== '' ? $namePart : 'ITEM';
    $raw = $namePart . ($categoryPart !== '' ? '-' . $categoryPart : '');
    $sanitized = preg_replace('/[^A-Z0-9\-]+/', '-', $raw);
    $sanitized = $sanitized !== null ? trim($sanitized, '-') : '';

    if ($sanitized === '') {
        return 'ITEM-' . $lineNumber;
    }

    $hash = strtoupper(substr(sha1($namePart . '|' . $categoryPart), 0, 8));
    $prefix = substr($sanitized, 0, 48);
    return $prefix . '-' . $hash;
}

function resolveProductImportHeaderMap(array $normalizedHeaders): array
{
    $headerMap = [];
    foreach ($normalizedHeaders as $index => $header) {
        $headerMap[$header] = $index;
    }

    $requiredStandard = ['name', 'sku', 'unit_price'];
    $hasStandardHeader = !array_diff($requiredStandard, $normalizedHeaders);
    if ($hasStandardHeader) {
        return [
            'hasHeaderRow' => true,
            'headerMap' => $headerMap,
            'skuOptional' => false,
            'unitPriceFallbackIndex' => null,
        ];
    }

    // Support common supplier sheet format: Items, Size, Cost, Selling, Unit.
    $hasItemsHeader = in_array('items', $normalizedHeaders, true) || in_array('item', $normalizedHeaders, true);
    $hasPriceHeader = in_array('selling', $normalizedHeaders, true) || in_array('unit_price', $normalizedHeaders, true) || in_array('cost', $normalizedHeaders, true) || in_array('price', $normalizedHeaders, true);
    $hasQtyHeader = in_array('unit', $normalizedHeaders, true) || in_array('qty', $normalizedHeaders, true) || in_array('stock_qty', $normalizedHeaders, true) || in_array('quantity', $normalizedHeaders, true);

    if ($hasItemsHeader && $hasPriceHeader && $hasQtyHeader) {
        $nameIndex = $headerMap['items'] ?? $headerMap['item'] ?? null;
        $categoryIndex = $headerMap['size'] ?? $headerMap['category'] ?? null;
        $priceIndex = $headerMap['selling'] ?? $headerMap['unit_price'] ?? $headerMap['price'] ?? $headerMap['cost'] ?? null;
        $fallbackPriceIndex = ($priceIndex === ($headerMap['cost'] ?? null))
            ? ($headerMap['selling'] ?? null)
            : ($headerMap['cost'] ?? null);
        $stockIndex = $headerMap['unit'] ?? $headerMap['qty'] ?? $headerMap['quantity'] ?? $headerMap['stock_qty'] ?? null;

        if ($nameIndex !== null && $priceIndex !== null && $stockIndex !== null) {
            return [
                'hasHeaderRow' => true,
                'headerMap' => [
                    'name' => $nameIndex,
                    'sku' => $headerMap['sku'] ?? -1,
                    'unit_price' => $priceIndex,
                    'stock_qty' => $stockIndex,
                    'reorder_level' => $headerMap['reorder_level'] ?? -1,
                    'category' => $categoryIndex ?? -1,
                ],
                'skuOptional' => true,
                'unitPriceFallbackIndex' => $fallbackPriceIndex,
            ];
        }
    }

    return [
        'hasHeaderRow' => false,
        'headerMap' => [
            'name' => 0,
            'sku' => 1,
            'unit_price' => 2,
            'stock_qty' => 3,
            'reorder_level' => 4,
            'category' => 5,
        ],
        'skuOptional' => false,
        'unitPriceFallbackIndex' => null,
    ];
}

function buildProductRowsFromCsv(string $csvFilePath): array
{
    $handle = fopen($csvFilePath, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to read the uploaded file.');
    }

    $firstRow = fgetcsv($handle);
    if (!$firstRow || count($firstRow) < 3) {
        fclose($handle);
        throw new RuntimeException('CSV is empty or missing required columns. Expected: name, sku, unit_price.');
    }

    $normalizedHeaders = array_map(static fn($h) => normalizeImportHeader((string) $h), $firstRow);
    $headerConfig = resolveProductImportHeaderMap($normalizedHeaders);
    $hasHeaderRow = (bool) $headerConfig['hasHeaderRow'];
    $headerMap = (array) $headerConfig['headerMap'];
    $skuOptional = (bool) $headerConfig['skuOptional'];
    $unitPriceFallbackIndex = $headerConfig['unitPriceFallbackIndex'];
    $rows = [];
    $errors = [];
    $skipped = 0;
    $lineNumber = 1;
    $maxRows = IMPORT_MAX_ROWS;
    $processedRows = 0;
    $seenSkus = [];

    if (!$hasHeaderRow) {
        $row = $firstRow;
        $lineNumber = 1;
        $name = trim((string) ($row[$headerMap['name']] ?? ''));
        $sku = trim((string) ($row[$headerMap['sku']] ?? ''));
        $category = trim((string) ($row[$headerMap['category']] ?? ''));

        if ($skuOptional && $sku === '') {
            $sku = generateImportSku($name, $category, $lineNumber);
        }

        if ($name !== '' || $sku !== '') {
            if ($name === '' || (!$skuOptional && $sku === '')) {
                $errors[] = 'Row 1: name and sku are required.';
            } elseif (isset($seenSkus[strtolower($sku)])) {
                $errors[] = 'Row 1: duplicate sku in file (' . $sku . ').';
            } else {
                $unitPrice = parseFloatValue((string) ($row[$headerMap['unit_price']] ?? ''), null);
                if ($unitPrice === null && is_int($unitPriceFallbackIndex) && $unitPriceFallbackIndex >= 0) {
                    $unitPrice = parseFloatValue((string) ($row[$unitPriceFallbackIndex] ?? ''), null);
                }
                $stockQty = parseIntValue((string) ($row[$headerMap['stock_qty']] ?? ''), 0);
                $reorderLevel = parseIntValue((string) ($row[$headerMap['reorder_level']] ?? ''), 5);

                if ($unitPrice === null) {
                    $errors[] = 'Row 1: unit_price must be a non-negative number.';
                } elseif ($stockQty === null) {
                    $errors[] = 'Row 1: stock_qty must be a non-negative number.';
                } elseif ($reorderLevel === null) {
                    $errors[] = 'Row 1: reorder_level must be a non-negative number.';
                }

                if (count($errors) > 0) {
                    // Skip invalid first data row when CSV has no header.
                    fclose($handle);
                    return [
                        'rows' => [],
                        'errors' => $errors,
                        'skipped' => $skipped,
                    ];
                }

                $rows[] = [
                    'name' => $name,
                    'sku' => $sku,
                    'unit_price' => $unitPrice,
                    'stock_qty' => $stockQty,
                    'reorder_level' => $reorderLevel,
                    'category' => $category,
                ];
                $seenSkus[strtolower($sku)] = true;
                $processedRows++;
            }
        }
    }

    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;
        if (!array_filter($row, static fn($cell) => trim((string) $cell) !== '')) {
            $skipped++;
            continue;
        }

        if ($processedRows >= $maxRows) {
            fclose($handle);
            throw new RuntimeException('CSV exceeds the maximum of ' . IMPORT_MAX_ROWS . ' data rows. Please split the file and try again.');
        }

        $name = trim((string) ($row[$headerMap['name']] ?? ''));
        $sku = trim((string) ($row[$headerMap['sku']] ?? ''));
        $category = trim((string) ($row[$headerMap['category']] ?? ''));

        if ($skuOptional && $sku === '') {
            $sku = generateImportSku($name, $category, $lineNumber);
        }

        if ($name === '' || (!$skuOptional && $sku === '')) {
            $errors[] = 'Row ' . $lineNumber . ': name and sku are required.';
            continue;
        }

        if (isset($seenSkus[strtolower($sku)])) {
            $errors[] = 'Row ' . $lineNumber . ': duplicate sku in file (' . $sku . ').';
            continue;
        }

        $unitPrice = parseFloatValue((string) ($row[$headerMap['unit_price']] ?? ''), null);
        if ($unitPrice === null && is_int($unitPriceFallbackIndex) && $unitPriceFallbackIndex >= 0) {
            $unitPrice = parseFloatValue((string) ($row[$unitPriceFallbackIndex] ?? ''), null);
        }
        if ($unitPrice === null) {
            $errors[] = 'Row ' . $lineNumber . ': unit_price must be a non-negative number.';
            continue;
        }

        $stockQty = parseIntValue((string) ($row[$headerMap['stock_qty']] ?? ''), 0);
        if ($stockQty === null) {
            $errors[] = 'Row ' . $lineNumber . ': stock_qty must be a non-negative number.';
            continue;
        }

        $reorderLevel = parseIntValue((string) ($row[$headerMap['reorder_level']] ?? ''), 5);
        if ($reorderLevel === null) {
            $errors[] = 'Row ' . $lineNumber . ': reorder_level must be a non-negative number.';
            continue;
        }

        $rows[] = [
            'name' => $name,
            'sku' => $sku,
            'unit_price' => $unitPrice,
            'stock_qty' => $stockQty,
            'reorder_level' => $reorderLevel,
            'category' => $category,
        ];

        $seenSkus[strtolower($sku)] = true;
        $processedRows++;
    }

    fclose($handle);

    return [
        'rows' => $rows,
        'errors' => $errors,
        'skipped' => $skipped,
    ];
}

function handleProductImport(InventoryRepository $inventoryRepo, string $userRole): array
{
    if (!canAccessPage('inventory', $userRole)) {
        try {
            $pdo = getDatabaseConnection();
            logSecurityAuditEvent('inventory_import_denied', 'denied', '', ['role' => $userRole], $pdo);
        } catch (Throwable $exception) {
            logSecurityAuditEvent('inventory_import_denied', 'denied', '', ['role' => $userRole]);
        }

        return [
            'type' => 'error',
            'message' => 'You are not allowed to import products.',
        ];
    }

    if (!hasValidCsrfToken()) {
        return [
            'type' => 'error',
            'message' => 'Request validation failed. Refresh the page and try importing again.',
        ];
    }

    if (!isset($_FILES['product_import_file'])) {
        return [
            'type' => 'error',
            'message' => 'No file uploaded. Choose an Excel CSV or XLSX file.',
        ];
    }

    $file = $_FILES['product_import_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [
            'type' => 'error',
            'message' => 'Upload failed. Please try again with a valid CSV or XLSX file.',
        ];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'type' => 'error',
            'message' => 'Invalid upload source. Please choose the file again.',
        ];
    }

    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > IMPORT_MAX_FILE_SIZE_BYTES) {
        return [
            'type' => 'error',
            'message' => 'File size must be greater than 0 and not exceed 25MB.',
        ];
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'xlsx'], true)) {
        return [
            'type' => 'error',
            'message' => 'Unsupported file type. Upload a CSV or XLSX file.',
        ];
    }

    $mimeType = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) ($finfo->file($tmpName) ?: '');
        unset($finfo);
    }

    $allowedMimeTypes = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true)) {
        return [
            'type' => 'error',
            'message' => 'Invalid file content type. Please upload a CSV or XLSX exported from Excel.',
        ];
    }

    try {
        $parsed = $extension === 'xlsx'
            ? buildProductRowsFromXlsx($tmpName)
            : buildProductRowsFromCsv($tmpName);

        if (count($parsed['rows']) === 0) {
            return [
                'type' => 'warning',
                'message' => 'No valid product rows found. Ensure file has columns: name, sku, unit_price, stock_qty, reorder_level (category optional).',
            ];
        }

        $result = $inventoryRepo->importProductsFromRows($parsed['rows']);
        $validRows = count($parsed['rows']);
        $invalidRows = count($parsed['errors']);
        $skippedRows = (int) ($parsed['skipped'] ?? 0);
        $createdRows = (int) ($result['created'] ?? 0);
        $updatedRows = (int) ($result['updated'] ?? 0);
        $excelTotalItems = $validRows + $invalidRows;
        $systemTotalItems = $inventoryRepo->getTotalCount();
        $message = sprintf(
            'Import complete. Excel total items: %d. System total items: %d.',
            $excelTotalItems,
            $systemTotalItems
        );

        if ($excelTotalItems !== $systemTotalItems) {
            $message .= sprintf(
                ' Breakdown: %d created, %d updated existing items, %d invalid rows, %d empty rows skipped.',
                $createdRows,
                $updatedRows,
                $invalidRows,
                $skippedRows
            );
        }

        return [
            'type' => 'success',
            'message' => $message,
        ];
    } catch (Throwable $exception) {
        error_log('[POS Import] ' . $exception->getMessage());
        $rawMessage = (string) $exception->getMessage();
        $safeMessage = preg_replace('/[\r\n\t]+/', ' ', $rawMessage);
        $safeMessage = $safeMessage !== null ? trim($safeMessage) : '';
        if (strlen($safeMessage) > 180) {
            $safeMessage = substr($safeMessage, 0, 180) . '...';
        }

        $friendlyMessage = 'Import failed while processing the file. Please review your CSV/XLSX and try again.';
        if ($safeMessage !== '') {
            $friendlyMessage .= ' Reason: ' . $safeMessage;
        }

        return [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Import failed: ' . $exception->getMessage()
                : $friendlyMessage,
        ];
    }
}

function buildProductRowsFromXlsx(string $xlsxFilePath): array
{
    $tableRows = readXlsxRows($xlsxFilePath, IMPORT_MAX_ROWS);
    if (!$tableRows || count($tableRows) < 1) {
        throw new RuntimeException('XLSX is empty or missing required columns. Expected: name, sku, unit_price.');
    }

    $firstRow = array_map(static fn($v) => (string) $v, $tableRows[0]);
    $normalizedHeaders = array_map(static fn($h) => normalizeImportHeader((string) $h), $firstRow);
    $headerConfig = resolveProductImportHeaderMap($normalizedHeaders);
    $hasHeaderRow = (bool) $headerConfig['hasHeaderRow'];
    $headerMap = (array) $headerConfig['headerMap'];
    $skuOptional = (bool) $headerConfig['skuOptional'];
    $unitPriceFallbackIndex = $headerConfig['unitPriceFallbackIndex'];
    $rows = [];
    $errors = [];
    $skipped = 0;
    $seenSkus = [];

    $startIndex = $hasHeaderRow ? 1 : 0;
    for ($i = $startIndex; $i < count($tableRows); $i++) {
        $row = array_map(static fn($v) => (string) $v, (array) $tableRows[$i]);
        $lineNumber = $i + 1;

        if (!array_filter($row, static fn($cell) => trim((string) $cell) !== '')) {
            $skipped++;
            continue;
        }

        $name = trim((string) ($row[$headerMap['name']] ?? ''));
        $sku = trim((string) ($row[$headerMap['sku']] ?? ''));
        $category = trim((string) ($row[$headerMap['category']] ?? ''));

        if ($skuOptional && $sku === '') {
            $sku = generateImportSku($name, $category, $lineNumber);
        }

        if ($name === '' || (!$skuOptional && $sku === '')) {
            $errors[] = 'Row ' . $lineNumber . ': name and sku are required.';
            continue;
        }

        if (isset($seenSkus[strtolower($sku)])) {
            $errors[] = 'Row ' . $lineNumber . ': duplicate sku in file (' . $sku . ').';
            continue;
        }

        $unitPrice = parseFloatValue((string) ($row[$headerMap['unit_price']] ?? ''), null);
        if ($unitPrice === null && is_int($unitPriceFallbackIndex) && $unitPriceFallbackIndex >= 0) {
            $unitPrice = parseFloatValue((string) ($row[$unitPriceFallbackIndex] ?? ''), null);
        }
        if ($unitPrice === null) {
            $errors[] = 'Row ' . $lineNumber . ': unit_price must be a non-negative number.';
            continue;
        }

        $stockQty = parseIntValue((string) ($row[$headerMap['stock_qty']] ?? ''), 0);
        if ($stockQty === null) {
            $errors[] = 'Row ' . $lineNumber . ': stock_qty must be a non-negative number.';
            continue;
        }

        $reorderLevel = parseIntValue((string) ($row[$headerMap['reorder_level']] ?? ''), 5);
        if ($reorderLevel === null) {
            $errors[] = 'Row ' . $lineNumber . ': reorder_level must be a non-negative number.';
            continue;
        }

        $rows[] = [
            'name' => $name,
            'sku' => $sku,
            'unit_price' => $unitPrice,
            'stock_qty' => $stockQty,
            'reorder_level' => $reorderLevel,
            'category' => $category,
        ];

        $seenSkus[strtolower($sku)] = true;
    }

    return [
        'rows' => $rows,
        'errors' => $errors,
        'skipped' => $skipped,
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - Mchongoma POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= e($currentPage) ?>-page">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <span class="brand-icon"><i class="fa-solid fa-bag-shopping"></i></span>
            <div>
                <h1>Mchongoma<br>Limited</h1>
            </div>
        </div>

        <nav class="menu">
            <?php foreach ($pageController->getPages() as $key => $page): ?>
                <?php if (!canAccessPage($key, $userRole)) { continue; } ?>
                <a class="menu-item <?= $currentPage === $key ? 'active' : '' ?>" href="?page=<?= e($key) ?>">
                    <i class="fa-solid <?= e($page['icon']) ?>"></i><?= e($page['title']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="profile">
                <span class="avatar"><?= e($userInitials) ?></span>
                <div>
                    <strong><?= e($userName) ?></strong>
                    <small><?= e($userRole) ?></small>
                </div>
            </div>
            <a class="logout" href="#" data-action="showLogoutConfirm"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
            <form id="logoutForm" method="post" action="logout.php" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            </form>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger" data-action="toggleSidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <strong><?= e($pageTitle) ?></strong>
            </div>
            <div class="topbar-actions">
                <button class="pill primary" data-action="showAddModal"><i class="fa-solid fa-plus"></i> Add</button>
                <span class="pill" id="clock">--:-- --</span>
                <button class="pill lang-btn" data-action="setLanguage" data-value="en">EN</button>
                <button class="pill lang-btn" data-action="setLanguage" data-value="sw">SW</button>
                <span class="pill"><i class="fa-solid fa-store"></i> Shop</span>
                <span class="icon-btn notification-btn" data-action="showNotifications">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($lowStock['count'] > 0): ?>
                        <span class="badge"><?= $lowStock['count'] ?></span>
                    <?php endif; ?>
                </span>
                <span class="pill"><?= e($userName) ?></span>
            </div>
        </header>

        <?php if ($accessDeniedMessage !== null): ?>
            <section class="db-warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= e($accessDeniedMessage) ?>
            </section>
        <?php endif; ?>

        <?php if ($usingDemoData): ?>
            <?php if (!$isDemoSession): ?>
                <section class="db-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    MySQL is not connected, showing demo data. Configure DB credentials in your environment and import sql/schema.sql.
                    <?php if ($errorMessage): ?>
                        <div class="hint">Error: <?= e($errorMessage) ?></div>
                    <?php endif; ?>
                </section>

                <section class="setup-check" aria-label="Setup checks">
                    <div class="setup-check-title">Setup Check</div>
                    <div class="setup-check-grid">
                        <div class="setup-item">
                            <span class="label">PDO MySQL Driver</span>
                            <span class="value <?= $setupStatus['pdoMysql'] ? 'ok' : 'bad' ?>">
                                <i class="fa-solid <?= $setupStatus['pdoMysql'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                <?= $setupStatus['pdoMysql'] ? 'Loaded' : 'Missing' ?>
                            </span>
                        </div>
                        <div class="setup-item">
                            <span class="label">MySQL Connection</span>
                            <span class="value <?= $setupStatus['dbConnected'] ? 'ok' : 'bad' ?>">
                                <i class="fa-solid <?= $setupStatus['dbConnected'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                <?= $setupStatus['dbConnected'] ? 'Connected' : 'Failed' ?>
                            </span>
                        </div>
                        <div class="setup-item">
                            <span class="label">Core Tables</span>
                            <span class="value <?= $setupStatus['schemaReady'] ? 'ok' : 'bad' ?>">
                                <i class="fa-solid <?= $setupStatus['schemaReady'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                <?= $setupStatus['schemaReady'] ? 'Ready' : 'Missing' ?>
                            </span>
                        </div>
                    </div>
                    <p class="setup-note">If any item shows Missing/Failed, enable pdo_mysql in php.ini, restart Apache, start MySQL, and import sql/schema.sql.</p>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (is_array($flashFeedback) && isset($flashFeedback['type'], $flashFeedback['message'])): ?>
            <section class="page-content" style="margin-top: 12px; margin-bottom: 0;">
                <div class="import-feedback <?= e((string) $flashFeedback['type']) ?>">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><?= e((string) $flashFeedback['message']) ?></span>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($currentPage === 'dashboard'): ?>
            <!-- Dashboard Content -->
            <section class="page-content dashboard-hero-shell">
                <div class="dashboard-hero-content">
                    <div>
                        <h2>Welcome back, <?= e($userName) ?></h2>
                        <p>Track performance, monitor stock pressure, and launch key actions from one command center.</p>
                        <div class="dashboard-hero-meta">
                            <span><i class="fa-regular fa-clock"></i> Updated <?= e($reportGeneratedAt) ?></span>
                            <span><i class="fa-solid fa-boxes-stacked"></i> <?= moneyFormat($inventoryTotalStockUnits) ?> units in stock</span>
                            <span><i class="fa-solid fa-triangle-exclamation"></i> <?= moneyFormat((int) ($lowStock['count'] ?? 0)) ?> low stock alerts</span>
                        </div>
                    </div>
                    <div class="dashboard-hero-actions">
                        <button class="btn btn-primary" data-action="showNewSaleModal">
                            <i class="fa-solid fa-cart-plus"></i> Start New Sale
                        </button>
                        <button class="btn btn-secondary" data-action="go" data-value="?page=reports">
                            <i class="fa-solid fa-chart-line"></i> Open Reports
                        </button>
                    </div>
                </div>
            </section>

            <section class="stats-grid dashboard-kpi-grid">
                <article class="stat-card stat-blue" data-action="go" data-value="?page=sales">
                    <div>
                        <p>Total Sales</p>
                        <h2>Tsh <?= moneyFormat($totals['totalSales']) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-chart-line"></i></span>
                </article>
                <article class="stat-card stat-green" data-action="go" data-value="?page=customers">
                    <div>
                        <p>Total Customers</p>
                        <h2><?= moneyFormat($totals['totalCustomers']) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-users"></i></span>
                </article>
                <article class="stat-card stat-pink" data-action="go" data-value="?page=inventory">
                    <div>
                        <p>Total Items</p>
                        <h2><?= moneyFormat($totals['totalItems'] ?? $totals['totalProducts'] ?? 0) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-cube"></i></span>
                </article>
                <article class="stat-card stat-orange" data-action="go" data-value="?page=transactions">
                    <div>
                        <p>Transactions Today</p>
                        <h2><?= moneyFormat($totals['transactionsToday']) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-receipt"></i></span>
                </article>
            </section>

            <section class="welcome dashboard-welcome">Welcome to Mchongoma Limited, <?= e($userName) ?>! Choose a common task below to get started.</section>

            <section class="quick-actions dashboard-quick-actions">
                <button data-action="showNewSaleModal"><i class="fa-solid fa-cart-plus"></i> Start a New Sale</button>
                <button data-action="go" data-value="?page=inventory"><i class="fa-solid fa-cube"></i> View All Products</button>
                <button data-action="go" data-value="?page=customers"><i class="fa-regular fa-user"></i> View Customers</button>
                <button data-action="go" data-value="?page=reports"><i class="fa-solid fa-file-lines"></i> View All Reports</button>
                <button data-action="go" data-value="?page=transactions"><i class="fa-regular fa-rectangle-list"></i> All Transactions</button>
                <button data-action="go" data-value="?page=suppliers"><i class="fa-solid fa-arrow-right"></i> Manage Suppliers</button>
                <button data-action="showEndOfDayReport"><i class="fa-regular fa-calendar-check"></i> End of Day Report</button>
            </section>

            <section class="bottom-grid dashboard-bottom-grid">
                <article class="panel chart-panel dashboard-panel">
                    <div class="panel-header">
                        <h3>Sales Information</h3>
                        <div class="tabs" id="chartTabs">
                            <span class="active" data-period="week">Week</span>
                            <span data-period="month">Month</span>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </article>

                <div class="side-panels">
                    <article class="panel low-stock-panel dashboard-panel">
                        <div class="panel-header">
                            <h3><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</h3>
                        </div>
                        <?php if ($lowStock['count'] > 0): ?>
                            <div class="low-stock-list">
                                <?php foreach (array_slice($lowStock['items'], 0, 4) as $item): ?>
                                    <div class="low-stock-item">
                                        <div>
                                            <strong><?= e($item['name']) ?></strong>
                                            <small>SKU: <?= e($item['sku']) ?></small>
                                        </div>
                                        <span class="stock-badge danger"><?= $item['stock_qty'] ?> left</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="muted success-text"><i class="fa-solid fa-check-circle"></i> <?= e($lowStock['message']) ?></p>
                        <?php endif; ?>
                    </article>

                    <article class="panel recent-sales dashboard-panel">
                        <div class="panel-header"><h3>Recent Sales</h3></div>
                        <?php foreach ($recentSales as $sale): ?>
                            <div class="sale-row">
                                <div>
                                    <strong><?= e($sale['customer_name']) ?></strong>
                                    <small><?= e($sale['transaction_no']) ?></small>
                                </div>
                                <div class="sale-amount">
                                    <strong>Tsh <?= moneyFormat((float) $sale['amount']) ?></strong>
                                    <small><?= e($sale['payment_method']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </article>
                </div>
            </section>

        <?php elseif ($currentPage === 'inventory'): ?>
            <!-- Inventory Page -->
            <section class="page-content inventory-page-shell">
                <div class="page-header inventory-page-header">
                    <div class="page-info">
                        <h2>Product Inventory</h2>
                        <p>Control stock health, pricing, and product visibility in real time.</p>
                        <p style="margin-top:6px; color:#6B7280; font-size:13px;">
                            <?= moneyFormat($inventoryProductCount) ?> items found
                            <span style="margin:0 6px;">|</span>
                            <?= moneyFormat($inventoryTotalStockUnits) ?> total stock remaining
                        </p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-secondary" data-action="showImportProductsModal">
                            <i class="fa-solid fa-file-import"></i> Import Excel
                        </button>
                        <a class="btn btn-secondary" href="export_products_xlsx.php">
                            <i class="fa-solid fa-file-export"></i> Export XLSX
                        </a>
                        <button class="btn btn-primary" data-action="showAddProductModal">
                            <i class="fa-solid fa-plus"></i> Add Product
                        </button>
                    </div>
                </div>

                <div class="inventory-kpi-grid">
                    <article class="inventory-kpi-card">
                        <span>Total Products</span>
                        <strong><?= moneyFormat($inventoryProductCount) ?></strong>
                    </article>
                    <article class="inventory-kpi-card">
                        <span>Total Units In Stock</span>
                        <strong><?= moneyFormat($inventoryTotalStockUnits) ?></strong>
                    </article>
                    <article class="inventory-kpi-card <?= $lowStock['count'] > 0 ? 'warning' : 'success' ?>">
                        <span>Low Stock Items</span>
                        <strong><?= moneyFormat((int) ($lowStock['count'] ?? 0)) ?></strong>
                    </article>
                    <article class="inventory-kpi-card">
                        <span>Average Unit Price</span>
                        <?php
                            $inventoryAverageUnitPrice = $inventoryProductCount > 0
                                ? array_sum(array_map(static fn(array $item): float => (float) ($item['unit_price'] ?? 0), $products)) / $inventoryProductCount
                                : 0.0;
                        ?>
                        <strong>Tsh <?= moneyFormat($inventoryAverageUnitPrice) ?></strong>
                    </article>
                </div>

                <?php if ($importFeedback): ?>
                    <section class="import-feedback <?= e($importFeedback['type']) ?>">
                        <i class="fa-solid <?= $importFeedback['type'] === 'success' ? 'fa-circle-check' : ($importFeedback['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') ?>"></i>
                        <?= e($importFeedback['message']) ?>
                    </section>
                <?php endif; ?>

                <div class="data-table-container inventory-table-container">
                    <div class="table-header inventory-table-header">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="productSearch" placeholder="Search products..." onkeyup="filterTable('productTable', this.value)">
                        </div>
                        <div class="table-filters">
                            <select onchange="filterByStock(this.value)">
                                <option value="all">All Products</option>
                                <option value="low">Low Stock Only</option>
                                <option value="ok">In Stock</option>
                            </select>
                            <?php
                                $inventoryCategories = [];
                                foreach ($products as $inventoryProduct) {
                                    $inventoryCategoryName = trim((string) ($inventoryProduct['category'] ?? ''));
                                    if ($inventoryCategoryName === '') {
                                        continue;
                                    }
                                    $inventoryCategoryKey = strtolower($inventoryCategoryName);
                                    if (!isset($inventoryCategories[$inventoryCategoryKey])) {
                                        $inventoryCategories[$inventoryCategoryKey] = $inventoryCategoryName;
                                    }
                                }
                                ksort($inventoryCategories);
                            ?>
                            <select onchange="filterByCategory(this.value)">
                                <option value="all">All Categories</option>
                                <?php foreach ($inventoryCategories as $inventoryCategoryKey => $inventoryCategoryName): ?>
                                    <option value="<?= e($inventoryCategoryKey) ?>"><?= e($inventoryCategoryName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="inventory-visible-indicator">
                            Showing <strong id="inventoryVisibleCount"><?= moneyFormat($inventoryProductCount) ?></strong>
                        </div>
                    </div>
                    <table class="data-table" id="productTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Stock Qty</th>
                                <th>Reorder Level</th>
                                <th>Unit Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <?php $inventoryCategory = trim((string) ($product['category'] ?? '')); ?>
                                <tr
                                    data-stock="<?= $product['stock_qty'] <= $product['reorder_level'] ? 'low' : 'ok' ?>"
                                    data-category="<?= e(strtolower($inventoryCategory !== '' ? $inventoryCategory : 'uncategorized')) ?>"
                                >
                                    <td><strong><?= e($product['name']) ?></strong></td>
                                    <td><code><?= e($product['sku']) ?></code></td>
                                    <td><?= e((string) ($product['category'] ?? '-')) ?></td>
                                    <td><?= $product['stock_qty'] ?></td>
                                    <td><?= $product['reorder_level'] ?></td>
                                    <td>Tsh <?= moneyFormat($product['unit_price']) ?></td>
                                    <td>
                                        <?php if ($product['stock_qty'] <= $product['reorder_level']): ?>
                                            <span class="status-badge danger">Low Stock</span>
                                        <?php else: ?>
                                            <span class="status-badge success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-icon" data-action="editProduct" data-value="<?= (int) $product['id'] ?>" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="btn-icon danger" data-action="deleteProduct" data-value="<?= (int) $product['id'] ?>" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="inventory-empty-state" id="inventoryNoResult" style="display:none;">
                        <i class="fa-regular fa-folder-open"></i>
                        <span>No products match your current filters.</span>
                    </div>
                </div>
            </section>

        <?php elseif ($currentPage === 'customers'): ?>
            <!-- Customers Page -->
            <section class="page-content">
                <div class="page-header">
                    <div class="page-info">
                        <h2>Customer Management</h2>
                        <p>View and manage your customers</p>
                    </div>
                    <div class="page-actions">
                        <a class="btn btn-secondary" href="export_customer_debts_xlsx.php">
                            <i class="fa-solid fa-file-export"></i> Export Debt XLSX
                        </a>
                        <a class="btn btn-secondary" href="export_customer_debts_pdf.php" target="_blank" rel="noopener">
                            <i class="fa-solid fa-file-pdf"></i> Export Debt PDF
                        </a>
                        <button class="btn btn-primary" data-action="showAddCustomerModal">
                            <i class="fa-solid fa-plus"></i> Add Customer
                        </button>
                    </div>
                </div>

                <div class="data-table-container">
                    <div class="table-header">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="customerSearch" placeholder="Search customers..." onkeyup="filterTable('customerTable', this.value)">
                        </div>
                    </div>
                    <table class="data-table" id="customerTable">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Total Orders</th>
                                <th>Total Spent</th>
                                <th>Outstanding Debt</th>
                                <th>Member Since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <?php $outstandingDebt = (float) ($customerOutstandingTotals[(int) $customer['id']] ?? 0); ?>
                                <tr>
                                    <td><strong><?= e($customer['name']) ?></strong></td>
                                    <td><?= e($customer['phone'] ?? 'N/A') ?></td>
                                    <td><?= $customer['total_orders'] ?></td>
                                    <td>Tsh <?= moneyFormat($customer['total_spent']) ?></td>
                                    <td>
                                        <?php if ($outstandingDebt > 0): ?>
                                            <strong style="color:#dc2626;">Tsh <?= moneyFormat($outstandingDebt) ?></strong>
                                        <?php else: ?>
                                            <span style="color:#16a34a;">Tsh 0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                                    <td>
                                        <button class="btn-icon" data-action="viewCustomer" data-value="<?= (int) $customer['id'] ?>" title="View">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button class="btn-icon" data-action="editCustomer" data-value="<?= (int) $customer['id'] ?>" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="btn-icon danger" data-action="deleteCustomer" data-value="<?= (int) $customer['id'] ?>" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                        <button class="btn-icon" data-action="printCustomerStatement" data-value="<?= (int) $customer['id'] ?>" title="Print Statement">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </button>
                                        <?php if ($outstandingDebt > 0): ?>
                                            <button class="btn-icon" data-action="receiveCustomerPayment" data-value="<?= (int) $customer['id'] ?>" title="Receive Payment">
                                                <i class="fa-solid fa-money-bill-wave"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="data-table-container" style="margin-top:18px;">
                    <div class="table-header">
                        <h3 style="margin:0; font-size:16px;">Customer Debt Ledger</h3>
                        <div class="table-filters">
                            <select id="customerDebtStatusFilter">
                                <option value="all">All Debts</option>
                                <option value="open">Open/Partial</option>
                                <option value="overdue">Overdue Only</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>

                    <?php
                        $todayDateTs = strtotime(date('Y-m-d'));
                        $overdueReminders = [];
                        foreach ($customerCredits as $credit) {
                            $reminderDueDate = (string) ($credit['due_date'] ?? '');
                            $reminderOutstanding = (float) ($credit['outstanding_amount'] ?? 0);
                            if ($reminderDueDate === '' || $reminderOutstanding <= 0) {
                                continue;
                            }

                            $dueTs = strtotime($reminderDueDate);
                            if ($dueTs === false || $dueTs >= $todayDateTs) {
                                continue;
                            }

                            $daysOverdue = (int) floor(($todayDateTs - $dueTs) / 86400);
                            $overdueReminders[] = sprintf(
                                'Reminder: %s owes Tsh %s, overdue by %d day(s). Due date was %s.',
                                (string) ($credit['customer_name'] ?? 'Customer'),
                                moneyFormat($reminderOutstanding),
                                max(1, $daysOverdue),
                                date('M d, Y', $dueTs)
                            );
                        }
                    ?>

                    <div style="margin: 10px 0 14px; padding: 10px 12px; border: 1px solid <?= count($overdueReminders) > 0 ? '#fecaca' : '#bbf7d0' ?>; background: <?= count($overdueReminders) > 0 ? '#fff1f2' : '#f0fdf4' ?>; border-radius: 8px;">
                        <strong style="display:block; margin-bottom:6px; color: <?= count($overdueReminders) > 0 ? '#b91c1c' : '#166534' ?>;">
                            <?= count($overdueReminders) > 0 ? 'Automatic Reminder Notes' : 'Debt Reminder Notes' ?>
                        </strong>
                        <?php if (count($overdueReminders) === 0): ?>
                            <span style="color:#166534;">No overdue customer debts today.</span>
                        <?php else: ?>
                            <ul style="margin:0; padding-left:18px; color:#7f1d1d;">
                                <?php foreach ($overdueReminders as $note): ?>
                                    <li><?= e($note) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <table class="data-table" id="customerDebtTable">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Sale Ref</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Outstanding</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customerCredits) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-check-circle"></i> No outstanding customer debts
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customerCredits as $credit): ?>
                                    <?php
                                        $creditOutstanding = (float) ($credit['outstanding_amount'] ?? 0);
                                        $creditDueDate = (string) ($credit['due_date'] ?? '');
                                        $isOverdue = $creditDueDate !== ''
                                            && strtotime($creditDueDate) < strtotime(date('Y-m-d'))
                                            && $creditOutstanding > 0;
                                        $statusText = $isOverdue ? 'Overdue' : (string) ($credit['status'] ?? 'Open');
                                        $statusClass = $isOverdue
                                            ? 'danger'
                                            : ($statusText === 'Paid' ? 'success' : 'warning');
                                        $rowFilterState = $isOverdue
                                            ? 'overdue'
                                            : (in_array($statusText, ['Open', 'Partial'], true) ? 'open' : strtolower($statusText));
                                    ?>
                                    <tr data-credit-status="<?= e($rowFilterState) ?>" data-overdue="<?= $isOverdue ? '1' : '0' ?>">
                                        <td><?= e((string) ($credit['customer_name'] ?? '')) ?></td>
                                        <td><code><?= e((string) ($credit['transaction_no'] ?? ('SALE-' . (int) ($credit['sale_id'] ?? 0)))) ?></code></td>
                                        <td>Tsh <?= moneyFormat((float) ($credit['total_amount'] ?? 0)) ?></td>
                                        <td>Tsh <?= moneyFormat((float) ($credit['paid_amount'] ?? 0)) ?></td>
                                        <td>
                                            <strong style="color:<?= $creditOutstanding > 0 ? '#dc2626' : '#16a34a' ?>;">
                                                Tsh <?= moneyFormat($creditOutstanding) ?>
                                            </strong>
                                        </td>
                                        <td><?= $creditDueDate !== '' ? e(date('M d, Y', strtotime($creditDueDate))) : '<span style="color:#6b7280;">N/A</span>' ?></td>
                                        <td><span class="status-badge <?= e($statusClass) ?>"><?= e($statusText) ?></span></td>
                                        <td><?= date('M d, Y H:i', strtotime((string) ($credit['created_at'] ?? 'now'))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'sales' || $currentPage === 'transactions'): ?>
            <!-- Sales/Transactions Page -->
            <?php if ($currentPage === 'sales'): ?>
                <section class="page-content sales-pos-shell">
                    <div class="sales-layout">
                        <div class="sales-products-panel">
                            <div class="sales-products-header">
                                <div>
                                    <h3>Point of Sale</h3>
                                    <p>Fast checkout with full product visibility</p>
                                </div>
                                <div class="sales-products-stats">
                                    <span><i class="fa-solid fa-boxes-stacked"></i> <?= moneyFormat(count($saleProductOptions)) ?> Products</span>
                                    <span><i class="fa-solid fa-circle-check"></i> <strong id="salesVisibleCount"><?= moneyFormat(count($saleProductOptions)) ?></strong> Visible</span>
                                </div>
                            </div>

                            <div class="sales-search-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="salesProductSearch" placeholder="Search products or scan barcode...">
                            </div>

                            <div class="sales-category-row">
                                <button type="button" class="sales-chip active" data-category="all">All Products</button>
                                <?php
                                    $salesCategoryOptions = [];
                                    foreach ($saleProductOptions as $product) {
                                        $categoryName = trim((string) ($product['category'] ?? ''));
                                        if ($categoryName === '') {
                                            continue;
                                        }
                                        $categoryKey = strtolower($categoryName);
                                        if (!isset($salesCategoryOptions[$categoryKey])) {
                                            $salesCategoryOptions[$categoryKey] = $categoryName;
                                        }
                                    }
                                    ksort($salesCategoryOptions);
                                ?>
                                <?php foreach ($salesCategoryOptions as $categoryKey => $categoryName): ?>
                                    <button type="button" class="sales-chip" data-category="<?= e($categoryKey) ?>"><?= e($categoryName) ?></button>
                                <?php endforeach; ?>
                            </div>

                            <div class="sales-products-grid" id="salesProductsGrid">
                                <?php foreach ($saleProductOptions as $product): ?>
                                    <?php
                                        $productName = (string) ($product['name'] ?? 'Product');
                                        $firstLetter = strtoupper(substr(trim($productName), 0, 1));
                                        $stockQty = (int) ($product['stock_qty'] ?? 0);
                                        $category = trim((string) ($product['category'] ?? ''));
                                        $searchBlob = strtolower($productName . ' ' . $category);
                                        $categoryKey = strtolower($category);
                                    ?>
                                    <button
                                        type="button"
                                        class="sales-product-card"
                                        data-product-id="<?= (int) ($product['id'] ?? 0) ?>"
                                        data-product-name="<?= e($productName) ?>"
                                        data-product-price="<?= (float) ($product['unit_price'] ?? 0) ?>"
                                        data-product-stock="<?= $stockQty ?>"
                                        data-product-search="<?= e($searchBlob) ?>"
                                        data-product-category="<?= e($categoryKey !== '' ? $categoryKey : 'uncategorized') ?>"
                                    >
                                        <span class="sales-product-icon"><?= e($firstLetter !== '' ? $firstLetter : 'P') ?></span>
                                        <strong><?= e($productName) ?></strong>
                                        <div class="sales-product-meta">
                                            <span class="sales-product-category"><?= e($category !== '' ? $category : 'General') ?></span>
                                            <span class="sales-product-stock <?= $stockQty > 0 ? 'in-stock' : 'out-stock' ?>"><?= $stockQty ?> in stock</span>
                                        </div>
                                        <span class="sales-product-price">Tsh <?= moneyFormat((float) ($product['unit_price'] ?? 0)) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="sales-no-result" id="salesNoResult" style="display:none;">
                                <i class="fa-regular fa-folder-open"></i> No matching products found.
                            </div>
                        </div>

                        <aside class="sales-current-panel">
                            <div class="sales-current-header">
                                <h3>Current Sale</h3>
                                <button type="button" id="salesClearAll">Clear All</button>
                            </div>

                            <div class="sales-cart-items" id="salesCartItems"></div>

                            <div class="sales-summary">
                                <div class="sales-summary-row">
                                    <span>Subtotal</span>
                                    <strong id="salesSubtotal">Tsh 0</strong>
                                </div>
                                <div class="sales-summary-row">
                                    <span>Tax</span>
                                    <strong id="salesTax">Tsh 0</strong>
                                </div>
                                <div class="sales-summary-row total">
                                    <span>Total</span>
                                    <strong id="salesTotal">Tsh 0</strong>
                                </div>
                            </div>

                            <button type="button" class="sales-charge-btn" id="salesChargeBtn">Charge Tsh 0</button>
                        </aside>
                    </div>
                </section>
            <?php else: ?>
                <section class="page-content">
                    <div class="page-header">
                        <div class="page-info">
                            <h2>Transaction History</h2>
                            <p>View all transactions</p>
                        </div>
                    </div>

                    <div class="data-table-container">
                        <div class="table-header">
                            <div class="search-box">
                                <i class="fa-solid fa-search"></i>
                                <input type="text" id="salesSearch" placeholder="Search transactions..." onkeyup="filterTable('salesTable', this.value)">
                            </div>
                            <div class="table-filters">
                                <select id="salesPaymentFilter" onchange="filterByPayment('salesTable', this.value)">
                                    <option value="all">All Payments</option>
                                    <option value="cash">Cash</option>
                                    <option value="mobile">Mobile Money</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                        </div>
                        <table class="data-table" id="salesTable">
                            <thead>
                                <tr>
                                    <th>Transaction #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date & Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allSales as $sale): ?>
                                    <?php
                                        $paymentMethodRaw = strtolower(trim((string) ($sale['payment_method'] ?? '')));
                                        $paymentMethodValue = str_replace(' ', '_', $paymentMethodRaw);
                                    ?>
                                    <tr data-payment="<?= e($paymentMethodValue) ?>">
                                        <td><code><?= e($sale['transaction_no']) ?></code></td>
                                        <td><?= e($sale['customer_name']) ?></td>
                                        <td><strong>Tsh <?= moneyFormat((float) $sale['amount']) ?></strong></td>
                                        <td>
                                            <span class="payment-badge <?= strtolower($sale['payment_method']) ?>">
                                                <?= e($sale['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                                        <td>
                                            <button class="btn-icon" data-action="viewReceipt" data-value="<?= e($sale['transaction_no']) ?>" title="View Receipt">
                                                <i class="fa-solid fa-receipt"></i>
                                            </button>
                                            <button class="btn-icon" data-action="printReceipt" data-value="<?= e($sale['transaction_no']) ?>" title="Print">
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="data-table-container" style="margin-top: 18px;">
                        <div class="table-header">
                            <h3 style="margin:0; font-size:16px;">Mobile Money Payment Status</h3>
                        </div>
                        <table class="data-table" id="mobileMoneyTable">
                            <thead>
                                <tr>
                                    <th>Provider</th>
                                    <th>Phone</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                    <th>Sale</th>
                                    <th>Status</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($mobileMoneyTransactions) === 0): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding: 20px;">
                                            <i class="fa-solid fa-mobile-screen-button"></i> No mobile money payments yet
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mobileMoneyTransactions as $payment): ?>
                                        <?php
                                            $status = strtolower((string) ($payment['status'] ?? 'pending'));
                                            $statusClass = 'warning';
                                            if ($status === 'success' || $status === 'completed' || $status === 'mock_approved') {
                                                $statusClass = 'success';
                                            } elseif ($status === 'failed' || $status === 'cancelled') {
                                                $statusClass = 'danger';
                                            }
                                        ?>
                                        <tr>
                                            <td><?= e((string) ($payment['provider'] ?? '')) ?></td>
                                            <td><?= e((string) ($payment['msisdn'] ?? '')) ?></td>
                                            <td><strong><?= e((string) ($payment['currency'] ?? 'TZS')) ?> <?= moneyFormat((float) ($payment['amount'] ?? 0)) ?></strong></td>
                                            <td><code><?= e((string) ($payment['external_reference'] ?? '')) ?></code></td>
                                            <td><?= e((string) ($payment['transaction_no'] ?? '-')) ?></td>
                                            <td><span class="status-badge <?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span></td>
                                            <td><?= date('M d, Y H:i', strtotime((string) ($payment['created_at'] ?? 'now'))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

        <?php elseif ($currentPage === 'reports'): ?>
            <!-- Reports Page -->
            <section class="page-content report-redesign-page">
                <div class="report-headline-row">
                    <div class="page-info">
                        <span class="report-eyebrow">Business Intelligence</span>
                        <h2>Reports & Performance</h2>
                        <p>Executive analytics across revenue, profit, products, and team output</p>
                        <p class="report-period-note">
                            Period: <strong><?= e($reportRangeLabel) ?></strong>
                            <span class="report-period-sep">|</span>
                            Updated: <?= e($reportGeneratedAt) ?>
                        </p>
                    </div>
                    <div class="report-toolbar">
                        <div class="report-time-filter">
                            <?php foreach ($reportRangeButtons as $rangeKey => $rangeLabel): ?>
                                <a
                                    class="<?= $reportRange === $rangeKey ? 'active' : '' ?>"
                                    href="?page=reports&amp;range=<?= e($rangeKey) ?>"
                                ><?= e($rangeLabel) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <a class="btn btn-secondary" href="export_report_pdf.php?range=<?= e($reportExportRange) ?>&amp;disposition=inline" target="_blank" rel="noopener">
                            <i class="fa-solid fa-print"></i> Print Report
                        </a>
                        <a class="btn btn-secondary" href="export_report_pdf.php?range=<?= e($reportExportRange) ?>&amp;disposition=attachment" rel="noopener">
                            <i class="fa-solid fa-download"></i> Download PDF
                        </a>
                        <a class="btn btn-secondary" href="export_report_csv.php?range=<?= e($reportExportRange) ?>" rel="noopener">
                            <i class="fa-solid fa-file-csv"></i> Export CSV
                        </a>
                        <a class="btn btn-secondary" href="export_report_xlsx.php?range=<?= e($reportExportRange) ?>" rel="noopener">
                            <i class="fa-regular fa-file-excel"></i> Export Excel
                        </a>
                        <a class="btn btn-secondary" href="?page=reports&amp;range=<?= e($reportRange) ?>">
                            <i class="fa-solid fa-rotate-right"></i> Refresh
                        </a>
                    </div>
                </div>

                <div class="report-snapshot-grid">
                    <article class="report-snapshot-card">
                        <span class="label"><i class="fa-solid fa-calendar-week"></i> Active Period</span>
                        <strong><?= e($reportRangeLabel) ?></strong>
                        <small>Reporting scope currently selected</small>
                    </article>
                    <article class="report-snapshot-card">
                        <span class="label"><i class="fa-solid fa-arrow-trend-up"></i> Revenue per Transaction</span>
                        <strong>Tsh <?= moneyFormat($reportTransactionsTotal > 0 ? ($reportRevenueTotal / $reportTransactionsTotal) : 0) ?></strong>
                        <small>Average value generated per sale</small>
                    </article>
                    <article class="report-snapshot-card">
                        <span class="label"><i class="fa-solid fa-chart-pie"></i> Profitability Mix</span>
                        <strong><?= number_format($reportProfitMargin, 1) ?>%</strong>
                        <small>Share of revenue retained as profit</small>
                    </article>
                </div>

                <div class="report-kpi-grid">
                    <article class="report-kpi-card">
                        <span>Total Revenue</span>
                        <strong>Tsh <?= moneyFormat($reportRevenueTotal) ?></strong>
                    </article>
                    <article class="report-kpi-card">
                        <span>Gross Profit</span>
                        <strong>Tsh <?= moneyFormat($reportGrossProfit) ?></strong>
                    </article>
                    <article class="report-kpi-card">
                        <span>Profit Margin</span>
                        <strong><?= number_format($reportProfitMargin, 1) ?>%</strong>
                    </article>
                    <article class="report-kpi-card">
                        <span>Transactions</span>
                        <strong><?= $reportTransactionsTotal ?></strong>
                    </article>
                    <article class="report-kpi-card">
                        <span>Average Ticket</span>
                        <strong>Tsh <?= moneyFormat($reportAverageTicket) ?></strong>
                    </article>
                    <article class="report-kpi-card">
                        <span>Top Payment Method</span>
                        <strong><?= e($reportTopPaymentMethod) ?></strong>
                        <small><?= number_format($reportTopPaymentShare, 1) ?>% of revenue</small>
                    </article>
                </div>

                <section class="report-card-shell">
                    <h3>Revenue vs Profit by Day</h3>
                    <?php $maxDayRevenue = max(1, ...array_map(static fn(array $row): float => (float) ($row['revenue'] ?? 0), $reportDaySeries)); ?>
                    <div class="report-day-chart">
                        <?php foreach ($reportDaySeries as $point): ?>
                            <?php
                                $revenuePct = ((float) $point['revenue'] / $maxDayRevenue) * 100;
                                $profitPct = ((float) $point['profit'] / $maxDayRevenue) * 100;
                            ?>
                            <div class="report-day-bar-group">
                                <div class="report-day-bars">
                                    <span class="bar revenue" style="height: <?= max(2, (int) round($revenuePct)) ?>%;"></span>
                                    <span class="bar profit" style="height: <?= max(2, (int) round($profitPct)) ?>%;"></span>
                                </div>
                                <small><?= e((string) $point['label']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="report-legend">
                        <span><i class="dot revenue"></i> Revenue</span>
                        <span><i class="dot profit"></i> Profit</span>
                    </div>
                </section>

                <div class="report-half-grid">
                    <section class="report-card-shell">
                        <h3>Sales by Hour</h3>
                        <?php $maxHourValue = max(1, ...array_map(static fn(array $row): float => (float) ($row['value'] ?? 0), $reportHourlySeries)); ?>
                        <div class="report-hour-chart">
                            <?php foreach ($reportHourlySeries as $point): ?>
                                <?php $hourPct = ((float) $point['value'] / $maxHourValue) * 100; ?>
                                <div class="report-hour-bar-group">
                                    <span class="hour-bar" style="height: <?= max(2, (int) round($hourPct)) ?>%;"></span>
                                    <small><?= e((string) $point['label']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="report-card-shell">
                        <h3>Revenue by Payment Method</h3>
                        <div class="report-payment-list">
                            <?php foreach ($reportPaymentBreakdown as $row): ?>
                                <div class="report-payment-row">
                                    <div>
                                        <strong><?= e((string) $row['method']) ?></strong>
                                        <small><?= number_format((float) $row['percentage'], 1) ?>%</small>
                                    </div>
                                    <div class="payment-track">
                                        <span style="width: <?= max(2, min(100, (int) round((float) $row['percentage']))) ?>%;"></span>
                                    </div>
                                    <strong>Tsh <?= moneyFormat((float) $row['amount']) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <section class="report-card-shell">
                    <h3>Cashier Performance</h3>
                    <table class="report-mini-table">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportCashierPerformance as $row): ?>
                                <tr>
                                    <td><?= e((string) $row['name']) ?></td>
                                    <td><?= (int) $row['sales_count'] ?></td>
                                    <td><strong>Tsh <?= moneyFormat((float) $row['revenue']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <section class="report-card-shell">
                    <h3>Top Selling Products</h3>
                    <?php $maxTopRevenue = max(1, ...array_map(static fn(array $row): float => (float) ($row['revenue'] ?? 0), $reportTopProducts)); ?>
                    <div class="report-top-list">
                        <?php foreach ($reportTopProducts as $index => $row): ?>
                            <?php $widthPct = ((float) $row['revenue'] / $maxTopRevenue) * 100; ?>
                            <div class="report-top-item">
                                <span class="rank"><?= $index + 1 ?></span>
                                <div class="top-name-wrap">
                                    <strong><?= e((string) $row['name']) ?></strong>
                                    <div class="top-track"><span style="width: <?= max(2, min(100, (int) round($widthPct))) ?>%;"></span></div>
                                </div>
                                <div class="top-values">
                                    <strong>Tsh <?= moneyFormat((float) $row['revenue']) ?></strong>
                                    <small><?= (int) $row['sold_qty'] ?> sold</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </section>

        <?php elseif ($currentPage === 'suppliers'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-truck"></i> Suppliers Management</h2>
                    <button class="btn btn-primary" data-action="openAddSupplierModal">
                        <i class="fa-solid fa-plus"></i> Add Supplier
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($suppliers) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-box-open"></i> No suppliers added yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <?php
                                        $status = trim((string) ($supplier['status'] ?? 'Active'));
                                        $statusClass = strtolower($status) === 'active' ? 'success' : 'warning';
                                    ?>
                                    <tr>
                                        <td><?= (int) ($supplier['id'] ?? 0) ?></td>
                                        <td><strong><?= e((string) ($supplier['name'] ?? '')) ?></strong></td>
                                        <td><?= e((string) ($supplier['contact_person'] ?? '-')) ?></td>
                                        <td><?= e((string) (($supplier['phone'] ?? '') !== '' ? $supplier['phone'] : 'N/A')) ?></td>
                                        <td><?= e((string) (($supplier['email'] ?? '') !== '' ? $supplier['email'] : 'N/A')) ?></td>
                                        <td><span class="status-badge <?= e($statusClass) ?>"><?= e($status) ?></span></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewSupplier"
                                                data-value="<?= (int) ($supplier['id'] ?? 0) ?>"
                                                data-name="<?= e((string) ($supplier['name'] ?? '')) ?>"
                                                data-contact="<?= e((string) (($supplier['contact_person'] ?? '') !== '' ? $supplier['contact_person'] : 'N/A')) ?>"
                                                data-phone="<?= e((string) (($supplier['phone'] ?? '') !== '' ? $supplier['phone'] : 'N/A')) ?>"
                                                data-email="<?= e((string) (($supplier['email'] ?? '') !== '' ? $supplier['email'] : 'N/A')) ?>"
                                                data-address="<?= e((string) (($supplier['address'] ?? '') !== '' ? $supplier['address'] : 'N/A')) ?>"
                                                data-status="<?= e($status) ?>"
                                                title="View"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button
                                                class="btn-icon"
                                                data-action="editSupplier"
                                                data-value="<?= (int) ($supplier['id'] ?? 0) ?>"
                                                data-name="<?= e((string) ($supplier['name'] ?? '')) ?>"
                                                data-contact="<?= e((string) (($supplier['contact_person'] ?? '') !== '' ? $supplier['contact_person'] : '')) ?>"
                                                data-phone="<?= e((string) (($supplier['phone'] ?? '') !== '' ? $supplier['phone'] : '')) ?>"
                                                data-email="<?= e((string) (($supplier['email'] ?? '') !== '' ? $supplier['email'] : '')) ?>"
                                                data-address="<?= e((string) (($supplier['address'] ?? '') !== '' ? $supplier['address'] : '')) ?>"
                                                data-status="<?= e($status) ?>"
                                                title="Edit"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button
                                                class="btn-icon danger"
                                                data-action="deleteSupplier"
                                                data-value="<?= (int) ($supplier['id'] ?? 0) ?>"
                                                title="Delete"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'employees'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-users"></i> Employees Management</h2>
                    <button class="btn btn-primary" data-action="openAddEmployeeModal">
                        <i class="fa-solid fa-plus"></i> Add Employee
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($employees) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-users-slash"></i> No employees added yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                    <?php
                                        $employeeStatus = trim((string) ($employee['status'] ?? 'Active'));
                                        $employeeStatusClass = strtolower($employeeStatus) === 'active' ? 'success' : 'warning';
                                    ?>
                                    <tr>
                                        <td><?= (int) ($employee['id'] ?? 0) ?></td>
                                        <td><strong><?= e((string) ($employee['name'] ?? '')) ?></strong></td>
                                        <td><?= e((string) (($employee['position'] ?? '') !== '' ? $employee['position'] : 'N/A')) ?></td>
                                        <td><?= e((string) (($employee['phone'] ?? '') !== '' ? $employee['phone'] : 'N/A')) ?></td>
                                        <td><?= e((string) (($employee['email'] ?? '') !== '' ? $employee['email'] : 'N/A')) ?></td>
                                        <td><strong>Tsh <?= moneyFormat((float) ($employee['salary'] ?? 0)) ?></strong></td>
                                        <td><span class="status-badge <?= e($employeeStatusClass) ?>"><?= e($employeeStatus) ?></span></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewEmployee"
                                                data-value="<?= (int) ($employee['id'] ?? 0) ?>"
                                                data-name="<?= e((string) ($employee['name'] ?? '')) ?>"
                                                data-position="<?= e((string) (($employee['position'] ?? '') !== '' ? $employee['position'] : 'N/A')) ?>"
                                                data-phone="<?= e((string) (($employee['phone'] ?? '') !== '' ? $employee['phone'] : 'N/A')) ?>"
                                                data-email="<?= e((string) (($employee['email'] ?? '') !== '' ? $employee['email'] : 'N/A')) ?>"
                                                data-salary="<?= (float) ($employee['salary'] ?? 0) ?>"
                                                data-status="<?= e($employeeStatus) ?>"
                                                title="View"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'users'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-user-shield"></i> Users & Privileges</h2>
                    <button class="btn btn-primary" data-action="openAddUserModal">
                        <i class="fa-solid fa-user-plus"></i> Add User
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($systemUsers) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-user-xmark"></i> No users found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($systemUsers as $systemUser): ?>
                                    <?php
                                        $systemUserRole = (string) ($systemUser['role'] ?? 'Staff');
                                        $isActiveUser = ((int) ($systemUser['is_active'] ?? 1)) === 1;
                                        $statusLabel = $isActiveUser ? 'Active' : 'Inactive';
                                        $statusClass = $isActiveUser ? 'success' : 'warning';
                                        $roleClass = match (strtolower($systemUserRole)) {
                                            'admin' => 'success',
                                            'manager' => 'warning',
                                            default => 'info',
                                        };
                                    ?>
                                    <tr>
                                        <td><?= (int) ($systemUser['id'] ?? 0) ?></td>
                                        <td><strong><?= e((string) ($systemUser['name'] ?? '')) ?></strong></td>
                                        <td><?= e((string) ($systemUser['email'] ?? '')) ?></td>
                                        <td><span class="status-badge <?= e($roleClass) ?>"><?= e($systemUserRole) ?></span></td>
                                        <td><span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                                        <td><?= e((string) ($systemUser['created_at'] ?? '')) ?></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="editUserRole"
                                                data-value="<?= (int) ($systemUser['id'] ?? 0) ?>"
                                                data-role="<?= e($systemUserRole) ?>"
                                                data-name="<?= e((string) ($systemUser['name'] ?? '')) ?>"
                                                title="Assign Privilege"
                                            >
                                                <i class="fa-solid fa-user-gear"></i>
                                            </button>
                                            <button
                                                class="btn-icon"
                                                data-action="editUserPermissions"
                                                data-value="<?= (int) ($systemUser['id'] ?? 0) ?>"
                                                data-name="<?= e((string) ($systemUser['name'] ?? '')) ?>"
                                                title="Custom Permissions"
                                            >
                                                <i class="fa-solid fa-sliders"></i>
                                            </button>
                                            <button
                                                class="btn-icon"
                                                data-action="resetUserPassword"
                                                data-value="<?= (int) ($systemUser['id'] ?? 0) ?>"
                                                data-name="<?= e((string) ($systemUser['name'] ?? '')) ?>"
                                                title="Reset Password"
                                            >
                                                <i class="fa-solid fa-key"></i>
                                            </button>
                                            <button
                                                class="btn-icon"
                                                data-action="toggleUserStatus"
                                                data-value="<?= (int) ($systemUser['id'] ?? 0) ?>"
                                                data-name="<?= e((string) ($systemUser['name'] ?? '')) ?>"
                                                data-status="<?= $isActiveUser ? 'active' : 'inactive' ?>"
                                                title="<?= $isActiveUser ? 'Deactivate User' : 'Activate User' ?>"
                                            >
                                                <i class="fa-solid <?= $isActiveUser ? 'fa-user-lock' : 'fa-user-check' ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'expenses'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-credit-card"></i> Expenses Management</h2>
                    <button class="btn btn-primary" data-action="openAddExpenseModal">
                        <i class="fa-solid fa-plus"></i> Add Expense
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-receipt"></i> No expenses recorded yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'invoices'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-file-lines"></i> Invoices Management</h2>
                    <button class="btn btn-primary" data-action="openAddInvoiceModal">
                        <i class="fa-solid fa-plus"></i> Create Invoice
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($invoicesRecords) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-inbox"></i> No invoices created yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoicesRecords as $invoice): ?>
                                    <?php
                                        $invoiceStatus = trim((string) ($invoice['status'] ?? 'Pending'));
                                        $invoiceStatusClass = strtolower($invoiceStatus) === 'paid'
                                            ? 'success'
                                            : (strtolower($invoiceStatus) === 'cancelled' ? 'danger' : 'warning');
                                        $invoiceDateRaw = (string) ($invoice['created_at'] ?? '');
                                        $invoiceDateTs = strtotime($invoiceDateRaw);
                                        $invoiceDateLabel = $invoiceDateTs !== false
                                            ? date('M d, Y H:i', $invoiceDateTs)
                                            : $invoiceDateRaw;
                                        $invoiceCustomerLabel = (string) (($invoice['customer_name'] ?? '') !== ''
                                            ? $invoice['customer_name']
                                            : ('Customer #' . (int) ($invoice['customer_id'] ?? 0)));
                                    ?>
                                    <tr>
                                        <td><strong><?= e((string) ($invoice['invoice_no'] ?? '')) ?></strong></td>
                                        <td><?= e($invoiceCustomerLabel) ?></td>
                                        <td>Tsh <?= moneyFormat((float) ($invoice['amount'] ?? 0)) ?></td>
                                        <td><span class="status-badge <?= e($invoiceStatusClass) ?>"><?= e($invoiceStatus) ?></span></td>
                                        <td><?= e($invoiceDateLabel) ?></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewInvoice"
                                                data-invoice-id="<?= (int) ($invoice['id'] ?? 0) ?>"
                                                data-invoice-no="<?= e((string) ($invoice['invoice_no'] ?? '')) ?>"
                                                data-customer="<?= e($invoiceCustomerLabel) ?>"
                                                data-amount="<?= moneyFormat((float) ($invoice['amount'] ?? 0)) ?>"
                                                data-status="<?= e($invoiceStatus) ?>"
                                                data-date="<?= e($invoiceDateLabel) ?>"
                                                title="View Invoice"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($invoiceStatus) !== 'paid'): ?>
                                                <button
                                                    class="btn-icon"
                                                    data-action="updateInvoiceStatus"
                                                    data-value="<?= (int) ($invoice['id'] ?? 0) ?>"
                                                    data-status="Paid"
                                                    title="Mark as Paid"
                                                >
                                                    <i class="fa-solid fa-circle-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (strtolower($invoiceStatus) !== 'cancelled'): ?>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="updateInvoiceStatus"
                                                    data-value="<?= (int) ($invoice['id'] ?? 0) ?>"
                                                    data-status="Cancelled"
                                                    title="Cancel Invoice"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'deliveries'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-boxes-stacked"></i> Deliveries Management</h2>
                    <button class="btn btn-primary" data-action="openAddDeliveryModal">
                        <i class="fa-solid fa-plus"></i> Add Delivery
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Delivery No</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($deliveriesRecords) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-truck"></i> No deliveries recorded yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deliveriesRecords as $delivery): ?>
                                    <?php
                                        $deliveryStatus = trim((string) ($delivery['status'] ?? 'Pending'));
                                        if ($deliveryStatus === '') {
                                            $deliveryStatus = 'Pending';
                                        }
                                        $deliveryStatusClass = strtolower($deliveryStatus) === 'delivered'
                                            ? 'success'
                                            : (strtolower($deliveryStatus) === 'cancelled' ? 'danger' : 'warning');
                                        $deliveryDateRaw = (string) ($delivery['created_at'] ?? '');
                                        $deliveryDateTs = strtotime($deliveryDateRaw);
                                        $deliveryDateLabel = $deliveryDateTs !== false
                                            ? date('M d, Y H:i', $deliveryDateTs)
                                            : $deliveryDateRaw;
                                        $deliveryCustomer = (string) (($delivery['customer_name'] ?? '') !== ''
                                            ? $delivery['customer_name']
                                            : ('Customer #' . (int) ($delivery['customer_id'] ?? 0)));
                                        $deliveryId = (int) ($delivery['id'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><strong><?= e((string) ($delivery['delivery_no'] ?? '')) ?></strong></td>
                                        <td><?= e($deliveryCustomer) ?></td>
                                        <td>Tsh <?= moneyFormat((float) ($delivery['amount'] ?? 0)) ?></td>
                                        <td><span class="status-badge <?= e($deliveryStatusClass) ?>"><?= e($deliveryStatus) ?></span></td>
                                        <td><?= e($deliveryDateLabel) ?></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewDelivery"
                                                data-delivery-id="<?= $deliveryId ?>"
                                                data-delivery-no="<?= e((string) ($delivery['delivery_no'] ?? '')) ?>"
                                                data-customer="<?= e($deliveryCustomer) ?>"
                                                data-amount="<?= moneyFormat((float) ($delivery['amount'] ?? 0)) ?>"
                                                data-status="<?= e($deliveryStatus) ?>"
                                                data-date="<?= e($deliveryDateLabel) ?>"
                                                title="View"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($deliveryStatus) === 'pending'): ?>
                                                <button
                                                    class="btn-icon"
                                                    data-action="updateDeliveryStatus"
                                                    data-value="<?= $deliveryId ?>"
                                                    data-status="In Transit"
                                                    title="Mark In Transit"
                                                >
                                                    <i class="fa-solid fa-truck-fast"></i>
                                                </button>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="updateDeliveryStatus"
                                                    data-value="<?= $deliveryId ?>"
                                                    data-status="Cancelled"
                                                    title="Cancel Delivery"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php elseif (strtolower($deliveryStatus) === 'in transit'): ?>
                                                <button
                                                    class="btn-icon"
                                                    data-action="updateDeliveryStatus"
                                                    data-value="<?= $deliveryId ?>"
                                                    data-status="Delivered"
                                                    title="Mark Delivered"
                                                >
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="updateDeliveryStatus"
                                                    data-value="<?= $deliveryId ?>"
                                                    data-status="Cancelled"
                                                    title="Cancel Delivery"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'receiving'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-box"></i> Receiving Management</h2>
                    <button class="btn btn-primary" data-action="openAddReceivingModal">
                        <i class="fa-solid fa-plus"></i> Add Receiving
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Receiving No</th>
                                <th>Supplier</th>
                                <th>Linked PO</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($receivingRecords) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-inbox"></i> No receiving records yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($receivingRecords as $receiving): ?>
                                    <?php
                                        $receivingStatus = trim((string) ($receiving['status'] ?? 'Pending'));
                                        $receivingStatusClass = strtolower($receivingStatus) === 'completed'
                                            ? 'success'
                                            : (strtolower($receivingStatus) === 'received' ? 'warning' : 'danger');
                                        $receivingDateRaw = (string) ($receiving['created_at'] ?? '');
                                        $receivingDateTs = strtotime($receivingDateRaw);
                                        $receivingDateLabel = $receivingDateTs !== false
                                            ? date('M d, Y H:i', $receivingDateTs)
                                            : $receivingDateRaw;
                                        $supplierLabel = (string) (($receiving['supplier_name'] ?? '') !== ''
                                            ? $receiving['supplier_name']
                                            : ('Supplier #' . (int) ($receiving['supplier_id'] ?? 0)));
                                        $linkedPoId = (int) ($receiving['purchase_order_id'] ?? 0);
                                        $linkedPoNo = trim((string) ($receiving['po_no'] ?? ''));
                                        $linkedPoLabel = $linkedPoId > 0
                                            ? ($linkedPoNo !== '' ? $linkedPoNo : ('PO #' . $linkedPoId))
                                            : '-';
                                    ?>
                                    <tr>
                                        <td><strong><?= e((string) ($receiving['receiving_no'] ?? '')) ?></strong></td>
                                        <td><?= e($supplierLabel) ?></td>
                                        <td><?= e($linkedPoLabel) ?></td>
                                        <td>Tsh <?= moneyFormat((float) ($receiving['amount'] ?? 0)) ?></td>
                                        <td><span class="status-badge <?= e($receivingStatusClass) ?>"><?= e($receivingStatus) ?></span></td>
                                        <td><?= e($receivingDateLabel) ?></td>
                                        <td>
                                            <button class="btn-icon" data-action="viewReceiving" data-value="<?= (int) ($receiving['id'] ?? 0) ?>" title="View">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($receivingStatus) === 'pending'): ?>
                                                <button class="btn-icon" data-action="updateReceivingStatus" data-value="<?= (int) ($receiving['id'] ?? 0) ?>" data-status="Received" title="Mark Received">
                                                    <i class="fa-solid fa-truck-ramp-box"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (strtolower($receivingStatus) !== 'completed'): ?>
                                                <button class="btn-icon" data-action="updateReceivingStatus" data-value="<?= (int) ($receiving['id'] ?? 0) ?>" data-status="Completed" title="Mark Completed">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'quotations'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-rectangle-list"></i> Quotations Management</h2>
                    <button class="btn btn-primary" data-action="openAddQuotationModal">
                        <i class="fa-solid fa-plus"></i> Create Quotation
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Quotation No</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($quotationsRecords) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-rectangle-list"></i> No quotations created yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quotationsRecords as $quotation): ?>
                                    <?php
                                        $quotationStatus = trim((string) ($quotation['status'] ?? ''));
                                        if ($quotationStatus === '') {
                                            $quotationStatus = 'Pending';
                                        }
                                        $quotationStatusClass = strtolower($quotationStatus) === 'approved'
                                            ? 'success'
                                            : (strtolower($quotationStatus) === 'rejected' ? 'danger' : 'warning');
                                        $quotationDateRaw = (string) ($quotation['created_at'] ?? '');
                                        $quotationDateTs = strtotime($quotationDateRaw);
                                        $quotationDateLabel = $quotationDateTs !== false
                                            ? date('M d, Y H:i', $quotationDateTs)
                                            : $quotationDateRaw;
                                        $quotationCustomer = (string) (($quotation['customer_name'] ?? '') !== ''
                                            ? $quotation['customer_name']
                                            : ('Customer #' . (int) ($quotation['customer_id'] ?? 0)));
                                        $quotationId = (int) ($quotation['id'] ?? 0);
                                        $canManageQuotation = in_array(strtolower(trim($userRole)), ['admin', 'manager'], true);
                                    ?>
                                    <tr>
                                        <td><strong><?= e((string) ($quotation['quotation_no'] ?? '')) ?></strong></td>
                                        <td><?= e($quotationCustomer) ?></td>
                                        <td>Tsh <?= moneyFormat((float) ($quotation['amount'] ?? 0)) ?></td>
                                        <td><span class="status-badge <?= e($quotationStatusClass) ?>"><?= e($quotationStatus) ?></span></td>
                                        <td><?= e($quotationDateLabel) ?></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewQuotation"
                                                data-quotation-id="<?= $quotationId ?>"
                                                data-quotation-no="<?= e((string) ($quotation['quotation_no'] ?? '')) ?>"
                                                data-customer="<?= e($quotationCustomer) ?>"
                                                data-amount="<?= moneyFormat((float) ($quotation['amount'] ?? 0)) ?>"
                                                data-status="<?= e($quotationStatus) ?>"
                                                data-date="<?= e($quotationDateLabel) ?>"
                                                title="View"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($quotationStatus) === 'pending' && $canManageQuotation): ?>
                                                <button
                                                    class="btn-icon"
                                                    data-action="editQuotation"
                                                    data-value="<?= $quotationId ?>"
                                                    data-customer-id="<?= (int) ($quotation['customer_id'] ?? 0) ?>"
                                                    data-customer="<?= e($quotationCustomer) ?>"
                                                    data-amount-raw="<?= (float) ($quotation['amount'] ?? 0) ?>"
                                                    title="Edit Quotation"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button
                                                class="btn-icon"
                                                data-action="printQuotation"
                                                data-value="<?= $quotationId ?>"
                                                title="Print Quotation"
                                            >
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                            <?php if (strtolower($quotationStatus) !== 'approved' && $canManageQuotation): ?>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="deleteQuotation"
                                                    data-value="<?= $quotationId ?>"
                                                    title="Delete Quotation"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (strtolower($quotationStatus) === 'pending' && $canManageQuotation): ?>
                                                <button
                                                    class="btn-icon"
                                                    data-action="updateQuotationStatus"
                                                    data-value="<?= $quotationId ?>"
                                                    data-status="Approved"
                                                    title="Approve Quotation"
                                                >
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="updateQuotationStatus"
                                                    data-value="<?= $quotationId ?>"
                                                    data-status="Rejected"
                                                    title="Reject Quotation"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php elseif (strtolower($quotationStatus) === 'approved' && $canManageQuotation): ?>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="updateQuotationStatus"
                                                    data-value="<?= $quotationId ?>"
                                                    data-status="Expired"
                                                    title="Mark Expired"
                                                >
                                                    <i class="fa-solid fa-hourglass-end"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (!$canManageQuotation): ?>
                                                <button
                                                    class="btn-icon"
                                                    type="button"
                                                    disabled
                                                    title="Edit, status updates, and delete are available to Admin/Manager only"
                                                    aria-label="Admin/Manager only actions"
                                                >
                                                    <i class="fa-solid fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'purchase-orders'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-cart-plus"></i> Purchase Orders Management</h2>
                    <button class="btn btn-primary" data-action="openAddPOModal">
                        <i class="fa-solid fa-plus"></i> Create PO
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PO No</th>
                                <th>Supplier</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Expected Delivery</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($purchaseOrdersRecords) === 0): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-cart"></i> No purchase orders created yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($purchaseOrdersRecords as $purchaseOrder): ?>
                                    <?php
                                        $poStatus = trim((string) ($purchaseOrder['status'] ?? 'Pending'));
                                        $poStatusClass = strtolower($poStatus) === 'received'
                                            ? 'success'
                                            : (strtolower($poStatus) === 'approved' ? 'warning' : (strtolower($poStatus) === 'cancelled' ? 'danger' : 'warning'));
                                        $poDateRaw = (string) ($purchaseOrder['created_at'] ?? '');
                                        $poDateTs = strtotime($poDateRaw);
                                        $poDateLabel = $poDateTs !== false
                                            ? date('M d, Y H:i', $poDateTs)
                                            : $poDateRaw;
                                        $poExpectedDeliveryRaw = trim((string) ($purchaseOrder['expected_delivery_date'] ?? ''));
                                        $poExpectedDeliveryLabel = '-';
                                        if ($poExpectedDeliveryRaw !== '') {
                                            $poExpectedDeliveryTs = strtotime($poExpectedDeliveryRaw);
                                            $poExpectedDeliveryLabel = $poExpectedDeliveryTs !== false
                                                ? date('M d, Y', $poExpectedDeliveryTs)
                                                : $poExpectedDeliveryRaw;
                                        }
                                        $poNotes = trim((string) ($purchaseOrder['notes'] ?? ''));
                                        $poNotesShort = $poNotes !== ''
                                            ? (strlen($poNotes) > 80 ? substr($poNotes, 0, 77) . '...' : $poNotes)
                                            : '-';
                                        $poSupplierLabel = (string) (($purchaseOrder['supplier_name'] ?? '') !== ''
                                            ? $purchaseOrder['supplier_name']
                                            : ('Supplier #' . (int) ($purchaseOrder['supplier_id'] ?? 0)));
                                        $poId = (int) ($purchaseOrder['id'] ?? 0);
                                        $poItems = $purchaseOrderItemsByOrder[$poId] ?? [];
                                        $poItemsCount = count($poItems);
                                        $poItemsLabel = $poItemsCount === 1 ? '1 item' : ($poItemsCount . ' items');
                                        $poFirstItemLabel = $poItemsCount > 0
                                            ? (string) ($poItems[0]['product_name'] ?? ('Product #' . (int) ($poItems[0]['product_id'] ?? 0)))
                                            : '';
                                        $poItemsSummary = $poItemsCount > 0
                                            ? ($poItemsLabel . ' - ' . $poFirstItemLabel)
                                            : '-';
                                        $poItemsJson = json_encode($poItems, JSON_UNESCAPED_UNICODE);
                                        if ($poItemsJson === false) {
                                            $poItemsJson = '[]';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?= e((string) ($purchaseOrder['po_no'] ?? '')) ?></strong></td>
                                        <td><?= e($poSupplierLabel) ?></td>
                                        <td title="<?= e($poItemsSummary) ?>"><?= e($poItemsSummary) ?></td>
                                        <td>Tsh <?= moneyFormat((float) ($purchaseOrder['amount'] ?? 0)) ?></td>
                                        <td><?= e($poExpectedDeliveryLabel) ?></td>
                                        <td><span class="status-badge <?= e($poStatusClass) ?>"><?= e($poStatus) ?></span></td>
                                        <td title="<?= e($poNotes !== '' ? $poNotes : '-') ?>"><?= e($poNotesShort) ?></td>
                                        <td><?= e($poDateLabel) ?></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewPO"
                                                data-po-id="<?= (int) ($purchaseOrder['id'] ?? 0) ?>"
                                                data-po-no="<?= e((string) ($purchaseOrder['po_no'] ?? '')) ?>"
                                                data-supplier="<?= e($poSupplierLabel) ?>"
                                                data-amount="<?= moneyFormat((float) ($purchaseOrder['amount'] ?? 0)) ?>"
                                                data-status="<?= e($poStatus) ?>"
                                                data-expected-delivery="<?= e($poExpectedDeliveryLabel) ?>"
                                                data-notes="<?= e($poNotes !== '' ? $poNotes : '-') ?>"
                                                data-items="<?= e($poItemsJson) ?>"
                                                data-date="<?= e($poDateLabel) ?>"
                                                title="View"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($poStatus) === 'pending'): ?>
                                                <button
                                                    class="btn-icon"
                                                    data-action="updatePOStatus"
                                                    data-value="<?= (int) ($purchaseOrder['id'] ?? 0) ?>"
                                                    data-status="Approved"
                                                    title="Approve PO"
                                                >
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="updatePOStatus"
                                                    data-value="<?= (int) ($purchaseOrder['id'] ?? 0) ?>"
                                                    data-status="Cancelled"
                                                    title="Cancel PO"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php elseif (strtolower($poStatus) === 'approved'): ?>
                                                <button
                                                    class="btn-icon"
                                                    data-action="updatePOStatus"
                                                    data-value="<?= (int) ($purchaseOrder['id'] ?? 0) ?>"
                                                    data-status="Received"
                                                    title="Mark Received"
                                                >
                                                    <i class="fa-solid fa-box-open"></i>
                                                </button>
                                                <button
                                                    class="btn-icon danger"
                                                    data-action="updatePOStatus"
                                                    data-value="<?= (int) ($purchaseOrder['id'] ?? 0) ?>"
                                                    data-status="Cancelled"
                                                    title="Cancel PO"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'returns'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-rotate-left"></i> Returns Management</h2>
                    <button class="btn btn-primary" data-action="openAddReturnModal">
                        <i class="fa-solid fa-plus"></i> Add Return
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Return No</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($returnsRecords) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-inbox"></i> No returns recorded yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($returnsRecords as $returnRow): ?>
                                    <?php
                                        $returnStatus = trim((string) ($returnRow['status'] ?? 'Pending'));
                                        $returnStatusClass = strtolower($returnStatus) === 'approved'
                                            ? 'success'
                                            : (strtolower($returnStatus) === 'rejected' ? 'danger' : 'warning');
                                        $returnDateRaw = (string) ($returnRow['created_at'] ?? '');
                                        $returnDate = $returnDateRaw;
                                        if ($returnDateRaw !== '') {
                                            try {
                                                $returnDate = (new DateTimeImmutable($returnDateRaw))->format('d M Y, H:i');
                                            } catch (Throwable $dateException) {
                                                $returnDate = $returnDateRaw;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= e((string) ($returnRow['return_no'] ?? '')) ?></td>
                                        <td><?= e((string) ($returnRow['product_name'] ?? ('Product #' . (int) ($returnRow['product_id'] ?? 0)))) ?></td>
                                        <td><?= (int) ($returnRow['quantity'] ?? 0) ?></td>
                                        <td><?= e((string) ($returnRow['reason'] ?? '-')) ?></td>
                                        <td><span class="status-pill <?= e($returnStatusClass) ?>"><?= e($returnStatus) ?></span></td>
                                        <td><?= e($returnDate) ?></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewReturn"
                                                data-return-no="<?= e((string) ($returnRow['return_no'] ?? '')) ?>"
                                                data-product="<?= e((string) ($returnRow['product_name'] ?? ('Product #' . (int) ($returnRow['product_id'] ?? 0)))) ?>"
                                                data-quantity="<?= (int) ($returnRow['quantity'] ?? 0) ?>"
                                                data-reason="<?= e((string) ($returnRow['reason'] ?? '-')) ?>"
                                                data-is-expired="<?= ((int) ($returnRow['is_expired'] ?? 0)) === 1 ? '1' : '0' ?>"
                                                data-status="<?= e($returnStatus) ?>"
                                                data-date="<?= e($returnDate) ?>"
                                                title="View Return"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($returnStatus) === 'pending'): ?>
                                                <button class="btn-icon" data-action="updateReturnStatus" data-value="<?= (int) ($returnRow['id'] ?? 0) ?>" data-status="Approved" data-reason="<?= e((string) ($returnRow['reason'] ?? '')) ?>" data-is-expired="<?= ((int) ($returnRow['is_expired'] ?? 0)) === 1 ? '1' : '0' ?>" title="Approve Return">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                                <button class="btn-icon danger" data-action="updateReturnStatus" data-value="<?= (int) ($returnRow['id'] ?? 0) ?>" data-status="Rejected" data-reason="<?= e((string) ($returnRow['reason'] ?? '')) ?>" data-is-expired="<?= ((int) ($returnRow['is_expired'] ?? 0)) === 1 ? '1' : '0' ?>" title="Reject Return">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-icon" title="Processed" disabled aria-disabled="true">
                                                    <i class="fa-regular fa-circle-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'appointments'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-calendar"></i> Appointments Management</h2>
                    <button class="btn btn-primary" data-action="openAddAppointmentModal">
                        <i class="fa-solid fa-plus"></i> Schedule Appointment
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($appointments) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-calendar-xmark"></i> No appointments scheduled yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): ?>
                                    <?php
                                        $appointmentStatus = trim((string) ($appointment['status'] ?? 'Scheduled'));
                                        $appointmentStatusClass = strtolower($appointmentStatus) === 'completed'
                                            ? 'success'
                                            : (strtolower($appointmentStatus) === 'cancelled' ? 'danger' : 'warning');
                                        $appointmentDateRaw = (string) ($appointment['appointment_date'] ?? '');
                                        $appointmentDateTs = strtotime($appointmentDateRaw);
                                        $appointmentDateLabel = $appointmentDateTs !== false
                                            ? date('M d, Y H:i', $appointmentDateTs)
                                            : $appointmentDateRaw;
                                    ?>
                                    <tr>
                                        <td><?= (int) ($appointment['id'] ?? 0) ?></td>
                                        <td><strong><?= e((string) ($appointment['title'] ?? '')) ?></strong></td>
                                        <td><?= e((string) (($appointment['customer_name'] ?? '') !== '' ? $appointment['customer_name'] : ('Customer #' . (int) ($appointment['customer_id'] ?? 0))) ) ?></td>
                                        <td><?= e($appointmentDateLabel) ?></td>
                                        <td><span class="status-badge <?= e($appointmentStatusClass) ?>"><?= e($appointmentStatus) ?></span></td>
                                        <td>
                                            <button
                                                class="btn-icon"
                                                data-action="viewAppointment"
                                                data-value="<?= (int) ($appointment['id'] ?? 0) ?>"
                                                data-title="<?= e((string) ($appointment['title'] ?? '')) ?>"
                                                data-customer="<?= e((string) (($appointment['customer_name'] ?? '') !== '' ? $appointment['customer_name'] : ('Customer #' . (int) ($appointment['customer_id'] ?? 0))) ) ?>"
                                                data-date="<?= e($appointmentDateLabel) ?>"
                                                data-status="<?= e($appointmentStatus) ?>"
                                                title="View"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <?php if (strtolower($appointmentStatus) !== 'completed'): ?>
                                                <button class="btn-icon" data-action="completeAppointment" data-value="<?= (int) ($appointment['id'] ?? 0) ?>" title="Mark Completed">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (strtolower($appointmentStatus) !== 'cancelled'): ?>
                                                <button class="btn-icon" data-action="cancelAppointment" data-value="<?= (int) ($appointment['id'] ?? 0) ?>" title="Cancel Appointment">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-icon danger" data-action="deleteAppointment" data-value="<?= (int) ($appointment['id'] ?? 0) ?>" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'locations'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-location-dot"></i> Store Locations</h2>
                    <button class="btn btn-primary" data-action="openAddLocationModal">
                        <i class="fa-solid fa-plus"></i> Add Location
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($configuredLocations) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-map"></i> No locations added yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($configuredLocations as $location): ?>
                                    <tr>
                                        <td><?= (int) ($location['id'] ?? 0) ?></td>
                                        <td><?= e((string) ($location['name'] ?? '')) ?></td>
                                        <td><?= e((string) ($location['address'] ?? '')) ?></td>
                                        <td><?= e((string) ($location['city'] ?? '')) ?></td>
                                        <td><?= e((string) ($location['phone'] ?? '')) ?></td>
                                        <td><span class="status-badge success"><?= e((string) ($location['status'] ?? 'Active')) ?></span></td>
                                        <td>
                                            <button class="btn-icon" title="Saved"><i class="fa-solid fa-check"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

        <?php elseif ($currentPage === 'messages'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-message"></i> Messages</h2>
                    <button class="btn btn-primary" data-action="openComposeMessageModal">
                        <i class="fa-solid fa-pen-to-square"></i> New Message
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-inbox"></i> No messages yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'security-logs'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-shield-halved"></i> Security Logs</h2>
                </div>

                <div class="data-table-container">
                    <div class="table-header" style="display:block;">
                        <form method="get" action="" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <input type="hidden" name="page" value="security-logs">
                            <div class="search-box" style="max-width:380px;">
                                <i class="fa-solid fa-search"></i>
                                <input type="text" name="q" value="<?= e($securityLogsSearch) ?>" placeholder="Search event, login, or IP...">
                            </div>
                            <div class="table-filters">
                                <select name="status" onchange="this.form.submit()">
                                    <?php foreach (['all' => 'All Status', 'success' => 'Success', 'failed' => 'Failed', 'blocked' => 'Blocked', 'denied' => 'Denied', 'error' => 'Error'] as $statusKey => $statusLabel): ?>
                                        <option value="<?= e($statusKey) ?>" <?= $securityLogsStatusFilter === $statusKey ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-filter"></i> Apply</button>
                            <a href="?page=security-logs" class="btn btn-secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
                        </form>
                    </div>

                    <table class="data-table" id="securityLogsTable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Login</th>
                                <th>User ID</th>
                                <th>IP</th>
                                <th>Meta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($securityAuditLogs) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">
                                        <i class="fa-solid fa-shield"></i> No security events found for this filter.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($securityAuditLogs as $event): ?>
                                    <?php
                                        $status = strtolower(trim((string) ($event['event_status'] ?? '')));
                                        $statusClass = 'warning';
                                        if ($status === 'success') {
                                            $statusClass = 'success';
                                        } elseif ($status === 'failed' || $status === 'denied' || $status === 'error' || $status === 'blocked') {
                                            $statusClass = 'danger';
                                        }

                                        $metaRaw = trim((string) ($event['meta_json'] ?? ''));
                                        $metaDisplay = $metaRaw;
                                        if (strlen($metaDisplay) > 120) {
                                            $metaDisplay = substr($metaDisplay, 0, 120) . '...';
                                        }
                                    ?>
                                    <tr data-status="<?= e($status) ?>">
                                        <td><?= date('M d, Y H:i:s', strtotime((string) ($event['created_at'] ?? 'now'))) ?></td>
                                        <td><code><?= e((string) ($event['event_type'] ?? '')) ?></code></td>
                                        <td><span class="status-badge <?= e($statusClass) ?>"><?= e(ucfirst($status !== '' ? $status : 'unknown')) ?></span></td>
                                        <td><?= e((string) ($event['login_identifier'] ?? '')) ?></td>
                                        <td><?= (int) ($event['user_id'] ?? 0) ?></td>
                                        <td><code><?= e((string) ($event['ip_address'] ?? '')) ?></code></td>
                                        <td title="<?= e($metaRaw) ?>"><?= e($metaDisplay) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'settings'): ?>
            <?php $selectedDenominations = getConfiguredDenominations($storeSettings); ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-gear"></i> Store Config</h2>
                </div>
                <div class="settings-redesign">
                    <section class="settings-hero">
                        <div>
                            <h3>Setup</h3>
                            <p>Cash, city, branches.</p>
                        </div>
                        <div class="hero-badge"><i class="fa-solid fa-location-dot"></i> Dar es Salaam</div>
                    </section>

                    <form method="post" action="?page=settings" id="storeConfigForm" class="settings-grid-form">
                        <input type="hidden" name="action" value="save_store_config">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                        <section class="settings-card">
                            <h4><i class="fa-solid fa-shop"></i> Profile</h4>
                            <div class="settings-two-col">
                                <div class="form-group">
                                    <label>Store Name</label>
                                    <input type="text" name="store_name" value="<?= e((string) ($storeSettings['store_name'] ?? 'Mchongoma Limited')) ?>" placeholder="Enter store name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Store Email</label>
                                    <input type="email" name="store_email" value="<?= e((string) ($storeSettings['store_email'] ?? '')) ?>" placeholder="Enter store email" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Store Phone</label>
                                    <input type="tel" name="store_phone" value="<?= e((string) ($storeSettings['store_phone'] ?? '')) ?>" placeholder="Enter store phone" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Default City</label>
                                    <input type="text" id="defaultCity" name="default_city" value="<?= e((string) ($storeSettings['default_city'] ?? 'Dar es Salaam')) ?>" placeholder="City" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Store Address</label>
                                <textarea name="store_address" placeholder="Enter store address" class="form-control" rows="3" required><?= e((string) ($storeSettings['store_address'] ?? 'Dar es Salaam')) ?></textarea>
                            </div>
                        </section>

                        <section class="settings-card">
                            <h4><i class="fa-solid fa-coins"></i> Cash</h4>
                            <div class="form-group">
                                <label>Starting Amount (Tsh)</label>
                                <input type="number" name="starting_amount" value="<?= e((string) ($storeSettings['starting_amount'] ?? '50')) ?>" min="50" step="1" class="form-control" required>
                                <small style="color:#6B7280;">Starting amount begins from 50 Tsh.</small>
                            </div>

                            <div class="form-group">
                                <label>Tanzania Denominations</label>
                                <div class="denomination-grid">
                                    <?php foreach ([50, 100, 200, 500, 1000, 2000, 5000, 10000] as $denomination): ?>
                                        <label class="denomination-chip">
                                            <input type="checkbox" name="cash_denominations[]" value="<?= $denomination ?>" <?= in_array($denomination, $selectedDenominations, true) ? 'checked' : '' ?>>
                                            <span>Tsh <?= number_format($denomination, 0, '.', ',') ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>

                        <section class="settings-card settings-card-full">
                            <h4><i class="fa-solid fa-mobile-screen-button"></i> Mobile Money Gateway</h4>
                            <div class="settings-two-col">
                                <div class="form-group">
                                    <label>Gateway Mode</label>
                                    <select name="mobile_money_mode" class="form-control">
                                        <option value="mock" <?= (($storeSettings['mobile_money_mode'] ?? 'mock') === 'mock') ? 'selected' : '' ?>>Mock (Testing)</option>
                                        <option value="live" <?= (($storeSettings['mobile_money_mode'] ?? 'mock') === 'live') ? 'selected' : '' ?>>Live</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Gateway Timeout (seconds)</label>
                                    <input type="number" name="mobile_money_timeout" value="<?= e((string) ($storeSettings['mobile_money_timeout'] ?? '15')) ?>" min="5" max="60" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Callback Secret</label>
                                    <input type="password" name="mobile_money_callback_secret" value="<?= e((string) ($storeSettings['mobile_money_callback_secret'] ?? '')) ?>" placeholder="Set long secret token" autocomplete="new-password" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Callback URL</label>
                                    <input type="text" value="<?= e((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php') . '/mobile_money_callback.php') ?>" readonly class="form-control">
                                </div>
                            </div>

                            <h5 style="margin-top:12px;">M-Pesa (Vodacom)</h5>
                            <div class="settings-two-col">
                                <div class="form-group">
                                    <label>M-Pesa API URL</label>
                                    <input type="url" name="mobile_money_mpesa_url" value="<?= e((string) ($storeSettings['mobile_money_mpesa_url'] ?? '')) ?>" placeholder="https://..." class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>M-Pesa API Token</label>
                                    <input type="password" name="mobile_money_mpesa_token" value="<?= e((string) ($storeSettings['mobile_money_mpesa_token'] ?? '')) ?>" placeholder="Enter token" autocomplete="new-password" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>M-Pesa Business ID</label>
                                    <input type="text" name="mobile_money_mpesa_business_id" value="<?= e((string) ($storeSettings['mobile_money_mpesa_business_id'] ?? '')) ?>" placeholder="Paybill/Till number" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>M-Pesa Command</label>
                                    <?php $mpesaCommand = (string) ($storeSettings['mobile_money_mpesa_command'] ?? 'customer_paybill'); ?>
                                    <select name="mobile_money_mpesa_command" class="form-control">
                                        <option value="customer_paybill" <?= $mpesaCommand === 'customer_paybill' ? 'selected' : '' ?>>customer_paybill</option>
                                        <option value="customer_buygoods" <?= $mpesaCommand === 'customer_buygoods' ? 'selected' : '' ?>>customer_buygoods</option>
                                        <option value="disburse" <?= $mpesaCommand === 'disburse' ? 'selected' : '' ?>>disburse</option>
                                    </select>
                                </div>
                            </div>

                            <h5 style="margin-top:12px;">Tigo Pesa</h5>
                            <div class="settings-two-col">
                                <div class="form-group">
                                    <label>Tigo Pesa API URL</label>
                                    <input type="url" name="mobile_money_tigo_url" value="<?= e((string) ($storeSettings['mobile_money_tigo_url'] ?? '')) ?>" placeholder="https://..." class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Tigo Pesa API Token</label>
                                    <input type="password" name="mobile_money_tigo_token" value="<?= e((string) ($storeSettings['mobile_money_tigo_token'] ?? '')) ?>" placeholder="Enter token" autocomplete="new-password" class="form-control">
                                </div>
                            </div>

                            <h5 style="margin-top:12px;">Airtel Money</h5>
                            <div class="settings-two-col">
                                <div class="form-group">
                                    <label>Airtel Money API URL</label>
                                    <input type="url" name="mobile_money_airtel_url" value="<?= e((string) ($storeSettings['mobile_money_airtel_url'] ?? '')) ?>" placeholder="https://..." class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Airtel Money API Token</label>
                                    <input type="password" name="mobile_money_airtel_token" value="<?= e((string) ($storeSettings['mobile_money_airtel_token'] ?? '')) ?>" placeholder="Enter token" autocomplete="new-password" class="form-control">
                                </div>
                            </div>
                        </section>

                        <section class="settings-card settings-card-full">
                            <h4><i class="fa-solid fa-map-location-dot"></i> Locations</h4>
                            <div id="locationRows" class="location-rows">
                                    <?php if (count($configuredLocations) > 0): ?>
                                        <?php foreach ($configuredLocations as $location): ?>
                                            <div data-location-row class="location-row">
                                                <input type="text" name="location_name[]" value="<?= e((string) ($location['name'] ?? '')) ?>" placeholder="Location name" class="form-control">
                                                <input type="text" name="location_address[]" value="<?= e((string) ($location['address'] ?? '')) ?>" placeholder="Address" class="form-control">
                                                <input type="text" name="location_city[]" value="<?= e((string) ($location['city'] ?? 'Dar es Salaam')) ?>" placeholder="City" class="form-control">
                                                <input type="text" name="location_phone[]" value="<?= e((string) ($location['phone'] ?? '')) ?>" placeholder="Phone" class="form-control">
                                                <button type="button" class="btn btn-secondary" data-action="removeLocationRow"><i class="fa-solid fa-minus"></i></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div data-location-row class="location-row">
                                            <input type="text" name="location_name[]" value="Main Store" placeholder="Location name" class="form-control">
                                            <input type="text" name="location_address[]" value="Dar es Salaam" placeholder="Address" class="form-control">
                                            <input type="text" name="location_city[]" value="Dar es Salaam" placeholder="City" class="form-control">
                                            <input type="text" name="location_phone[]" placeholder="Phone" class="form-control">
                                            <button type="button" class="btn btn-secondary" data-action="removeLocationRow"><i class="fa-solid fa-minus"></i></button>
                                        </div>
                                    <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-secondary" data-action="addLocationRow" style="margin-top:10px;">
                                <i class="fa-solid fa-plus"></i> Add Location
                            </button>
                        </section>

                        <div class="settings-submit-row">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                        </div>
                    </form>
                </div>
            </section>

        <?php else: ?>
            <!-- Coming Soon Page for other sections -->
            <section class="page-content coming-soon">
                <div class="coming-soon-box">
                    <i class="fa-solid fa-hammer"></i>
                    <h2><?= e($pageTitle) ?></h2>
                    <p>This section is under construction and will be available soon.</p>
                    <button class="btn btn-primary" data-action="go" data-value="?page=dashboard">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </button>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>

<!-- Modal Overlay -->
<div class="modal-overlay" id="modalOverlay" data-action="closeModal"></div>

<!-- Generic Modal -->
<div class="modal" id="modal">
    <div class="modal-header">
        <h3 id="modalTitle">Modal Title</h3>
        <button class="modal-close" data-action="closeModal">&times;</button>
    </div>
    <div class="modal-body" id="modalBody">
        <!-- Dynamic content -->
    </div>
    <div class="modal-footer" id="modalFooter">
        <!-- Dynamic buttons -->
    </div>
</div>

<!-- Notification Panel -->
<div class="notification-panel" id="notificationPanel">
    <div class="notification-header">
        <h4>Notifications</h4>
        <button data-action="closeNotifications">&times;</button>
    </div>
    <div class="notification-list">
        <?php if ($lowStock['count'] > 0): ?>
            <div class="notification-item warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Low Stock Alert</strong>
                    <p><?= $lowStock['count'] ?> products need restocking</p>
                </div>
            </div>
        <?php else: ?>
            <div class="notification-item success">
                <i class="fa-solid fa-check-circle"></i>
                <div>
                    <strong>All Good!</strong>
                    <p>No alerts at this time</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script type="application/json" id="appConfig"><?= json_encode([
    'salesChartData' => ['week' => $weeklySales, 'month' => $monthlySales],
    'currentPage' => $currentPage,
    'csrfToken' => getCsrfToken(),
    'saleCustomers' => $saleCustomerOptions,
    'saleProducts' => $saleProductOptions,
    'checkoutPaymentOptions' => getAvailableCheckoutPaymentOptions($storeSettings),
    'flashReceipt' => is_array($flashReceipt) ? $flashReceipt : null,
    'inventoryProducts' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'sku' => (string) ($item['sku'] ?? ''),
        'category' => (string) ($item['category'] ?? ''),
        'stock_qty' => (int) ($item['stock_qty'] ?? 0),
        'reorder_level' => (int) ($item['reorder_level'] ?? 5),
        'unit_price' => (float) ($item['unit_price'] ?? 0),
    ], $products),
    'poProducts' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'sku' => (string) ($item['sku'] ?? ''),
        'stock_qty' => (int) ($item['stock_qty'] ?? 0),
        'unit_price' => (float) ($item['unit_price'] ?? 0),
    ], $poProductOptions),
    'returnProducts' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'sku' => (string) ($item['sku'] ?? ''),
        'stock_qty' => (int) ($item['stock_qty'] ?? 0),
    ], $returnProductOptions),
    'customers' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'phone' => (string) ($item['phone'] ?? ''),
    ], $customers),
    'suppliers' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'status' => (string) ($item['status'] ?? 'Active'),
    ], $suppliers),
    'receivingPurchaseOrders' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'po_no' => (string) ($item['po_no'] ?? ''),
        'supplier_id' => (int) ($item['supplier_id'] ?? 0),
        'supplier_name' => (string) ($item['supplier_name'] ?? ''),
        'status' => (string) ($item['status'] ?? 'Pending'),
        'amount' => (float) ($item['amount'] ?? 0),
    ], $receivingPurchaseOrderOptions),
    'receivingRecords' => array_map(static function (array $item) use ($receivingItemsByRecord): array {
        $receivingId = (int) ($item['id'] ?? 0);
        $rawItems = $receivingItemsByRecord[$receivingId] ?? [];
        $items = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $rawItem) {
                if (!is_array($rawItem)) {
                    continue;
                }
                $items[] = [
                    'product_id' => (int) ($rawItem['product_id'] ?? 0),
                    'product_name' => (string) ($rawItem['product_name'] ?? ''),
                    'quantity_received' => (int) ($rawItem['quantity_received'] ?? 0),
                    'quantity_rejected' => (int) ($rawItem['quantity_rejected'] ?? 0),
                    'unit_cost' => (float) ($rawItem['unit_cost'] ?? 0),
                    'line_total' => (float) ($rawItem['line_total'] ?? 0),
                ];
            }
        }

        return [
            'id' => $receivingId,
            'receiving_no' => (string) ($item['receiving_no'] ?? ''),
            'supplier_id' => (int) ($item['supplier_id'] ?? 0),
            'purchase_order_id' => (int) ($item['purchase_order_id'] ?? 0),
            'purchase_order_no' => (string) ($item['po_no'] ?? ''),
            'supplier_name' => (string) ($item['supplier_name'] ?? ''),
            'status' => (string) ($item['status'] ?? 'Pending'),
            'amount' => (float) ($item['amount'] ?? 0),
            'created_at' => (string) ($item['created_at'] ?? ''),
            'items' => $items,
        ];
    }, $receivingRecords),
    'users' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'email' => (string) ($item['email'] ?? ''),
        'role' => (string) ($item['role'] ?? ''),
        'is_active' => ((int) ($item['is_active'] ?? 1)) === 1,
    ], $systemUsers),
    'userPermissionOverrides' => $userPermissionOverridesByUser,
    'availablePages' => array_map(
        static fn(string $key, array $meta): array => [
            'key' => $key,
            'title' => (string) ($meta['title'] ?? $key),
        ],
        array_keys($pages),
        array_values($pages)
    ),
    'dashboardEodSummary' => [
        'totalSales' => (float) ($dashboardEodSummary['totalSales'] ?? 0),
        'transactions' => (int) ($dashboardEodSummary['transactions'] ?? 0),
        'cash' => (float) ($dashboardEodSummary['cash'] ?? 0),
        'mobileMoney' => (float) ($dashboardEodSummary['mobileMoney'] ?? 0),
    ],
    'customerCredits' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'sale_id' => (int) ($item['sale_id'] ?? 0),
        'customer_id' => (int) ($item['customer_id'] ?? 0),
        'customer_name' => (string) ($item['customer_name'] ?? ''),
        'transaction_no' => (string) ($item['transaction_no'] ?? ''),
        'total_amount' => (float) ($item['total_amount'] ?? 0),
        'paid_amount' => (float) ($item['paid_amount'] ?? 0),
        'outstanding_amount' => (float) ($item['outstanding_amount'] ?? 0),
        'status' => (string) ($item['status'] ?? 'Open'),
        'due_date' => (string) ($item['due_date'] ?? ''),
        'created_at' => (string) ($item['created_at'] ?? ''),
    ], $customerCredits),
], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
