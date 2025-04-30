<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

final readonly class SQLiteQueryFactory implements QueryFactory
{
    public function createQueryBuilder(string $table): QueryBuilder
    {
        return SQLiteQueryBuilder::table($table);
    }
}
