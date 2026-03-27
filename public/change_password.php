<?php

declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com;");

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';

ensureSecureSessionStarted();
requireAuthentication();

if (!isPasswordChangeRequired()) {
    header('Location: index.php');
    exit;
}

$user = currentUser();
$userId = (int) (($user['id'] ?? 0) ?: 0);
$error = null;
$success = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hasValidPasswordChangeCsrfToken()) {
        $error = 'Request validation failed. Refresh and try again.';
    } else {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!hash_equals($newPassword, $confirmPassword)) {
            $error = 'New password and confirmation do not match.';
        } else {
            try {
                $pdo = getDatabaseConnection();
                $result = changeAuthenticatedUserPassword($pdo, $userId, $currentPassword, $newPassword);
                if (($result['ok'] ?? false) === true) {
                    $success = 'Password changed successfully. Redirecting...';
                    header('Refresh: 1; url=index.php');
                } else {
                    $error = (string) ($result['message'] ?? 'Unable to change password.');
                }
            } catch (Throwable $exception) {
                error_log('[Password Change] ' . $exception->getMessage());
                $error = 'Unable to update password right now. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Mchongoma Limited</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
<div class="login-shell">
    <section class="login-brand-panel">
        <div class="login-brand-mark"><i class="fa-solid fa-key"></i></div>
        <h1>Password Update Required</h1>
        <p>Your account is using an installation password. Set a personal password to continue securely.</p>
        <div class="login-brand-pills">
            <span><i class="fa-solid fa-shield-halved"></i> Secure Account</span>
            <span><i class="fa-solid fa-user-lock"></i> One-Time Rotation</span>
        </div>
    </section>

    <section class="login-card" aria-label="Change password form">
        <h2>Change Password</h2>
        <p class="login-subtitle">Signed in as <?= htmlspecialchars((string) ($user['email'] ?? $user['name'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?></p>

        <?php if ($error !== null): ?>
            <div class="login-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($success !== null): ?>
            <div class="status-badge status-success" style="margin-bottom: 12px;"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(passwordChangeCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <label for="current_password">Current Password</label>
            <div class="login-input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input id="current_password" name="current_password" type="password" required placeholder="Current password">
            </div>

            <label for="new_password">New Password</label>
            <div class="login-input-wrap">
                <i class="fa-solid fa-key"></i>
                <input id="new_password" name="new_password" type="password" required minlength="8" placeholder="At least 8 characters">
            </div>

            <label for="confirm_password">Confirm New Password</label>
            <div class="login-input-wrap">
                <i class="fa-solid fa-check"></i>
                <input id="confirm_password" name="confirm_password" type="password" required minlength="8" placeholder="Repeat new password">
            </div>

            <button class="login-btn" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Update Password</span>
            </button>
        </form>

        <p class="login-support">If you are not the intended user, <a class="login-text-link" href="logout.php">sign out</a>.</p>
    </section>
</div>
</body>
</html>
