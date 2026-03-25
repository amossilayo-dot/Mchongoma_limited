<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/InventoryRepository.php';
require_once __DIR__ . '/../app/XlsxHelper.php';

ensureSecureSessionStarted();
requireAuthentication();

try {
    $pdo = getDatabaseConnection();
    $inventoryRepo = new InventoryRepository($pdo);
    $products = $inventoryRepo->getProducts(5000);

    $rows = [
        ['name', 'sku', 'unit_price', 'stock_qty', 'reorder_level'],
    ];

    foreach ($products as $product) {
        $rows[] = [
            (string) ($product['name'] ?? ''),
            (string) ($product['sku'] ?? ''),
            (string) ($product['unit_price'] ?? '0'),
            (string) ($product['stock_qty'] ?? '0'),
            (string) ($product['reorder_level'] ?? '0'),
        ];
    }

    $tempFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'products_' . date('Ymd_His') . '.xlsx';
    writeXlsxFile($tempFile, 'Products', $rows);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="products_export_' . date('Ymd_His') . '.xlsx"');
    header('Content-Length: ' . (string) filesize($tempFile));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    readfile($tempFile);
    @unlink($tempFile);
    exit;
} catch (Throwable $exception) {
    error_log('[POS Export XLSX] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate XLSX export at this time.';
    exit;
}
