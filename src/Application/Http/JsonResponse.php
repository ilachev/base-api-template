<?php

declare(strict_types=1);

namespace App\Application\Http;

use JsonException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use App\Application\Error\Error;

final readonly class JsonResponse
{
    /**
     * @param array<string, mixed> $data
     * @throws JsonException
     */
    public function success(array $data, int $status = 200): ResponseInterface
    {
        return $this->encode($data, $status);
    }

    /**
     * @throws JsonException
     */
    public function error(Error $error, int $status): ResponseInterface
    {
        return $this->encode(['error' => ['message' => $error->text()]], $status);
    }

    /**
     * @param array<string, mixed> $data
     * @throws JsonException
     */
    private function encode(array $data, int $status): ResponseInterface
    {
        try {
            return new Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode(['data' => $data], JSON_THROW_ON_ERROR)
            );
        } catch (JsonException) {
            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode(
                    $data,
                    JSON_THROW_ON_ERROR
                )
            );
        }
    }
}
