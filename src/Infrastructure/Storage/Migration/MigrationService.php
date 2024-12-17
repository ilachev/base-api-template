<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration;

use App\Infrastructure\Storage\StorageInterface;

final class MigrationService
{
    /** @var array<MigrationInterface> */
    private array $migrations = [];

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly MigrationRepository $repository
    ) {}

    public function addMigration(MigrationInterface $migration): void
    {
        $this->migrations[] = $migration;
    }

    public function migrate(): void
    {
        $executedMigrations = $this->repository->getExecutedMigrations();

        usort(
            $this->migrations,
            static fn (MigrationInterface $a, MigrationInterface $b) =>
            strcmp($a->getVersion(), $b->getVersion())
        );

        foreach ($this->migrations as $migration) {
            $version = $migration->getVersion();
            if (!in_array($version, $executedMigrations, true)) {
                $sql = $migration->up();
                $this->storage->execute($sql);
                $this->repository->add($version);
            }
        }
    }

    public function rollback(): void
    {
        $executedMigrations = $this->repository->getExecutedMigrations();

        $migrations = array_filter(
            $this->migrations,
            static fn (MigrationInterface $migration) =>
            in_array($migration->getVersion(), $executedMigrations, true)
        );

        usort(
            $migrations,
            static fn (MigrationInterface $a, MigrationInterface $b) =>
            strcmp($b->getVersion(), $a->getVersion())
        );

        foreach ($migrations as $migration) {
            $version = $migration->getVersion();
            $sql = $migration->down();
            $this->storage->execute($sql);
            $this->repository->remove($version);
        }
    }
}
