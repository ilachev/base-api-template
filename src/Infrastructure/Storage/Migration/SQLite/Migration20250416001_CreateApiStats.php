<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration\SQLite;

use App\Infrastructure\Storage\Migration\AbstractMigration;

final readonly class Migration20250416001_CreateApiStats extends AbstractMigration
{
    public function up(): string
    {
        return <<<'SQL'
                CREATE TABLE api_stats (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL,
                    route TEXT NOT NULL,
                    method TEXT NOT NULL,
                    status_code INTEGER NOT NULL,
                    execution_time REAL NOT NULL,
                    request_time INTEGER NOT NULL,
                    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
                );
                CREATE INDEX idx_api_stats_session_id ON api_stats(session_id);
                CREATE INDEX idx_api_stats_route ON api_stats(route);
                CREATE INDEX idx_api_stats_request_time ON api_stats(request_time);
            SQL;
    }

    public function down(): string
    {
        return <<<'SQL'
                DROP TABLE IF EXISTS api_stats;
            SQL;
    }
}
