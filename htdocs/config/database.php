<?php
declare(strict_types=1);

use Markly\Constants;

/**
 * Returns the merged configuration array.
 *
 * @return array<string, mixed>
 */
function markly_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $configFile = __DIR__ . '/config.php';
    if (!is_file($configFile)) {
        $configFile = __DIR__ . '/config.example.php';
    }

    $loaded = require $configFile;
    if (!is_array($loaded) || !isset($loaded['db'])) {
        throw new RuntimeException('Invalid configuration.');
    }

    $config = array_merge([
        'session_name'            => 'markly_session',
        'base_url'                => Constants::BASE_PATH,
        'debug'                   => false,
        'demo_mode'               => false,
        'session_regen_interval'  => 300,
    ], $loaded);

    $config['base_url'] = markly_normalize_base_path((string)($config['base_url'] ?? '/'));

    if (!defined('MARKLY_DEBUG_INITIALISED')) {
        define('MARKLY_DEBUG_INITIALISED', true);
        ini_set('display_errors', $config['debug'] ? '1' : '0');
        error_reporting($config['debug'] ? E_ALL : E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    }

    $appOverrides = [];
    if (isset($loaded['app']) && is_array($loaded['app'])) {
        $appOverrides = $loaded['app'];
    }

    $config['app'] = array_merge([
        'name'    => Constants::APP_NAME,
        'tagline' => Constants::APP_TAGLINE,
        'version' => Constants::APP_VERSION,
        'base'    => $config['base_url'],
    ], $appOverrides);

    return $config;
}

/**
 * Normalises a configured base path to a consistent format.
 */
function markly_normalize_base_path(string $base): string
{
    $trimmed = trim($base);

    if ($trimmed === '' || $trimmed === '/') {
        return '/';
    }

    if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
        return rtrim($trimmed, '/');
    }

    return '/' . trim($trimmed, '/');
}

/**
 * Generates an absolute path (or URL) within the configured base.
 */
function markly_base_path(string $path = ''): string
{
    $config = markly_config();
    $base = (string)($config['base_url'] ?? '/');
    $isAbsolute = str_starts_with($base, 'http://') || str_starts_with($base, 'https://');

    if ($path === '' || $path === '/') {
        if ($isAbsolute) {
            return rtrim($base, '/') . '/';
        }

        return $base === '/' ? '/' : rtrim($base, '/') . '/';
    }

    $normalized = '/' . ltrim($path, '/');

    if ($isAbsolute) {
        return rtrim($base, '/') . $normalized;
    }

    if ($base === '/' || $base === '') {
        return $normalized;
    }

    return rtrim($base, '/') . $normalized;
}

/**
 * Returns a shared PDO instance configured for strict error handling.
 */
function markly_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = markly_config();
    $dbConfig = $config['db'];

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    $pdo = new PDO($dbConfig['dsn'], $dbConfig['user'], $dbConfig['pass'], $options);

    return $pdo;
}
