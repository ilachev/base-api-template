<?php

declare(strict_types=1);

namespace App\Domain\Stats;

interface ApiStatRepository
{
    public function save(ApiStat $stat): void;

    /**
     * @return array<ApiStat>
     */
    public function findBySessionId(string $sessionId): array;

    /**
     * @return array<ApiStat>
     */
    public function findByRoute(string $route): array;

    /**
     * Find statistics within a time range.
     *
     * @param int $startTime Unix timestamp
     * @param int $endTime Unix timestamp
     * @return array<ApiStat>
     */
    public function findByTimeRange(int $startTime, int $endTime): array;

    /**
     * Find statistics by session and route.
     *
     * @return array<ApiStat>
     */
    public function findBySessionAndRoute(string $sessionId, string $route): array;
}
