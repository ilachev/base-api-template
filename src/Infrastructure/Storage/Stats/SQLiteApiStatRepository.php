<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Stats;

use App\Domain\Stats\ApiStat;
use App\Domain\Stats\ApiStatRepository;
use App\Infrastructure\Storage\Repository\AbstractRepository;

final class SQLiteApiStatRepository extends AbstractRepository implements ApiStatRepository
{
    private const TABLE = 'api_stats';
    private const PRIMARY_KEY = 'id';

    public function save(ApiStat $stat): void
    {
        $this->saveEntity($stat, self::TABLE, self::PRIMARY_KEY, $stat->id);
    }

    /**
     * @return array<ApiStat>
     */
    public function findBySessionId(string $sessionId): array
    {
        $query = $this->query(self::TABLE)
            ->where('session_id', $sessionId)
            ->orderBy('request_time', 'DESC');

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByRoute(string $route): array
    {
        $query = $this->query(self::TABLE)
            ->where('route', $route)
            ->orderBy('request_time', 'DESC');

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByTimeRange(int $startTime, int $endTime): array
    {
        $query = $this->query(self::TABLE)
            ->whereRaw('request_time BETWEEN :start_time AND :end_time', [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ])
            ->orderBy('request_time', 'DESC');

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findBySessionAndRoute(string $sessionId, string $route): array
    {
        $query = $this->query(self::TABLE)
            ->where('session_id', $sessionId)
            ->where('route', $route)
            ->orderBy('request_time', 'DESC');

        return $this->fetchAll(ApiStat::class, $query);
    }
}
