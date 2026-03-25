<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

ensureSecureSessionStarted();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php');
    exit;
}

$sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
$requestToken = (string) ($_POST['csrf_token'] ?? '');

if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
    header('Location: index.php');
    exit;
}

logoutUser();
header('Location: login.php');
exit;
