<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration\SQLite;

use App\Infrastructure\Storage\Migration\AbstractMigration;

final readonly class Migration20240317001_CreateSessions extends AbstractMigration
{
    public function up(): string
    {
        return <<<'SQL'
                CREATE TABLE sessions (
                    id TEXT PRIMARY KEY,
                    user_id INTEGER,
                    payload TEXT NOT NULL,
                    expires_at INTEGER NOT NULL,
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL
                );
                CREATE INDEX idx_sessions_user_id ON sessions(user_id);
                CREATE INDEX idx_sessions_expires_at ON sessions(expires_at);
            SQL;
    }

    public function down(): string
    {
        return <<<'SQL'
                DROP TABLE IF EXISTS sessions;
            SQL;
    }
}
