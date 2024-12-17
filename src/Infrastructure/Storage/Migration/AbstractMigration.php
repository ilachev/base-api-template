<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration;

readonly abstract class AbstractMigration implements MigrationInterface
{
    public function getVersion(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }
}
