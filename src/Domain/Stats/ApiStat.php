<?php

declare(strict_types=1);

namespace App\Domain\Stats;

use App\Domain\Entity;

final readonly class ApiStat implements Entity
{
    public function __construct(
        public ?int $id,
        public string $sessionId,
        public string $route,
        public string $method,
        public int $statusCode,
        public float $executionTime,
        public int $requestTime,
    ) {}
}
