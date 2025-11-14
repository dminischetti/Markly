<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/autoload.php';

use Markly\Auth;
use Markly\Csrf;
use Markly\LinksRepo;
use Markly\NotesRepo;
use Markly\Response;
use RuntimeException;

$config = markly_config();
$pdo = markly_pdo();
$auth = new Auth($pdo, $config);
$notesRepo = new NotesRepo($pdo);
$linksRepo = new LinksRepo($pdo);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$payload = [];

if ($method !== 'GET') {
    $payload = read_payload();
}

switch ($method) {
    case 'GET':
        handle_get($auth, $notesRepo, $linksRepo);
        break;
    case 'POST':
        handle_post($auth, $notesRepo, $payload);
        break;
    default:
        Response::json([
            'error' => 'unsupported_method',
            'message' => 'HTTP method not allowed.',
        ], 405);
}

function handle_get(Auth $auth, NotesRepo $notesRepo, LinksRepo $linksRepo): void
{
    $user = $auth->user();
    $userId = $user['id'] ?? null;
    $isAuth = $auth->check();

    if (isset($_GET['id']) || isset($_GET['slug'])) {
        $note = null;
        $etagHeader = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if (isset($_GET['id']) && $isAuth) {
            $note = $notesRepo->findById((int)$_GET['id'], (int)$userId);
        } elseif (isset($_GET['slug'])) {
            $slug = (string)$_GET['slug'];
            if ($isAuth) {
                $note = $notesRepo->findBySlug($slug, (int)$userId);
            }
            if ($note === null) {
                $note = $notesRepo->findPublicBySlug($slug);
            }
        }

        if ($note === null) {
            Response::json([
                'error' => 'not_found',
                'message' => 'The requested note could not be found.',
            ], 404);
        }

        $etag = '"v' . (int)$note['version'] . '"';
        if ($etagHeader !== '' && $etagHeader === $etag) {
            http_response_code(304);
            header('ETag: ' . $etag);
            exit;
        }

        Response::json([
            'note' => format_note($note),
            'etag' => $etag,
        ], 200, ['ETag' => $etag]);
    }

    if (!$isAuth) {
        Response::json([
            'error' => 'unauthorised',
            'message' => 'Authentication is required to access notes.',
        ], 401);
    }

    if (isset($_GET['search'])) {
        $term = (string)$_GET['search'];
        $results = array_map('format_note_summary', $notesRepo->search((int)$userId, $term));
        Response::json(['results' => $results]);
    }

    if (isset($_GET['tag'])) {
        $tag = (string)$_GET['tag'];
        $results = array_map('format_note_summary', $notesRepo->listByTag((int)$userId, $tag));
        Response::json(['results' => $results]);
    }

    if (isset($_GET['backlinks'])) {
        $slug = (string)$_GET['backlinks'];
        $results = $linksRepo->backlinks((int)$userId, $slug);
        Response::json(['results' => $results]);
    }

    if (isset($_GET['graph'])) {
        $graph = $linksRepo->graph((int)$userId);
        Response::json($graph);
    }

    $notes = array_map('format_note_summary', $notesRepo->listForUser((int)$userId));
    $tags = $notesRepo->tagsForUser((int)$userId);
    Response::json([
        'notes' => $notes,
        'tags'  => $tags,
    ]);
}

