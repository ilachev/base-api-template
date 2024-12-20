<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\Migration;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Storage\Migration\MigrationService;
use App\Infrastructure\Storage\Migration\MigrationInterface;
use App\Infrastructure\Storage\Migration\MigrationRepository;
use App\Infrastructure\Storage\SQLiteStorage;

final class MigrationServiceTest extends TestCase
{
    private const TEST_DB = ':memory:';
    private SQLiteStorage $storage;
    private MigrationRepository $repository;
    private MigrationService $service;

    protected function setUp(): void
    {
        $this->storage = new SQLiteStorage(self::TEST_DB);

        // Create migrations table
        $this->storage->execute(<<<SQL
            CREATE TABLE IF NOT EXISTS migrations (
                version TEXT PRIMARY KEY,
                executed_at INTEGER NOT NULL
            )
        SQL);

        $this->repository = new MigrationRepository($this->storage);
        $this->service = new MigrationService($this->storage, $this->repository);
    }

    public function testMigrateExecutesNewMigrationsInOrder(): void
    {
        // Arrange
        $migration1 = new class implements MigrationInterface {
            public function getVersion(): string
            {
                return '20240317001';
            }

            public function up(): string
            {
                return 'CREATE TABLE test1 (id INTEGER PRIMARY KEY);';
            }

            public function down(): string
            {
                return 'DROP TABLE test1;';
            }
        };

        $migration2 = new class implements MigrationInterface {
            public function getVersion(): string
            {
                return '20240317002';
            }

            public function up(): string
            {
                return 'CREATE TABLE test2 (id INTEGER PRIMARY KEY);';
            }

            public function down(): string
            {
                return 'DROP TABLE test2;';
            }
        };

        // Act
        $this->service->addMigration($migration2);
        $this->service->addMigration($migration1);
        $this->service->migrate();

        // Assert
        $tables = $this->storage->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('test1', 'test2')");
        $tableNames = array_column($tables, 'name');
        sort($tableNames);

        $this->assertEquals(['test1', 'test2'], $tableNames);

        $migrations = $this->storage->query('SELECT version FROM migrations ORDER BY version');
        $versions = array_column($migrations, 'version');

        $this->assertEquals(['20240317001', '20240317002'], $versions);
    }

    public function testMigrateHandlesMultipleStatements(): void
    {
        // Arrange
        $migration = new class implements MigrationInterface {
            public function getVersion(): string
            {
                return '20240317001';
            }

            public function up(): string
            {
                return <<<SQL
                    CREATE TABLE test1 (
                        id INTEGER PRIMARY KEY,
                        name TEXT NOT NULL
                    );
                    CREATE INDEX idx_test1_name ON test1(name);
                    CREATE TABLE test2 (id INTEGER PRIMARY KEY);
                SQL;
            }

            public function down(): string
            {
                return <<<SQL
                    DROP TABLE test2;
                    DROP TABLE test1;
                SQL;
            }
        };

        // Act
        $this->service->addMigration($migration);
        $this->service->migrate();

        // Assert
        $tables = $this->storage->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('test1', 'test2')");
        $tableNames = array_column($tables, 'name');
        sort($tableNames);

        $this->assertEquals(['test1', 'test2'], $tableNames);

        $indexes = $this->storage->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='test1'");
        $indexNames = array_column($indexes, 'name');

        $this->assertContains('idx_test1_name', $indexNames);
    }

    public function testRollbackExecutesMigrationsInReverseOrder(): void
    {
        // Arrange - execute migrations first
        $migration1 = new class implements MigrationInterface {
            public function getVersion(): string
            {
                return '20240317001';
            }

            public function up(): string
            {
                return 'CREATE TABLE test1 (id INTEGER PRIMARY KEY);';
            }

            public function down(): string
            {
                return 'DROP TABLE test1;';
            }
        };

        $migration2 = new class implements MigrationInterface {
            public function getVersion(): string
            {
                return '20240317002';
            }

            public function up(): string
            {
                return 'CREATE TABLE test2 (id INTEGER PRIMARY KEY);';
            }

            public function down(): string
            {
                return 'DROP TABLE test2;';
            }
        };

        $this->service->addMigration($migration1);
        $this->service->addMigration($migration2);
        $this->service->migrate();

        // Act - perform rollback
        $this->service->rollback();

        // Assert
        $tables = $this->storage->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('test1', 'test2')");

        $this->assertEmpty($tables);

        $migrations = $this->storage->query('SELECT version FROM migrations');

        $this->assertEmpty($migrations);
    }

    public function testMigrateSkipsAlreadyExecutedMigrations(): void
    {
        // Arrange
        $migration1 = new class implements MigrationInterface {
            public function getVersion(): string
            {
                return '20240317001';
            }

            public function up(): string
            {
                return 'CREATE TABLE test1 (id INTEGER PRIMARY KEY);';
            }

            public function down(): string
            {
                return 'DROP TABLE test1;';
            }
        };

        $migration2 = new class implements MigrationInterface {
            public function getVersion(): string
            {
                return '20240317002';
            }

            public function up(): string
            {
                return 'CREATE TABLE test2 (id INTEGER PRIMARY KEY);';
            }

            public function down(): string
            {
                return 'DROP TABLE test2;';
            }
        };

        // Add both migrations before execution
        $this->service->addMigration($migration1);
        $this->service->addMigration($migration2);

        // Execute first round of migrations
        $this->service->migrate();

        // Act - try to execute migrations again
        $this->service->migrate();

        // Assert
        $tables = $this->storage->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('test1', 'test2')");
        $tableNames = array_column($tables, 'name');
        sort($tableNames);

        $this->assertEquals(['test1', 'test2'], $tableNames);

        $migrations = $this->storage->query('SELECT version FROM migrations ORDER BY version');
        $versions = array_column($migrations, 'version');

        $this->assertEquals(['20240317001', '20240317002'], $versions);
    }
}
