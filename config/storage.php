<?php

declare(strict_types=1);

return [
    // The storage engine to use: 'sqlite' or 'pgsql'
    'engine' => 'pgsql',

    // SQLite configuration
    'sqlite' => [
        'database' => __DIR__ . '/../db/app.sqlite',
        'migrations_path' => __DIR__ . '/../src/Infrastructure/Storage/Migration/SQLite',
    ],

    // PostgreSQL configuration
    'pgsql' => [
        'host' => getenv('DB_HOST') ?: 'localhost',  // Use 'db-postgres' if connecting from within Docker
        'port' => (int) (getenv('DB_PORT') ?: 5432),
        'database' => getenv('DB_NAME') ?: 'app',
        'username' => getenv('DB_USER') ?: 'app',
        'password' => getenv('DB_PASSWORD') ?: 'password',
        'schema' => 'public',
        'migrations_path' => __DIR__ . '/../src/Infrastructure/Storage/Migration/PostgreSQL',
    ],
];
