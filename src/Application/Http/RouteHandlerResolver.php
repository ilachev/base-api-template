<?php

declare(strict_types=1);

namespace App\Application\Http;

use App\Application\Handlers\HandlerFactoryInterface;
use App\Application\Handlers\HandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RouteHandlerResolver implements RequestHandler
{
    public function __construct(
        private HandlerFactoryInterface $handlerFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var class-string<HandlerInterface> $handlerClass */
        $handlerClass = $request->getAttribute('handler');

        $handler = $this->handlerFactory->create($handlerClass);

        return $handler->handle($request);
    }
}
