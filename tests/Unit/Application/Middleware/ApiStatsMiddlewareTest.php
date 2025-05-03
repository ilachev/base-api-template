<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Middleware;

use App\Application\Http\RequestHandler;
use App\Application\Middleware\ApiStatsMiddleware;
use App\Application\Routing\RouteResult;
use App\Application\Routing\RouteStatus;
use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionService;
use App\Domain\Stats\ApiStat;
use App\Domain\Stats\ApiStatService;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Unit\Domain\Stats\TestApiStatRepository;
use Tests\Unit\Infrastructure\Logger\TestLogger;

final class ApiStatsMiddlewareTest extends TestCase
{
    private TestApiStatRepository $repository;

    private ApiStatService $statService;

    private SessionService $sessionService;

    private TestLogger $logger;

    private ApiStatsMiddleware $middleware;

    protected function setUp(): void
    {
        $this->repository = new TestApiStatRepository();
        $this->statService = new ApiStatService($this->repository);

        // Создаем тестовое хранилище сессий
        $testSessionRepository = new class implements SessionRepository {
            public function findById(string $id): Session
            {
                $now = time();

                return new Session(
                    id: $id,
                    userId: null,
                    payload: '{}',
                    expiresAt: $now + 3600,
                    createdAt: $now,
                    updatedAt: $now,
                );
            }

            public function save(Session $session): void {}

            public function delete(string $id): void {}

            public function deleteExpired(): void {}

            public function findByUserId(int $userId): array
            {
                return [];
            }

            public function findAll(): array
            {
                return [];
            }
        };

        // Создаем реальный экземпляр SessionService
        $this->logger = new TestLogger();
        $this->sessionService = new SessionService($testSessionRepository, $this->logger);

        $this->middleware = new ApiStatsMiddleware($this->statService, $this->sessionService, $this->logger);
    }

    public function testProcessWithSessionId(): void
    {
        // Используем валидный формат ID сессии (32 hex символа)
        $sessionId = '0123456789abcdef0123456789abcdef';
        $routeName = 'test.route';
        $routePath = '/test/path';
        $method = 'GET';
        $statusCode = 200;

        $routeResult = new RouteResult(RouteStatus::FOUND, $routeName, ['action' => 'test']);

        $request = new ServerRequest($method, 'https://example.com' . $routePath);
        $request = $request->withAttribute('sessionId', $sessionId)
            ->withAttribute(RouteResult::class, $routeResult);

        $response = new Response($statusCode);

        $handler = new class ($response) implements RequestHandler {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        // До выполнения middleware репозиторий должен быть пустым
        self::assertEmpty($this->repository->stats);

        $result = $this->middleware->process($request, $handler);

        // Проверяем, что middleware пропустил запрос и вернул ответ
        self::assertSame($response, $result);

        // Проверяем, что статистика была сохранена
        self::assertCount(1, $this->repository->stats);

        self::assertNotEmpty($this->repository->stats);
        $stat = $this->repository->stats[0];
        self::assertInstanceOf(ApiStat::class, $stat);
        self::assertSame($sessionId, $stat->sessionId);
        self::assertSame($routeName, $stat->route);
        self::assertSame($method, $stat->method);
        self::assertSame($statusCode, $stat->statusCode);
        self::assertGreaterThan(0, $stat->executionTime);
        // Проверяем, что время запроса установлено
        self::assertGreaterThan(0, $stat->requestTime);
    }

    public function testProcessWithoutSessionId(): void
    {
        $request = new ServerRequest('GET', 'https://example.com/test');
        $response = new Response(200);

        $handler = new class ($response) implements RequestHandler {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $this->middleware->process($request, $handler);

        // Проверяем, что middleware пропустил запрос и вернул ответ
        self::assertSame($response, $result);

        // Проверяем, что статистика НЕ была сохранена (нет sessionId)
        self::assertEmpty($this->repository->stats);
    }

    public function testProcessWithoutRouteResult(): void
    {
        // Используем валидный формат ID сессии (32 hex символа)
        $sessionId = '0123456789abcdef0123456789abcdef';
        $path = '/no-route-result';
        $method = 'GET';
        $statusCode = 404;

        $request = new ServerRequest($method, 'https://example.com' . $path);
        $request = $request->withAttribute('sessionId', $sessionId);

        $response = new Response($statusCode);

        $handler = new class ($response) implements RequestHandler {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $this->middleware->process($request, $handler);

        // Проверяем, что middleware пропустил запрос и вернул ответ
        self::assertSame($response, $result);

        // Проверяем, что статистика была сохранена с URI путем вместо имени маршрута
        self::assertCount(1, $this->repository->stats);

        self::assertNotEmpty($this->repository->stats);
        $stat = $this->repository->stats[0];
        self::assertInstanceOf(ApiStat::class, $stat);
        self::assertSame($sessionId, $stat->sessionId);
        self::assertSame($path, $stat->route);
        self::assertSame($method, $stat->method);
        self::assertSame($statusCode, $stat->statusCode);
    }
}
