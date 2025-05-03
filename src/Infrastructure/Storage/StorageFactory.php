<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Storage\Query\QueryFactory;

/**
 * Factory for creating storage instances based on configuration.
 */
final readonly class StorageFactory
{
    /**
     * @param array{
     *     engine: string,
     *     sqlite?: array{database: string},
     *     pgsql?: array{
     *         host: string,
     *         port: int,
     *         database: string,
     *         username: string,
     *         password: string,
     *         schema?: string
     *     }
     * } $config
     */
    public function __construct(
        private array $config,
        private Logger $logger,
    ) {}

    /**
     * Create a storage implementation based on configuration.
     */
    public function createStorage(): Storage
    {
        $engine = $this->config['engine'];

        $this->logger->info("Creating {$engine} storage");

        return match ($engine) {
            'sqlite' => $this->createSQLiteStorage(),
            'pgsql' => $this->createPostgreSQLStorage(),
            default => throw new StorageException("Unsupported storage engine: {$engine}"),
        };
    }

    /**
     * Create a query factory for the configured storage engine.
     */
    public function createQueryFactory(): QueryFactory
    {
        $engine = $this->config['engine'];

        if ($engine === 'pgsql') {
            $schema = $this->config['pgsql']['schema'] ?? 'public';

            return new Query\PostgreSQLQueryFactory($schema);
        }

        return new Query\SQLiteQueryFactory();
    }

    private function createSQLiteStorage(): SQLiteStorage
    {
        $databasePath = $this->config['sqlite']['database'] ?? __DIR__ . '/../../../db/app.sqlite';
        $databaseDir = \dirname($databasePath);

        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0o755, true);
        }

        return new SQLiteStorage($databasePath);
    }

    private function createPostgreSQLStorage(): PostgreSQLStorage
    {
        $pgConfig = $this->config['pgsql'] ?? [];

        $host = $pgConfig['host'] ?? 'localhost';
        $port = $pgConfig['port'] ?? 5432;
        $database = $pgConfig['database'] ?? 'app';
        $username = $pgConfig['username'] ?? 'app';
        $password = $pgConfig['password'] ?? 'password';
        $schema = $pgConfig['schema'] ?? 'public';

        return new PostgreSQLStorage(
            host: $host,
            port: $port,
            dbname: $database,
            username: $username,
            password: $password,
            schema: $schema,
        );
    }
}
