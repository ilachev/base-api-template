<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI\Fixtures;

class ServiceWithNoTypeHint
{
    /**
     * @param mixed $param
     */
    public function __construct(
        public $param
    ) {
    }
}
