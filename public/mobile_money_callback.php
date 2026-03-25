<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/MobileMoneyGateway.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = [];

if (is_string($rawInput) && trim($rawInput) !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (count($payload) === 0 && count($_POST) > 0) {
    $payload = $_POST;
}

if (count($payload) === 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Empty callback payload.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$headers = function_exists('getallheaders') ? (array) getallheaders() : [];
$token = (string) (
    $headers['X-Callback-Token']
    ?? $headers['x-callback-token']
    ?? $_SERVER['HTTP_X_CALLBACK_TOKEN']
    ?? ''
);

try {
    $pdo = getDatabaseConnection();
    $gateway = new MobileMoneyGateway($pdo);

    if (!$gateway->verifyCallbackToken($token)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized callback token.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = $gateway->processCallback($payload);
    $statusCode = (int) ($result['statusCode'] ?? 200);
    unset($result['statusCode']);

    http_response_code($statusCode);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[POS Mobile Callback] ' . $exception->getMessage());
    $debug = in_array(strtolower(trim((string) (getenv('APP_DEBUG') ?: '0'))), ['1', 'true', 'yes', 'on'], true);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $debug ? $exception->getMessage() : 'Callback handling failed.',
    ], JSON_UNESCAPED_UNICODE);
}
