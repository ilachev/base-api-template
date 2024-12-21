<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator\Fixtures;

final readonly class EntityWithPrivateProperty
{
    public function __construct(
        private string $privateField = '',
    ) {
    }

    public function getPrivateField(): string
    {
        return $this->privateField;
    }
}
