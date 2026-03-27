<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if (!isDevelopmentToolAccessAllowed()) {
    http_response_code(404);
    exit('Not found');
}

try {
    $pdo = getDatabaseConnection();
    echo "✓ Connected to database successfully!\n\n";

    // Get all tables
    echo "=== EXISTING TABLES ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "No tables found in database!\n";
    } else {
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }

    echo "\n=== TABLE STRUCTURES ===\n\n";

    // Show structure of each table
    foreach ($tables as $table) {
        echo "Table: $table\n";
        echo str_repeat("-", 80) . "\n";

        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            printf("  %-20s %-20s %-10s %-10s %s\n",
                $column['Field'],
                $column['Type'],
                $column['Null'],
                $column['Key'],
                $column['Extra']
            );
        }

        // Show indexes
        $stmt = $pdo->query("SHOW INDEX FROM `$table`");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($indexes)) {
            echo "\n  Indexes:\n";
            $shownIndexes = [];
            foreach ($indexes as $index) {
                $indexName = $index['Key_name'];
                if (!in_array($indexName, $shownIndexes)) {
                    $shownIndexes[] = $indexName;
                    echo "    - {$index['Key_name']} on {$index['Column_name']}\n";
                }
            }
        }

        echo "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
