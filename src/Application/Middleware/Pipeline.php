<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\Middleware;
use App\Application\Http\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Pipeline implements RequestHandler
{
    /**
     * @param Middleware[] $middlewares
     */
    public function __construct(
        private RequestHandler $handler,
        private array $middlewares = [],
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middlewares = $this->middlewares;
        $middleware = array_shift($middlewares);

        if ($middleware === null) {
            return $this->handler->handle($request);
        }

        return $middleware->process(
            $request,
            new self($this->handler, $middlewares),
        );
    }
}
