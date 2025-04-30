<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

final readonly class PostgreSQLQueryFactory implements QueryFactory
{
    public function __construct(
        private string $schema = 'public',
    ) {}

    public function createQueryBuilder(string $table): QueryBuilder
    {
        return PostgreSQLQueryBuilder::table($table, $this->schema);
    }
}
