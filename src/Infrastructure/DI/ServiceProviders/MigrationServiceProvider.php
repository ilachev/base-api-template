<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Storage\Migration\MigrationLoader;
use App\Infrastructure\Storage\Migration\MigrationRepository;
use App\Infrastructure\Storage\Migration\MigrationService;
use App\Infrastructure\Storage\StorageInterface;

/**
 * @implements ServiceProvider<object>
 */
final readonly class MigrationServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Migration loader
        $container->set(
            MigrationLoader::class,
            static function (Container $container): MigrationLoader {
                /** @var array{
                 *     engine: string,
                 *     pgsql?: array{migrations_path: string}
                 * } $storageConfig
                 */
                $storageConfig = require ProjectPath::getConfigPath('storage.php');

                // PostgreSQL migrations path
                $migrationsPath = $storageConfig['pgsql']['migrations_path'] ?? '';

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new MigrationLoader($migrationsPath, $logger);
            },
        );

        // Migration repository
        $container->set(
            MigrationRepository::class,
            static function (Container $container): MigrationRepository {
                /** @var StorageInterface $storage */
                $storage = $container->get(StorageInterface::class);

                return new MigrationRepository($storage);
            },
        );

        // Migration service
        $container->set(
            MigrationService::class,
            static function (Container $container): MigrationService {
                /** @var StorageInterface $storage */
                $storage = $container->get(StorageInterface::class);

                /** @var MigrationRepository $repository */
                $repository = $container->get(MigrationRepository::class);

                /** @var MigrationLoader $loader */
                $loader = $container->get(MigrationLoader::class);

                return new MigrationService($storage, $repository, $loader);
            },
        );
    }
}
