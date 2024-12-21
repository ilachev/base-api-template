<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator\Fixtures;

final readonly class EntityWithProtectedProperty
{
    public function __construct(
        protected string $protectedField = '',
    ) {
    }
}
