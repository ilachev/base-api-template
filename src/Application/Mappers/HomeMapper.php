<?php

declare(strict_types=1);

namespace App\Application\Mappers;

use App\Api\V1\HomeData;
use App\Api\V1\HomeResponse;
use App\Infrastructure\Hydrator\HydratorInterface;

final readonly class HomeMapper
{
    public function __construct(
        private HydratorInterface $hydrator,
    ) {}

    public function toResponse(string $message): HomeResponse
    {
        // Create HomeData using hydrator
        $data = $this->hydrator->hydrate(HomeData::class, ['message' => $message]);

        // Create response and set data
        $response = new HomeResponse();
        $response->setData($data);

        return $response;
    }
}
