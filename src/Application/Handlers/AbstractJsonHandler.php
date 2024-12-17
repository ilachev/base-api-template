<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use JsonException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * @template T of array<string, mixed>
 */
readonly abstract class AbstractJsonHandler implements HandlerInterface
{
    /**
     * @param T $data
     */
    protected function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        try {
            $jsonData = json_encode(
                $data,
                JSON_THROW_ON_ERROR
            );

            return new Response(
                $status,
                ['Content-Type' => 'application/json'],
                $jsonData
            );
        } catch (JsonException $e) {
            return $this->jsonError('JSON encoding failed', 500);
        }
    }

    protected function jsonError(string $message, int $status = 400): ResponseInterface
    {
        try {
            return new Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => $message], JSON_THROW_ON_ERROR)
            );
        } catch (JsonException) {
            return new Response(500, [], 'Internal Server Error');
        }
    }
}
