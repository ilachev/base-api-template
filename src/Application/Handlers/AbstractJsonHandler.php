<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Error\Error;
use App\Application\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;

abstract readonly class AbstractJsonHandler implements HandlerInterface
{
    public function __construct(
        private JsonResponse $jsonResponse,
    ) {}

    /**
     * /**
     * @throws \JsonException
     */
    protected function jsonResponse(string $data, int $status = 200): ResponseInterface
    {
        return $this->jsonResponse->success($data, $status);
    }

    /**
     * @throws \JsonException
     */
    protected function jsonError(Error $error, int $status = 400): ResponseInterface
    {
        return $this->jsonResponse->error($error, $status);
    }
}
