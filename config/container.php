<?php

declare(strict_types=1);

use App\Application\Client\ClientConfig;
use App\Application\Client\ClientDetector;
use App\Application\Client\ClientDetectorInterface;
use App\Application\Client\DefaultSessionPayloadFactory;
use App\Application\Client\GeoLocationConfig;
use App\Application\Client\GeoLocationService;
use App\Application\Client\SessionPayloadFactory;
use App\Application\Handlers\HandlerFactoryInterface;
use App\Application\Handlers\HomeHandler;
use App\Application\Http\JsonResponse;
use App\Application\Mappers\HomeMapper;
use App\Application\Middleware\ApiStatsMiddleware;
use App\Application\Middleware\SessionMiddleware;
use App\Application\Routing\RouteDefinition;
use App\Application\Routing\RouteDefinitionInterface;
use App\Application\Routing\RouterInterface;
use App\Domain\Home\HomeService;
use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionService;
use App\Domain\Stats\ApiStatRepository;
use App\Domain\Stats\ApiStatService;
use App\Infrastructure\Cache\CacheConfig;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\RoadRunnerCacheService;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ContainerHandlerFactory;
use App\Infrastructure\DI\DIContainer;
use App\Infrastructure\GeoLocation\IP2LocationGeoLocationService;
use App\Infrastructure\Hydrator\DefaultJsonFieldAdapter;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\HydratorInterface;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Logger\ReadableOutputLogger;
use App\Infrastructure\Logger\RoadRunnerLogger;
use App\Infrastructure\Routing\Router;
use App\Infrastructure\Storage\Migration\MigrationLoader;
use App\Infrastructure\Storage\Migration\MigrationRepository;
use App\Infrastructure\Storage\Migration\MigrationService;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Session\CachedSessionRepository;
use App\Infrastructure\Storage\Session\PostgreSQLSessionRepository;
use App\Infrastructure\Storage\Session\SQLiteSessionRepository;
use App\Infrastructure\Storage\SQLiteStorage;
use App\Infrastructure\Storage\Stats\PostgreSQLApiStatRepository;
use App\Infrastructure\Storage\Stats\SQLiteApiStatRepository;
use App\Infrastructure\Storage\StorageFactory;
use App\Infrastructure\Storage\StorageInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;

