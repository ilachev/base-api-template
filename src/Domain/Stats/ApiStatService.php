<?php

declare(strict_types=1);

namespace App\Domain\Stats;

final readonly class ApiStatService
{
    public function __construct(
        private ApiStatRepository $repository,
    ) {}

    public function saveApiCall(ApiStat $stat): void
    {
        $this->repository->save($stat);
    }

    /**
     * @return array<ApiStat>
     */
    public function getClientStats(string $clientId): array
    {
        /** @var array<ApiStat> $stats */
        $stats = $this->repository->findByClientId($clientId);

        return $stats;
    }

    /**
     * @return array<ApiStat>
     */
    public function getRouteStats(string $route): array
    {
        /** @var array<ApiStat> $stats */
        $stats = $this->repository->findByRoute($route);

        return $stats;
    }

    /**
     * Get statistics for a specific time period.
     *
     * @param int $startTime Unix timestamp
     * @param int $endTime Unix timestamp
     * @return array<ApiStat>
     */
    public function getStatsByTimeRange(int $startTime, int $endTime): array
    {
        return $this->repository->findByTimeRange($startTime, $endTime);
    }

    /**
     * Get statistics for a specific client and route.
     *
     * @return array<ApiStat>
     */
    public function getStatsByClientAndRoute(string $clientId, string $route): array
    {
        return $this->repository->findByClientAndRoute($clientId, $route);
    }
}
