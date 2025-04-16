<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Session;

use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Infrastructure\Storage\Repository\AbstractRepository;

final class SQLiteSessionRepository extends AbstractRepository implements SessionRepository
{
    private const TABLE = 'sessions';
    private const PRIMARY_KEY = 'id';

    public function findById(string $id): ?Session
    {
        $query = $this->query(self::TABLE)
            ->where(self::PRIMARY_KEY, $id);

        return $this->fetchOne(Session::class, $query);
    }

    /**
     * @return array<Session>
     */
    public function findByUserId(int $userId): array
    {
        $query = $this->query(self::TABLE)
            ->where('user_id', $userId);

        return $this->fetchAll(Session::class, $query);
    }

    /**
     * @return array<Session>
     */
    public function findAll(): array
    {
        $query = $this->query(self::TABLE);

        return $this->fetchAll(Session::class, $query);
    }

    public function save(Session $session): void
    {
        $this->saveEntity($session, self::TABLE, self::PRIMARY_KEY, $session->id);
    }

    public function delete(string $id): void
    {
        $this->deleteEntity(self::TABLE, self::PRIMARY_KEY, $id);
    }

    public function deleteExpired(): void
    {
        $query = $this->query(self::TABLE)
            ->whereRaw('expires_at < :current_time', ['current_time' => time()]);

        [$sql, $params] = $query->buildDeleteQuery();
        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $this->storage->execute($sql, $castParams);
    }
}
