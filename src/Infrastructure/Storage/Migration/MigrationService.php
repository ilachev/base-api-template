<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration;

use App\Infrastructure\Storage\Storage;

final class MigrationService
{
    /** @var array<Migration> */
    private array $migrations = [];

    public function __construct(
        private readonly Storage $storage,
        private readonly MigrationRepository $repository,
        private readonly MigrationLoader $loader,
    ) {}

    /**
     * Load all migrations from the configured directory.
     */
    public function loadMigrations(): void
    {
        $this->migrations = $this->loader->loadMigrations();
    }

    /**
     * Add a migration manually.
     */
    public function addMigration(Migration $migration): void
    {
        $this->migrations[] = $migration;
    }

    public function migrate(): void
    {
        $executedMigrations = $this->repository->getExecutedMigrations();
        $processedVersions = [];

        usort(
            $this->migrations,
            static fn(Migration $a, Migration $b) => strcmp($a->getVersion(), $b->getVersion()),
        );

        foreach ($this->migrations as $migration) {
            $version = $migration->getVersion();
            if (\in_array($version, $processedVersions, true)) {
                continue;
            }

            if (!\in_array($version, $executedMigrations, true)) {
                $sqlQueries = array_filter(
                    array_map('trim', explode(';', $migration->up())),
                    static fn(string $sql): bool => !empty($sql),
                );

                foreach ($sqlQueries as $sql) {
                    $this->storage->execute($sql);
                }

                $this->repository->add($version);
            }

            $processedVersions[] = $version;
        }
    }

    public function rollback(): void
    {
        $executedMigrations = $this->repository->getExecutedMigrations();

        $migrations = array_filter(
            $this->migrations,
            static fn(Migration $migration) => \in_array($migration->getVersion(), $executedMigrations, true),
        );

        usort(
            $migrations,
            static fn(Migration $a, Migration $b) => strcmp($b->getVersion(), $a->getVersion()),
        );

        foreach ($migrations as $migration) {
            $version = $migration->getVersion();
            $sqlQueries = array_filter(
                array_map('trim', explode(';', $migration->down())),
                static fn(string $sql): bool => !empty($sql),
            );

            foreach ($sqlQueries as $sql) {
                $this->storage->execute($sql);
            }

            $this->repository->remove($version);
        }
    }
}
