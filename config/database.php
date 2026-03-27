<?php

declare(strict_types=1);

/**
 * Check if running in production environment
 */
function getAppEnvironment(): string
{
    $raw = getenv('APP_ENV');
    if (is_string($raw) && trim($raw) !== '') {
        return strtolower(trim($raw));
    }

    // Safe local default: if APP_ENV is unset and request is localhost, treat as development.
    if (isLocalRequest()) {
        return 'development';
    }

    return 'production';
}

function isProductionEnvironment(): bool
{
    return !in_array(getAppEnvironment(), ['development', 'dev', 'local', 'test', 'testing'], true);
}

function isLocalRequest(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return in_array($remoteAddress, ['127.0.0.1', '::1'], true);
}

function isDevelopmentToolAccessAllowed(): bool
{
    $allowTools = strtolower(trim((string) (getenv('APP_ALLOW_SETUP_TOOLS') ?: '0')));
    $isAllowed = in_array($allowTools, ['1', 'true', 'yes', 'on'], true);

    return !isProductionEnvironment() && $isAllowed && isLocalRequest();
}

function isDebugModeEnabledFromEnvironment(): bool
{
    $raw = strtolower(trim((string) (getenv('APP_DEBUG') ?: '0')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function isEnvFlagEnabled(string $name): bool
{
    $raw = strtolower(trim((string) (getenv($name) ?: '0')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function enforceRuntimeSecurityConfiguration(): void
{
    if (!isProductionEnvironment()) {
        return;
    }

    $violations = [];
    if (isDebugModeEnabledFromEnvironment()) {
        $violations[] = 'APP_DEBUG must be disabled in production';
    }
    if (isEnvFlagEnabled('APP_ALLOW_SETUP_TOOLS')) {
        $violations[] = 'APP_ALLOW_SETUP_TOOLS must be disabled in production';
    }
    if (isEnvFlagEnabled('APP_ALLOW_DEMO_LOGIN')) {
        $violations[] = 'APP_ALLOW_DEMO_LOGIN must be disabled in production';
    }

    if (count($violations) === 0) {
        return;
    }

    $message = 'Unsafe runtime configuration: ' . implode('; ', $violations) . '.';
    error_log($message);

    if (PHP_SAPI === 'cli') {
        throw new RuntimeException($message);
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Server configuration error. Contact administrator.');
}

enforceRuntimeSecurityConfiguration();

function getDatabaseConnection(): PDO
{
    // Get credentials from environment variables
    $host = getenv('DB_HOST') ?: null;
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_DATABASE') ?: null;
    $username = getenv('DB_USERNAME') ?: null;
    $password = getenv('DB_PASSWORD');

    // In production, require all credentials to be set via environment variables
    if (isProductionEnvironment()) {
        if ($host === null || $database === null || $username === null) {
            throw new RuntimeException(
                'Database credentials must be configured via environment variables in production. ' .
                'Set DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD.'
            );
        }
    } else {
        // Development fallbacks (for XAMPP/WAMP/local development only)
        $host = $host ?? '127.0.0.1';
        $database = $database ?? 'pos_mchongoma';
        $username = $username ?? 'root';
        $password = $password !== false ? $password : '';

        // Log warning if using default credentials in development
        if ($username === 'root' && $password === '') {
            error_log('WARNING: Using default database credentials. Configure DB_USERNAME and DB_PASSWORD environment variables.');
        }
    }

    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException(
            'MySQL PDO driver is missing. Enable "extension=pdo_mysql" in php.ini, restart Apache, then reload the page.'
        );
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $attempts = [
        ['host' => $host, 'port' => $port],
    ];

    // Some local Windows/XAMPP setups fail on 127.0.0.1 TCP but work on localhost/socket.
    if ($host === '127.0.0.1') {
        $attempts[] = ['host' => 'localhost', 'port' => $port];
        $attempts[] = ['host' => 'localhost', 'port' => null];
    }

    $lastException = null;

    foreach ($attempts as $attempt) {
        $dsn = sprintf('mysql:host=%s;', $attempt['host']);
        if ($attempt['port'] !== null && $attempt['port'] !== '') {
            $dsn .= sprintf('port=%s;', (string) $attempt['port']);
        }
        $dsn .= sprintf('dbname=%s;charset=utf8mb4', $database);

        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            $lastException = $exception;
            continue;
        }
    }

    // Log full error for debugging but don't expose details to users
    if ($lastException instanceof PDOException) {
        error_log('Database connection failed: ' . $lastException->getMessage());
    }

    // In production, hide sensitive connection details
    if (isProductionEnvironment()) {
        throw new RuntimeException(
            'Database connection failed. Please contact the administrator.',
            0,
            $lastException
        );
    }

    // In development, provide helpful debugging info (but still hide password)
    throw new RuntimeException(
        sprintf(
            'Database connection failed for %s:%s/%s. Check MySQL service and credentials. See error log for details.',
            $host,
            $port,
            $database
        ),
        0,
        $lastException
    );
}
