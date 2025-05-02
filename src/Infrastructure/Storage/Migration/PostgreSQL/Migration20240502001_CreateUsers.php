<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration\PostgreSQL;

use App\Infrastructure\Storage\Migration\AbstractMigration;

final readonly class Migration20240502001_CreateUsers extends AbstractMigration
{
    public function up(): string
    {
        return <<<'SQL'
                CREATE TABLE users (
                    id BIGSERIAL PRIMARY KEY,
                    password_hash TEXT NOT NULL,
                    created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL
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
