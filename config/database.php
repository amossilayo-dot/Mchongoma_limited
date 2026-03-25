<?php

declare(strict_types=1);

function getDatabaseConnection(): PDO
{
    // Local defaults for XAMPP/WAMP. Environment variables can still override these values.
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_DATABASE') ?: 'pos_mchongoma';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

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
        throw new RuntimeException(
            sprintf(
                'Database connection failed for %s:%s/%s with user "%s". Check MySQL service, credentials, and database import from sql/schema.sql. Original error: %s',
                $host,
                $port,
                $database,
                $username,
                $exception->getMessage()
            ),
            0,
            $exception
        );
    }
}
