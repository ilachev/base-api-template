<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator\Fixtures;

/**
 * Mock class with a setter that throws an exception for testing error handling.
 */
final class TestProtobufInvalidSetter
{
    /** @phpstan-ignore-next-line */
    private mixed $value = null;

    /**
     * This setter will throw an exception when called.
     */
    public function setValue(mixed $value): self
    {
        throw new \RuntimeException('This setter is designed to fail for testing');
    }
}
