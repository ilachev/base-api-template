<?php

declare(strict_types=1);

namespace App\Domain\Session;

use App\Domain\Entity;

final readonly class Session implements Entity
{
    public function __construct(
        public string $id,
        public ?int $userId,
        public string $payload,
        public int $expiresAt,
        public int $createdAt,
        public int $updatedAt,
    ) {}

    public function isExpired(): bool
    {
        return $this->expiresAt < time();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }
}
