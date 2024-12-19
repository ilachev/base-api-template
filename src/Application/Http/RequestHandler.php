<?php

declare(strict_types=1);

namespace App\Application\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Application\Handlers\HandlerInterface;

final readonly class RequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private HandlerInterface $handler
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
