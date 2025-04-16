<?php

declare(strict_types=1);

use App\Application\Client\ClientConfig;
use App\Application\Client\ClientDataFactory;
use App\Application\Client\ClientDetector;
use App\Application\Client\DefaultClientDataFactory;
use App\Application\Handlers\HandlerFactoryInterface;
use App\Application\Mappers\HomeMapper;
use App\Application\Middleware\SessionMiddleware;
use App\Application\Routing\RouteDefinition;
use App\Application\Routing\RouteDefinitionInterface;
use App\Application\Routing\RouterInterface;
use App\Domain\Home\HomeService;
use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionService;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ContainerHandlerFactory;
use App\Infrastructure\Hydrator\DefaultJsonFieldAdapter;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\HydratorInterface;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Logger\RoadRunnerLogger;
use App\Infrastructure\Routing\FastRouteAdapter;
use App\Infrastructure\Storage\Query\QueryBuilderFactory;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Session\SQLiteSessionRepository;
use App\Infrastructure\Storage\SQLiteStorage;
use App\Infrastructure\Storage\StorageInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;

return static function (Container $container): void {
    $container->bind(ContainerInterface::class, Container::class);

    $container->bind(ServerRequestFactoryInterface::class, Psr17Factory::class);
    $container->bind(StreamFactoryInterface::class, Psr17Factory::class);
    $container->bind(UploadedFileFactoryInterface::class, Psr17Factory::class);

    $container->bind(LoggerInterface::class, RoadRunnerLogger::class);
    $container->bind(StorageInterface::class, SQLiteStorage::class);
    $container->bind(HandlerFactoryInterface::class, ContainerHandlerFactory::class);
    $container->bind(RouterInterface::class, FastRouteAdapter::class);
    $container->set(
        RouteDefinitionInterface::class,
        static fn() => new RouteDefinition(__DIR__ . '/routes.php'),
    );
    $container->bind(HydratorInterface::class, Hydrator::class);

    // JSON field adapter
    $container->bind(JsonFieldAdapter::class, DefaultJsonFieldAdapter::class);
    $container->set(
        DefaultJsonFieldAdapter::class,
        static function (ContainerInterface $container): DefaultJsonFieldAdapter {
            /** @var HydratorInterface $hydrator */
            $hydrator = $container->get(HydratorInterface::class);

            return new DefaultJsonFieldAdapter($hydrator);
        },
    );

    // QueryBuilder factory
    $container->bind(QueryBuilderFactory::class, QueryBuilderFactory::class);
    $container->bind(QueryFactory::class, QueryBuilderFactory::class);

    // Session config
    $container->set(
        SessionConfig::class,
        static function (): SessionConfig {
            /** @var array{cookie_name: string, cookie_ttl: int, session_ttl: int, use_fingerprint: bool} $sessionConfig */
            $sessionConfig = require __DIR__ . '/session.php';

            return SessionConfig::fromArray($sessionConfig);
        },
    );

    // Client detection config
    $container->set(
        ClientConfig::class,
        static function (): ClientConfig {
            /** @var array{
             *     similarity_threshold: float,
             *     max_sessions_per_ip: int,
             *     ip_match_weight: float,
             *     user_agent_match_weight: float,
             *     attributes_match_weight: float,
             * } $clientConfig
             */
            $clientConfig = require __DIR__ . '/client.php';

            return ClientConfig::fromArray($clientConfig);
        },
    );

    // Client data factory
    $container->bind(ClientDataFactory::class, DefaultClientDataFactory::class);
    $container->set(
        DefaultClientDataFactory::class,
        static fn(): DefaultClientDataFactory => new DefaultClientDataFactory(),
    );

    // Client detector service
    $container->set(
        ClientDetector::class,
        static function (ContainerInterface $container): ClientDetector {
            /** @var SessionRepository $sessionRepository */
            $sessionRepository = $container->get(SessionRepository::class);

            /** @var ClientConfig $clientConfig */
            $clientConfig = $container->get(ClientConfig::class);

            return new ClientDetector(
                $sessionRepository,
                $clientConfig,
            );
        },
    );

    // Domain services
    $container->bind(HomeService::class, HomeService::class);
    $container->bind(SessionService::class, SessionService::class);

    // Repositories
    $container->bind(SessionRepository::class, SQLiteSessionRepository::class);

    // Application services and mappers
    $container->bind(HomeMapper::class, HomeMapper::class);

    // Set up session middleware
    $container->set(
        SessionMiddleware::class,
        static function (ContainerInterface $container): SessionMiddleware {
            /** @var SessionService $sessionService */
            $sessionService = $container->get(SessionService::class);

            /** @var LoggerInterface $logger */
            $logger = $container->get(LoggerInterface::class);

            /** @var SessionConfig $config */
            $config = $container->get(SessionConfig::class);

            /** @var ClientDataFactory $clientDataFactory */
            $clientDataFactory = $container->get(ClientDataFactory::class);

            /** @var JsonFieldAdapter $jsonAdapter */
            $jsonAdapter = $container->get(JsonFieldAdapter::class);

            return new SessionMiddleware(
                $sessionService,
                $logger,
                $config,
                $clientDataFactory,
                $jsonAdapter,
            );
        },
    );

    $container->set(
        Worker::class,
        static fn(): WorkerInterface => Worker::create(),
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
                $uploadFactory,
            );
        },
    );

    $container->set(
        SQLiteStorage::class,
        static function (): SQLiteStorage {
            $databasePath = __DIR__ . '/../db/app.sqlite';
            $databaseDir = dirname($databasePath);

            if (!is_dir($databaseDir)) {
                mkdir($databaseDir, 0o755, true);
            }

            return new SQLiteStorage($databasePath);
        },
    );

    $container->set(Container::class, static fn() => $container);
};
