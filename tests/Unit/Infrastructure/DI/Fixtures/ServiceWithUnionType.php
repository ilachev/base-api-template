<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI\Fixtures;

final readonly class ServiceWithUnionType
{
    public function __construct(
        public \stdClass|string $param,
    ) {}
}
