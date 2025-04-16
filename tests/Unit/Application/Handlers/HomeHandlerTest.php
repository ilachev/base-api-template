<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\Handlers\HomeHandler;
use App\Application\Http\JsonResponse;
use App\Application\Mappers\HomeMapper;
use App\Domain\Home\HomeService;
use App\Infrastructure\Hydrator\Hydrator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HomeHandlerTest extends TestCase
{
    private HomeHandler $handler;

    protected function setUp(): void
    {
        $homeService = new HomeService();
        $hydrator = new Hydrator();
        $homeMapper = new HomeMapper($hydrator);
        $this->handler = new HomeHandler($homeService, $homeMapper, new JsonResponse());
    }

    /**
     * @throws \JsonException
     */
    public function testHandleReturnsWelcomeMessage(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = $this->handler->handle($request);

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals(
            'application/json',
            $response->getHeaderLine('Content-Type'),
        );

        $responseContent = (string) $response->getBody();
        $body = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($body);
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('message', $body['data']);
        self::assertEquals('Welcome to our API', $body['data']['message']);
    }
}
