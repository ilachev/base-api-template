<?php

declare(strict_types=1);

namespace App\Domain\Session;

use App\Domain\Entity;
use ProtoPhpGen\Attributes\ProtoField;
use ProtoPhpGen\Attributes\ProtoMapping;

#[ProtoMapping(class: 'App\Api\V1\Session')]
final readonly class Session implements Entity
{
    public function __construct(
        #[ProtoField(name: 'id')]
        public string $id,
        #[ProtoField(name: 'user_id')]
        public ?int $userId,
        #[ProtoField(name: 'payload', type: 'json')]
        public string $payload,
        #[ProtoField(name: 'expires_at', type: 'datetime')]
        public int $expiresAt,
        #[ProtoField(name: 'created_at', type: 'datetime')]
        public int $createdAt,
        #[ProtoField(name: 'updated_at', type: 'datetime')]
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
