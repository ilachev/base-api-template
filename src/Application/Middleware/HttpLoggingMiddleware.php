<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Logger\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class HttpLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Logger $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $this->logger->info('Request.', [
            'request' => $request->getQueryParams(),
        ]);

        $response = $handler->handle($request);

        $this->logger->info('Application responded.', [
            'response' => $response->getBody()->getContents(),
        ]);

        return $response;
    }
}
