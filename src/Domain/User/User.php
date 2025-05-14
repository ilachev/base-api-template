<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Entity;
use ProtoPhpGen\Attributes\ProtoField;
use ProtoPhpGen\Attributes\ProtoMapping;

#[ProtoMapping(class: 'App\\Api\\V1\\User')]
final readonly class User implements Entity
{
    public function __construct(
        #[ProtoField(name: 'id')]
        public ?int $id,
        #[ProtoField(name: 'password_hash')]
        public string $passwordHash,
        #[ProtoField(name: 'created_at', type: 'datetime')]
        public int $createdAt,
        #[ProtoField(name: 'updated_at', type: 'datetime')]
        public int $updatedAt,
    ) {}
}
