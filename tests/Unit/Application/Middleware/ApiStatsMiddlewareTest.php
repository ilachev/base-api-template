<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Middleware;

use App\Application\Middleware\ApiStatsMiddleware;
use App\Application\Routing\RouteResult;
use App\Application\Routing\RouteStatus;
use App\Domain\Stats\ApiStat;
use App\Domain\Stats\ApiStatService;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\Unit\Domain\Stats\TestApiStatRepository;

final class ApiStatsMiddlewareTest extends TestCase
{
    private TestApiStatRepository $repository;

    private ApiStatService $statService;

    private ApiStatsMiddleware $middleware;

    protected function setUp(): void
    {
        $this->repository = new TestApiStatRepository();
        $this->statService = new ApiStatService($this->repository);
        $this->middleware = new ApiStatsMiddleware($this->statService);
    }

    public function testProcessWithSessionId(): void
    {
        $sessionId = 'test-session-id';
        $routeName = 'test.route';
        $routePath = '/test/path';
        $method = 'GET';
        $statusCode = 200;

        $routeResult = new RouteResult(RouteStatus::FOUND, $routeName, ['action' => 'test']);

        $request = new ServerRequest($method, 'https://example.com' . $routePath);
        $request = $request->withAttribute('sessionId', $sessionId)
            ->withAttribute(RouteResult::class, $routeResult);

        $response = new Response($statusCode);

        $handler = new class ($response) implements RequestHandlerInterface {
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

        $handler = new class ($response) implements RequestHandlerInterface {
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
        $sessionId = 'test-session-id';
        $path = '/no-route-result';
        $method = 'GET';
        $statusCode = 404;

        $request = new ServerRequest($method, 'https://example.com' . $path);
        $request = $request->withAttribute('sessionId', $sessionId);

        $response = new Response($statusCode);

        $handler = new class ($response) implements RequestHandlerInterface {
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
