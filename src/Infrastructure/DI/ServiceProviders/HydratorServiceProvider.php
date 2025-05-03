<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\LimitedReflectionCache;
use App\Infrastructure\Hydrator\ProtobufHydration;
use App\Infrastructure\Hydrator\ReflectionCache;
use App\Infrastructure\Hydrator\ReflectionHydrator;
use App\Infrastructure\Hydrator\SetterProtobufHydration;

/**
 * @implements ServiceProvider<object>
 */
final readonly class HydratorServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Регистрация основного интерфейса
        $container->bind(Hydrator::class, ReflectionHydrator::class);

        // Кэш рефлексии
        $container->bind(ReflectionCache::class, LimitedReflectionCache::class);
        $container->set(
            LimitedReflectionCache::class,
            static function () {
                return new LimitedReflectionCache(100); // Размер кэша настраивается здесь
            },
        );

        // Адаптер Protobuf
        $container->bind(ProtobufHydration::class, SetterProtobufHydration::class);

        // Основной гидратор
        $container->set(
            ReflectionHydrator::class,
            static function (Container $container): ReflectionHydrator {
                /** @var ReflectionCache $cache */
                $cache = $container->get(ReflectionCache::class);

                /** @var ProtobufHydration $protobufHydration */
                $protobufHydration = $container->get(ProtobufHydration::class);

                return new ReflectionHydrator($cache, $protobufHydration);
            },
        );
    }
}
