<?php

declare(strict_types=1);

namespace App\Infrastructure\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Spiral\RoadRunner\Logger;

final class RoadRunnerLogger implements LoggerInterface
{
    private ?string $requestId = null;

    public function __construct(
        private Logger $logger,
    ) {}

    /**
     * Устанавливает ID запроса для всех последующих логов.
     */
    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId;
    }

    /**
     * Возвращает текущий ID запроса.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Добавляем requestId в контекст лога, если он установлен и ещё не добавлен
        if ($this->requestId !== null && !isset($context['request_id'])) {
            $context['request_id'] = $this->requestId;
        }

        $this->logger->log($level, (string) $message, $context);
    }
}
