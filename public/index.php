<?php

declare(strict_types=1);

const IMPORT_MAX_ROWS = 20000;
const IMPORT_MAX_FILE_SIZE_BYTES = 25 * 1024 * 1024;

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

$pageController = new PageController();
$currentPage = $pageController->getCurrentPage();
$authUser = currentUser();
$userName = (string) ($authUser['name'] ?? 'User');
$userRole = (string) ($authUser['role'] ?? 'Staff');
$isDemoSession = (bool) ($authUser['is_demo'] ?? false);
$letters = preg_replace('/[^A-Za-z]/', '', $userName);
$userInitials = strtoupper(substr($letters !== null && $letters !== '' ? $letters : 'US', 0, 2));
$flashFeedback = $_SESSION['flash_feedback'] ?? null;
unset($_SESSION['flash_feedback']);

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
$saleProductOptions = [
    ['id' => 1, 'name' => 'Sugar 1kg', 'unit_price' => 3800],
    ['id' => 2, 'name' => 'Rice 1kg', 'unit_price' => 3500],
    ['id' => 3, 'name' => 'Soap Bar', 'unit_price' => 1200],
    ['id' => 4, 'name' => 'Milk 500ml', 'unit_price' => 1800],
];
$saleCustomerOptions = [
    ['id' => 1, 'name' => 'Walk-in Customer'],
    ['id' => 2, 'name' => 'Mchina'],
];
$allSales = $recentSales;
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

    if (isStoreConfigSaveRequest()) {
        $_SESSION['flash_feedback'] = handleStoreConfigSave($pdo);
        header('Location: ?page=settings');
        exit;
    }

    if (isEntityCreateRequest()) {
        $_SESSION['flash_feedback'] = handleEntityCreate($pdo, $currentPage, $userName);
        header('Location: ?page=' . urlencode($currentPage));
        exit;
    }

    if (isEntityUpdateRequest()) {
        $_SESSION['flash_feedback'] = handleEntityUpdate($pdo, $currentPage);
        header('Location: ?page=' . urlencode($currentPage));
        exit;
    }

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
    $saleProductOptions = array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'category' => (string) ($item['category'] ?? ''),
        'stock_qty' => (int) ($item['stock_qty'] ?? 0),
        'unit_price' => (float) ($item['unit_price'] ?? 0),
    ], $inventoryRepo->getProducts(500));
    $saleCustomerOptions = array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
    ], $customerRepo->getCustomers(500));
    $allSales = $salesRepo->getSales(50);
    $configuredLocations = $locationsRepo->getLocations(200);
    $mobileMoneyTransactions = $mobileMoneyRepo->getRecentTransactions(50);
    $storeSettings = getStoreSettings($pdo, $storeSettings);
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

