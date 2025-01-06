<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Error\Error;
use App\Application\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JsonResponse $jsonResponse,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws \JsonException
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $this->logger->error((string) $e);

            return $this->jsonResponse->error(
                Error::INTERNAL_ERROR,
                500,
            );
        }
    }
}
