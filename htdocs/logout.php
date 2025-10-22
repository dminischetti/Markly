<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/src/autoload.php';

use Markly\Auth;
use Markly\Csrf;

$config = markly_config();
$pdo = markly_pdo();
$auth = new Auth($pdo, $config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    if (Csrf::validate($token)) {
        $auth->logout();
    }
    header('Location: /login.php');
    exit;
}

$auth->logout();
header('Location: /login.php');
exit;
