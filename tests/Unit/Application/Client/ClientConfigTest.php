<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Client;

use App\Application\Client\ClientConfig;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    public function testCreateWithDefaultValues(): void
    {
        $config = new ClientConfig();

        self::assertSame(0.6, $config->similarityThreshold);
        self::assertSame(5, $config->maxSessionsPerIp);
        self::assertSame(0.3, $config->ipMatchWeight);
        self::assertSame(0.3, $config->userAgentMatchWeight);
        self::assertSame(0.4, $config->attributesMatchWeight);
    }

    public function testCreateWithCustomValues(): void
    {
        $config = new ClientConfig(
            similarityThreshold: 0.8,
            maxSessionsPerIp: 10,
            ipMatchWeight: 0.4,
            userAgentMatchWeight: 0.2,
            attributesMatchWeight: 0.4,
        );

        self::assertSame(0.8, $config->similarityThreshold);
        self::assertSame(10, $config->maxSessionsPerIp);
        self::assertSame(0.4, $config->ipMatchWeight);
        self::assertSame(0.2, $config->userAgentMatchWeight);
        self::assertSame(0.4, $config->attributesMatchWeight);
    }

    public function testFromArrayWithAllValues(): void
    {
        $config = ClientConfig::fromArray([
            'similarity_threshold' => 0.7,
            'max_sessions_per_ip' => 15,
            'ip_match_weight' => 0.25,
            'user_agent_match_weight' => 0.25,
            'attributes_match_weight' => 0.5,
        ]);

        self::assertSame(0.7, $config->similarityThreshold);
        self::assertSame(15, $config->maxSessionsPerIp);
        self::assertSame(0.25, $config->ipMatchWeight);
        self::assertSame(0.25, $config->userAgentMatchWeight);
        self::assertSame(0.5, $config->attributesMatchWeight);
    }

    public function testFromArrayWithMissingValues(): void
    {
        $config = ClientConfig::fromArray([
            'similarity_threshold' => 0.75,
        ]);

        self::assertSame(0.75, $config->similarityThreshold);
        self::assertSame(5, $config->maxSessionsPerIp); // default
        self::assertSame(0.3, $config->ipMatchWeight); // default
        self::assertSame(0.3, $config->userAgentMatchWeight); // default
        self::assertSame(0.4, $config->attributesMatchWeight); // default
    }

    public function testValidatesWeightsSum(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Sum of match weights must be 1.0, got 0.9');

        new ClientConfig(
            ipMatchWeight: 0.3,
            userAgentMatchWeight: 0.3,
            attributesMatchWeight: 0.3, // Сумма весов = 0.9
        );
    }
}
