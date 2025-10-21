<?php
declare(strict_types=1);

return [
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=markly;charset=utf8mb4',
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
    ],
    'session_name' => 'markly_session',
    'base_url' => '',
    'debug' => false,
    'demo_mode' => true,
    'session_regen_interval' => 0,
];
