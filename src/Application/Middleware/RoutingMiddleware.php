<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Error\Error;
use App\Application\Http\JsonResponse;
use App\Application\Http\Middleware;
use App\Application\Http\RequestHandler;
use App\Application\Routing\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RoutingMiddleware implements Middleware
{
    public function __construct(
        private RouterInterface $router,
        private JsonResponse $jsonResponse,
    ) {}

    /**
     * @throws \JsonException
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        $routeResult = $this->router->dispatch($request);

        if (!$routeResult->isFound()) {
            return $this->jsonResponse->error(
                Error::NOT_FOUND,
                $routeResult->getStatusCode(),
            );
        }

        return $handler->handle(
            $request->withAttribute('routeParams', $routeResult->getParams())
                ->withAttribute('handler', $routeResult->getHandler()),
        );
    }
}
