<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Routing;

use App\Application\Routing\RouteResult;
use FastRoute\Dispatcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouteResultTest extends TestCase
{
    public function testIsFoundReturnsTrue(): void
    {
        $routeResult = new RouteResult([
            Dispatcher::FOUND,
            'App\Handler',
            ['id' => '1']
        ]);

        self::assertTrue($routeResult->isFound());
    }

    public function testIsFoundReturnsFalse(): void
    {
        $routeResult = new RouteResult([
            Dispatcher::NOT_FOUND
        ]);

        self::assertFalse($routeResult->isFound());
    }

    public function testGetHandlerReturnsHandler(): void
    {
        $handler = 'App\Handler';
        $routeResult = new RouteResult([
            Dispatcher::FOUND,
            $handler,
            []
        ]);

        self::assertEquals($handler, $routeResult->getHandler());
    }

    public function testGetHandlerThrowsExceptionWhenNotFound(): void
    {
        $routeResult = new RouteResult([
            Dispatcher::NOT_FOUND
        ]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Route not found');

        $routeResult->getHandler();
    }

    public function testGetParamsReturnsParams(): void
    {
        $params = ['id' => '1'];
        $routeResult = new RouteResult([
            Dispatcher::FOUND,
            'App\Handler',
            $params
        ]);

        self::assertEquals($params, $routeResult->getParams());
    }

    public function testGetParamsReturnsEmptyArrayWhenNoParams(): void
    {
        $routeResult = new RouteResult([
            Dispatcher::FOUND,
            'App\Handler'
        ]);

        self::assertEquals([], $routeResult->getParams());
    }

    public function testGetStatusCodeReturns200WhenFound(): void
    {
        $routeResult = new RouteResult([
            Dispatcher::FOUND,
            'App\Handler',
            []
        ]);

        self::assertEquals(200, $routeResult->getStatusCode());
    }

    public function testGetStatusCodeReturns404WhenNotFound(): void
    {
        $routeResult = new RouteResult([
            Dispatcher::NOT_FOUND
        ]);

        self::assertEquals(404, $routeResult->getStatusCode());
    }
}