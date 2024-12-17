<?php

declare(strict_types=1);

use App\Infrastructure\DI\Container;
use App\Infrastructure\Storage\Migration\MigrationRepository;
use App\Infrastructure\Storage\SQLiteStorage;
use App\Infrastructure\Storage\StorageInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Logger;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;

return static function (Container $container): void {
    $container->bind(ServerRequestFactoryInterface::class, Psr17Factory::class);
    $container->bind(StreamFactoryInterface::class, Psr17Factory::class);
    $container->bind(UploadedFileFactoryInterface::class, Psr17Factory::class);
    $container->bind(StorageInterface::class, SQLiteStorage::class);

    $container->set(
        Worker::class,
        static fn(): WorkerInterface => Worker::create()
    );

    $container->set(
        Logger::class,
        static fn(): LoggerInterface => $container->get(Worker::class)->getLogger()
    );

    $container->set(
        PSR7Worker::class,
        static function (ContainerInterface $container): PSR7Worker {
            /** @var WorkerInterface $worker */
            $worker = $container->get(Worker::class);

            /** @var ServerRequestFactoryInterface $requestFactory */
            $requestFactory = $container->get(ServerRequestFactoryInterface::class);

            /** @var StreamFactoryInterface $streamFactory */
            $streamFactory = $container->get(StreamFactoryInterface::class);

            /** @var UploadedFileFactoryInterface $uploadFactory */
            $uploadFactory = $container->get(UploadedFileFactoryInterface::class);

            return new PSR7Worker(
                $worker,
                $requestFactory,
                $streamFactory,
                $uploadFactory
            );
        }
    );

    $container->set(
        SQLiteStorage::class,
        static function () {
            $databasePath = __DIR__ . '/../db/app.sqlite';
            $databaseDir = dirname($databasePath);

            if (!is_dir($databaseDir)) {
                mkdir($databaseDir, 0755, true);
            }

            return new SQLiteStorage($databasePath);
        }
    );

    $container->set(
        MigrationRepository::class,
        static function (ContainerInterface $container) {
            $storage = $container->get(StorageInterface::class);

            return new MigrationRepository($storage);
        }
    );
};
