<?php

declare(strict_types=1);

namespace App\Domain\Stats;

final readonly class ApiStat
{
    public function __construct(
        public ?int $id,
        public string $clientId,
        public string $route,
        public string $method,
        public int $statusCode,
        public float $executionTime,
        public int $requestTime,
    ) {}
}
