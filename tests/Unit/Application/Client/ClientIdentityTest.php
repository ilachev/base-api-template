<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Client;

use App\Application\Client\ClientIdentity;
use App\Domain\Session\Session;
use PHPUnit\Framework\TestCase;

final class ClientIdentityTest extends TestCase
{
    public function testFromSessionCreatesIdentityWithCorrectData(): void
    {
        $payload = json_encode([
            'ip' => '192.168.1.1',
            'userAgent' => 'Test Browser',
            'acceptLanguage' => 'en-US',
            'xForwardedFor' => '10.0.0.1',
        ]);

        $session = new Session(
            id: 'test-session-id',
            userId: 123,
            payload: $payload !== false ? $payload : '{}',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time(),
        );

        $identity = ClientIdentity::fromSession($session);

        self::assertSame('test-session-id', $identity->id);
        self::assertSame('192.168.1.1', $identity->ipAddress);
        self::assertSame('Test Browser', $identity->userAgent);
        self::assertCount(2, $identity->attributes);
        self::assertArrayHasKey('acceptLanguage', $identity->attributes);
        self::assertArrayHasKey('xForwardedFor', $identity->attributes);
    }

    public function testFromSessionHandlesInvalidPayload(): void
    {
        $session = new Session(
            id: 'test-session-id',
            userId: 123,
            payload: 'invalid-json',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time(),
        );

        $identity = ClientIdentity::fromSession($session);

        self::assertSame('test-session-id', $identity->id);
        self::assertSame('unknown', $identity->ipAddress);
        self::assertNull($identity->userAgent);
        self::assertEmpty($identity->attributes);
    }

    public function testMatchesReturnsTrueForSameId(): void
    {
        $identity1 = new ClientIdentity(
            id: 'same-id',
            ipAddress: '192.168.1.1',
            userAgent: 'Test Browser',
        );

        $identity2 = new ClientIdentity(
            id: 'same-id',
            ipAddress: '10.0.0.1', // разные IP, но одинаковый ID
            userAgent: 'Other Browser',
        );

        self::assertTrue($identity1->matches($identity2));
    }

    public function testMatchesReturnsTrueForSameIpAndUserAgent(): void
    {
        $identity1 = new ClientIdentity(
            id: 'id1',
            ipAddress: '192.168.1.1',
            userAgent: 'Test Browser',
        );

        $identity2 = new ClientIdentity(
            id: 'id2', // разные ID
            ipAddress: '192.168.1.1', // но одинаковый IP и User-Agent
            userAgent: 'Test Browser',
        );

        self::assertTrue($identity1->matches($identity2));
    }

    public function testMatchesReturnsTrueForSameIpAndAttributes(): void
    {
        $identity1 = new ClientIdentity(
            id: 'id1',
            ipAddress: '192.168.1.1',
            userAgent: 'Browser 1',
            attributes: ['accept_language' => 'en-US', 'screen_size' => '1920x1080'],
        );

        $identity2 = new ClientIdentity(
            id: 'id2', // разные ID и User-Agent
            ipAddress: '192.168.1.1', // но одинаковый IP и некоторые атрибуты
            userAgent: 'Browser 2',
            attributes: ['accept_language' => 'en-US', 'other_param' => 'value'],
        );

        self::assertTrue($identity1->matches($identity2));
    }

    public function testMatchesReturnsFalseForDifferentClient(): void
    {
        $identity1 = new ClientIdentity(
            id: 'id1',
            ipAddress: '192.168.1.1',
            userAgent: 'Browser 1',
        );

        $identity2 = new ClientIdentity(
            id: 'id2',
            ipAddress: '10.0.0.1', // другой IP
            userAgent: 'Browser 2', // другой User-Agent
        );

        self::assertFalse($identity1->matches($identity2));
    }

    public function testMatchesWithStrictIpRequiresSameIp(): void
    {
        $identity1 = new ClientIdentity(
            id: 'id1',
            ipAddress: '192.168.1.1',
            userAgent: 'Test Browser',
        );

        $identity2 = new ClientIdentity(
            id: 'id2',
            ipAddress: '10.0.0.1', // другой IP
            userAgent: 'Test Browser', // одинаковый User-Agent
        );

        self::assertFalse($identity1->matches($identity2, true));
    }
}
