<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_SECONDS = 600;
const LOGIN_BLOCK_SECONDS = 600;

function getClientIpAddress(): string
{
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function ensureAuthSecurityTables(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_attempt_throttle (
            throttle_key CHAR(64) PRIMARY KEY,
            login_identifier VARCHAR(255) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            first_attempt INT NOT NULL DEFAULT 0,
            last_attempt INT NOT NULL DEFAULT 0,
            blocked_until INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_last_attempt (last_attempt),
            INDEX idx_blocked_until (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS security_audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            event_status VARCHAR(32) NOT NULL,
            login_identifier VARCHAR(255) NOT NULL DEFAULT \'\',
            user_id INT NULL,
            ip_address VARCHAR(64) NOT NULL,
            meta_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_event_status (event_status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
}

function logSecurityAuditEvent(string $eventType, string $eventStatus, string $login = '', array $meta = [], ?PDO $pdo = null): void
{
    $userId = is_array($_SESSION['user'] ?? null) ? (int) (($_SESSION['user']['id'] ?? 0) ?: 0) : null;
    $metaJson = count($meta) > 0 ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    if ($pdo instanceof PDO) {
        try {
            ensureAuthSecurityTables($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO security_audit_logs (
                    event_type, event_status, login_identifier, user_id, ip_address, meta_json
                 ) VALUES (
                    :event_type, :event_status, :login_identifier, :user_id, :ip_address, :meta_json
                 )'
            );
            $stmt->execute([
                ':event_type' => substr($eventType, 0, 80),
                ':event_status' => substr($eventStatus, 0, 32),
                ':login_identifier' => substr(strtolower(trim($login)), 0, 255),
                ':user_id' => $userId > 0 ? $userId : null,
                ':ip_address' => substr(getClientIpAddress(), 0, 64),
                ':meta_json' => $metaJson,
            ]);
            return;
        } catch (Throwable $exception) {
            error_log('[Security Audit] ' . $exception->getMessage());
        }
    }

    $fallback = [
        'event' => $eventType,
        'status' => $eventStatus,
        'login' => strtolower(trim($login)),
        'ip' => getClientIpAddress(),
        'meta' => $meta,
    ];
    error_log('[Security Audit] ' . json_encode($fallback));
}

function ensureSecureSessionStarted(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - (int) $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

function isAuthenticated(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function currentUser(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function requireAuthentication(): void
{
    if (isAuthenticated()) {
        return;
    }

    $redirect = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/public/index.php'));
    header('Location: login.php?redirect=' . $redirect);
    exit;
}

function hasUsersTable(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $stmt->execute([':table_name' => 'users']);

    return ((int) $stmt->fetch()['total']) === 1;
}

function authenticateUser(string $login, string $password): array
{
    $login = trim($login);
    if ($login === '' || $password === '') {
        return ['ok' => false, 'message' => 'Username/email and password are required.'];
    }

    $throttleState = getLoginThrottleState($login);
    if ($throttleState['blocked'] === true) {
        $retryAfter = (int) ($throttleState['retry_after'] ?? LOGIN_BLOCK_SECONDS);
        $retryAfter = max(1, $retryAfter);

        logSecurityAuditEvent('login_blocked', 'blocked', $login, ['retry_after' => $retryAfter]);

        return [
            'ok' => false,
            'message' => 'Too many login attempts. Try again in ' . $retryAfter . ' seconds.',
        ];
    }

    try {
        $pdo = getDatabaseConnection();
    } catch (Throwable $exception) {
        if (!isProductionEnvironment() && isDemoLoginEnabled()) {
            return authenticateDemoFallbackUser($login, $password);
        }

        logSecurityAuditEvent('login_db_unavailable', 'error', $login);
        return ['ok' => false, 'message' => 'Database is unavailable. Please try again in a moment.'];
    }

    $persistentThrottleState = getLoginThrottleState($login, $pdo);
    if ($persistentThrottleState['blocked'] === true) {
        $retryAfter = (int) ($persistentThrottleState['retry_after'] ?? LOGIN_BLOCK_SECONDS);
        $retryAfter = max(1, $retryAfter);

        registerFailedLoginAttempt($login, $pdo);
        logSecurityAuditEvent('login_blocked', 'blocked', $login, ['retry_after' => $retryAfter], $pdo);

        return [
            'ok' => false,
            'message' => 'Too many login attempts. Try again in ' . $retryAfter . ' seconds.',
        ];
    }

    if (!hasUsersTable($pdo)) {
        return ['ok' => false, 'message' => 'Users table is missing. Import database_schema.sql to enable login.'];
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, password, role
         FROM users
         WHERE email = :login_email OR name = :login_name
         LIMIT 1'
    );
    $stmt->execute([
        ':login_email' => $login,
        ':login_name' => $login,
    ]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        registerFailedLoginAttempt($login, $pdo);
        logSecurityAuditEvent('login_failed', 'failed', $login, [], $pdo);
        return ['ok' => false, 'message' => 'Invalid username/email or password.'];
    }

    clearFailedLoginAttempts($login, $pdo);
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];

    logSecurityAuditEvent('login_success', 'success', $login, ['user_id' => (int) $user['id']], $pdo);

    return ['ok' => true, 'message' => null];
}

function authenticateDemoFallbackUser(string $login, string $password): array
{
    $normalizedLogin = strtolower(trim($login));
    $allowedLogins = ['admin', 'admin@mchongoma.com'];
    $expectedPassword = (string) (getenv('DEMO_ADMIN_PASSWORD') ?: 'admin123');

    if (!in_array($normalizedLogin, $allowedLogins, true) || !hash_equals($expectedPassword, $password)) {
        return [
            'ok' => false,
            'message' => 'Database is unavailable. For offline demo login use admin / admin123.',
        ];
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => 0,
        'name' => 'Admin User',
        'email' => 'admin@mchongoma.com',
        'role' => 'admin',
        'is_demo' => true,
    ];

    return ['ok' => true, 'message' => null];
}

function isDemoLoginEnabled(): bool
{
    $raw = strtolower(trim((string) (getenv('APP_ALLOW_DEMO_LOGIN') ?: '0')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function loginCsrfToken(): string
{
    if (empty($_SESSION['login_csrf_token'])) {
        $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['login_csrf_token'];
}

function hasValidLoginCsrfToken(): bool
{
    $sessionToken = (string) ($_SESSION['login_csrf_token'] ?? '');
    $requestToken = (string) ($_POST['csrf_token'] ?? '');

    return $sessionToken !== '' && $requestToken !== '' && hash_equals($sessionToken, $requestToken);
}

function getLoginThrottleKey(string $login): string
{
    $normalizedLogin = strtolower(trim($login));
    $ip = getClientIpAddress();
    return hash('sha256', $normalizedLogin . '|' . $ip);
}

function pruneExpiredLoginThrottleEntries(): void
{
    $entries = $_SESSION['login_attempts'] ?? null;
    if (!is_array($entries)) {
        $_SESSION['login_attempts'] = [];
        return;
    }

    $now = time();
    foreach ($entries as $key => $entry) {
        if (!is_array($entry)) {
            unset($entries[$key]);
            continue;
        }

        $lastAttempt = (int) ($entry['last_attempt'] ?? 0);
        $blockedUntil = (int) ($entry['blocked_until'] ?? 0);
        $isExpired = ($now - $lastAttempt) > LOGIN_WINDOW_SECONDS && $blockedUntil <= $now;

        if ($isExpired) {
            unset($entries[$key]);
        }
    }

    $_SESSION['login_attempts'] = $entries;
}

function getLoginThrottleState(string $login, ?PDO $pdo = null): array
{
    pruneExpiredLoginThrottleEntries();

    $key = getLoginThrottleKey($login);
    $entries = $_SESSION['login_attempts'] ?? [];
    $entry = $entries[$key] ?? [];
    $blockedUntil = (int) ($entry['blocked_until'] ?? 0);
    $now = time();

    if ($blockedUntil > $now) {
        return [
            'blocked' => true,
            'retry_after' => $blockedUntil - $now,
        ];
    }

    if ($pdo instanceof PDO) {
        try {
            ensureAuthSecurityTables($pdo);
            $stmt = $pdo->prepare(
                'SELECT blocked_until
                 FROM login_attempt_throttle
                 WHERE throttle_key = :throttle_key
                 LIMIT 1'
            );
            $stmt->execute([':throttle_key' => $key]);
            $row = $stmt->fetch();
            $dbBlockedUntil = (int) ($row['blocked_until'] ?? 0);

            if ($dbBlockedUntil > $now) {
                return [
                    'blocked' => true,
                    'retry_after' => $dbBlockedUntil - $now,
                ];
            }
        } catch (Throwable $exception) {
            error_log('[Auth Throttle] ' . $exception->getMessage());
        }
    }

    return [
        'blocked' => false,
        'retry_after' => 0,
    ];
}

function registerFailedLoginAttemptInDatabase(string $login, PDO $pdo): void
{
    ensureAuthSecurityTables($pdo);

    $key = getLoginThrottleKey($login);
    $now = time();
    $stmt = $pdo->prepare(
        'SELECT attempts, first_attempt
         FROM login_attempt_throttle
         WHERE throttle_key = :throttle_key
         LIMIT 1'
    );
    $stmt->execute([':throttle_key' => $key]);
    $existing = $stmt->fetch();

    $firstAttempt = (int) ($existing['first_attempt'] ?? 0);
    $attempts = (int) ($existing['attempts'] ?? 0);
    if ($firstAttempt <= 0 || ($now - $firstAttempt) > LOGIN_WINDOW_SECONDS) {
        $firstAttempt = $now;
        $attempts = 1;
    } else {
        $attempts++;
    }

    $blockedUntil = 0;
    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $blockedUntil = $now + LOGIN_BLOCK_SECONDS;
    }

    $upsert = $pdo->prepare(
        'INSERT INTO login_attempt_throttle (
            throttle_key, login_identifier, ip_address, attempts, first_attempt, last_attempt, blocked_until
         ) VALUES (
            :throttle_key, :login_identifier, :ip_address, :attempts, :first_attempt, :last_attempt, :blocked_until
         )
         ON DUPLICATE KEY UPDATE
            attempts = VALUES(attempts),
            first_attempt = VALUES(first_attempt),
            last_attempt = VALUES(last_attempt),
            blocked_until = VALUES(blocked_until),
            login_identifier = VALUES(login_identifier),
            ip_address = VALUES(ip_address)'
    );
    $upsert->execute([
        ':throttle_key' => $key,
        ':login_identifier' => substr(strtolower(trim($login)), 0, 255),
        ':ip_address' => substr(getClientIpAddress(), 0, 64),
        ':attempts' => $attempts,
        ':first_attempt' => $firstAttempt,
        ':last_attempt' => $now,
        ':blocked_until' => $blockedUntil,
    ]);
}

function clearFailedLoginAttemptsInDatabase(string $login, PDO $pdo): void
{
    ensureAuthSecurityTables($pdo);

    $stmt = $pdo->prepare('DELETE FROM login_attempt_throttle WHERE throttle_key = :throttle_key');
    $stmt->execute([':throttle_key' => getLoginThrottleKey($login)]);
}

function registerFailedLoginAttempt(string $login, ?PDO $pdo = null): void
{
    pruneExpiredLoginThrottleEntries();

    $key = getLoginThrottleKey($login);
    $entries = $_SESSION['login_attempts'] ?? [];
    $entry = is_array($entries[$key] ?? null) ? $entries[$key] : [];
    $now = time();

    $firstAttempt = (int) ($entry['first_attempt'] ?? 0);
    if ($firstAttempt <= 0 || ($now - $firstAttempt) > LOGIN_WINDOW_SECONDS) {
        $firstAttempt = $now;
        $attempts = 1;
    } else {
        $attempts = (int) ($entry['attempts'] ?? 0) + 1;
    }

    $blockedUntil = 0;
    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $blockedUntil = $now + LOGIN_BLOCK_SECONDS;
    }

    $entries[$key] = [
        'attempts' => $attempts,
        'first_attempt' => $firstAttempt,
        'last_attempt' => $now,
        'blocked_until' => $blockedUntil,
    ];

    $_SESSION['login_attempts'] = $entries;

    if ($pdo instanceof PDO) {
        try {
            registerFailedLoginAttemptInDatabase($login, $pdo);
        } catch (Throwable $exception) {
            error_log('[Auth Throttle] ' . $exception->getMessage());
        }
    }
}

function clearFailedLoginAttempts(string $login, ?PDO $pdo = null): void
{
    $key = getLoginThrottleKey($login);
    $entries = $_SESSION['login_attempts'] ?? [];
    if (!is_array($entries)) {
        $_SESSION['login_attempts'] = [];
        return;
    }

    unset($entries[$key]);
    $_SESSION['login_attempts'] = $entries;

    if ($pdo instanceof PDO) {
        try {
            clearFailedLoginAttemptsInDatabase($login, $pdo);
        } catch (Throwable $exception) {
            error_log('[Auth Throttle] ' . $exception->getMessage());
        }
    }
}

function canAccessPage(string $page, string $role): bool
{
    $role = strtolower(trim($role));

    $permissions = [
        'admin' => ['*'],
        'manager' => [
            'dashboard', 'inventory', 'customers', 'suppliers', 'reports', 'receiving',
            'sales', 'deliveries', 'expenses', 'appointments', 'employees', 'invoices',
            'quotations', 'purchase-orders', 'returns', 'transactions', 'locations', 'messages', 'security-logs'
        ],
        'staff' => ['dashboard', 'inventory', 'customers', 'sales', 'transactions', 'reports'],
        'cashier' => ['dashboard', 'customers', 'sales', 'transactions'],
    ];

    $allowed = $permissions[$role] ?? ['dashboard'];

    return in_array('*', $allowed, true) || in_array($page, $allowed, true);
}
