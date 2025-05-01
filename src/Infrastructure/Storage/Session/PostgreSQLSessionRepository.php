<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Session;

use App\Domain\Session\Session;
use App\Domain\Session\SessionPayload;
use App\Domain\Session\SessionRepository;
use App\Infrastructure\Hydrator\HydratorInterface;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Repository\AbstractRepository;
use App\Infrastructure\Storage\StorageInterface;

/**
 * PostgreSQL implementation of the session repository.
 */
final class PostgreSQLSessionRepository extends AbstractRepository implements SessionRepository
{
    private const TABLE_NAME = 'sessions';

    public function __construct(
        StorageInterface $storage,
        HydratorInterface $hydrator,
        QueryFactory $queryFactory,
        private readonly JsonFieldAdapter $jsonFieldAdapter,
    ) {
        parent::__construct($storage, $hydrator, $queryFactory);
    }

    public function findById(string $id): ?Session
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('id', $id);

        return $this->fetchOne(Session::class, $query);
    }

    public function findByFingerprint(string $fingerprint): ?Session
    {
        $query = $this->query(self::TABLE_NAME)
            ->where("payload->>'fingerprint'", $fingerprint);

        return $this->fetchOne(Session::class, $query);
    }

    /**
     * @return array<Session>
     */
    public function findByUserId(int $userId): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where('user_id', $userId);

        return $this->fetchAll(Session::class, $query);
    }

    /**
     * @return array<Session>
     */
    public function findAll(): array
    {
        $query = $this->query(self::TABLE_NAME);

        return $this->fetchAll(Session::class, $query);
    }

    /**
     * @return array<Session>
     */
    public function findByIp(string $ip, int $limit): array
    {
        $query = $this->query(self::TABLE_NAME)
            ->where("payload->>'ip'", $ip)
            ->orderBy('created_at', 'DESC')
            ->limit($limit);

        return $this->fetchAll(Session::class, $query);
    }

    /**
     * @param array<string> $ids
     * @return array<Session>
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $query = $this->query(self::TABLE_NAME)
            ->whereIn('id', $ids);

        return $this->fetchAll(Session::class, $query);
    }

    public function save(Session $session): void
    {
        // Convert SessionPayload to JSON for storage
        $data = $this->extractEntityData($session);

        if (isset($data['payload']) && $data['payload'] instanceof SessionPayload) {
            $data['payload'] = $this->jsonFieldAdapter->serialize($data['payload']);
        }

        $this->saveEntity($session, self::TABLE_NAME, 'id', $session->id);
    }

    /**
     * @param Session[] $sessions
     */
    public function saveMultiple(array $sessions): void
    {
        $this->storage->transaction(function () use ($sessions): void {
            foreach ($sessions as $session) {
                $this->save($session);
            }
        });
    }

    public function delete(string $id): void
    {
        $this->deleteEntity(self::TABLE_NAME, 'id', $id);
    }

    /**
     * @param string[] $ids
     */
    public function deleteMultiple(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $deleteQuery = $this->query(self::TABLE_NAME)
            ->whereIn('id', $ids);

        [$sql, $params] = $deleteQuery->buildDeleteQuery();
        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $this->storage->execute($sql, $castParams);
    }

    public function deleteExpired(): void
    {
        $timestamp = time();
        $deleteQuery = $this->query(self::TABLE_NAME)
            ->where('expires_at', $timestamp, '<');

        [$sql, $params] = $deleteQuery->buildDeleteQuery();
        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $this->storage->execute($sql, $castParams);
    }
}
