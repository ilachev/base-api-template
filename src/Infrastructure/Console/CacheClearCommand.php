<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Cache\CacheService;
use Psr\Log\LoggerInterface;

final readonly class CacheClearCommand
{
    public function __construct(
        private CacheService $cacheService,
        private LoggerInterface $logger,
    ) {}

    /**
     * Очищает весь кеш.
     *
     * @param bool $quiet Если true, не выводит сообщения в консоль
     * @return bool True в случае успеха, false в случае ошибки
     */
    public function clear(bool $quiet = false): bool
    {
        try {
            $success = $this->cacheService->clear();

            if ($success) {
                $this->logger->info('Cache cleared successfully via console command');
                if (!$quiet) {
                    echo "Cache cleared successfully.\n";
                }

                return true;
            }

            $this->logger->warning('Cache clear reported failure without throwing exception');
            if (!$quiet) {
                echo "Warning: Cache clearing completed but reported failure.\n";
            }

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to clear cache', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            if (!$quiet) {
                echo "Error: Failed to clear cache: {$e->getMessage()}\n";
            }

            return false;
        }
    }
}
