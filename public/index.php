<?php

declare(strict_types=1);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'");

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

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

$pageController = new PageController();
$currentPage = $pageController->getCurrentPage();
$pageTitle = $pageController->getPageTitle();

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
    'totalItems' => 720,
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
$allSales = $recentSales;

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

    if (isInventoryImportRequest()) {
        $importFeedback = handleProductImport($inventoryRepo);
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
    $products = $inventoryRepo->getProducts(50);
    $customers = $customerRepo->getCustomers(50);
    $allSales = $salesRepo->getSales(50);
} catch (Throwable $exception) {
    $usingDemoData = true;
    error_log('[POS Dashboard] ' . $exception->getMessage());
    $errorMessage = isDebugMode()
        ? $exception->getMessage()
        : 'Database is unavailable. Showing demo data until connection is restored.';

    if (isInventoryImportRequest()) {
        $importFeedback = [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Import failed: ' . $exception->getMessage()
                : 'Import failed. Please check your file and try again.',
        ];
    }
}

function moneyFormat(float|int $amount): string
{
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
    $requiredHeaders = ['name', 'sku', 'unit_price'];

    $hasHeaderRow = !array_diff($requiredHeaders, $normalizedHeaders);
    $headerMap = [];
    $rows = [];
    $errors = [];
    $skipped = 0;
    $lineNumber = 1;
    $maxRows = 5000;
    $processedRows = 0;
    $seenSkus = [];

    if ($hasHeaderRow) {
        foreach ($normalizedHeaders as $index => $header) {
            $headerMap[$header] = $index;
        }
    } else {
        $headerMap = [
            'name' => 0,
            'sku' => 1,
            'unit_price' => 2,
            'stock_qty' => 3,
            'reorder_level' => 4,
        ];

        $row = $firstRow;
        $lineNumber = 1;
        $name = trim((string) ($row[$headerMap['name']] ?? ''));
        $sku = trim((string) ($row[$headerMap['sku']] ?? ''));

        if ($name !== '' || $sku !== '') {
            if ($name === '' || $sku === '') {
                $errors[] = 'Row 1: name and sku are required.';
            } elseif (isset($seenSkus[strtolower($sku)])) {
                $errors[] = 'Row 1: duplicate sku in file (' . $sku . ').';
            } else {
                $unitPrice = parseFloatValue((string) ($row[$headerMap['unit_price']] ?? ''), null);
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
            throw new RuntimeException('CSV exceeds the maximum of 5000 data rows. Please split the file and try again.');
        }

        $name = trim((string) ($row[$headerMap['name']] ?? ''));
        $sku = trim((string) ($row[$headerMap['sku']] ?? ''));

        if ($name === '' || $sku === '') {
            $errors[] = 'Row ' . $lineNumber . ': name and sku are required.';
            continue;
        }

        if (isset($seenSkus[strtolower($sku)])) {
            $errors[] = 'Row ' . $lineNumber . ': duplicate sku in file (' . $sku . ').';
            continue;
        }

        $unitPrice = parseFloatValue((string) ($row[$headerMap['unit_price']] ?? ''), null);
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

function handleProductImport(InventoryRepository $inventoryRepo): array
{
    if (!hasValidCsrfToken()) {
        return [
            'type' => 'error',
            'message' => 'Request validation failed. Refresh the page and try importing again.',
        ];
    }

    if (!isset($_FILES['product_import_file'])) {
        return [
            'type' => 'error',
            'message' => 'No file uploaded. Choose a CSV file exported from Excel.',
        ];
    }

    $file = $_FILES['product_import_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [
            'type' => 'error',
            'message' => 'Upload failed. Please try again with a valid CSV file.',
        ];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'type' => 'error',
            'message' => 'Invalid upload source. Please choose the file again.',
        ];
    }

    $maxFileSizeBytes = 2 * 1024 * 1024;
    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > $maxFileSizeBytes) {
        return [
            'type' => 'error',
            'message' => 'File size must be greater than 0 and not exceed 2MB.',
        ];
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        return [
            'type' => 'error',
            'message' => 'Unsupported file type. Save your Excel file as CSV, then import it.',
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
        'application/octet-stream',
    ];

    if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true)) {
        return [
            'type' => 'error',
            'message' => 'Invalid file content type. Please upload a CSV exported from Excel.',
        ];
    }

    try {
        $parsed = buildProductRowsFromCsv($tmpName);

        if (count($parsed['rows']) === 0) {
            return [
                'type' => 'warning',
                'message' => 'No valid product rows found. Ensure CSV has columns: name, sku, unit_price, stock_qty, reorder_level.',
            ];
        }

        $result = $inventoryRepo->importProductsFromRows($parsed['rows']);
        $message = sprintf(
            'Import complete: %d created, %d updated, %d empty rows skipped.',
            $result['created'],
            $result['updated'],
            $parsed['skipped']
        );

        if (!empty($parsed['errors'])) {
            $message .= ' Some rows had issues: ' . implode(' | ', array_slice($parsed['errors'], 0, 3));
        }

        return [
            'type' => 'success',
            'message' => $message,
        ];
    } catch (Throwable $exception) {
        error_log('[POS Import] ' . $exception->getMessage());
        return [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Import failed: ' . $exception->getMessage()
                : 'Import failed while processing the file. Please review your CSV and try again.',
        ];
    }
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
<body>
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
                <a class="menu-item <?= $pageController->isActive($key) ? 'active' : '' ?>" href="?page=<?= e($key) ?>">
                    <i class="fa-solid <?= e($page['icon']) ?>"></i><?= e($page['title']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="profile">
                <span class="avatar">AD</span>
                <div>
                    <strong>Admin User</strong>
                    <small>Admin</small>
                </div>
            </div>
            <a class="logout" href="#" onclick="showLogoutConfirm()"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <strong><?= e($pageTitle) ?></strong>
            </div>
            <div class="topbar-actions">
                <button class="pill primary" onclick="showAddModal()"><i class="fa-solid fa-plus"></i> Add</button>
                <span class="pill" id="clock">--:-- --</span>
                <span class="pill">EN</span>
                <span class="pill"><i class="fa-solid fa-store"></i> Shop</span>
                <span class="icon-btn notification-btn" onclick="showNotifications()">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($lowStock['count'] > 0): ?>
                        <span class="badge"><?= $lowStock['count'] ?></span>
                    <?php endif; ?>
                </span>
                <span class="pill">Admin User</span>
            </div>
        </header>

        <?php if ($usingDemoData): ?>
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

        <?php if ($currentPage === 'dashboard'): ?>
            <!-- Dashboard Content -->
            <section class="stats-grid">
                <article class="stat-card stat-blue" onclick="window.location='?page=sales'">
                    <div>
                        <p>Total Sales</p>
                        <h2>Tsh <?= moneyFormat($totals['totalSales']) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-chart-line"></i></span>
                </article>
                <article class="stat-card stat-green" onclick="window.location='?page=customers'">
                    <div>
                        <p>Total Customers</p>
                        <h2><?= moneyFormat($totals['totalCustomers']) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-users"></i></span>
                </article>
                <article class="stat-card stat-pink" onclick="window.location='?page=inventory'">
                    <div>
                        <p>Total Products</p>
                        <h2><?= moneyFormat($totals['totalItems']) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-cube"></i></span>
                </article>
                <article class="stat-card stat-orange" onclick="window.location='?page=transactions'">
                    <div>
                        <p>Transactions Today</p>
                        <h2><?= moneyFormat($totals['transactionsToday']) ?></h2>
                    </div>
                    <span><i class="fa-solid fa-receipt"></i></span>
                </article>
            </section>

            <section class="welcome">Welcome to Mchongoma Limited, Admin! Choose a common task below to get started.</section>

            <section class="quick-actions">
                <button onclick="showNewSaleModal()"><i class="fa-solid fa-cart-plus"></i> Start a New Sale</button>
                <button onclick="window.location='?page=inventory'"><i class="fa-solid fa-cube"></i> View All Products</button>
                <button onclick="window.location='?page=customers'"><i class="fa-regular fa-user"></i> View Customers</button>
                <button onclick="window.location='?page=reports'"><i class="fa-solid fa-file-lines"></i> View All Reports</button>
                <button onclick="window.location='?page=transactions'"><i class="fa-regular fa-rectangle-list"></i> All Transactions</button>
                <button onclick="window.location='?page=suppliers'"><i class="fa-solid fa-arrow-right"></i> Manage Suppliers</button>
                <button onclick="showEndOfDayReport()"><i class="fa-regular fa-calendar-check"></i> End of Day Report</button>
            </section>

            <section class="bottom-grid">
                <article class="panel chart-panel">
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
                    <article class="panel low-stock-panel">
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

                    <article class="panel recent-sales">
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
            <section class="page-content">
                <div class="page-header">
                    <div class="page-info">
                        <h2>Product Inventory</h2>
                        <p>Manage your products and stock levels</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-secondary" onclick="showImportProductsModal()">
                            <i class="fa-solid fa-file-import"></i> Import Excel
                        </button>
                        <button class="btn btn-primary" onclick="showAddProductModal()">
                            <i class="fa-solid fa-plus"></i> Add Product
                        </button>
                    </div>
                </div>

                <?php if ($importFeedback): ?>
                    <section class="import-feedback <?= e($importFeedback['type']) ?>">
                        <i class="fa-solid <?= $importFeedback['type'] === 'success' ? 'fa-circle-check' : ($importFeedback['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') ?>"></i>
                        <?= e($importFeedback['message']) ?>
                    </section>
                <?php endif; ?>

                <div class="data-table-container">
                    <div class="table-header">
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
                        </div>
                    </div>
                    <table class="data-table" id="productTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Stock Qty</th>
                                <th>Reorder Level</th>
                                <th>Unit Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr data-stock="<?= $product['stock_qty'] <= $product['reorder_level'] ? 'low' : 'ok' ?>">
                                    <td><strong><?= e($product['name']) ?></strong></td>
                                    <td><code><?= e($product['sku']) ?></code></td>
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
                                        <button class="btn-icon" onclick="editProduct(<?= $product['id'] ?>)" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="btn-icon danger" onclick="deleteProduct(<?= $product['id'] ?>)" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                    <button class="btn btn-primary" onclick="showAddCustomerModal()">
                        <i class="fa-solid fa-plus"></i> Add Customer
                    </button>
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
                                <th>Member Since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><strong><?= e($customer['name']) ?></strong></td>
                                    <td><?= e($customer['phone'] ?? 'N/A') ?></td>
                                    <td><?= $customer['total_orders'] ?></td>
                                    <td>Tsh <?= moneyFormat($customer['total_spent']) ?></td>
                                    <td><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                                    <td>
                                        <button class="btn-icon" onclick="viewCustomer(<?= $customer['id'] ?>)" title="View">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button class="btn-icon" onclick="editCustomer(<?= $customer['id'] ?>)" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="btn-icon danger" onclick="deleteCustomer(<?= $customer['id'] ?>)" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'sales' || $currentPage === 'transactions'): ?>
            <!-- Sales/Transactions Page -->
            <section class="page-content">
                <div class="page-header">
                    <div class="page-info">
                        <h2><?= $currentPage === 'sales' ? 'Point of Sale' : 'Transaction History' ?></h2>
                        <p><?= $currentPage === 'sales' ? 'Create and manage sales' : 'View all transactions' ?></p>
                    </div>
                    <?php if ($currentPage === 'sales'): ?>
                        <button class="btn btn-primary" onclick="showNewSaleModal()">
                            <i class="fa-solid fa-plus"></i> New Sale
                        </button>
                    <?php endif; ?>
                </div>

                <div class="data-table-container">
                    <div class="table-header">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="salesSearch" placeholder="Search transactions..." onkeyup="filterTable('salesTable', this.value)">
                        </div>
                        <div class="table-filters">
                            <select>
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
                                <tr>
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
                                        <button class="btn-icon" onclick="viewReceipt('<?= e($sale['transaction_no']) ?>')" title="View Receipt">
                                            <i class="fa-solid fa-receipt"></i>
                                        </button>
                                        <button class="btn-icon" onclick="printReceipt('<?= e($sale['transaction_no']) ?>')" title="Print">
                                            <i class="fa-solid fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'reports'): ?>
            <!-- Reports Page -->
            <section class="page-content">
                <div class="page-header">
                    <div class="page-info">
                        <h2>Reports & Analytics</h2>
                        <p>View business insights and generate reports</p>
                    </div>
                </div>

                <div class="reports-grid">
                    <article class="report-card" onclick="generateReport('daily')">
                        <div class="report-icon blue"><i class="fa-solid fa-calendar-day"></i></div>
                        <h3>Daily Sales Report</h3>
                        <p>View today's sales summary and transactions</p>
                    </article>
                    <article class="report-card" onclick="generateReport('weekly')">
                        <div class="report-icon green"><i class="fa-solid fa-calendar-week"></i></div>
                        <h3>Weekly Sales Report</h3>
                        <p>Sales performance for the past 7 days</p>
                    </article>
                    <article class="report-card" onclick="generateReport('monthly')">
                        <div class="report-icon purple"><i class="fa-solid fa-calendar"></i></div>
                        <h3>Monthly Sales Report</h3>
                        <p>Complete monthly breakdown and trends</p>
                    </article>
                    <article class="report-card" onclick="generateReport('inventory')">
                        <div class="report-icon orange"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <h3>Inventory Report</h3>
                        <p>Stock levels and low inventory alerts</p>
                    </article>
                    <article class="report-card" onclick="generateReport('customers')">
                        <div class="report-icon pink"><i class="fa-solid fa-users"></i></div>
                        <h3>Customer Report</h3>
                        <p>Customer purchases and loyalty insights</p>
                    </article>
                    <article class="report-card" onclick="generateReport('profit')">
                        <div class="report-icon teal"><i class="fa-solid fa-chart-pie"></i></div>
                        <h3>Profit & Loss</h3>
                        <p>Revenue, expenses, and profit margins</p>
                    </article>
                </div>
            </section>

        <?php elseif ($currentPage === 'suppliers'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-truck"></i> Suppliers Management</h2>
                    <button class="btn btn-primary" onclick="openAddSupplierModal()">
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
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-box-open"></i> No suppliers added yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'employees'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-users"></i> Employees Management</h2>
                    <button class="btn btn-primary" onclick="openAddEmployeeModal()">
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
                            <tr>
                                <td colspan="8" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-users-slash"></i> No employees added yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'expenses'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-credit-card"></i> Expenses Management</h2>
                    <button class="btn btn-primary" onclick="openAddExpenseModal()">
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
                    <button class="btn btn-primary" onclick="openAddInvoiceModal()">
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
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-inbox"></i> No invoices created yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'deliveries'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-boxes-stacked"></i> Deliveries Management</h2>
                    <button class="btn btn-primary" onclick="openAddDeliveryModal()">
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
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-truck"></i> No deliveries recorded yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'receiving'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-box"></i> Receiving Management</h2>
                    <button class="btn btn-primary" onclick="openAddReceivingModal()">
                        <i class="fa-solid fa-plus"></i> Add Receiving
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Receiving No</th>
                                <th>Supplier</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-inbox"></i> No receiving records yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'quotations'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-rectangle-list"></i> Quotations Management</h2>
                    <button class="btn btn-primary" onclick="openAddQuotationModal()">
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
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-rectangle-list"></i> No quotations created yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'purchase-orders'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-cart-plus"></i> Purchase Orders Management</h2>
                    <button class="btn btn-primary" onclick="openAddPOModal()">
                        <i class="fa-solid fa-plus"></i> Create PO
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PO No</th>
                                <th>Supplier</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-cart"></i> No purchase orders created yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'returns'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-rotate-left"></i> Returns Management</h2>
                    <button class="btn btn-primary" onclick="openAddReturnModal()">
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
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-inbox"></i> No returns recorded yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'appointments'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-calendar"></i> Appointments Management</h2>
                    <button class="btn btn-primary" onclick="openAddAppointmentModal()">
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
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-calendar-xmark"></i> No appointments scheduled yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'locations'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-location-dot"></i> Store Locations</h2>
                    <button class="btn btn-primary" onclick="openAddLocationModal()">
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
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 20px;">
                                    <i class="fa-solid fa-map"></i> No locations added yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($currentPage === 'messages'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-message"></i> Messages</h2>
                    <button class="btn btn-primary" onclick="openComposeMessageModal()">
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

        <?php elseif ($currentPage === 'settings'): ?>
            <section class="page-content">
                <div class="content-header">
                    <h2><i class="fa-solid fa-gear"></i> Store Configuration</h2>
                </div>
                <div class="settings-container">
                    <div class="settings-panel">
                        <h3>Basic Settings</h3>
                        <form>
                            <div class="form-group">
                                <label>Store Name</label>
                                <input type="text" placeholder="Enter store name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Store Email</label>
                                <input type="email" placeholder="Enter store email" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Store Phone</label>
                                <input type="tel" placeholder="Enter store phone" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Store Address</label>
                                <textarea placeholder="Enter store address" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="button" class="btn btn-primary">Save Settings</button>
                        </form>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <!-- Coming Soon Page for other sections -->
            <section class="page-content coming-soon">
                <div class="coming-soon-box">
                    <i class="fa-solid fa-hammer"></i>
                    <h2><?= e($pageTitle) ?></h2>
                    <p>This section is under construction and will be available soon.</p>
                    <button class="btn btn-primary" onclick="window.location='?page=dashboard'">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </button>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>

<!-- Modal Overlay -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>

<!-- Generic Modal -->
<div class="modal" id="modal">
    <div class="modal-header">
        <h3 id="modalTitle">Modal Title</h3>
        <button class="modal-close" onclick="closeModal()">&times;</button>
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
        <button onclick="closeNotifications()">&times;</button>
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
<script>
    window.salesChartData = {
        week: <?= json_encode($weeklySales, JSON_THROW_ON_ERROR) ?>,
        month: <?= json_encode($monthlySales, JSON_THROW_ON_ERROR) ?>
    };
    window.currentPage = '<?= e($currentPage) ?>';
    window.csrfToken = <?= json_encode(getCsrfToken(), JSON_THROW_ON_ERROR) ?>;
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
