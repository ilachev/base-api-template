<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Pipeline implements RequestHandlerInterface
{
    /**
     * @param RequestHandlerInterface $handler
     * @param MiddlewareInterface[] $middlewares
     */
    public function __construct(
        private readonly RequestHandlerInterface $handler,
        private array $middlewares = [],
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->middlewares);

        if ($middleware === null) {
            return $this->handler->handle($request);
        }

        return $middleware->process(
            $request,
            new self($this->handler, $this->middlewares)
        );
    }
}
