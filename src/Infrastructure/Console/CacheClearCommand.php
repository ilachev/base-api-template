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
     */
    public function clear(): void
    {
        try {
            $this->cacheService->clear();
            $this->logger->info('Cache cleared successfully via console command');
            echo "Cache cleared successfully.\n";
        } catch (\Throwable $e) {
            $this->logger->error('Failed to clear cache', [
                'error' => $e->getMessage(),
            ]);
            echo "Error: Failed to clear cache: {$e->getMessage()}\n";
        }
    }
}