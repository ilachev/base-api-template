<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration\SQLite;

use App\Infrastructure\Storage\Migration\AbstractMigration;

final readonly class Migration20240502001_CreateUsers extends AbstractMigration
{
    public function up(): string
    {
        return <<<'SQL'
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    password_hash TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL
                );
            SQL;
    }

    public function down(): string
    {
        return <<<'SQL'
                DROP TABLE IF EXISTS users;
            SQL;
    }
}
