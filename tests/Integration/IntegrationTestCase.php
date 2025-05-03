<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\App;
use App\Infrastructure\DI\Container;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class IntegrationTestCase extends TestCase
{
    /**
     * @var Container<object>
     */
    protected Container $container;

    protected function setUp(): void
    {
        // Get the shared app instance created in bootstrap.php
        $app = TestAppFactory::getApp();
        $this->container = $app->getContainer();
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

        // Use the application to process the request directly
        $app = TestAppFactory::getApp();

        return $app->handleRequest($request);
    }
}
