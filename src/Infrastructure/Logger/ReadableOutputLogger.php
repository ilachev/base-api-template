<?php

declare(strict_types=1);

namespace App\Infrastructure\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class ReadableOutputLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Convert level to string safely
        $stringLevel = match (true) {
            \is_string($level) => $level,
            \is_scalar($level) => (string) $level,
            default => 'unknown',
        };

        $stringMessage = $message instanceof \Stringable ? (string) $message : $message;

        // Format the message
        $formattedMessage = $this->format($stringLevel, $stringMessage, $context);

        // Write the formatted message with a newline character
        $this->write($formattedMessage);
    }

    /**
     * Write message to stderr with a newline character.
     */
    protected function write(string $message): void
    {
        file_put_contents('php://stderr', $message . PHP_EOL);
    }

    /**
     * Format log message with level and context.
     *
     * @param array<string, mixed> $context
     */
    protected function format(string $level, string $message, array $context = []): string
    {
        return \sprintf('[php %s] %s %s', $level, $message, $this->formatContext($context));
    }

    /**
     * Format context array as JSON.
     *
     * @param array<string, mixed> $context
     */
    protected function formatContext(array $context): string
    {
        try {
            return json_encode($context, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return print_r($context, true);
        }
    }
}
