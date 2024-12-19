<?php

declare(strict_types=1);

namespace App\Application\Routing;

final readonly class RouteResult
{
    public function __construct(
        private RouteStatus $status,
        private ?string $handler = null,
        /** @var array<string, string> */
        private array $params = []
    ) {}

    public function isFound(): bool
    {
        return $this->status === RouteStatus::FOUND;
    }

    public function getHandler(): string
    {
        if (!$this->isFound()) {
            throw RouteException::routeNotFound();
        }
        if ($this->handler === null) {
            throw RouteException::handlerNotFound();
        }

        return $this->handler;
    }

    /**
     * @return array<string, string>
     */
    public function getParams(): array
    {
        if (!$this->isFound()) {
            throw RouteException::routeNotFound();
        }

        return $this->params;
    }

    public function getStatusCode(): int
    {
        return $this->status->getStatusCode();
    }
}
