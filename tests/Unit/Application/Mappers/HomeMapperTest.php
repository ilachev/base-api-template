<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Mappers;

use App\Application\Mappers\HomeMapper;
use App\Infrastructure\Hydrator\Hydrator;
use PHPUnit\Framework\TestCase;

final class HomeMapperTest extends TestCase
{
    public function testToResponse(): void
    {
        $hydrator = new Hydrator();
        $mapper = new HomeMapper($hydrator);

        $response = $mapper->toResponse('Welcome to our API');

        self::assertNotNull($response->getData());
        self::assertEquals('Welcome to our API', $response->getData()->getMessage());
    }
}
