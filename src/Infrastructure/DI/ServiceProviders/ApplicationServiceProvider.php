<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Application\Client\ClientDetector;
use App\Application\Client\SessionPayloadFactory;
use App\Application\Handlers\HomeHandler;
use App\Application\Http\JsonResponse;
use App\Application\Mappers\DataTransferObjectMapper;
use App\Application\Mappers\HomeMapper;
use App\Application\Middleware\SessionMiddleware;
use App\Domain\Home\HomeService;
use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionService;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Hydrator\DefaultJsonFieldAdapter;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Hydrator\ReflectionHydrator;
use App\Infrastructure\Logger\Logger;

/**
 * @implements ServiceProvider<object>
 */
final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // JsonResponse
        $container->bind(JsonResponse::class, JsonResponse::class);

        // Hydrator
        $container->bind(Hydrator::class, ReflectionHydrator::class);

        // JSON field adapter
        $container->bind(JsonFieldAdapter::class, DefaultJsonFieldAdapter::class);
        $container->set(
            DefaultJsonFieldAdapter::class,
            static function (Container $container): DefaultJsonFieldAdapter {
                /** @var Hydrator $hydrator */
                $hydrator = $container->get(Hydrator::class);

                return new DefaultJsonFieldAdapter($hydrator);
            },
        );

        // Home service
        $container->bind(HomeService::class, HomeService::class);

        // DataTransferObjectMapper
        $container->set(
            DataTransferObjectMapper::class,
            static function (Container $container): DataTransferObjectMapper {
                /** @var Hydrator $hydrator */
                $hydrator = $container->get(Hydrator::class);

                return new DataTransferObjectMapper($hydrator);
            },
        );

        // Home mapper
        $container->set(
            HomeMapper::class,
            static function (Container $container): HomeMapper {
                /** @var DataTransferObjectMapper $dtoMapper */
                $dtoMapper = $container->get(DataTransferObjectMapper::class);

                return new HomeMapper($dtoMapper);
            },
        );

        // Home handler
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

        // Session middleware
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

                /** @var ClientDetector $clientDetector */
                $clientDetector = $container->get(ClientDetector::class);

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
    }
}
