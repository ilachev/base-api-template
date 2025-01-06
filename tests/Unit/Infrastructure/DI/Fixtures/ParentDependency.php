<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI\Fixtures;

final readonly class ParentDependency
{
    public function __construct(
        public NestedDependency $nested,
    ) {}
}
