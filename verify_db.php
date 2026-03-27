<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if (!isDevelopmentToolAccessAllowed()) {
    http_response_code(404);
    exit('Not found');
}

// Quick database check script
$host = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'pos_mchongoma';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ SUCCESS! Database 'pos_mchongoma' is connected!\n\n";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📊 Tables found (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }

    echo "\n🎉 Your database is ready to use!\n";
    echo "👉 Access your app: http://localhost/pos-php-mchongoma/public/index.php\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "\n💡 Solution: Import the database using the web importer:\n";
    echo "   http://localhost/pos-php-mchongoma/import_schema.php\n";
}
