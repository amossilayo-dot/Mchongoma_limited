<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

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
    <title>Database Schema Importer - POS Mchongoma</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
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
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .schema-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .table-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .table-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }
        .icon {
            font-size: 24px;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            max-height: 300px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span class="icon">🗄️</span>
            Database Schema Importer
        </h1>
        <p class="subtitle">POS Mchongoma - ERD Schema Verification & Import</p>

        <?php
        // Database configuration (read from your config)
        $host = '127.0.0.1';
        $port = '3306';
        $username = 'root';
        $password = '';
        $database = 'pos_mchongoma';

        $action = $_GET['action'] ?? '';

        if ($action === 'import') {
            echo '<div class="status-box info"><strong>📥 Starting Import...</strong></div>';

            try {
                // Connect without specifying database first
                $pdo = new PDO(
                    "mysql:host=$host;port=$port;charset=utf8mb4",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );

                echo '<div class="status-box success">✓ Connected to MySQL server</div>';

                // Read the schema file
                $schemaFile = __DIR__ . '/database_schema.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("Schema file not found: $schemaFile");
                }

                $sql = file_get_contents($schemaFile);
                echo '<div class="status-box success">✓ Schema file loaded</div>';

                // Execute the SQL
                $pdo->exec($sql);

                echo '<div class="status-box success"><strong>✓ Database schema imported successfully!</strong></div>';

                // Verify tables were created
                $pdo->query("USE $database");
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                echo '<div class="schema-info">';
                echo '<strong>📋 Tables Created (' . count($tables) . '):</strong>';
                echo '<div class="table-list">';
                foreach ($tables as $table) {
                    echo '<div class="table-item">✓ ' . htmlspecialchars($table) . '</div>';
                }
                echo '</div>';
                echo '</div>';

                echo '<a href="public/index.php" class="btn btn-success">🚀 Go to Application</a> ';
                echo '<a href="import_schema.php" class="btn">🔄 Refresh</a>';

            } catch (PDOException $e) {
                echo '<div class="status-box error">';
                echo '<strong>❌ Database Error:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';
                echo '<a href="import_schema.php" class="btn">← Back</a>';
            } catch (Exception $e) {
                echo '<div class="status-box error">';
                echo '<strong>❌ Error:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';
                echo '<a href="import_schema.php" class="btn">← Back</a>';
            }

        } else {
            // Show import form
            ?>
            <div class="status-box info">
                <strong>📊 ERD Schema Verification</strong><br><br>
                This tool will import the database schema that matches your ERD diagram exactly.
            </div>

            <div class="schema-info">
                <strong>Core Tables (from ERD):</strong>
                <div class="table-list">
                    <div class="table-item">👤 users</div>
                    <div class="table-item">👥 customers</div>
                    <div class="table-item">📦 products</div>
                    <div class="table-item">🏭 warehouses</div>
                    <div class="table-item">📝 orders</div>
                    <div class="table-item">📋 order_items</div>
                    <div class="table-item">💳 payments</div>
                    <div class="table-item">💰 expenses</div>
                </div>

                <strong style="margin-top: 15px; display: block;">Additional Tables (for full app):</strong>
                <div class="table-list">
                    <div class="table-item">💵 sales</div>
                    <div class="table-item">🚚 suppliers</div>
                    <div class="table-item">👔 employees</div>
                    <div class="table-item">📄 invoices</div>
                    <div class="table-item">🚛 deliveries</div>
                    <div class="table-item">📋 quotations</div>
                    <div class="table-item">🛒 purchase_orders</div>
                    <div class="table-item">📥 receiving</div>
                    <div class="table-item">↩️ returns</div>
                    <div class="table-item">📅 appointments</div>
                    <div class="table-item">📍 locations</div>
                    <div class="table-item">✉️ messages</div>
                </div>
            </div>

            <div class="status-box warning">
                <strong>⚠️ Warning:</strong><br>
                This will DROP the existing database (if any) and create a fresh one with sample data.
                All existing data will be lost!
            </div>

            <a href="?action=import" class="btn btn-success" onclick="return confirm('Are you sure? This will delete all existing data and create a fresh database!')">
                🚀 Import Schema Now
            </a>
            <a href="public/index.php" class="btn">← Back to Application</a>

            <div style="margin-top: 30px;">
                <h3>Schema Preview:</h3>
                <pre><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/database_schema.sql')); ?></pre>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
