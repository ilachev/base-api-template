<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI\Fixtures;

final readonly class Circular2
{
    public function __construct(public Circular1 $c1)
    {
    }
}
