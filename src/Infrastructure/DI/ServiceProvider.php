<?php

declare(strict_types=1);

namespace App\Infrastructure\DI;

/**
 * @template T of object
 */
interface ServiceProvider
{
    /**
     * Register services to the container.
     * @param Container<T> $container
     */
    public function register(Container $container): void;
}
