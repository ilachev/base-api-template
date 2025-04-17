<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stats;

use App\Domain\Stats\ApiStat;
use App\Domain\Stats\ApiStatRepository;
use App\Domain\Stats\ApiStatService;
use PHPUnit\Framework\TestCase;

final class ApiStatServiceTest extends TestCase
{
    private TestApiStatRepository $repository;

    private ApiStatService $service;

    protected function setUp(): void
    {
        $this->repository = new TestApiStatRepository();
        $this->service = new ApiStatService($this->repository);
    }

    public function testSaveApiCall(): void
    {
        $stat = new ApiStat(
            id: null,
            sessionId: 'test-session',
            route: '/test/route',
            method: 'GET',
            statusCode: 200,
            executionTime: 123.45,
            requestTime: time(),
        );

        $this->service->saveApiCall($stat);

        self::assertCount(1, $this->repository->stats);
        self::assertSame($stat, $this->repository->stats[0]);
    }

    public function testGetSessionStats(): void
    {
        $sessionId = 'test-session';
        $stat1 = new ApiStat(
            id: 1,
            sessionId: $sessionId,
            route: '/test/route1',
            method: 'GET',
            statusCode: 200,
            executionTime: 100.0,
            requestTime: time(),
        );

        $stat2 = new ApiStat(
            id: 2,
            sessionId: $sessionId,
            route: '/test/route2',
            method: 'POST',
            statusCode: 201,
            executionTime: 200.0,
            requestTime: time(),
        );

        $stat3 = new ApiStat(
            id: 3,
            sessionId: 'other-session',
            route: '/test/route1',
            method: 'GET',
            statusCode: 200,
            executionTime: 150.0,
            requestTime: time(),
        );

        $this->repository->stats = [$stat1, $stat2, $stat3];

        $result = $this->service->getSessionStats($sessionId);

        self::assertCount(2, $result);
        self::assertContains($stat1, $result);
        self::assertContains($stat2, $result);
        self::assertNotContains($stat3, $result);
    }

    public function testGetRouteStats(): void
    {
        $route = '/test/route';
        $stat1 = new ApiStat(
            id: 1,
            sessionId: 'session1',
            route: $route,
            method: 'GET',
            statusCode: 200,
            executionTime: 100.0,
            requestTime: time(),
        );

        $stat2 = new ApiStat(
            id: 2,
            sessionId: 'session2',
            route: $route,
            method: 'GET',
            statusCode: 200,
            executionTime: 150.0,
            requestTime: time(),
        );

        $stat3 = new ApiStat(
            id: 3,
            sessionId: 'session1',
            route: '/different/route',
            method: 'POST',
            statusCode: 201,
            executionTime: 200.0,
            requestTime: time(),
        );

        $this->repository->stats = [$stat1, $stat2, $stat3];

        $result = $this->service->getRouteStats($route);

        self::assertCount(2, $result);
        self::assertContains($stat1, $result);
        self::assertContains($stat2, $result);
        self::assertNotContains($stat3, $result);
    }

    public function testGetStatsByTimeRange(): void
    {
        $now = time();
        $startTime = $now - 3600; // 1 час назад
        $endTime = $now;

        $stat1 = new ApiStat(
            id: 1,
            sessionId: 'session1',
            route: '/test/route1',
            method: 'GET',
            statusCode: 200,
            executionTime: 100.0,
            requestTime: $now - 1800, // 30 минут назад - в диапазоне
        );

        $stat2 = new ApiStat(
            id: 2,
            sessionId: 'session2',
            route: '/test/route2',
            method: 'POST',
            statusCode: 201,
            executionTime: 150.0,
            requestTime: $now - 7200, // 2 часа назад - вне диапазона
        );

        $stat3 = new ApiStat(
            id: 3,
            sessionId: 'session1',
            route: '/test/route3',
            method: 'GET',
            statusCode: 200,
            executionTime: 120.0,
            requestTime: $now, // прямо сейчас - в диапазоне
        );

        $this->repository->stats = [$stat1, $stat2, $stat3];

        $result = $this->service->getStatsByTimeRange($startTime, $endTime);

        self::assertCount(2, $result);
        self::assertContains($stat1, $result);
        self::assertContains($stat3, $result);
        self::assertNotContains($stat2, $result);
    }

    public function testGetStatsBySessionAndRoute(): void
    {
        $sessionId = 'test-session';
        $route = '/test/route';

        $stat1 = new ApiStat(
            id: 1,
            sessionId: $sessionId,
            route: $route,
            method: 'GET',
            statusCode: 200,
            executionTime: 100.0,
            requestTime: time(),
        );

        $stat2 = new ApiStat(
            id: 2,
            sessionId: $sessionId,
            route: '/different/route',
            method: 'POST',
            statusCode: 201,
            executionTime: 150.0,
            requestTime: time(),
        );

        $stat3 = new ApiStat(
            id: 3,
            sessionId: 'other-session',
            route: $route,
            method: 'GET',
            statusCode: 200,
            executionTime: 120.0,
            requestTime: time(),
        );

        $this->repository->stats = [$stat1, $stat2, $stat3];

        $result = $this->service->getStatsBySessionAndRoute($sessionId, $route);

        self::assertCount(1, $result);
        self::assertContains($stat1, $result);
        self::assertNotContains($stat2, $result);
        self::assertNotContains($stat3, $result);
    }
}

/**
 * Тестовый репозиторий для тестирования ApiStatService.
 */
final class TestApiStatRepository implements ApiStatRepository
{
    /** @var array<ApiStat> */
    public array $stats = [];

    public function save(ApiStat $stat): void
    {
        $this->stats[] = $stat;
    }

    /**
     * @return array<ApiStat>
     */
    public function findBySessionId(string $sessionId): array
    {
        return array_filter(
            $this->stats,
            static fn(ApiStat $stat) => $stat->sessionId === $sessionId,
        );
    }

    /**
     * @return array<ApiStat>
     */
    public function findByRoute(string $route): array
    {
        return array_filter(
            $this->stats,
            static fn(ApiStat $stat) => $stat->route === $route,
        );
    }

    /**
     * @return array<ApiStat>
     */
    public function findByTimeRange(int $startTime, int $endTime): array
    {
        return array_filter(
            $this->stats,
            static fn(ApiStat $stat) => $stat->requestTime >= $startTime && $stat->requestTime <= $endTime,
        );
    }

    /**
     * @return array<ApiStat>
     */
    public function findBySessionAndRoute(string $sessionId, string $route): array
    {
        return array_filter(
            $this->stats,
            static fn(ApiStat $stat) => $stat->sessionId === $sessionId && $stat->route === $route,
        );
    }
}
