<?php

declare(strict_types=1);

namespace App\Application\Mappers;

use App\Infrastructure\Hydrator\Hydrator;
use Google\Protobuf\Internal\Message;

/**
 * Generic mapper for Protocol Buffer messages.
 * Uses existing hydrator and adapter infrastructure to avoid manual field mapping.
 */
final readonly class DataTransferObjectMapper
{
    public function __construct(
        private Hydrator $hydrator,
    ) {}

    /**
     * Maps data to a specified Protobuf class using the hydrator.
     *
     * @template T of object
     * @param class-string<T> $dtoClass Target DTO class
     * @param array<string, mixed> $data Data to map
     * @return T The hydrated DTO
     */
    public function toDto(string $dtoClass, array $data): object
    {
        return $this->hydrator->hydrate($dtoClass, $data);
    }

    /**
     * Maps an array to a response object with setData method.
     * Specifically designed for Protobuf responses with a setData method.
     *
     * @template T of Message
     * @template D of Message
     * @param class-string<D> $dataClass Data object class (e.g., HomeData)
     * @param class-string<T> $responseClass Response class (e.g., HomeResponse)
     * @param array<string, mixed> $data Source data
     * @return T The populated response object
     */
    public function toResponse(string $dataClass, string $responseClass, array $data): object
    {
        // Create data object
        $dataObject = $this->hydrator->hydrate($dataClass, $data);

        // Create response and set data
        try {
            /** @var T $response */
            $response = new $responseClass();

            if (method_exists($response, 'setData')) {
                $response->setData($dataObject);
            } else {
                throw new \InvalidArgumentException(
                    "Response class {$responseClass} does not have a setData method",
                );
            }

            return $response;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                "Unable to create response of class {$responseClass}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
