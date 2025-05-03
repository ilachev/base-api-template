<?php

declare(strict_types=1);

namespace App\Infrastructure\Logger;

final class RoadRunnerLogger implements Logger
{
    public ?string $requestId = null {
        get {
            return $this->requestId;
        }
        set {
            $this->requestId = $value;
        }
    }

    public function __construct(
        private readonly ReadableOutputLogger $logger,
    ) {}

    /**
     * @param array<mixed> $context
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if ($this->requestId !== null && !isset($context['request_id'])) {
            $context['request_id'] = $this->requestId;
        }

        $this->logger->log($level, $message, $context);
    }
}
