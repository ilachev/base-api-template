<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator\Fixtures;

use Google\Protobuf\Internal\Message;

/**
 * Test fixture that extends Protobuf Message
 * Used for testing isProtobufMessage() method in ReflectionCache.
 */
final class TestProtobufMessage extends Message
{
    // No need to implement all abstract methods as this is just a test fixture
    // and we're only testing the inheritance checking, not actual usage

    public function __construct()
    {
        // Empty constructor
    }

    public function clear()
    {
        // Required by abstract class
        return null;
    }

    public function mergeFrom($data)
    {
        // Required by abstract class
        return null;
    }

    public function serializeToString(): string
    {
        return '';
    }

    public function parseFromString(string $data): null
    {
        // Required by abstract class
        return null;
    }
}
