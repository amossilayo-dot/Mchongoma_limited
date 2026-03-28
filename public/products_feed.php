<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../app/Auth.php';
ensureSecureSessionStarted();
requireAuthentication();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/InventoryRepository.php';

try {
    $pdo = getDatabaseConnection();
    $repo = new InventoryRepository($pdo);

    $limitRaw = (int) ($_GET['limit'] ?? 200);
    $limit = max(1, min($limitRaw, 1000));
    $query = trim((string) ($_GET['q'] ?? ''));

    if ($query !== '') {
        $products = $repo->searchProducts($query, $limit);
    } else {
        $products = $repo->getProducts($limit, 0);
    }

    $payload = array_map(static fn(array $item) => [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'sku' => (string) ($item['sku'] ?? ''),
        'category' => (string) ($item['category'] ?? ''),
        'stock_qty' => (int) ($item['stock_qty'] ?? 0),
        'unit_price' => (float) ($item['unit_price'] ?? 0),
    ], $products);

    echo json_encode([
        'success' => true,
        'items' => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('[POS Products Feed] ' . $exception->getMessage());

    $debug = in_array(strtolower(trim((string) (getenv('APP_DEBUG') ?: '0'))), ['1', 'true', 'yes', 'on'], true);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $debug ? $exception->getMessage() : 'Unable to load products feed.',
    ], JSON_UNESCAPED_UNICODE);
}
