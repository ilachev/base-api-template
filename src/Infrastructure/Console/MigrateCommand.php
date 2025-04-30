<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Storage\Migration\MigrationService;

final readonly class MigrateCommand
{
    public function __construct(
        private MigrationService $migrationService,
    ) {
        // Load migrations from the configured path
        $this->migrationService->loadMigrations();
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