function handle_post(Auth $auth, NotesRepo $notesRepo, array $payload): void
{
    if (!$auth->check()) {
        Response::json([
            'error' => 'unauthorised',
            'message' => 'Authentication is required to modify notes.',
        ], 401);
    }

    $user = $auth->user();
    $userId = (int)$user['id'];

    $methodOverride = strtoupper((string)($payload['_method'] ?? 'POST'));

    if ($methodOverride === 'POST') {
        Csrf::require(fetch_token($payload));
        $title = trim((string)($payload['title'] ?? ''));
        $content = (string)($payload['content'] ?? '');
        if ($title === '' || $content === '') {
            Response::json([
                'error' => 'validation_failed',
                'message' => 'Title and content are required.',
                'details' => [
                    'title' => $title === '' ? 'required' : 'filled',
                    'content' => $content === '' ? 'required' : 'filled',
                ],
            ], 422);
        }
        $note = $notesRepo->create($userId, [
            'title'   => $title,
            'content' => $content,
            'tags'    => (string)($payload['tags'] ?? ''),
            'slug'    => $payload['slug'] ?? null,
        ]);
        Response::json(['note' => format_note($note)], 201, ['ETag' => '"v' . (int)$note['version'] . '"']);
    }

    if ($methodOverride === 'PUT') {
        Csrf::require(fetch_token($payload));
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            Response::json([
                'error' => 'validation_failed',
                'message' => 'A valid note id is required.',
                'details' => ['id' => 'required'],
            ], 422);
        }

        $expected = extract_version_from_match();
        if ($expected === null) {
            $expected = (int)($payload['version'] ?? 0);
        }
        if ($expected <= 0) {
            Response::json([
                'error' => 'missing_if_match',
                'message' => 'Provide the current note version via If-Match header or payload.',
            ], 428);
        }

        try {
            $note = $notesRepo->update($id, $userId, [
                'title'   => (string)($payload['title'] ?? ''),
                'content' => (string)($payload['content'] ?? ''),
                'tags'    => (string)($payload['tags'] ?? ''),
                'slug'    => $payload['slug'] ?? null,
            ], $expected);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'version_conflict') {
                Response::json([
                    'error' => 'version_conflict',
                    'message' => 'The note was updated elsewhere. Refresh and retry.',
                ], 409);
            }
            Response::json([
                'error' => 'not_found',
                'message' => 'The requested note could not be found.',
            ], 404);
        }

        $etag = '"v' . (int)$note['version'] . '"';
        Response::json(['note' => format_note($note)], 200, ['ETag' => $etag]);
    }

    if ($methodOverride === 'DELETE') {
        Csrf::require(fetch_token($payload));
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            Response::json([
                'error' => 'validation_failed',
                'message' => 'A valid note id is required.',
                'details' => ['id' => 'required'],
            ], 422);
        }
        $notesRepo->delete($id, $userId);
        Response::json(['ok' => true]);
    }

    Response::json([
        'error' => 'unknown_action',
        'message' => 'Unsupported _method override provided.',
    ], 400);
}

/**
 * @param array<string, mixed> $note
 * @return array<string, mixed>
 */
function format_note(array $note): array
{
    return [
        'id'         => (int)$note['id'],
        'slug'       => (string)$note['slug'],
        'title'      => (string)$note['title'],
        'content'    => (string)$note['content'],
        'tags'       => $note['tags'] !== '' ? explode(',', (string)$note['tags']) : [],
        'tags_raw'   => (string)$note['tags'],
        'is_public'  => (bool)$note['is_public'],
        'version'    => (int)$note['version'],
        'updated_at' => (string)$note['updated_at'],
        'created_at' => (string)$note['created_at'],
    ];
}

/**
 * @param array<string, mixed> $note
 * @return array<string, mixed>
 */
function format_note_summary(array $note): array
{
    return [
        'id'         => (int)$note['id'],
        'slug'       => (string)$note['slug'],
        'title'      => (string)$note['title'],
        'tags'       => $note['tags'] !== '' ? explode(',', (string)$note['tags']) : [],
        'is_public'  => isset($note['is_public']) ? (bool)$note['is_public'] : false,
        'version'    => isset($note['version']) ? (int)$note['version'] : null,
        'updated_at' => (string)$note['updated_at'],
    ];
}

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

function extract_version_from_match(): ?int
{
    $header = $_SERVER['HTTP_IF_MATCH'] ?? '';
    if (!is_string($header) || $header === '') {
        return null;
    }

    if (preg_match('/"v(\d+)"/', $header, $m) === 1) {
        return (int)$m[1];
    }

    return null;
}
