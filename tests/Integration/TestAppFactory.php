<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\App;

/**
 * Factory class to provide a single App instance across all integration tests.
 *
 * This ensures that the same container is used in bootstrap and tests,
 * eliminating inconsistencies between different container instances.
 */
final class TestAppFactory
{
    /** @var App<object>|null */
    private static ?App $app = null;

    /**
     * Sets the application instance to be used across all tests.
     *
     * @param App<object> $app
     */
    public static function setApp(App $app): void
    {
        self::$app = $app;
    }

    /**
     * Returns the shared application instance.
     *
     * @return App<object>
     * @throws \RuntimeException If app was not properly initialized in bootstrap
     */
    public static function getApp(): App
    {
        if (self::$app === null) {
            throw new \RuntimeException(
                'App instance not initialized. Make sure bootstrap.php has been executed.',
            );
        }

        return self::$app;
    }
}
