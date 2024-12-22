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
     * @throws JsonException
     */
    public function success(string $data, int $status = 200): ResponseInterface
    {
        return $this->encode($data, $status);
    }

    /**
     * @throws JsonException
     */
    public function error(Error $error, int $status): ResponseInterface
    {
        return $this->encode($error->text(), $status);
    }

    /**
     * @throws JsonException
     */
    private function encode(string $data, int $status): ResponseInterface
    {
        try {
            return new Response(
                $status,
                ['Content-Type' => 'application/json'],
                $data
            );
        } catch (JsonException) {
            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode(
                    ['error' => $data],
                    JSON_THROW_ON_ERROR
                )
            );
        }
    }
}
