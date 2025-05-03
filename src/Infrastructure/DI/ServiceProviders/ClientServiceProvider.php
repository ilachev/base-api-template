<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Application\Client\ClientConfig;
use App\Application\Client\ClientDetector;
use App\Application\Client\ClientDetectorInterface;
use App\Application\Client\DefaultSessionPayloadFactory;
use App\Application\Client\GeoLocationService;
use App\Application\Client\SessionPayloadFactory;
use App\Domain\Session\SessionRepository;
use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;

/**
 * @implements ServiceProvider<object>
 */
final readonly class ClientServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
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
                $clientConfig = require ProjectPath::getConfigPath('client.php');

                return ClientConfig::fromArray($clientConfig);
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
    }
}
