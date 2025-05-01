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
                    payload JSONB NOT NULL,
                    expires_at BIGINT NOT NULL,
                    created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL
                );
                CREATE INDEX idx_sessions_user_id ON sessions(user_id);
                CREATE INDEX idx_sessions_expires_at ON sessions(expires_at);
                CREATE INDEX idx_sessions_ip ON sessions((payload->>'ip'));
                CREATE INDEX idx_sessions_fingerprint ON sessions((payload->>'fingerprint'));
            SQL;
    }

    public function down(): string
    {
        return <<<'SQL'
                DROP TABLE IF EXISTS sessions;
            SQL;
    }
}
