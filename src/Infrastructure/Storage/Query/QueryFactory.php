<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

interface QueryFactory
{
    public function createQueryBuilder(string $table): QueryBuilder;
}
