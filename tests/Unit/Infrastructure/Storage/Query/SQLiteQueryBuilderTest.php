<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\Query;

use App\Infrastructure\Storage\Query\SQLiteQueryBuilder;
use App\Infrastructure\Storage\StorageException;
use PHPUnit\Framework\TestCase;

final class SQLiteQueryBuilderTest extends TestCase
{
    private SQLiteQueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = SQLiteQueryBuilder::table('test_table');
    }

    public function testSelect(): void
    {
        [$query, $params] = $this->builder
            ->select('name')
            ->buildSelectQuery();

        self::assertSame('SELECT name FROM test_table', $query);
        self::assertEmpty($params);
    }

    public function testSelectMultipleColumns(): void
    {
        [$query, $params] = $this->builder
            ->select(['id', 'name', 'email'])
            ->buildSelectQuery();

        self::assertSame('SELECT id, name, email FROM test_table', $query);
        self::assertEmpty($params);
    }

    public function testWhere(): void
    {
        [$query, $params] = $this->builder
            ->where('id', 1)
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table WHERE id = :id', $query);
        self::assertSame(['id' => 1], $params);
    }

    public function testWhereWithCustomOperator(): void
    {
        [$query, $params] = $this->builder
            ->where('age', 18, '>=')
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table WHERE age >= :age', $query);
        self::assertSame(['age' => 18], $params);
    }

    public function testMultipleWhereClauses(): void
    {
        [$query, $params] = $this->builder
            ->where('status', 'active')
            ->where('age', 21, '>=')
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table WHERE status = :status AND age >= :age', $query);
        self::assertSame(['status' => 'active', 'age' => 21], $params);
    }

    public function testWhereIn(): void
    {
        [$query, $params] = $this->builder
            ->whereIn('status', ['active', 'pending'])
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table WHERE status IN (:status_in_0, :status_in_1)', $query);
        self::assertSame(['status_in_0' => 'active', 'status_in_1' => 'pending'], $params);
    }

    public function testWhereInThrowsExceptionWithEmptyArray(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot use whereIn with empty values array');

        $this->builder->whereIn('status', []);
    }

    public function testWhereRaw(): void
    {
        [$query, $params] = $this->builder
            ->whereRaw('created_at > :date', ['date' => '2023-01-01'])
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table WHERE created_at > :date', $query);
        self::assertSame(['date' => '2023-01-01'], $params);
    }

    public function testOrderBy(): void
    {
        [$query, $params] = $this->builder
            ->orderBy('name')
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table ORDER BY name ASC', $query);
        self::assertEmpty($params);
    }

    public function testOrderByDesc(): void
    {
        [$query, $params] = $this->builder
            ->orderBy('created_at', 'DESC')
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table ORDER BY created_at DESC', $query);
        self::assertEmpty($params);
    }

    public function testOrderByMultipleColumns(): void
    {
        [$query, $params] = $this->builder
            ->orderBy('status', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table ORDER BY status ASC, created_at DESC', $query);
        self::assertEmpty($params);
    }

    public function testLimit(): void
    {
        [$query, $params] = $this->builder
            ->limit(10)
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table LIMIT 10', $query);
        self::assertEmpty($params);
    }

    public function testLimitWithNegativeValueThrowsException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Limit cannot be negative');

        $this->builder->limit(-5);
    }

    public function testOffset(): void
    {
        [$query, $params] = $this->builder
            ->limit(10)
            ->offset(20)
            ->buildSelectQuery();

        self::assertSame('SELECT * FROM test_table LIMIT 10 OFFSET 20', $query);
        self::assertEmpty($params);
    }

    public function testOffsetWithNegativeValueThrowsException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Offset cannot be negative');

        $this->builder->offset(-5);
    }

    public function testBuildInsertQuery(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => time(),
        ];

        [$query, $params] = $this->builder->buildInsertQuery($data);

        self::assertSame(
            'INSERT INTO test_table (name, email, created_at) VALUES (:name, :email, :created_at)',
            $query,
        );
        self::assertSame($data, $params);
    }

    public function testBuildInsertQueryWithEmptyDataThrowsException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot insert empty data');

        $this->builder->buildInsertQuery([]);
    }

    public function testBuildUpdateQuery(): void
    {
        $data = [
            'name' => 'Updated Name',
            'updated_at' => time(),
        ];

        $this->builder->where('id', 1);

        [$query, $params] = $this->builder->buildUpdateQuery($data);

        self::assertSame(
            'UPDATE test_table SET name = :name, updated_at = :updated_at WHERE id = :id',
            $query,
        );
        self::assertSame(['name' => 'Updated Name', 'updated_at' => $data['updated_at'], 'id' => 1], $params);
    }

    public function testBuildUpdateQueryWithoutWhereThrowsException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Update query must have WHERE clause for safety');

        $this->builder->buildUpdateQuery(['name' => 'New Name']);
    }

    public function testBuildUpdateQueryWithEmptyDataThrowsException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot update with empty data');

        $this->builder->where('id', 1)->buildUpdateQuery([]);
    }

    public function testBuildDeleteQuery(): void
    {
        $this->builder->where('id', 1);

        [$query, $params] = $this->builder->buildDeleteQuery();

        self::assertSame('DELETE FROM test_table WHERE id = :id', $query);
        self::assertSame(['id' => 1], $params);
    }

    public function testBuildDeleteQueryWithoutWhereThrowsException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Delete query must have WHERE clause for safety');

        $this->builder->buildDeleteQuery();
    }

    public function testCamelToSnakeKeys(): void
    {
        $input = [
            'userId' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'createdAt' => time(),
        ];

        $expected = [
            'user_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'created_at' => $input['createdAt'],
        ];

        $result = SQLiteQueryBuilder::camelToSnakeKeys($input);

        self::assertEquals($expected, $result);
    }

    public function testSnakeToCamelKeys(): void
    {
        $input = [
            'user_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'created_at' => time(),
        ];

        $expected = [
            'userId' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'createdAt' => $input['created_at'],
        ];

        $result = SQLiteQueryBuilder::snakeToCamelKeys($input);

        self::assertEquals($expected, $result);
    }
}
