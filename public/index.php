<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/DashboardRepository.php';

$usingDemoData = false;
$errorMessage = null;

$totals = [
    'totalSales' => 1035400,
    'totalCustomers' => 1,
    'totalItems' => 720,
    'transactionsToday' => 1,
];
$weeklySales = [
    'labels' => ['18', '19', '20', '21', '22', '23', '24'],
    'values' => [6000, 0, 8000, 90000, 0, 600000, 1035400],
];
$lowStock = ['count' => 0, 'message' => 'All products are well stocked.'];
$recentSales = [
    ['customer_name' => 'Walk-in Customer', 'transaction_no' => 'TXN-20260324-141851106-101', 'amount' => 1035400, 'payment_method' => 'Cash', 'created_at' => '2026-03-24 22:18:51'],
    ['customer_name' => 'Walk-in Customer', 'transaction_no' => 'TXN-20260323-055039687-596', 'amount' => 89900, 'payment_method' => 'Cash', 'created_at' => '2026-03-23 17:50:39'],
    ['customer_name' => 'Mchina', 'transaction_no' => 'TXN-20260323-053456909-829', 'amount' => 51000, 'payment_method' => 'Cash', 'created_at' => '2026-03-23 17:34:56'],
    ['customer_name' => 'Mchina', 'transaction_no' => 'TXN-20260321-0904', 'amount' => 5500, 'payment_method' => 'Cash', 'created_at' => '2026-03-21 09:04:00'],
];

try {
    $repo = new DashboardRepository(getDatabaseConnection());
    $totals = $repo->getTotals();
    $weeklySales = $repo->getWeeklySales();
    $lowStock = $repo->getLowStockSummary();
    $recentSales = $repo->getRecentSales(4);
} catch (Throwable $exception) {
    $usingDemoData = true;
    $errorMessage = $exception->getMessage();
}

function moneyFormat(float|int $amount): string
{
    return number_format((float) $amount, 0, '.', ',');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-icon"><i class="fa-solid fa-bag-shopping"></i></span>
            <div>
                <h1>Mchongoma<br>Limited</h1>
            </div>
        </div>

        <nav class="menu">
            <a class="menu-item active" href="#"><i class="fa-solid fa-table-cells-large"></i>Dashboard</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-cube"></i>Inventory</a>
            <a class="menu-item" href="#"><i class="fa-regular fa-user"></i>Customers</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-truck"></i>Suppliers</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-chart-column"></i>Reports</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-box"></i>Receiving</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-cart-shopping"></i>Sales</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-boxes-stacked"></i>Deliveries</a>
            <a class="menu-item" href="#"><i class="fa-regular fa-credit-card"></i>Expenses</a>
            <a class="menu-item" href="#"><i class="fa-regular fa-calendar"></i>Appointments</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-users"></i>Employees</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-gear"></i>Store Config</a>
            <a class="menu-item" href="#"><i class="fa-regular fa-file-lines"></i>Invoices</a>
            <a class="menu-item" href="#"><i class="fa-regular fa-rectangle-list"></i>Quotations</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-cart-plus"></i>Purchase Orders</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-rotate-left"></i>Returns</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-receipt"></i>Transactions</a>
            <a class="menu-item" href="#"><i class="fa-solid fa-location-dot"></i>Locations</a>
            <a class="menu-item" href="#"><i class="fa-regular fa-message"></i>Messages</a>
        </nav>

        <div class="sidebar-footer">
            <div class="profile">
                <span class="avatar">AD</span>
                <div>
                    <strong>Admin User</strong>
                    <small>Admin</small>
                </div>
            </div>
            <a class="logout" href="#"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <strong>Dashboard</strong>
            <div class="topbar-actions">
                <button class="pill"><i class="fa-solid fa-plus"></i> Add</button>
                <span class="pill">10:35 pm 24-03-2026</span>
                <span class="pill">EN</span>
                <span class="pill">SW</span>
                <span class="pill"><i class="fa-solid fa-store"></i> Shop</span>
                <span class="icon-btn"><i class="fa-regular fa-bell"></i></span>
                <span class="pill">Admin User</span>
            </div>
        </header>

        <section class="stats-grid">
            <article class="stat-card stat-blue">
                <div>
                    <h2><?= moneyFormat($totals['totalSales']) ?></h2>
                    <p>Total Sales</p>
                </div>
                <span><i class="fa-solid fa-cart-shopping"></i></span>
            </article>
            <article class="stat-card stat-green">
                <div>
                    <h2><?= moneyFormat($totals['totalCustomers']) ?></h2>
                    <p>Total Customers</p>
                </div>
                <span><i class="fa-solid fa-users"></i></span>
            </article>
            <article class="stat-card stat-pink">
                <div>
                    <h2><?= moneyFormat($totals['totalItems']) ?></h2>
                    <p>Total Items</p>
                </div>
                <span><i class="fa-solid fa-cube"></i></span>
            </article>
            <article class="stat-card stat-orange">
                <div>
                    <h2><?= moneyFormat($totals['transactionsToday']) ?></h2>
                    <p>Transactions Today</p>
                </div>
                <span><i class="fa-solid fa-dollar-sign"></i></span>
            </article>
        </section>

        <section class="welcome">Welcome to Mchongoma Limited, Admin! Choose a common task below to get started.</section>

        <?php if ($usingDemoData): ?>
            <section class="db-warning">
                MySQL is not connected, so demo data is showing. Configure DB credentials in your environment and import sql/schema.sql.
                <?php if ($errorMessage): ?>
                    <div class="hint">Error: <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="quick-actions">
            <button><i class="fa-solid fa-cart-plus"></i> Start a New Sale</button>
            <button><i class="fa-solid fa-cube"></i> View All Products</button>
            <button><i class="fa-regular fa-user"></i> View Customers</button>
            <button><i class="fa-solid fa-file-lines"></i> View All Reports</button>
            <button><i class="fa-regular fa-rectangle-list"></i> All Transactions</button>
            <button><i class="fa-solid fa-arrow-right"></i> Manage Suppliers</button>
            <button><i class="fa-regular fa-calendar-check"></i> End of Day Report</button>
        </section>

        <section class="bottom-grid">
            <article class="panel chart-panel">
                <div class="panel-header">
                    <h3>Sales Information</h3>
                    <div class="tabs"><span class="active">Week</span><span>Month</span></div>
                </div>
                <canvas id="salesChart" height="180"></canvas>
            </article>

            <div class="side-panels">
                <article class="panel">
                    <div class="panel-header">
                        <h3><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</h3>
                    </div>
                    <p class="muted"><?= htmlspecialchars($lowStock['message'], ENT_QUOTES, 'UTF-8') ?></p>
                </article>

                <article class="panel recent-sales">
                    <div class="panel-header"><h3>Recent Sales</h3></div>
                    <?php foreach ($recentSales as $sale): ?>
                        <div class="sale-row">
                            <div>
                                <strong><?= htmlspecialchars((string) $sale['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars((string) $sale['transaction_no'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <div class="sale-amount">
                                <strong>Tsh <?= moneyFormat((float) $sale['amount']) ?></strong>
                                <small><?= htmlspecialchars((string) $sale['payment_method'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </article>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    window.salesChartData = <?= json_encode($weeklySales, JSON_THROW_ON_ERROR) ?>;
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
