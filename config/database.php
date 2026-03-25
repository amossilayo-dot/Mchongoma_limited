<?php

declare(strict_types=1);

/**
 * Check if running in production environment
 */
function isProductionEnvironment(): bool
{
    $env = getenv('APP_ENV') ?: 'development';
    return in_array($env, ['production', 'prod'], true);
}

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

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $exception) {
        // Log full error for debugging but don't expose details to users
        error_log('Database connection failed: ' . $exception->getMessage());

        // In production, hide sensitive connection details
        if (isProductionEnvironment()) {
            throw new RuntimeException(
                'Database connection failed. Please contact the administrator.',
                0,
                $exception
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
            $exception
        );
    }
}
