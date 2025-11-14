<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/autoload.php';

use Markly\Auth;
use Markly\Csrf;
use Markly\Response;

$config = markly_config();
$pdo = markly_pdo();
$auth = new Auth($pdo, $config);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string)($_GET['action'] ?? '');

if ($method === 'GET') {
    if ($action === 'session') {
        if ($auth->check()) {
            $user = $auth->user();
            Response::json([
                'auth'  => true,
                'email' => $user['email'],
            ]);
        }

        Response::json(['auth' => false]);
    }

    if ($action === 'csrf') {
        $token = Csrf::issue();
        Response::json(['csrf' => $token]);
    }

    Response::json([
        'error' => 'unknown_action',
        'message' => 'Action parameter is missing or unsupported.',
    ], 400);
}

$payload = read_payload();

if ($method === 'POST') {
    if ($action === 'logout') {
        $token = $payload['_token'] ?? Csrf::tokenFromRequest();
        Csrf::require(is_string($token) ? $token : null);
        $auth->logout();
        Response::json(['ok' => true]);
    }

    $token = $payload['_token'] ?? Csrf::tokenFromRequest();
    Csrf::require(is_string($token) ? $token : null);

    $email = trim((string)($payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');

    if ($email === '' || $password === '') {
        Response::json([
            'error' => 'validation_failed',
            'message' => 'Email and password are required.',
            'details' => [
                'email' => $email === '' ? 'required' : 'filled',
                'password' => $password === '' ? 'required' : 'filled',
            ],
        ], 422);
    }

    if (!$auth->login($email, $password)) {
        Response::json([
            'error' => 'invalid_credentials',
            'message' => 'Email or password is incorrect.',
        ], 401);
    }

    $user = $auth->user();
    $newToken = Csrf::issue();
    Response::json([
        'ok'    => true,
        'email' => $user['email'],
        'csrf'  => $newToken,
    ]);
}

Response::json([
    'error' => 'unsupported_method',
    'message' => 'Only GET and POST requests are accepted.',
], 405);

/**
 * @return array<string, mixed>
 */
function read_payload(): array
{
    $data = $_POST;

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if ($contentType && str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }
    } elseif ($data === []) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
                $data = $parsed;
            }
        }
    }

    return is_array($data) ? $data : [];
}
