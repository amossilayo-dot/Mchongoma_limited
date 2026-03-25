<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (isProductionEnvironment()) {
    http_response_code(404);
    exit('Not found');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Checker - POS Mchongoma</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .status-card {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .status-card.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .status-card.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .status-card.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .status-card h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: #333;
        }
        .status-card p {
            font-size: 14px;
            color: #666;
        }
        .table-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .table-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid #28a745;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-item.missing {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        .table-name {
            font-weight: bold;
            color: #333;
        }
        .table-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .table-status.ok {
            background: #28a745;
            color: white;
        }
        .table-status.missing {
            background: #dc3545;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #5568d3;
        }
        .details {
            margin-top: 10px;
            padding: 10px;
            background: #f1f3f5;
            border-radius: 4px;
            font-size: 12px;
            color: #495057;
        }
        .icon {
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span class="icon">🔍</span>
            Database Schema Verification
        </h1>
        <p class="subtitle">POS Mchongoma System - Checking database structure against ERD diagram</p>

        <?php
        declare(strict_types=1);

        require_once __DIR__ . '/../config/database.php';

        $requiredTables = [
            'users' => 'Core: User authentication and roles',
            'customers' => 'Core: Customer information',
            'products' => 'Core: Product catalog',
            'warehouses' => 'Core: Warehouse/location management',
            'orders' => 'Core: Customer orders',
            'order_items' => 'Core: Order line items',
            'payments' => 'Core: Payment records',
            'expenses' => 'Core: Expense tracking',
            'sales' => 'Supporting: Sales transactions',
            'suppliers' => 'Supporting: Supplier management',
            'employees' => 'Supporting: Employee records',
            'invoices' => 'Supporting: Invoice generation',
            'deliveries' => 'Supporting: Delivery tracking',
            'quotations' => 'Supporting: Quotation management',
            'purchase_orders' => 'Supporting: Purchase orders',
            'receiving' => 'Supporting: Stock receiving',
            'returns' => 'Supporting: Product returns',
            'appointments' => 'Supporting: Appointment scheduling',
            'locations' => 'Supporting: Store locations',
            'messages' => 'Supporting: Internal messaging'
        ];

        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->query("SHOW TABLES");
            $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $missingTables = array_diff(array_keys($requiredTables), $existingTables);
            $extraTables = array_diff($existingTables, array_keys($requiredTables));
            $matchingTables = array_intersect(array_keys($requiredTables), $existingTables);

            $totalRequired = count($requiredTables);
            $totalExisting = count($matchingTables);
            $percentComplete = round(($totalExisting / $totalRequired) * 100);

            ?>
            <div class="status-grid">
                <div class="status-card <?= $percentComplete === 100 ? 'success' : 'warning' ?>">
                    <h3>✅ Database Connection</h3>
                    <p>Successfully connected to database</p>
                </div>

                <div class="status-card <?= $percentComplete === 100 ? 'success' : 'warning' ?>">
                    <h3>📊 Tables Found</h3>
                    <p><?= $totalExisting ?> / <?= $totalRequired ?> tables (<?= $percentComplete ?>% complete)</p>
                </div>

                <div class="status-card <?= count($missingTables) === 0 ? 'success' : 'error' ?>">
                    <h3>⚠️ Missing Tables</h3>
                    <p><?= count($missingTables) ?> tables need to be created</p>
                </div>

                <div class="status-card <?= count($extraTables) === 0 ? 'success' : 'warning' ?>">
                    <h3>📦 Extra Tables</h3>
                    <p><?= count($extraTables) ?> additional tables found</p>
                </div>
            </div>

            <div class="table-list">
                <h2 style="margin-bottom: 15px;">Required Tables Status</h2>
                <?php foreach ($requiredTables as $table => $description): ?>
                    <div class="table-item <?= in_array($table, $missingTables) ? 'missing' : '' ?>">
                        <div>
                            <div class="table-name"><?= strtoupper($table) ?></div>
                            <div style="font-size: 12px; color: #666;"><?= $description ?></div>
                            <?php if (!in_array($table, $missingTables)): ?>
                                <?php
                                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
                                $count = $stmt->fetch()['count'];
                                ?>
                                <div class="details">
                                    <?= $count ?> record(s) |
                                    <?php
                                    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
                                    $columns = $stmt->fetchAll();
                                    echo count($columns) . ' columns';
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="table-status <?= in_array($table, $missingTables) ? 'missing' : 'ok' ?>">
                            <?= in_array($table, $missingTables) ? 'MISSING' : 'OK' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($missingTables) > 0): ?>
                <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #ffc107;">
                    <h3 style="color: #856404; margin-bottom: 10px;">⚠️ Action Required</h3>
                    <p style="color: #856404; margin-bottom: 15px;">
                        The following tables are missing: <strong><?= implode(', ', array_map('strtoupper', $missingTables)) ?></strong>
                    </p>
                    <p style="color: #856404; margin-bottom: 10px;">To fix this:</p>
                    <ol style="color: #856404; margin-left: 20px; margin-bottom: 15px;">
                        <li>Import the file <code>database_schema.sql</code> using phpMyAdmin</li>
                        <li>Or run: <code>mysql -u root -p &lt; database_schema.sql</code></li>
                        <li>Refresh this page to verify</li>
                    </ol>
                    <a href="database_schema.sql" class="btn" download>📥 Download Schema SQL File</a>
                </div>
            <?php else: ?>
                <div style="background: #d4edda; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #28a745;">
                    <h3 style="color: #155724; margin-bottom: 10px;">✅ Database Schema Complete!</h3>
                    <p style="color: #155724;">
                        All required tables are present and match the ERD diagram. Your database is ready to use!
                    </p>
                    <a href="../public/index.php?page=dashboard" class="btn">🚀 Go to Dashboard</a>
                </div>
            <?php endif; ?>

        <?php
        } catch (Exception $e) {
            ?>
            <div class="status-card error">
                <h3>❌ Database Connection Failed</h3>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
                <div class="details" style="margin-top: 10px;">
                    <strong>To fix:</strong>
                    <ol style="margin-left: 20px; margin-top: 5px;">
                        <li>Make sure MySQL/XAMPP is running</li>
                        <li>Check database credentials in config/database.php</li>
                        <li>Create the database if it doesn't exist: <code>CREATE DATABASE pos_mchongoma;</code></li>
                        <li>Import database_schema.sql</li>
                    </ol>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
