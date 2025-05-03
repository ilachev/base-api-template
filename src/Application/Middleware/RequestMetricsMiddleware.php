<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Logger\RoadRunnerLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestMetricsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Logger $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $startTime = hrtime(true);
        $requestId = uniqid();

        // Устанавливаем requestId в логгере, если это RoadRunnerLogger
        if ($this->logger instanceof RoadRunnerLogger) {
            $this->logger->requestId = $requestId;
        }

        $response = $handler->handle(
            $request->withAttribute('requestId', $requestId),
        );

        $executionTime = (hrtime(true) - $startTime) / 1_000_000;

        return $response
            ->withHeader('X-Request-ID', $requestId)
            ->withHeader('X-Response-Time', \sprintf('%.2f ms', $executionTime))
            ->withHeader('Server-Timing', \sprintf('app;dur=%.2f', $executionTime));
    }
}
