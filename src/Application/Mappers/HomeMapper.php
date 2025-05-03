<?php

declare(strict_types=1);

namespace App\Application\Mappers;

use App\Api\V1\HomeData;
use App\Api\V1\HomeResponse;

final readonly class HomeMapper
{
    public function __construct(
        private DataTransferObjectMapper $dtoMapper,
    ) {}

    public function toResponse(string $message): HomeResponse
    {
        return $this->dtoMapper->toResponse(
            HomeData::class,
            HomeResponse::class,
            ['message' => $message],
        );
    }
}
