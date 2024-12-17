<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use App\Application\Handlers\HomeHandler;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HomeHandlerTest extends TestCase
{
    private HomeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new HomeHandler();
    }

    public function testHandleReturnsWelcomeMessage(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = $this->handler->handle($request);

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals(
            'application/json',
            $response->getHeaderLine('Content-Type')
        );

        $body = json_decode((string)$response->getBody(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('message', $body);
        self::assertEquals('Welcome to our API', $body['message']);
    }
}
