<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI\Fixtures;

final readonly class ServiceWithBuiltinType
{
    public function __construct(public string $value) {}
}
