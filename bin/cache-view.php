#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Cache\CacheConfig;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\DIContainer;
use Psr\Log\LoggerInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;

// Обработка аргументов командной строки
$specificKey = $argv[1] ?? null;

// Инициализация контейнера
/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new DIContainer();
$containerConfig($container);

// Получаем кэш-сервис
/** @var CacheService $cacheService */
$cacheService = $container->get(CacheService::class);

/** @var CacheConfig $config */
$config = $container->get(CacheConfig::class);

/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

// Проверяем доступность кеша
if (!$cacheService->isAvailable()) {
    echo "\033[31mКеш-сервис недоступен\033[0m\n";
    exit(1);
}

// Подключаемся к RPC напрямую для дампа всех данных
try {
    $address = !empty($config->address) ? $config->address : 'tcp://127.0.0.1:6001';
    $rpc = RPC::create($address);
    $factory = new Factory($rpc);
    $engine = $config->engine === '' ? 'local-memory' : $config->engine;

    $storage = $factory->select($engine);

    // Информация о движке кеша
    echo "Информация о кеш-сервисе:\n";
    echo '- Название движка: ' . $storage->getName() . "\n";
    echo '- Доступность: ' . ($cacheService->isAvailable() ? "\033[32mДоступен\033[0m" : "\033[31mНедоступен\033[0m") . "\n";
    echo '- Адрес: ' . $address . "\n";
    echo '- Префикс ключей: ' . $config->defaultPrefix . "\n";
    echo '- TTL по умолчанию: ' . $config->defaultTtl . " сек.\n\n";

    // Обрабатываем конкретный ключ, если указан
    if ($specificKey !== null) {
        echo "Поиск ключа: \033[36m{$specificKey}\033[0m\n";

        // Если ключ указан без префикса, добавляем префикс по умолчанию
        $fullKey = $specificKey;
        if (strpos($specificKey, $config->defaultPrefix) !== 0) {
            $fullKey = $config->defaultPrefix . $specificKey;
            echo "Используем полный ключ с префиксом: \033[36m{$fullKey}\033[0m\n";
        }

        if ($cacheService->has($specificKey)) {
            $value = $cacheService->get($specificKey);
            outputKeyValue($specificKey, $value, $storage);
        } elseif ($cacheService->has($fullKey)) {
            $value = $cacheService->get($fullKey);
            outputKeyValue($fullKey, $value, $storage);
        } else {
            echo "\033[33mКлюч не найден в кеше\033[0m\n";
        }

        exit(0);
    }

    // Если конкретный ключ не указан, пытаемся вывести все ключи
    echo "Поиск всех ключей в кеше...\n";

    // В RoadRunner KV нет метода для получения всех ключей напрямую
    // Используем альтернативный подход - пытаемся проверить наличие ключей по шаблонам

    // Проверяем ключи по определенным шаблонам
    $prefixes = ['session:', 'geo:', 'cache:', $config->defaultPrefix];

    $found = false;
    $foundCount = 0;

    foreach ($prefixes as $prefix) {
        // Для каждого префикса создаем тестовые ключи
        $testKeys = [];
        for ($i = 0; $i < 1000; ++$i) {
            $testKey = $prefix . str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $testKeys[] = $testKey;

            // Проверяем блоками по 50 ключей для оптимизации
            if (count($testKeys) >= 50) {
                $results = $storage->getMultiple($testKeys, null);
                foreach ($results as $key => $value) {
                    if ($value !== null) {
                        $found = true;
                        ++$foundCount;
                        outputKeyValue($key, $value, $storage);
                    }
                }
                $testKeys = [];
            }
        }
    }

    // Проверка ключей из логов
    $keys = findKeysInLogs($config->defaultPrefix);
    foreach ($keys as $key) {
        if (!$cacheService->has($key)) {
            continue;
        }

        $value = $cacheService->get($key);
        if ($value !== null) {
            $found = true;
            ++$foundCount;
            outputKeyValue($key, $value, $storage);
        }
    }

    if (!$found) {
        echo "\033[33mНе найдено ни одного ключа в кеше\033[0m\n";
        echo "Примечание: RoadRunner KV не предоставляет метод для получения списка всех ключей,\n";
        echo "поэтому скрипт проверяет только наиболее вероятные ключи.\n";
    } else {
        echo "\nНайдено ключей: \033[32m{$foundCount}\033[0m\n";
    }

    echo "\nДля просмотра конкретного ключа выполните команду:\n";
    echo "php bin/cache-view.php название_ключа\n";

} catch (Throwable $e) {
    echo "\033[31mОшибка при обращении к кеш-сервису: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

/**
 * Выводит информацию о ключе и его значении в кеше.
 */
function outputKeyValue(string $key, mixed $value, object $storage): void
{
    echo "----------------------------------------\n";
    echo "Ключ: \033[32m{$key}\033[0m\n";

    echo 'Тип: ' . gettype($value) . "\n";

    echo 'Значение: ';
    if (is_string($value)) {
        // Для строк проверяем, является ли это JSON и выводим красиво, если да
        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "JSON данные\n";
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } elseif (strlen($value) > 100) {
            // Для длинных строк выводим только начало
            echo substr($value, 0, 100) . "...\n";
            echo 'Размер: ' . strlen($value) . " байт\n";
        } else {
            echo $value . "\n";
        }
    } elseif (is_array($value)) {
        echo "\n" . json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo var_export($value, true) . "\n";
    }

    // Пытаемся получить TTL ключа
    try {
        $ttl = $storage->getTtl($key);
        if ($ttl) {
            $now = new DateTimeImmutable();
            $expireAt = $ttl;
            $diffSeconds = $expireAt->getTimestamp() - $now->getTimestamp();

            echo 'Истекает: ' . $ttl->format('Y-m-d H:i:s');
            echo ' (через ' . formatTimeRemaining($diffSeconds) . ")\n";
        } else {
            echo "TTL: бессрочно\n";
        }
    } catch (Throwable $e) {
        echo 'TTL: ошибка при получении времени жизни - ' . $e->getMessage() . "\n";
    }

    echo "\n";
}

/**
 * Форматирует оставшееся время в удобный формат.
 */
function formatTimeRemaining(int $seconds): string
{
    if ($seconds < 0) {
        return 'истёк';
    }

    if ($seconds < 60) {
        return "{$seconds} сек.";
    }

    if ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return "{$minutes} мин. {$remainingSeconds} сек.";
    }

    $hours = floor($seconds / 3600);
    $remainingSeconds = $seconds % 3600;
    $minutes = floor($remainingSeconds / 60);
    $remainingSeconds %= 60;

    return "{$hours} ч. {$minutes} мин. {$remainingSeconds} сек.";
}

/**
 * Находит ключи кеша в файлах логов.
 *
 * @return array<string>
 */
function findKeysInLogs(string $prefix): array
{
    $keys = [];
    $logFiles = glob(__DIR__ . '/../var/log/*.log');

    foreach ($logFiles as $logFile) {
        if (file_exists($logFile) && is_readable($logFile)) {
            $content = file_get_contents($logFile);
            if ($content === false) {
                continue;
            }

            preg_match_all('/"key":"([^"]+)"/', $content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $key) {
                    // Фильтруем ключи с нужным префиксом
                    if (strpos($key, $prefix) === 0) {
                        $keys[$key] = true;
                    }
                }
            }
        }
    }

    return array_keys($keys);
}
