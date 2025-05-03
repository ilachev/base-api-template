<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Error\Error;
use App\Application\Http\JsonResponse;
use App\Application\Http\Middleware;
use App\Application\Http\RequestHandler;
use App\Infrastructure\Logger\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ErrorHandlerMiddleware implements Middleware
{
    public function __construct(
        private JsonResponse $jsonResponse,
        private Logger $logger,
    ) {}

    /**
     * @throws \JsonException
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
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
