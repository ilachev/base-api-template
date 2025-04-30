<?php

declare(strict_types=1);

return [
    // The storage engine to use: 'sqlite' or 'pgsql'
    'engine' => 'sqlite',

    // SQLite configuration
    'sqlite' => [
        'database' => __DIR__ . '/../db/app.sqlite',
        'migrations_path' => __DIR__ . '/../src/Infrastructure/Storage/Migration/SQLite',
    ],

    // PostgreSQL configuration
    'pgsql' => [
        'host' => 'localhost',
        'port' => 5432,
        'database' => 'app',
        'username' => 'app',
        'password' => 'password',
        'schema' => 'public',
        'migrations_path' => __DIR__ . '/../src/Infrastructure/Storage/Migration/PostgreSQL',
    ],
];
