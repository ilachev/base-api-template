<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Storage\Migration\MigrationInterface;
use App\Infrastructure\Storage\Migration\MigrationService;

final readonly class MigrateCommand
{
    public function __construct(
        private MigrationService $migrationService,
    ) {
        foreach ($this->findMigrations() as $migrationClass) {
            $this->migrationService->addMigration(new $migrationClass());
        }
    }

    /**
     * @return list<class-string<MigrationInterface>>
     */
    private function findMigrations(): array
    {
        $migrations = [];
        $migrationPath = __DIR__ . '/../../Infrastructure/Storage/Migration';

        if (!is_dir($migrationPath)) {
            return [];
        }

        $files = glob("{$migrationPath}/Migration[0-9]*.php");
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $className = 'App\Infrastructure\Storage\Migration\\'
                . basename($file, '.php');

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            if (!$reflection->implementsInterface(MigrationInterface::class)) {
                continue;
            }
            /** @var class-string<MigrationInterface> $className */
            $migrations[] = $className;
        }

        return $migrations;
    }

    public function migrate(): void
    {
        $this->migrationService->migrate();
    }

    public function rollback(): void
    {
        $this->migrationService->rollback();
    }
}
