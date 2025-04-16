<?php

declare(strict_types=1);

namespace App\Domain\Session;

final readonly class SessionService
{
    public function __construct(
        private SessionRepository $repository,
    ) {}

    public function createSession(?int $userId, string $payload, int $ttl = 3600): Session
    {
        $now = time();
        $id = $this->generateSessionId();

        $expireTime = $now + $ttl;

        // Обеспечиваем, что expiresAt будет int, даже если ttl очень большой
        if ($expireTime < 0 || $ttl === PHP_INT_MAX) {
            $expireTime = PHP_INT_MAX;
        }

        $session = new Session(
            id: $id,
            userId: $userId,
            payload: $payload,
            expiresAt: $expireTime,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repository->save($session);

        return $session;
    }

    public function validateSession(string $sessionId): ?Session
    {
        $session = $this->repository->findById($sessionId);

        if ($session === null || $session->isExpired()) {
            return null;
        }

        return $session;
    }

    public function refreshSession(string $sessionId, int $ttl = 3600): ?Session
    {
        $session = $this->repository->findById($sessionId);

        if ($session === null || $session->isExpired()) {
            return null;
        }

        $now = time();
        $expireTime = $now + $ttl;

        // Обеспечиваем, что expiresAt будет int, даже если ttl очень большой
        if ($expireTime < 0 || $ttl === PHP_INT_MAX) {
            $expireTime = PHP_INT_MAX;
        }

        $refreshedSession = new Session(
            id: $session->id,
            userId: $session->userId,
            payload: $session->payload,
            expiresAt: $expireTime,
            createdAt: $session->createdAt,
            updatedAt: $now,
        );

        $this->repository->save($refreshedSession);

        return $refreshedSession;
    }

    public function deleteSession(string $sessionId): void
    {
        $this->repository->delete($sessionId);
    }

    public function deleteUserSessions(int $userId): void
    {
        $sessions = $this->repository->findByUserId($userId);

        foreach ($sessions as $session) {
            $this->repository->delete($session->id);
        }
    }

    public function cleanupExpiredSessions(): void
    {
        $this->repository->deleteExpired();
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
