<?php

declare(strict_types=1);

namespace App\Domain\Session;

interface SessionRepository
{
    public function findById(string $id): ?Session;

    /**
     * @return array<Session>
     */
    public function findByUserId(int $userId): array;

    public function save(Session $session): void;

    public function delete(string $id): void;

    public function deleteExpired(): void;
}
