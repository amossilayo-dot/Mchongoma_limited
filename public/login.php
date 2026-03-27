<?php

declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com;");

require_once __DIR__ . '/../app/Auth.php';

ensureSecureSessionStarted();

if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = null;
$redirect = (string) ($_GET['redirect'] ?? 'index.php');
if ($redirect === '' || str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://')) {
    $redirect = 'index.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hasValidLoginCsrfToken()) {
        $error = 'Session expired. Refresh and try again.';
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = authenticateUser($email, $password);

        if (($result['ok'] ?? false) === true) {
            if (($result['force_password_change'] ?? false) === true) {
                header('Location: change_password.php');
                exit;
            }

            $postRedirect = (string) ($_POST['redirect'] ?? 'index.php');
            if ($postRedirect === '' || str_starts_with($postRedirect, 'http://') || str_starts_with($postRedirect, 'https://')) {
                $postRedirect = 'index.php';
            }
            header('Location: ' . $postRedirect);
            exit;
        }

        $error = (string) ($result['message'] ?? 'Login failed.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Mchongoma Limited</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
<div class="login-shell">
    <section class="login-brand-panel">
        <div class="login-brand-mark"><i class="fa-solid fa-bag-shopping"></i></div>
        <h1>Mchongoma Limited</h1>
        <p>Manage sales, inventory, customers, and reports from one focused workspace.</p>
        <div class="login-brand-pills">
            <span><i class="fa-solid fa-shield-halved"></i> Secure Session</span>
            <span><i class="fa-solid fa-chart-line"></i> Live Dashboard</span>
            <span><i class="fa-solid fa-warehouse"></i> Inventory Control</span>
        </div>
    </section>

    <section class="login-card" aria-label="Login form">
        <h2>Welcome back</h2>
        <p class="login-subtitle">Sign in to continue to your dashboard</p>

        <?php if (canUseDemoLoginFallback()): ?>
            <p class="login-hint" style="margin-top: 0; margin-bottom: 12px;">
                Offline demo: <strong>admin</strong> / <strong>admin123</strong>
            </p>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="login-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(loginCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

            <label for="email">Username or Email</label>
            <div class="login-input-wrap">
                <i class="fa-regular fa-envelope"></i>
                <input id="email" name="email" type="text" required placeholder="admin or admin@mchongoma.com" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <label for="password">Password</label>
            <div class="login-input-wrap has-action">
                <i class="fa-solid fa-lock"></i>
                <input id="password" name="password" type="password" required placeholder="Enter your password">
                <button type="button" class="login-password-toggle" id="passwordToggle" aria-label="Show password" aria-pressed="false">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>

            <div class="login-meta-row">
                <label class="login-check" for="remember_me">
                    <input id="remember_me" name="remember_me" type="checkbox" value="1">
                    <span>Remember me</span>
                </label>
                <a class="login-text-link" href="mailto:support@mchongoma.com?subject=Password%20Reset%20Request">Forgot password?</a>
            </div>

            <button class="login-btn" type="submit" id="loginSubmitBtn">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Sign In</span>
            </button>
        </form>

        <p class="login-support">Need help? Contact <a class="login-text-link" href="mailto:support@mchongoma.com">support@mchongoma.com</a></p>
        <p class="login-legal">
            <a class="login-text-link" href="#" aria-label="Privacy Policy">Privacy Policy</a>
            <span>•</span>
            <a class="login-text-link" href="#" aria-label="Terms of Use">Terms of Use</a>
        </p>
        <p class="login-hint">Mchongoma Limited &copy; 2026</p>
    </section>
</div>
<script src="assets/js/login.js"></script>
</body>
</html>
