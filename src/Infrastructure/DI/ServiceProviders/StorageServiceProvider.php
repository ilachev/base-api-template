<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Storage;
use App\Infrastructure\Storage\StorageFactory;

/**
 * @implements ServiceProvider<object>
 */
final readonly class StorageServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Storage factory
        $container->set(
            StorageFactory::class,
            static function (Container $container): StorageFactory {
                /** @var array{
                 *     engine: string,
                 *     pgsql?: array{
                 *         host: string,
                 *         port: int,
                 *         database: string,
                 *         username: string,
                 *         password: string,
                 *         schema?: string
                 *     }
                 * } $storageConfig
                 */
                $storageConfig = require ProjectPath::getConfigPath('storage.php');

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new StorageFactory($storageConfig, $logger);
            },
        );

        // PostgreSQL storage implementation
        $container->set(
            Storage::class,
            static function (Container $container): Storage {
                /** @var StorageFactory $factory */
                $factory = $container->get(StorageFactory::class);

                return $factory->createStorage();
            },
        );

        // PostgreSQL query factory
        $container->set(
            QueryFactory::class,
            static function (Container $container): QueryFactory {
                /** @var StorageFactory $factory */
                $factory = $container->get(StorageFactory::class);

                return $factory->createQueryFactory();
            },
        );
    }
}
