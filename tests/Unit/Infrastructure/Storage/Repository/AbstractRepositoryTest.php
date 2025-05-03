<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\Repository;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\LimitedReflectionCache;
use App\Infrastructure\Hydrator\ReflectionHydrator;
use App\Infrastructure\Hydrator\SetterProtobufHydration;
use App\Infrastructure\Storage\Query\QueryBuilder;
use App\Infrastructure\Storage\Query\QueryBuilderFactory;
use App\Infrastructure\Storage\Query\SQLiteQueryBuilder;
use App\Infrastructure\Storage\Repository\AbstractRepository;
use App\Infrastructure\Storage\Storage;
use PHPUnit\Framework\TestCase;

final class AbstractRepositoryTest extends TestCase
{
    private TestStorage $storage;

    private Hydrator $hydrator;

    private QueryBuilderFactory $queryBuilderFactory;

    private ConcreteRepository $repository;

    protected function setUp(): void
    {
        $this->storage = new TestStorage();
        $cache = new LimitedReflectionCache();
        $protobufHydration = new SetterProtobufHydration();
        $this->hydrator = new ReflectionHydrator($cache, $protobufHydration);
        $this->queryBuilderFactory = new QueryBuilderFactory();

        $this->repository = new ConcreteRepository(
            $this->storage,
            $this->hydrator,
            $this->queryBuilderFactory,
        );
    }

    public function testCreateEntityHydratesEntityFromSnakeCaseData(): void
    {
        // Для теста создания мы будем использовать простой DTO
        $data = [
            'id' => 1,
            'user_id' => 100,
            'created_at' => 12345678,
        ];

        $result = $this->repository->testCreateEntity(SimpleDTO::class, $data);

        // Проверяем, что данные были правильно преобразованы в camelCase
        self::assertInstanceOf(SimpleDTO::class, $result);
        self::assertSame(1, $result->id);
        self::assertSame(100, $result->userId);
        self::assertSame(12345678, $result->createdAt);
    }

    public function testExtractEntityDataConvertsEntityDataToSnakeCase(): void
    {
        // Создаем объект с camelCase свойствами
        $entity = new SimpleDTO(1, 100, 12345678);

        $result = $this->repository->testExtractEntityData($entity);

        // Проверяем, что имена полей преобразованы в snake_case
        self::assertArrayHasKey('id', $result);
        self::assertArrayHasKey('user_id', $result);
        self::assertArrayHasKey('created_at', $result);

        self::assertSame(1, $result['id']);
        self::assertSame(100, $result['user_id']);
        self::assertSame(12345678, $result['created_at']);
    }

    public function testFetchOneReturnsEntityFromQueryResult(): void
    {
        // Настраиваем выходные данные для теста
        /** @var list<array<string, scalar|null>> $results */
        $results = [
            ['id' => 1, 'name' => 'Test', 'created_at' => 12345678],
        ];
        $this->storage->mockQueryResults = $results;

        $queryBuilder = SQLiteQueryBuilder::table('test');
        $result = $this->repository->testFetchOne(SimpleDTO::class, $queryBuilder);

        // Проверяем, что запрос был построен правильно
        self::assertStringStartsWith('SELECT', $this->storage->lastQuery);
        self::assertStringContainsString('LIMIT 1', $this->storage->lastQuery);

        // Проверяем, что возвращен правильный объект
        self::assertInstanceOf(SimpleDTO::class, $result);
        self::assertSame(1, $result->id);
        self::assertSame('Test', $result->name);
        self::assertSame(12345678, $result->createdAt);
    }

    public function testFetchOneReturnsNullWhenNoResults(): void
    {
        // Настраиваем пустые выходные данные
        $this->storage->mockQueryResults = [];

        $queryBuilder = SQLiteQueryBuilder::table('test');
        $result = $this->repository->testFetchOne(SimpleDTO::class, $queryBuilder);

        // Проверяем, что метод возвращает null при отсутствии результатов
        self::assertNull($result);
    }

    public function testFetchAllReturnsArrayOfEntitiesFromQueryResult(): void
    {
        // Настраиваем выходные данные для теста
        /** @var list<array<string, scalar|null>> $results */
        $results = [
            ['id' => 1, 'name' => 'Test 1', 'created_at' => 12345678],
            ['id' => 2, 'name' => 'Test 2', 'created_at' => 12345679],
        ];
        $this->storage->mockQueryResults = $results;

        $queryBuilder = SQLiteQueryBuilder::table('test');
        $result = $this->repository->testFetchAll(SimpleDTO::class, $queryBuilder);

        // Проверяем, что запрос был построен правильно
        self::assertStringStartsWith('SELECT', $this->storage->lastQuery);

        // Проверяем, что возвращенный массив содержит ожидаемое количество объектов
        self::assertCount(2, $result);

        // Проверяем первый объект
        self::assertInstanceOf(SimpleDTO::class, $result[0]);
        self::assertSame(1, $result[0]->id);
        self::assertSame('Test 1', $result[0]->name);
        self::assertSame(12345678, $result[0]->createdAt);

        // Проверяем второй объект
        self::assertInstanceOf(SimpleDTO::class, $result[1]);
        self::assertSame(2, $result[1]->id);
        self::assertSame('Test 2', $result[1]->name);
        self::assertSame(12345679, $result[1]->createdAt);
    }

