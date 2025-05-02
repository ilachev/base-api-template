<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Entity;

final readonly class User implements Entity
{
    public function __construct(
        public ?int $id,
        public string $passwordHash,
        public int $createdAt,
        public int $updatedAt,
    ) {}
}
