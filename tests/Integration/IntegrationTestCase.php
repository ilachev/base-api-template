<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\App;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    /** @var App<object> */
    protected static App $app;

    final public static function setUpBeforeClass(): void
    {
        // Инициализируем приложение
        static::$app = new App(__DIR__ . '/../../config/container.php');
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
}
