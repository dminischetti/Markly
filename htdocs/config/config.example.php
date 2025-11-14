<?php
declare(strict_types=1);

return [
    'db' => [
        'dsn'  => getenv('MARKLY_DB_DSN') ?: 'mysql:host=localhost;dbname=markly;charset=utf8mb4',
        'user' => getenv('MARKLY_DB_USER') ?: 'your_db_user',
        'pass' => getenv('MARKLY_DB_PASS') ?: 'your_db_password',
    ],
    'session_name' => getenv('MARKLY_SESSION_NAME') ?: 'markly_session',
    'base_url' => getenv('MARKLY_BASE_URL') ?: '/markly',
    'debug' => getenv('MARKLY_DEBUG') === '1',
    // Enable only for demos and testing; disables session regeneration safeguards.
    'demo_mode' => false,
    'session_regen_interval' => (int)(getenv('MARKLY_SESSION_REGEN') ?: 300),
];
