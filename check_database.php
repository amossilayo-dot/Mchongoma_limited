<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "✓ Database connection successful\n\n";

    // Get list of all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Current tables in database:\n";
    echo "================================\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }

    echo "\n\nDetailed table structures:\n";
    echo "================================\n\n";

    foreach ($tables as $table) {
        echo "Table: $table\n";
        echo str_repeat("-", 80) . "\n";

        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll();

        foreach ($columns as $column) {
            printf("  %-20s %-15s %-5s %-5s %s\n",
                $column['Field'],
                $column['Type'],
                $column['Null'],
                $column['Key'],
                $column['Extra']
            );
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
