<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI\Fixtures;

final class ServiceWithNoTypeHint
{
    /** @phpstan-ignore-next-line */
    public function __construct(
        public $param,
    ) {}
}
