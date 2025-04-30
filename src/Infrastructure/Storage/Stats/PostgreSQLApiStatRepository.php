<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Stats;

use App\Domain\Stats\ApiStat;
use App\Domain\Stats\ApiStatRepository;
use App\Infrastructure\Hydrator\HydratorInterface;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Repository\AbstractRepository;
use App\Infrastructure\Storage\StorageInterface;

/**
 * PostgreSQL implementation of the API stats repository.
 */
final class PostgreSQLApiStatRepository extends AbstractRepository implements ApiStatRepository
{
    private const TABLE_NAME = 'api_stats';

    public function __construct(
        StorageInterface $storage,
        HydratorInterface $hydrator,
        QueryFactory $queryFactory,
    ) {
        parent::__construct($storage, $hydrator, $queryFactory);
    }

    public function save(ApiStat $stat): void
    {
        $this->saveEntity($stat, self::TABLE_NAME, 'id', $stat->id);
    }

    /**
     * @param array<ApiStat> $stats
     */
    public function saveMultiple(array $stats): void
    {
        if (empty($stats)) {
            return;
        }

        $this->storage->transaction(function () use ($stats): void {
            foreach ($stats as $stat) {
                $this->save($stat);
            }
        });
    }

    /**
     * @return array<ApiStat>
     */
    public function findBySessionId(string $sessionId, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByRoute(string $route, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('route', $route)
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findBySessionAndRoute(string $sessionId, string $route, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('session_id', $sessionId)
            ->where('route', $route)
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByMethod(string $method, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('method', $method)
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }

    /**
     * @return array<ApiStat>
     */
    public function findByTimeRange(int $startTimestamp, int $endTimestamp, int $limit = 100, int $offset = 0): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('request_time', $startTimestamp, '>=')
            ->where('request_time', $endTimestamp, '<=')
            ->orderBy('request_time', 'DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->fetchAll(ApiStat::class, $query);
    }
}
