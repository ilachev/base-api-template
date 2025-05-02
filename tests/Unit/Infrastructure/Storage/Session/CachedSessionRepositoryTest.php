<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\Session;

use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Infrastructure\Cache\CacheConfig;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\FallbackStorage;
use App\Infrastructure\Cache\RoadRunnerCacheService;
use App\Infrastructure\Storage\Session\CachedSessionRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Cache\MockStorage;
use Tests\Unit\Infrastructure\Logger\TestLogger;

final class CachedSessionRepositoryTest extends TestCase
{
    private const SESSION_ID = '1234567890abcdef1234567890abcdef';
    private const USER_ID = 123;

    private CachedSessionRepository $repository;

    private MockObject&SessionRepository $innerRepository;

    private CacheService $cacheService;

    private MockStorage $storage;

    protected function setUp(): void
    {
        // Мокаем внутренний репозиторий
        $this->innerRepository = $this->createMock(SessionRepository::class);

        // Создаем тестовый логгер
        $logger = new TestLogger();

        // Используем FallbackStorage вместо RPC-зависимого хранилища
        $this->storage = new MockStorage();

        $cacheConfig = new CacheConfig(
            engine: 'mock',
            address: 'tcp://127.0.0.1:6001',
            defaultPrefix: 'test:',
            defaultTtl: 3600,
        );

        // Создаем сервис кеширования без RPC-зависимости
        $this->cacheService = new RoadRunnerCacheService($cacheConfig, $logger);

        // Заменяем хранилище на наш мок
        $reflection = new \ReflectionProperty($this->cacheService, 'storage');
        $reflection->setAccessible(true);
        $reflection->setValue($this->cacheService, $this->storage);

        // Устанавливаем флаг доступности кеша
        $reflection = new \ReflectionProperty($this->cacheService, 'available');
        $reflection->setAccessible(true);
        $reflection->setValue($this->cacheService, true);

        // Создаем репозиторий
        $this->repository = new CachedSessionRepository(
            $this->innerRepository,
            $this->cacheService,
            $logger,
        );

    }

    public function testFindByIdUsesCache(): void
    {
        $session = $this->createSession();

        // Настраиваем поведение внутреннего репозитория
        $this->innerRepository
            ->expects(self::once()) // Должен быть вызван только один раз
            ->method('findById')
            ->with(self::SESSION_ID)
            ->willReturn($session);

        // Первый вызов - данных в кеше нет, должен быть запрос к репозиторию
        $result1 = $this->repository->findById(self::SESSION_ID);
        self::assertSame($session, $result1);

        // Проверяем наличие в кеше
        self::assertTrue($this->storage->has('test:session:' . self::SESSION_ID));

        // Второй вызов - данные должны быть взяты из кеша
        $result2 = $this->repository->findById(self::SESSION_ID);
        self::assertSame($session, $result2);
    }

    public function testFindByUserIdUsesCache(): void
    {
        $sessions = [$this->createSession()];

        // Настраиваем поведение внутреннего репозитория
        $this->innerRepository
            ->expects(self::once()) // Должен быть вызван только один раз
            ->method('findByUserId')
            ->with(self::USER_ID)
            ->willReturn($sessions);

        // Первый вызов - данных в кеше нет, должен быть запрос к репозиторию
        $result1 = $this->repository->findByUserId(self::USER_ID);
        self::assertSame($sessions, $result1);

        // Проверяем наличие в кеше
        self::assertTrue($this->storage->has('test:session_user:' . self::USER_ID));

        // Второй вызов - данные должны быть взяты из кеша
        $result2 = $this->repository->findByUserId(self::USER_ID);
        self::assertSame($sessions, $result2);
    }

    public function testFindAllDoesNotUseCache(): void
    {
        $sessions = [$this->createSession()];

        // Репозиторий должен быть вызван при каждом запросе
        $this->innerRepository
            ->expects(self::exactly(2))
            ->method('findAll')
            ->willReturn($sessions);

        $result1 = $this->repository->findAll();
        self::assertSame($sessions, $result1);

        $result2 = $this->repository->findAll();
        self::assertSame($sessions, $result2);
    }

    public function testSaveUpdatesCache(): void
    {
        $session = $this->createSession();

        $this->innerRepository
            ->expects(self::once())
            ->method('save')
            ->with($session);

        $this->repository->save($session);

        // Проверяем, что сессия попала в кеш
        self::assertTrue($this->storage->has('test:session:' . self::SESSION_ID));

        // И что значение в кеше соответствует сессии
        $cachedSession = $this->cacheService->get('session:' . self::SESSION_ID);
        self::assertSame($session, $cachedSession);
    }

    public function testDeleteInvalidatesCache(): void
    {
        $session = $this->createSession();

        // Настраиваем мок для поиска сессии
        $this->innerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::SESSION_ID)
            ->willReturn($session);

        // Настраиваем мок для удаления
        $this->innerRepository
            ->expects(self::once())
            ->method('delete')
            ->with(self::SESSION_ID);

        // Сначала поместим сессию в кеш
        $this->cacheService->set('session:' . self::SESSION_ID, $session);
        $this->cacheService->set('session_user:' . self::USER_ID, [$session]);

        // Удаляем сессию через репозиторий
        $this->repository->delete(self::SESSION_ID);

        // Проверяем, что кеш был инвалидирован
        self::assertFalse($this->storage->has('test:session:' . self::SESSION_ID));
        self::assertFalse($this->storage->has('test:session_user:' . self::USER_ID));
    }

    public function testDeleteExpiredDoesNotInvalidateCache(): void
    {
        $session = $this->createSession();

        // Помещаем сессию в кеш
        $this->cacheService->set('session:' . self::SESSION_ID, $session);

        // Настраиваем мок для удаления истекших сессий
        $this->innerRepository
            ->expects(self::once())
            ->method('deleteExpired');

        // Вызываем метод удаления истекших сессий
        $this->repository->deleteExpired();

        // Проверяем, что кеш не был инвалидирован
        self::assertTrue($this->storage->has('test:session:' . self::SESSION_ID));
    }

    private function createSession(string $payload = '{"foo":"bar"}'): Session
    {
        $now = time();
        $expiresAt = $now + 3600;

        return new Session(
            id: self::SESSION_ID,
            userId: self::USER_ID,
            payload: $payload,
            expiresAt: $expiresAt,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
