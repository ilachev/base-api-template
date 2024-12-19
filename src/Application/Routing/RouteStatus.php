<?php

declare(strict_types=1);

namespace App\Application\Routing;

enum RouteStatus: int
{
    case FOUND = 0;
    case METHOD_NOT_ALLOWED = 1;
    case NOT_FOUND = 2;

    public function getStatusCode(): int
    {
        return match($this) {
            self::FOUND => 200,
            self::METHOD_NOT_ALLOWED => 405,
            self::NOT_FOUND => 404,
        };
    }
}
