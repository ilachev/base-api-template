<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator;

use App\Api\V1\HomeData;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\LimitedReflectionCache;
use App\Infrastructure\Hydrator\ReflectionHydrator;
use App\Infrastructure\Hydrator\SetterProtobufHydration;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Hydrator\Fixtures\TestEntity;

final class HydratorProtobufTest extends TestCase
{
    private Hydrator $hydrator;

    protected function setUp(): void
    {
        $cache = new LimitedReflectionCache();
        $protobufHydration = new SetterProtobufHydration();
        $this->hydrator = new ReflectionHydrator($cache, $protobufHydration);
    }

    public function testHydrateProtobufObject(): void
    {
        $data = [
            'message' => 'Hello from test',
        ];

        /** @var HomeData $result */
        $result = $this->hydrator->hydrate(HomeData::class, $data);

        self::assertInstanceOf(HomeData::class, $result);
        self::assertEquals('Hello from test', $result->getMessage());
    }

    public function testHydrateRegularObject(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test Entity',
        ];

        /** @var TestEntity $result */
        $result = $this->hydrator->hydrate(TestEntity::class, $data);

        self::assertInstanceOf(TestEntity::class, $result);
        self::assertEquals(1, $result->id);
        self::assertEquals('Test Entity', $result->name);
    }
}
