<?php

declare(strict_types=1);

namespace App\Domain\Session;

use Psr\Log\LoggerInterface;

final readonly class SessionService
{
    public function __construct(
        private SessionRepository $repository,
        private LoggerInterface $logger,
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

    /**
     * Обновляет сессию если необходимо.
     *
     * @param string $sessionId Идентификатор сессии
     * @param int $ttl Время жизни в секундах
     * @param bool $onlyIfNeeded Если true, сессия обновляется только если до истечения осталось менее 50% TTL
     * @return Session|null Обновленная сессия или null если сессия не найдена или истекла
     */
    public function refreshSession(string $sessionId, int $ttl = 3600, bool $onlyIfNeeded = true): ?Session
    {
        $session = $this->repository->findById($sessionId);

        if ($session === null || $session->isExpired()) {
            return null;
        }

        $now = time();

        // Если установлен флаг onlyIfNeeded, проверяем, нужно ли обновлять сессию
        // Если до истечения осталось больше половины TTL, то не обновляем
        if ($onlyIfNeeded) {
            $remainingTime = $session->expiresAt - $now;
            $halfTtl = $ttl / 2;

            if ($remainingTime > $halfTtl) {
                // Возвращаем существующую сессию без обновления
                $this->logger->debug('Session not refreshed (not needed)', [
                    'session_id' => $sessionId,
                    'remaining_seconds' => $remainingTime,
                    'ttl_threshold' => $halfTtl,
                ]);

                return $session;
            }

            $this->logger->debug('Session needs refresh', [
                'session_id' => $sessionId,
                'remaining_seconds' => $remainingTime,
                'ttl_threshold' => $halfTtl,
            ]);
        }

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
