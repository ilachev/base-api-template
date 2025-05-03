<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\Middleware;
use App\Application\Http\RequestHandler;
use App\Infrastructure\Logger\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HttpLoggingMiddleware implements Middleware
{
    public function __construct(
        private Logger $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
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
