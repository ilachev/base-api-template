<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Application\Handlers\HandlerFactoryInterface;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ContainerHandlerFactory;
use App\Infrastructure\DI\DIContainer;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Logger\ReadableOutputLogger;
use App\Infrastructure\Logger\RoadRunnerLogger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;

/**
 * @implements ServiceProvider<object>
 */
final readonly class CoreServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Container bindings
        $container->bind(Container::class, DIContainer::class);

        // PSR-7 factories
        $container->bind(ServerRequestFactoryInterface::class, Psr17Factory::class);
        $container->bind(StreamFactoryInterface::class, Psr17Factory::class);
        $container->bind(UploadedFileFactoryInterface::class, Psr17Factory::class);

        // Logger
        $container->bind(ReadableOutputLogger::class, ReadableOutputLogger::class);
        $container->bind(Logger::class, RoadRunnerLogger::class);

        // Handler factory
        $container->bind(HandlerFactoryInterface::class, ContainerHandlerFactory::class);
        $container->set(
            ContainerHandlerFactory::class,
            static fn(Container $container): ContainerHandlerFactory => new ContainerHandlerFactory($container),
        );

        // RoadRunner workers
        $container->set(
            Worker::class,
            static fn(): WorkerInterface => Worker::create(),
        );

        $container->set(
            PSR7Worker::class,
            static function (Container $container): PSR7Worker {
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
                    $uploadFactory,
                );
            },
        );
    }
}
