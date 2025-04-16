<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Session;

use App\Domain\Session\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testIsExpiredReturnsTrueWhenSessionIsExpired(): void
    {
        $now = time();
        $session = new Session(
            id: 'test-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: $now - 100, // в прошлом
            createdAt: $now - 200,
            updatedAt: $now - 100,
        );

        self::assertTrue($session->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenSessionIsNotExpired(): void
    {
        $now = time();
        $session = new Session(
            id: 'test-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: $now + 100, // в будущем
            createdAt: $now - 200,
            updatedAt: $now - 100,
        );

        self::assertFalse($session->isExpired());
    }

    public function testIsValidReturnsTrueWhenSessionIsNotExpired(): void
    {
        $now = time();
        $session = new Session(
            id: 'test-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: $now + 100, // в будущем
            createdAt: $now - 200,
            updatedAt: $now - 100,
        );

        self::assertTrue($session->isValid());
    }

    public function testIsValidReturnsFalseWhenSessionIsExpired(): void
    {
        $now = time();
        $session = new Session(
            id: 'test-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: $now - 100, // в прошлом
            createdAt: $now - 200,
            updatedAt: $now - 100,
        );

        self::assertFalse($session->isValid());
    }

    public function testSessionCanHaveNullUserId(): void
    {
        $now = time();
        $session = new Session(
            id: 'test-session-id',
            userId: null,
            payload: '{}',
            expiresAt: $now + 100,
            createdAt: $now - 200,
            updatedAt: $now - 100,
        );

        self::assertNull($session->userId);
        self::assertTrue($session->isValid());
    }
}
