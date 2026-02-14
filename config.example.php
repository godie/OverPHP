<?php

declare(strict_types=1);

return [
    'allowed_origins' => [
        'http://localhost:5173',
    ],
    'route_prefix' => getenv('API_PREFIX') ?: '/api',
    'benchmark' => [
        'enabled' => filter_var(getenv('BENCHMARK_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],
    // ─── Database ─────────────────────────────────────────────────
    // Supported drivers: 'mysql', 'sqlite'
    // Set DB_DRIVER env var or change 'driver' below.
    'database' => [
        'enabled' => filter_var(getenv('DB_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'driver' => getenv('DB_DRIVER') ?: 'mysql',

        // --- MySQL configuration ---
        'mysql' => [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_NAME') ?: 'overphp',
            'username' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
            'options' => [],
        ],

        // --- SQLite configuration ---
        // To use SQLite, set 'driver' => 'sqlite' (or DB_DRIVER=sqlite)
        // and uncomment the block below.
        //
        // 'sqlite' => [
        //     // File-based database (the file is created automatically):
        //     'path' => getenv('DB_SQLITE_PATH') ?: __DIR__ . '/database/overphp.sqlite',
        //
        //     // Or use an in-memory database (data lost on shutdown):
        //     // 'path' => ':memory:',
        //
        //     'options' => [],
        // ],
    ],
];
