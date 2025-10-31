<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/src/autoload.php';

use Markly\Auth;
use Markly\Constants;
use Markly\Csrf;

$config = markly_config();
$pdo = markly_pdo();
$auth = new Auth($pdo, $config);

$error = null;

if ($auth->check()) {
    header('Location: /index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    if (!Csrf::validate($token)) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } elseif (!$auth->login($email, $password)) {
            $error = 'Invalid email or password.';
        } else {
            header('Location: /index.php');
            exit;
        }
    }
}

$token = Csrf::issue();
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(Constants::APP_NAME . ' · Sign in', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#f9fafb">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-body">
    <main class="auth-card" role="main">
        <h1><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
        <p class="auth-subtitle"><?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php if ($error !== null): ?>
            <div class="auth-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" class="auth-form" novalidate>
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" autocomplete="email" required>
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="btn-primary">Sign in</button>
        </form>
        <p class="auth-footer">Demo account: <code>admin@example.com</code> / <code>admin123</code></p>
    </main>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function () {
            var fallback = document.createElement('div');
            fallback.className = 'sw-fallback';
            fallback.textContent = 'Offline mode is unavailable in this browser session.';
            document.body.appendChild(fallback);
        });
    }
    </script>
</body>
</html>