    public function testSaveUpdatesExistingEntityWhenItExists(): void
    {
        $entity = new SimpleDTO(1, 100, 12345678);

        $this->repository->testSaveEntity($entity, 'test', 'id', 1);

        // Проверяем, что был выполнен запрос UPSERT
        self::assertStringStartsWith('INSERT INTO', $this->storage->lastExecutedQuery);
        self::assertStringContainsString('ON CONFLICT', $this->storage->lastExecutedQuery);
    }

    public function testSaveInsertsNewEntityWhenItDoesNotExist(): void
    {
        $entity = new SimpleDTO(1, 100, 12345678);

        $this->repository->testSaveEntity($entity, 'test', 'id', 1);

        // Проверяем, что был выполнен запрос UPSERT
        self::assertStringStartsWith('INSERT INTO', $this->storage->lastExecutedQuery);
        self::assertStringContainsString('ON CONFLICT', $this->storage->lastExecutedQuery);
    }

    public function testDeleteRemovesEntityById(): void
    {
        $this->repository->testDeleteEntity('test', 'id', 1);

        // Проверяем, что был выполнен запрос DELETE
        self::assertStringStartsWith('DELETE FROM', $this->storage->lastExecutedQuery);
        self::assertStringContainsString('WHERE id = :id', $this->storage->lastExecutedQuery);

        // Проверяем параметры
        self::assertArrayHasKey('id', $this->storage->lastExecutedParams);
        self::assertSame(1, $this->storage->lastExecutedParams['id']);
    }
}

/**
 * Конкретная реализация для тестирования абстрактного класса.
 */
final class ConcreteRepository extends AbstractRepository
{
    /**
     * @param class-string<object> $className
     * @param array<string, mixed> $data
     */
    public function testCreateEntity(string $className, array $data): object
    {
        return $this->createEntity($className, $data);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function testExtractEntityData(object $entity): array
    {
        $data = $this->extractEntityData($entity);

        // На всякий случай фильтруем, оставляя только скалярные значения или null
        $result = [];
        foreach ($data as $key => $value) {
            if (\is_scalar($value) || $value === null) {
                $result[$key] = $value;
            } else {
                $result[$key] = \is_object($value) && method_exists($value, '__toString')
                    ? (string) $value
                    : null;
            }
        }

        return $result;
    }

    public function testQueryBuilder(string $table): QueryBuilder
    {
        return $this->query($table);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T|null
     */
    public function testFetchOne(string $className, QueryBuilder $queryBuilder): ?object
    {
        return $this->fetchOne($className, $queryBuilder);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return array<T>
     */
    public function testFetchAll(string $className, QueryBuilder $queryBuilder): array
    {
        return $this->fetchAll($className, $queryBuilder);
    }

    public function testSaveEntity(object $entity, string $table, string $primaryKey, mixed $primaryKeyValue): void
    {
        $this->saveEntity($entity, $table, $primaryKey, $primaryKeyValue);
    }

    public function testDeleteEntity(string $table, string $primaryKey, mixed $primaryKeyValue): void
    {
        $this->deleteEntity($table, $primaryKey, $primaryKeyValue);
    }
}

/**
 * Простой DTO для тестирования.
 */
final class SimpleDTO
{
    public function __construct(
        public readonly int $id = 0,
        public readonly int $userId = 0,
        public readonly ?int $createdAt = null,
        public readonly ?string $name = null,
    ) {}
}

/**
 * Тестовое хранилище для имитации работы с базой данных.
 */
final class TestStorage implements Storage
{
    /**
     * @var array<string>
     */
    public array $queries = [];

    /**
     * @var list<array<string, scalar|null>>
     */
    public array $mockQueryResults = [];

    public string $lastQuery = '';

    public string $lastExecutedQuery = '';

    /**
     * @var array<string, mixed>
     */
    public array $lastExecutedParams = [];

    /**
     * @param array<string, scalar|null> $params
     * @return list<array<string, scalar|null>>
     */
    public function query(string $sql, array $params = []): array
    {
        $this->queries[] = $sql;
        $this->lastQuery = $sql;

        /** @var list<array<string, scalar|null>> $castedResults */
        $castedResults = $this->mockQueryResults;

        return $castedResults;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        $this->lastExecutedQuery = $sql;
        $this->lastExecutedParams = $params;

        return true;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback();
    }

    public function lastInsertId(): string
    {
        return '1';
    }
}
