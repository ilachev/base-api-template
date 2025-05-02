<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Routing;

use App\Infrastructure\Routing\DefaultRouteCollector;
use PHPUnit\Framework\TestCase;

final class DefaultRouteCollectorTest extends TestCase
{
    public function testAddRoute(): void
    {
        $collector = new DefaultRouteCollector();
        $collector->addRoute('GET', '/test', 'TestHandler');

        $routes = $collector->getRoutes();

        self::assertCount(1, $routes);
        self::assertEquals('GET', $routes[0]['method']);
        self::assertEquals('/test', $routes[0]['path']);
        self::assertEquals('TestHandler', $routes[0]['handler']);
    }

    public function testAddMultipleRoutes(): void
    {
        $collector = new DefaultRouteCollector();
        $collector->addRoute('GET', '/test1', 'Handler1');
        $collector->addRoute('POST', '/test2', 'Handler2');
        $collector->addRoute('PUT', '/test3', 'Handler3');

        $routes = $collector->getRoutes();

        self::assertCount(3, $routes);

        // First route
        self::assertEquals('GET', $routes[0]['method']);
        self::assertEquals('/test1', $routes[0]['path']);
        self::assertEquals('Handler1', $routes[0]['handler']);

        // Second route
        self::assertEquals('POST', $routes[1]['method']);
        self::assertEquals('/test2', $routes[1]['path']);
        self::assertEquals('Handler2', $routes[1]['handler']);

        // Third route
        self::assertEquals('PUT', $routes[2]['method']);
        self::assertEquals('/test3', $routes[2]['path']);
        self::assertEquals('Handler3', $routes[2]['handler']);
    }
}
