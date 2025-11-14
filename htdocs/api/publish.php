<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/autoload.php';

use Markly\Auth;
use Markly\Csrf;
use Markly\NotesRepo;
use Markly\Response;

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    Response::json([
        'error' => 'unsupported_method',
        'message' => 'HTTP method not allowed.',
    ], 405);
}

$config = markly_config();
$pdo = markly_pdo();
$auth = new Auth($pdo, $config);

if (!$auth->check()) {
    Response::json([
        'error' => 'unauthorised',
        'message' => 'Authentication is required to publish notes.',
    ], 401);
}

$payload = read_payload();
Csrf::require(fetch_token($payload));

$id = (int)($payload['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) {
    Response::json([
        'error' => 'validation_failed',
        'message' => 'A valid note id is required.',
        'details' => ['id' => 'required'],
    ], 422);
}

$publicFlag = $payload['public'] ?? $_GET['public'] ?? '0';
$public = filter_var($publicFlag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($public === null) {
    $public = (string)$publicFlag === '1';
}

$user = $auth->user();
$repo = new NotesRepo($pdo);
$ok = $repo->togglePublish($id, (int)$user['id'], (bool)$public);

if (!$ok) {
    Response::json([
        'error' => 'not_found',
        'message' => 'The requested note could not be found.',
    ], 404);
}

Response::json(['ok' => true, 'is_public' => (bool)$public]);

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

/**
 * @param array<string, mixed> $payload
 */
function fetch_token(array $payload): ?string
{
    $token = $payload['_token'] ?? Csrf::tokenFromRequest();
    return is_string($token) ? $token : null;
}
