<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

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

function authenticateUser(string $email, string $password): array
{
    $email = trim($email);
    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Email and password are required.'];
    }

    try {
        $pdo = getDatabaseConnection();
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => 'Database is unavailable. Please try again in a moment.'];
    }

    if (!hasUsersTable($pdo)) {
        return ['ok' => false, 'message' => 'Users table is missing. Import database_schema.sql to enable login.'];
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, password, role
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];

    return ['ok' => true, 'message' => null];
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

function canAccessPage(string $page, string $role): bool
{
    $role = strtolower(trim($role));

    $permissions = [
        'admin' => ['*'],
        'manager' => [
            'dashboard', 'inventory', 'customers', 'suppliers', 'reports', 'receiving',
            'sales', 'deliveries', 'expenses', 'appointments', 'employees', 'invoices',
            'quotations', 'purchase-orders', 'returns', 'transactions', 'locations', 'messages'
        ],
        'staff' => ['dashboard', 'inventory', 'customers', 'sales', 'transactions', 'reports'],
        'cashier' => ['dashboard', 'customers', 'sales', 'transactions'],
    ];

    $allowed = $permissions[$role] ?? ['dashboard'];

    return in_array('*', $allowed, true) || in_array($page, $allowed, true);
}
