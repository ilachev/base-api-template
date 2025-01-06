<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration;

use App\Infrastructure\Storage\StorageInterface;

final readonly class MigrationRepository
{
    public function __construct(
        private StorageInterface $storage,
    ) {
        $this->createMigrationsTable();
    }

    public function createMigrationsTable(): void
    {
        $sql = <<<'SQL'
                CREATE TABLE IF NOT EXISTS migrations (
                    version TEXT PRIMARY KEY,
                    executed_at INTEGER NOT NULL
                )
            SQL;

        $this->storage->execute($sql);
    }

    /**
     * @return list<scalar|null>
     */
    public function getExecutedMigrations(): array
    {
        $result = $this->storage->query('SELECT version FROM migrations ORDER BY version');

        return array_column($result, 'version');
    }

    public function add(string $version): void
    {
        $this->storage->execute(
            'INSERT INTO migrations (version, executed_at) VALUES (:version, :executed_at)',
            [
                'version' => $version,
                'executed_at' => time(),
            ],
        );
    }

    public function remove(string $version): void
    {
        $this->storage->execute(
            'DELETE FROM migrations WHERE version = :version',
            ['version' => $version],
        );
    }
}
