<?php

declare(strict_types=1);

namespace App\Application\Routing;

use FastRoute\Dispatcher;

final readonly class RouteResult
{
    private const array STATUS_CODES = [
        Dispatcher::FOUND => 200,
        Dispatcher::METHOD_NOT_ALLOWED => 405,
        Dispatcher::NOT_FOUND => 404
    ];

    /**
     * @param array{0: int, 1?: string, 2?: array<string, string>} $routeInfo
     */
    public function __construct(
        private array $routeInfo
    ) {}

    public function isFound(): bool
    {
        return isset($this->routeInfo[0]) && $this->routeInfo[0] === Dispatcher::FOUND;
    }

    public function getHandler(): string
    {
        if (!$this->isFound()) {
            throw new \RuntimeException('Route not found');
        }

        if (!isset($this->routeInfo[1])) {
            throw new \RuntimeException('Handler not found');
        }

        return $this->routeInfo[1];
    }

    /**
     * @return array<string, string>
     */
    public function getParams(): array
    {
        if (!$this->isFound()) {
            throw new \RuntimeException('Route not found');
        }

        return $this->routeInfo[2] ?? [];
    }

    public function getStatusCode(): int
    {
        $status = $this->routeInfo[0] ?? Dispatcher::NOT_FOUND;

        return self::STATUS_CODES[$status] ?? 500;
    }
}