return static function (Container $container): void {
    $container->bind(Container::class, DIContainer::class);

    // Регистрация сервиса кеширования
    $container->bind(CacheService::class, RoadRunnerCacheService::class);

    $container->bind(ServerRequestFactoryInterface::class, Psr17Factory::class);
    $container->bind(StreamFactoryInterface::class, Psr17Factory::class);
    $container->bind(UploadedFileFactoryInterface::class, Psr17Factory::class);

    // Register ReadableOutputLogger and RoadRunnerLogger
    $container->bind(ReadableOutputLogger::class, ReadableOutputLogger::class);
    $container->bind(Logger::class, RoadRunnerLogger::class);
    // Storage configuration and factory
    $container->set(
        StorageFactory::class,
        static function (Container $container): StorageFactory {
            /** @var array{
             *     engine: string,
             *     sqlite?: array{database: string},
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
            $storageConfig = require __DIR__ . '/storage.php';

            /** @var Logger $logger */
            $logger = $container->get(Logger::class);

            return new StorageFactory($storageConfig, $logger);
        },
    );

    // Storage implementation based on configuration
    $container->set(
        StorageInterface::class,
        static function (Container $container): StorageInterface {
            /** @var StorageFactory $factory */
            $factory = $container->get(StorageFactory::class);

            return $factory->createStorage();
        },
    );

    // Query factory based on storage engine
    $container->set(
        QueryFactory::class,
        static function (Container $container): QueryFactory {
            /** @var StorageFactory $factory */
            $factory = $container->get(StorageFactory::class);

            return $factory->createQueryFactory();
        },
    );

    $container->bind(HandlerFactoryInterface::class, ContainerHandlerFactory::class);
    $container->set(
        ContainerHandlerFactory::class,
        static fn(Container $container): ContainerHandlerFactory => new ContainerHandlerFactory($container),
    );

    $container->bind(RouterInterface::class, Router::class);
    $container->set(
        RouteDefinitionInterface::class,
        static fn() => new RouteDefinition(__DIR__ . '/routes.php'),
    );

    // Then bind interface to it
    $container->bind(HydratorInterface::class, Hydrator::class);

    // Register JsonResponse
    $container->bind(JsonResponse::class, JsonResponse::class);

    // JSON field adapter
    $container->bind(JsonFieldAdapter::class, DefaultJsonFieldAdapter::class);
    $container->set(
        DefaultJsonFieldAdapter::class,
        static function (Container $container): DefaultJsonFieldAdapter {
            /** @var HydratorInterface $hydrator */
            $hydrator = $container->get(HydratorInterface::class);

            return new DefaultJsonFieldAdapter($hydrator);
        },
    );

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

    // Cache config
    $container->set(
        CacheConfig::class,
        static function (): CacheConfig {
            /** @var array{
             *     engine: string,
             *     address: string,
             *     default_prefix: string,
             *     default_ttl: int,
             *     serializer: int,
             * } $cacheConfig
             */
            $cacheConfig = require __DIR__ . '/cache.php';

            return CacheConfig::fromArray($cacheConfig);
        },
    );

    // GeoLocation config
    $container->set(
        GeoLocationConfig::class,
        static function (): GeoLocationConfig {
            /** @var array{
             *     db_path: string,
             *     download_token: string,
             *     download_url: string,
             *     database_code: string,
             *     cache_ttl: int,
             * } $geoConfig
             */
            $geoConfig = require __DIR__ . '/geolocation.php';

            return GeoLocationConfig::fromArray($geoConfig);
        },
    );

    // GeoLocation service
    $container->bind(GeoLocationService::class, IP2LocationGeoLocationService::class);

    // IP2Location implementation
    $container->set(
        IP2LocationGeoLocationService::class,
        static function (Container $container): IP2LocationGeoLocationService {
            /** @var GeoLocationConfig $config */
            $config = $container->get(GeoLocationConfig::class);

            /** @var CacheService $cache */
            $cache = $container->get(CacheService::class);

            /** @var Logger $logger */
            $logger = $container->get(Logger::class);

            return new IP2LocationGeoLocationService($config, $cache, $logger);
        },
    );

    // Session payload factory
    $container->bind(SessionPayloadFactory::class, DefaultSessionPayloadFactory::class);
    $container->set(
        DefaultSessionPayloadFactory::class,
        static function (Container $container): DefaultSessionPayloadFactory {
            /** @var GeoLocationService $geoLocationService */
            $geoLocationService = $container->get(GeoLocationService::class);

            return new DefaultSessionPayloadFactory($geoLocationService);
        },
    );

    // Client detector service
    $container->set(
        ClientDetector::class,
        static function (Container $container): ClientDetector {
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

    // Bind interface to implementation
    $container->bind(ClientDetectorInterface::class, ClientDetector::class);

    // Domain services
    $container->bind(HomeService::class, HomeService::class);

    // Session service
    $container->set(
        SessionService::class,
        static function (Container $container): SessionService {
            /** @var SessionRepository $repository */
            $repository = $container->get(SessionRepository::class);

            /** @var Logger $logger */
            $logger = $container->get(Logger::class);

            return new SessionService($repository, $logger);
        },
    );

    $container->bind(ApiStatService::class, ApiStatService::class);

    // Repository bindings based on storage engine
    $container->set(
        SessionRepository::class,
        static function (Container $container): SessionRepository {
            /** @var array{engine: string} $storageConfig */
            $storageConfig = require __DIR__ . '/storage.php';
            $engine = $storageConfig['engine'];

            // Choose the appropriate repository implementation based on the storage engine
            $baseRepositoryClass = match ($engine) {
                'pgsql' => PostgreSQLSessionRepository::class,
                default => 'App\Infrastructure\Storage\Session\SQLiteSessionRepository',
            };

            /** @var SessionRepository $baseRepository */
            $baseRepository = $container->get($baseRepositoryClass);

            /** @var CacheService $cacheService */
            $cacheService = $container->get(CacheService::class);

            /** @var Logger $logger */
            $logger = $container->get(Logger::class);

            return new CachedSessionRepository($baseRepository, $cacheService, $logger);
        },
    );

    // Register both SQLite and PostgreSQL implementations
    $container->bind(SQLiteSessionRepository::class, SQLiteSessionRepository::class);
    $container->bind(PostgreSQLSessionRepository::class, PostgreSQLSessionRepository::class);

    // ApiStat Repository binding based on storage engine
    $container->set(
        ApiStatRepository::class,
        static function (Container $container): ApiStatRepository {
            /** @var array{engine: string} $storageConfig */
            $storageConfig = require __DIR__ . '/storage.php';
            $engine = $storageConfig['engine'];

            // Choose the appropriate repository implementation based on the storage engine
            $repositoryClass = match ($engine) {
                'pgsql' => PostgreSQLApiStatRepository::class,
                default => 'App\Infrastructure\Storage\Stats\SQLiteApiStatRepository',
            };

            return $container->get($repositoryClass);
        },
    );

    // Register both SQLite and PostgreSQL implementations
    $container->bind(SQLiteApiStatRepository::class, SQLiteApiStatRepository::class);
    $container->bind(PostgreSQLApiStatRepository::class, PostgreSQLApiStatRepository::class);

    // Migration services
    $container->set(
        MigrationLoader::class,
        static function (Container $container): MigrationLoader {
            /** @var array{
             *     engine: string,
             *     sqlite?: array{migrations_path: string},
             *     pgsql?: array{migrations_path: string}
             * } $storageConfig
             */
            $storageConfig = require __DIR__ . '/storage.php';
            $engine = $storageConfig['engine'];

            // Get migrations path based on active engine
            $migrationsPath = $storageConfig[$engine]['migrations_path'] ?? '';

            /** @var Logger $logger */
            $logger = $container->get(Logger::class);

            return new MigrationLoader($migrationsPath, $logger);
        },
    );

    $container->set(
        MigrationRepository::class,
        static function (Container $container): MigrationRepository {
            /** @var StorageInterface $storage */
            $storage = $container->get(StorageInterface::class);

            return new MigrationRepository($storage);
        },
    );

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

    // Application services and mappers
    $container->set(
        HomeMapper::class,
        static function (Container $container): HomeMapper {
            /** @var HydratorInterface $hydrator */
            $hydrator = $container->get(HydratorInterface::class);

            return new HomeMapper($hydrator);
        },
    );

    // Register HomeHandler
    $container->set(
        HomeHandler::class,
        static function (Container $container): HomeHandler {
            /** @var HomeService $homeService */
            $homeService = $container->get(HomeService::class);

            /** @var HomeMapper $homeMapper */
            $homeMapper = $container->get(HomeMapper::class);

            /** @var JsonResponse $jsonResponse */
            $jsonResponse = $container->get(JsonResponse::class);

            return new HomeHandler($homeService, $homeMapper, $jsonResponse);
        },
    );

    // Set up session middleware
    $container->set(
        SessionMiddleware::class,
        static function (Container $container): SessionMiddleware {
            /** @var SessionService $sessionService */
            $sessionService = $container->get(SessionService::class);

            /** @var Logger $logger */
            $logger = $container->get(Logger::class);

            /** @var SessionConfig $config */
            $config = $container->get(SessionConfig::class);

            /** @var SessionPayloadFactory $sessionPayloadFactory */
            $sessionPayloadFactory = $container->get(SessionPayloadFactory::class);

            /** @var JsonFieldAdapter $jsonAdapter */
            $jsonAdapter = $container->get(JsonFieldAdapter::class);

            /** @var ClientDetectorInterface $clientDetector */
            $clientDetector = $container->get(ClientDetectorInterface::class);

            return new SessionMiddleware(
                $sessionService,
                $logger,
                $config,
                $sessionPayloadFactory,
                $jsonAdapter,
                $clientDetector,
            );
        },
    );

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

    // SQLiteStorage is now created by the StorageFactory

    // Set up API stats middleware
    $container->set(
        ApiStatsMiddleware::class,
        static function (Container $container): ApiStatsMiddleware {
            /** @var ApiStatService $apiStatService */
            $apiStatService = $container->get(ApiStatService::class);

            /** @var SessionService $sessionService */
            $sessionService = $container->get(SessionService::class);

            /** @var Logger $logger */
            $logger = $container->get(Logger::class);

            return new ApiStatsMiddleware($apiStatService, $sessionService, $logger);
        },
    );

    $container->set(Container::class, static fn() => $container);
};
