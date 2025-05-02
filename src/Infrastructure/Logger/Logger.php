<?php

declare(strict_types=1);

namespace App\Infrastructure\Logger;

interface Logger
{
    /**
     * @param array<mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function alert(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function critical(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function error(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function notice(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context
     */
    public function debug(string $message, array $context = []): void;
}
