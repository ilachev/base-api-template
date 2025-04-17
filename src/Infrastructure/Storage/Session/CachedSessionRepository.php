<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Session;

use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Storage\Repository\AbstractCachedRepository;
use Psr\Log\LoggerInterface;

final class CachedSessionRepository extends AbstractCachedRepository implements SessionRepository
{
    private const CACHE_KEY_PREFIX = 'session:';
    private const CACHE_USER_PREFIX = 'session_user:';

    public function __construct(
        private readonly SessionRepository $repository,
        CacheService $cache,
        LoggerInterface $logger,
    ) {
        parent::__construct(
            cache: $cache,
            logger: $logger,
            cacheKeyPrefix: '',
        );
    }

    public function findById(string $id): ?Session
    {
        $cacheKey = $this->getSessionCacheKey($id);

        /** @var ?Session */
        return $this->getOrSetCacheValue($cacheKey, fn() => $this->repository->findById($id));
    }

    /**
     * @return array<Session>
     */
    public function findByUserId(int $userId): array
    {
        $cacheKey = $this->getUserSessionsCacheKey($userId);

        /** @var array<Session> */
        return $this->getOrSetCacheValue($cacheKey, fn() => $this->repository->findByUserId($userId));
    }

    public function findAll(): array
    {
        // This operation is likely admin-only and not performance-critical
        // We don't cache it to avoid storing large amounts of data in cache
        return $this->repository->findAll();
    }

    public function save(Session $session): void
    {
        $this->repository->save($session);

        // Update the cache for this session
        $sessionCacheKey = $this->getSessionCacheKey($session->id);
        $this->setCacheValue($sessionCacheKey, $session);

        // Invalidate user sessions cache if user is associated
        if ($session->userId !== null) {
            $userCacheKey = $this->getUserSessionsCacheKey($session->userId);
            $this->deleteCacheValue($userCacheKey);
        }
    }

    public function delete(string $id): void
    {
        $session = $this->repository->findById($id);
        $this->repository->delete($id);

        // Clear session from cache
        $sessionCacheKey = $this->getSessionCacheKey($id);
        $this->deleteCacheValue($sessionCacheKey);

        // Invalidate user sessions cache if user is associated
        if ($session !== null && $session->userId !== null) {
            $userCacheKey = $this->getUserSessionsCacheKey($session->userId);
            $this->deleteCacheValue($userCacheKey);
        }
    }

    public function deleteExpired(): void
    {
        $this->repository->deleteExpired();

        // No need to invalidate cache as expired sessions shouldn't be requested
        // and the specific keys will eventually expire from the cache naturally
    }

    private function getSessionCacheKey(string $id): string
    {
        return self::CACHE_KEY_PREFIX . $id;
    }

    private function getUserSessionsCacheKey(int $userId): string
    {
        return self::CACHE_USER_PREFIX . $userId;
    }
}
