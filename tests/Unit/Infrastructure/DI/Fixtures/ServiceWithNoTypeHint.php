<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI\Fixtures;

final class ServiceWithNoTypeHint
{
    public function __construct(
        public $param,
    ) {}
}
