<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Application\Error\Error;

final class RouteException extends \RuntimeException
{
    public function __construct(
        private Error $error,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): Error
    {
        return $this->error;
    }

    public static function routeNotFound(): self
    {
        return new self(
            Error::NOT_FOUND,
            'Route not found',
        );
    }

    public static function handlerNotFound(): self
    {
        return new self(
            Error::INTERNAL_ERROR,
            'Handler not found',
        );
    }
}
