<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class HttpLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $requestId = $request->getAttribute('requestId');

        $this->logger->info('Request.', [
            'request_id' => $requestId,
            'request' => $request->getQueryParams(),
        ]);

        $response = $handler->handle($request);

        $this->logger->info('Application responded.', [
            'request_id' => $requestId,
            'response' => $response->getBody()->getContents(),
        ]);

        return $response;
    }
}
