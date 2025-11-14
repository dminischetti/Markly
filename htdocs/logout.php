<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/src/autoload.php';

use Markly\Auth;
use Markly\Csrf;

$config = markly_config();
$pdo = markly_pdo();
$auth = new Auth($pdo, $config);
$asset = static fn(string $path = ''): string => markly_base_path($path);
$loginUrl = $asset('login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    if (Csrf::validate($token)) {
        $auth->logout();
    }
    header('Location: ' . $loginUrl);
    exit;
}

$auth->logout();
header('Location: ' . $loginUrl);
exit;