function handleStoreConfigSave(PDO $pdo): array
{
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

function handleEntityCreate(PDO $pdo, string $currentPage, string $userName): array
{
    if (!hasValidCsrfToken()) {
        return [
            'type' => 'error',
            'message' => 'Request validation failed. Refresh the page and try again.',
        ];
    }

    $entity = (string) ($_POST['entity'] ?? '');

    try {
        switch ($entity) {
            case 'sale':
                $paymentMethod = (string) ($_POST['payment_method'] ?? 'Cash');
                $customerId = (int) ($_POST['customer_id'] ?? 0);
                $productId = (int) ($_POST['product_id'] ?? 0);
                $quantity = (int) ($_POST['quantity'] ?? 0);
                $amount = (float) ($_POST['amount'] ?? 0);

                if ($customerId <= 0) {
                    throw new RuntimeException('Customer is required for a sale.');
                }
                if ($productId <= 0) {
                    throw new RuntimeException('Product is required for a sale.');
                }
                if ($quantity <= 0) {
                    throw new RuntimeException('Quantity must be greater than zero.');
                }

                $mobileResult = null;

                $pdo->beginTransaction();
                try {
                    $inventoryRepo = new InventoryRepository($pdo);
                    $product = $inventoryRepo->getProduct($productId);
                    if (!is_array($product)) {
                        throw new RuntimeException('Selected product was not found.');
                    }

                    $derivedAmount = (float) ($product['unit_price'] ?? 0) * $quantity;
                    if ($derivedAmount > 0) {
                        $amount = $derivedAmount;
                    }

                    if ($paymentMethod === 'Mobile Money') {
                        $gateway = new MobileMoneyGateway($pdo);
                        $mobileResult = $gateway->initiate([
                            'provider' => (string) ($_POST['mobile_money_provider'] ?? ''),
                            'phone' => (string) ($_POST['mobile_money_phone'] ?? ''),
                            'amount' => $amount,
                            'currency' => 'TZS',
                            'reference' => (string) ($_POST['mobile_money_reference'] ?? ''),
                        ]);

                        if (!($mobileResult['success'] ?? false)) {
                            throw new RuntimeException((string) ($mobileResult['message'] ?? 'Mobile money payment failed.'));
                        }
                    }

                    $inventoryRepo->deductStock($productId, $quantity);

                    $saleId = (new SalesRepository($pdo))->createSale([
                        'customer_id' => $customerId,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                    ]);

                    if (
                        is_array($mobileResult)
                        && isset($mobileResult['transaction_id'])
                        && isset($gateway)
                    ) {
                        $gateway->attachSaleId((int) $mobileResult['transaction_id'], $saleId);
                    }

                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }

                $message = 'Sale created successfully. Stock deducted for quantity ' . $quantity . '.';
                if (is_array($mobileResult)) {
                    $message .= ' ' . (string) ($mobileResult['message'] ?? '');
                    if (!empty($mobileResult['external_reference'])) {
                        $message .= ' Ref: ' . (string) $mobileResult['external_reference'] . '.';
                    }
                }

                return ['type' => 'success', 'message' => trim($message)];

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
                ]);
                return ['type' => 'success', 'message' => 'Invoice created successfully.'];

            case 'delivery':
                (new DeliveriesRepository($pdo))->createDelivery([
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Delivery created successfully.'];

            case 'receiving':
                (new ReceivingRepository($pdo))->createReceiving([
                    'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Receiving record created successfully.'];

            case 'quotation':
                (new QuotationsRepository($pdo))->createQuotation([
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Quotation created successfully.'];

            case 'purchase_order':
                (new PurchaseOrdersRepository($pdo))->createOrder([
                    'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
                    'amount' => (float) ($_POST['amount'] ?? 0),
                ]);
                return ['type' => 'success', 'message' => 'Purchase order created successfully.'];

            case 'return':
                (new ReturnsRepository($pdo))->createReturn([
                    'product_id' => (int) ($_POST['product_id'] ?? 0),
                    'quantity' => (int) ($_POST['quantity'] ?? 0),
                    'reason' => (string) ($_POST['reason'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Return created successfully.'];

            case 'appointment':
                (new AppointmentsRepository($pdo))->createAppointment([
                    'title' => (string) ($_POST['title'] ?? ''),
                    'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                    'appointment_date' => (string) ($_POST['appointment_date'] ?? ''),
                ]);
                return ['type' => 'success', 'message' => 'Appointment created successfully.'];

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
        return [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Save failed: ' . $exception->getMessage()
                : 'Could not save the record. Please check your input and try again.',
        ];
    }
}

function handleEntityUpdate(PDO $pdo, string $currentPage): array
{
    if (!hasValidCsrfToken()) {
        return [
            'type' => 'error',
            'message' => 'Request validation failed. Refresh the page and try again.',
        ];
    }

    $entity = (string) ($_POST['entity'] ?? '');

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
        }

        return [
            'type' => 'warning',
            'message' => 'Unknown update action requested. No changes were made.',
        ];
    } catch (Throwable $exception) {
        error_log('[POS Update] ' . $exception->getMessage());
        return [
            'type' => 'error',
            'message' => isDebugMode()
                ? 'Update failed: ' . $exception->getMessage()
                : 'Could not update the record. Please check your input and try again.',
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
        $invalidRows = count($parsed['errors']);
        $importedRows = (int) ($result['processed'] ?? 0);
        $totalDataRows = $importedRows + $invalidRows;
        $message = sprintf(
            'Import complete: %d total data rows, %d imported (%d created, %d updated), %d invalid, %d empty rows skipped.',
            $totalDataRows,
            $importedRows,
            $result['created'],
            $result['updated'],
            $invalidRows,
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
<body class="<?= $currentPage === 'sales' ? 'sales-page' : '' ?>">
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
            <section class="stats-grid">
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
                        <p>Total Products</p>
                        <h2><?= moneyFormat($totals['totalItems']) ?></h2>
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

            <section class="welcome">Welcome to Mchongoma Limited, <?= e($userName) ?>! Choose a common task below to get started.</section>

            <section class="quick-actions">
                <button data-action="showNewSaleModal"><i class="fa-solid fa-cart-plus"></i> Start a New Sale</button>
                <button data-action="go" data-value="?page=inventory"><i class="fa-solid fa-cube"></i> View All Products</button>
                <button data-action="go" data-value="?page=customers"><i class="fa-regular fa-user"></i> View Customers</button>
                <button data-action="go" data-value="?page=reports"><i class="fa-solid fa-file-lines"></i> View All Reports</button>
                <button data-action="go" data-value="?page=transactions"><i class="fa-regular fa-rectangle-list"></i> All Transactions</button>
                <button data-action="go" data-value="?page=suppliers"><i class="fa-solid fa-arrow-right"></i> Manage Suppliers</button>
                <button data-action="showEndOfDayReport"><i class="fa-regular fa-calendar-check"></i> End of Day Report</button>
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
                        <p style="margin-top:6px; color:#6B7280; font-size:13px;">
                            <?= moneyFormat($inventoryProductCount) ?> items found
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
                                <tr data-stock="<?= $product['stock_qty'] <= $product['reorder_level'] ? 'low' : 'ok' ?>">
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
                    <button class="btn btn-primary" data-action="showAddCustomerModal">
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
                                        <button class="btn-icon" data-action="viewCustomer" data-value="<?= (int) $customer['id'] ?>" title="View">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button class="btn-icon" data-action="editCustomer" data-value="<?= (int) $customer['id'] ?>" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="btn-icon danger" data-action="deleteCustomer" data-value="<?= (int) $customer['id'] ?>" title="Delete">
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
            <?php if ($currentPage === 'sales'): ?>
                <section class="page-content sales-pos-shell">
                    <div class="sales-layout">
                        <div class="sales-products-panel">
                            <div class="sales-search-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="salesProductSearch" placeholder="Search products or scan barcode...">
                            </div>

                            <div class="sales-category-row">
                                <button type="button" class="sales-chip active">All</button>
                            </div>

                            <div class="sales-products-grid" id="salesProductsGrid">
                                <?php foreach ($saleProductOptions as $product): ?>
                                    <?php
                                        $productName = (string) ($product['name'] ?? 'Product');
                                        $firstLetter = strtoupper(substr(trim($productName), 0, 1));
                                        $stockQty = (int) ($product['stock_qty'] ?? 0);
                                        $category = trim((string) ($product['category'] ?? ''));
                                        $searchBlob = strtolower($productName . ' ' . $category);
                                    ?>
                                    <button
                                        type="button"
                                        class="sales-product-card"
                                        data-product-id="<?= (int) ($product['id'] ?? 0) ?>"
                                        data-product-name="<?= e($productName) ?>"
                                        data-product-price="<?= (float) ($product['unit_price'] ?? 0) ?>"
                                        data-product-stock="<?= $stockQty ?>"
                                        data-product-search="<?= e($searchBlob) ?>"
                                    >
                                        <span class="sales-product-icon"><?= e($firstLetter !== '' ? $firstLetter : 'P') ?></span>
                                        <strong><?= e($productName) ?></strong>
                                        <span class="sales-product-price">Tsh <?= moneyFormat((float) ($product['unit_price'] ?? 0)) ?></span>
                                        <span class="sales-product-stock"><?= $stockQty ?> in stock</span>
                                    </button>
                                <?php endforeach; ?>
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
                                <select onchange="filterByPayment('salesTable', this.value)">
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
            <section class="page-content">
                <div class="page-header">
                    <div class="page-info">
                        <h2>Reports & Analytics</h2>
                        <p>View business insights and generate reports</p>
                    </div>
                    <a class="btn btn-secondary" href="export_report_pdf.php?type=daily" target="_blank" rel="noopener">
                        <i class="fa-solid fa-file-pdf"></i> Export Daily PDF
                    </a>
                </div>

                <div class="reports-grid">
                    <article class="report-card" data-action="generateReport" data-value="daily">
                        <div class="report-icon blue"><i class="fa-solid fa-calendar-day"></i></div>
                        <h3>Daily Sales Report</h3>
                        <p>View today's sales summary and transactions</p>
                    </article>
                    <article class="report-card" data-action="generateReport" data-value="weekly">
                        <div class="report-icon green"><i class="fa-solid fa-calendar-week"></i></div>
                        <h3>Weekly Sales Report</h3>
                        <p>Sales performance for the past 7 days</p>
                    </article>
                    <article class="report-card" data-action="generateReport" data-value="monthly">
                        <div class="report-icon purple"><i class="fa-solid fa-calendar"></i></div>
                        <h3>Monthly Sales Report</h3>
                        <p>Complete monthly breakdown and trends</p>
                    </article>
                    <article class="report-card" data-action="generateReport" data-value="inventory">
                        <div class="report-icon orange"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <h3>Inventory Report</h3>
                        <p>Stock levels and low inventory alerts</p>
                    </article>
                    <article class="report-card" data-action="generateReport" data-value="customers">
                        <div class="report-icon pink"><i class="fa-solid fa-users"></i></div>
                        <h3>Customer Report</h3>
                        <p>Customer purchases and loyalty insights</p>
                    </article>
                    <article class="report-card" data-action="generateReport" data-value="profit">
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
    'inventoryProducts' => array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'sku' => (string) ($item['sku'] ?? ''),
        'category' => (string) ($item['category'] ?? ''),
        'stock_qty' => (int) ($item['stock_qty'] ?? 0),
        'reorder_level' => (int) ($item['reorder_level'] ?? 5),
        'unit_price' => (float) ($item['unit_price'] ?? 0),
    ], $products),
], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
