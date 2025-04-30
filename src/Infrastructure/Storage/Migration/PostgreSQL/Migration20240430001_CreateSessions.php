<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration\PostgreSQL;

use App\Infrastructure\Storage\Migration\AbstractMigration;

final readonly class Migration20240430001_CreateSessions extends AbstractMigration
{
    public function up(): string
    {
        return <<<'SQL'
                CREATE TABLE sessions (
                    id TEXT PRIMARY KEY,
                    user_id INTEGER,
                    payload TEXT NOT NULL,
                    fingerprint TEXT,
                    ip TEXT NOT NULL,
                    expires_at INTEGER NOT NULL,
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL
                );
                CREATE INDEX idx_sessions_user_id ON sessions(user_id);
                CREATE INDEX idx_sessions_expires_at ON sessions(expires_at);
                CREATE INDEX idx_sessions_fingerprint ON sessions(fingerprint);
                CREATE INDEX idx_sessions_ip ON sessions(ip);
            SQL;
    }

    public function down(): string
    {
        return <<<'SQL'
                DROP TABLE IF EXISTS sessions;
            SQL;
    }
}
