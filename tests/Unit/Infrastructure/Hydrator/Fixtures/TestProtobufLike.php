<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator\Fixtures;

/**
 * Mock class that mimics Protobuf-generated classes with protected properties and public setters/getters.
 */
final class TestProtobufLike
{
    private string $message = '';

    private int $number = 0;

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setNumber(int $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getNumber(): int
    {
        return $this->number;
    }
}
