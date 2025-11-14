<?php
declare(strict_types=1);

require __DIR__ . '/../htdocs/src/autoload.php';

// Mock config for tests
function markly_config(): array {
    return [
        'db' => [
            'dsn' => 'sqlite::memory:',
            'user' => '',
            'pass' => '',
        ],
        'session_name' => 'markly_test',
        'base_url' => '/',
        'debug' => true,
        'demo_mode' => false,
        'session_regen_interval' => 0,
        'app' => [
            'name' => 'Markly',
            'version' => '1.2.0',
        ],
    ];
}

function markly_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}
