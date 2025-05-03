<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Http\Middleware;
use App\Application\Http\RouteHandlerResolver;
use App\Application\Middleware\ApiStatsMiddleware;
use App\Application\Middleware\ErrorHandlerMiddleware;
use App\Application\Middleware\HttpLoggingMiddleware;
use App\Application\Middleware\Pipeline;
use App\Application\Middleware\RequestMetricsMiddleware;
use App\Application\Middleware\RoutingMiddleware;
use App\Application\Middleware\SessionMiddleware;
use App\Infrastructure\App;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class IntegrationTestCase extends TestCase
{
    /** @var App<object> */
    protected static App $app;

    protected ContainerInterface $container;

    final public static function setUpBeforeClass(): void
    {
        // Инициализируем приложение
        static::$app = new App(__DIR__ . '/../../config/container.php');
    }

    protected function setUp(): void
    {
        $this->container = static::$app->getContainer();
    }

    /**
     * Создает тестовый запрос с заданными параметрами.
     *
     * @param string $method HTTP метод (GET, POST, и т.д.)
     * @param string $uri URI запроса
     * @param array<string, string> $headers Заголовки запроса
     * @param string|null $body Тело запроса
     * @param array<string, string> $cookies Cookie запроса
     */
    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        ?string $body = null,
        array $cookies = [],
    ): ServerRequest {
        $request = new ServerRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $bodyStream = Stream::create($body);
            $request = $request->withBody($bodyStream);
        }

        if (!empty($cookies)) {
            $request = $request->withCookieParams($cookies);
        }

        return $request;
    }

    /**
     * Выполняет HTTP-запрос и возвращает ответ.
     *
     * @param string $method HTTP метод (GET, POST, и т.д.)
     * @param string $uri URI запроса
     * @param array<string, string> $headers Заголовки запроса
     * @param string|null $body Тело запроса
     * @param array<string, string> $cookies Cookie запроса
     */
    protected function makeRequest(
        string $method,
        string $uri,
        array $headers = [],
        ?string $body = null,
        array $cookies = [],
    ): ResponseInterface {
        $request = $this->createRequest($method, $uri, $headers, $body, $cookies);

        // Создаем фейковый PSR7Worker для обработки запроса
        $worker = new class ($this->container) {
            private ContainerInterface $container;

            public function __construct(ContainerInterface $container)
            {
                $this->container = $container;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var RouteHandlerResolver $resolver */
                $resolver = $this->container->get(RouteHandlerResolver::class);

                /** @var array<Middleware> $middlewares */
                $middlewares = [
                    $this->container->get(ErrorHandlerMiddleware::class),
                    $this->container->get(RequestMetricsMiddleware::class),
                    $this->container->get(SessionMiddleware::class),
                    $this->container->get(ApiStatsMiddleware::class),
                    $this->container->get(RoutingMiddleware::class),
                    $this->container->get(HttpLoggingMiddleware::class),
                ];

                $pipeline = new Pipeline($resolver, $middlewares);

                return $pipeline->handle($request);
            }
        };

        return $worker->handle($request);
    }
}
