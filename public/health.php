<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function envFlagEnabled(string $name): bool
{
    $raw = strtolower(trim((string) (getenv($name) ?: '0')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function getAppEnvForHealth(): string
{
    $raw = getenv('APP_ENV');
    if (is_string($raw) && trim($raw) !== '') {
        return strtolower(trim($raw));
    }

    return 'production';
}

function isProductionForHealth(string $appEnv): bool
{
    return !in_array($appEnv, ['development', 'dev', 'local', 'test', 'testing'], true);
}

function isLocalRequestForHealth(): bool
{
    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
}

function isHealthAccessAuthorized(): bool
{
    if (isLocalRequestForHealth()) {
        return true;
    }

    $expectedToken = trim((string) (getenv('APP_HEALTHCHECK_TOKEN') ?: ''));
    if ($expectedToken === '') {
        return false;
    }

    $providedToken = trim((string) (
        $_SERVER['HTTP_X_HEALTHCHECK_TOKEN']
        ?? $_GET['token']
        ?? ''
    ));

    return $providedToken !== '' && hash_equals($expectedToken, $providedToken);
}

if (!isHealthAccessAuthorized()) {
    http_response_code(404);
    echo json_encode([
        'status' => 'not_found',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$appEnv = getAppEnvForHealth();
$isProduction = isProductionForHealth($appEnv);

$checks = [
    'app_env_production' => !$isProduction ? null : true,
    'app_debug_disabled' => !$isProduction ? null : !envFlagEnabled('APP_DEBUG'),
    'setup_tools_disabled' => !$isProduction ? null : !envFlagEnabled('APP_ALLOW_SETUP_TOOLS'),
    'demo_login_disabled' => !$isProduction ? null : !envFlagEnabled('APP_ALLOW_DEMO_LOGIN'),
    'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
    'db_connection' => false,
];

$dbHost = trim((string) (getenv('DB_HOST') ?: ''));
$dbPort = trim((string) (getenv('DB_PORT') ?: '3306'));
$dbName = trim((string) (getenv('DB_DATABASE') ?: ''));
$dbUser = trim((string) (getenv('DB_USERNAME') ?: ''));
$dbPassRaw = getenv('DB_PASSWORD');
$dbPass = $dbPassRaw === false ? '' : (string) $dbPassRaw;

if ($dbHost !== '' && $dbName !== '' && $dbUser !== '' && extension_loaded('pdo_mysql')) {
    try {
        $dsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->query('SELECT 1');
        $checks['db_connection'] = true;
    } catch (Throwable $exception) {
        $checks['db_connection'] = false;
    }
}

$requiredChecks = ['pdo_mysql_loaded', 'db_connection'];
if ($isProduction) {
    $requiredChecks[] = 'app_debug_disabled';
    $requiredChecks[] = 'setup_tools_disabled';
    $requiredChecks[] = 'demo_login_disabled';
}

$failed = [];
foreach ($requiredChecks as $checkName) {
    if (($checks[$checkName] ?? false) !== true) {
        $failed[] = $checkName;
    }
}

$healthy = count($failed) === 0;
http_response_code($healthy ? 200 : 503);

echo json_encode([
    'status' => $healthy ? 'ok' : 'degraded',
    'timestamp' => gmdate('c'),
    'environment' => $appEnv,
    'checks' => $checks,
    'failed_checks' => $failed,
], JSON_UNESCAPED_UNICODE);
