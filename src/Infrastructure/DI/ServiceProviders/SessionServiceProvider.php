<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionService;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Storage\Session\CachedSessionRepository;
use App\Infrastructure\Storage\Session\PostgreSQLSessionRepository;

/**
 * @implements ServiceProvider<object>
 */
final class SessionServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Session config
        $container->set(
            SessionConfig::class,
            static function (): SessionConfig {
                /** @var array{cookie_name: string, cookie_ttl: int, session_ttl: int, use_fingerprint: bool} $sessionConfig */
                $sessionConfig = require ProjectPath::getConfigPath('session.php');

                return SessionConfig::fromArray($sessionConfig);
            },
        );

        // PostgreSQL Session repository
        $container->bind(PostgreSQLSessionRepository::class, PostgreSQLSessionRepository::class);

        // Session repository with caching
        $container->set(
            SessionRepository::class,
            static function (Container $container): SessionRepository {
                /** @var PostgreSQLSessionRepository $baseRepository */
                $baseRepository = $container->get(PostgreSQLSessionRepository::class);

                /** @var CacheService $cacheService */
                $cacheService = $container->get(CacheService::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new CachedSessionRepository($baseRepository, $cacheService, $logger);
            },
        );

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
    }
}
