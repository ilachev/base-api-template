<?php

declare(strict_types=1);

namespace App\Infrastructure\GeoLocation;

use App\Application\Client\GeoLocationConfig;
use App\Application\Client\GeoLocationData;
use App\Application\Client\GeoLocationService;
use App\Infrastructure\Cache\CacheService;
use IP2Location\Database;
use Psr\Log\LoggerInterface;

/**
 * Реализация сервиса геолокации с использованием официальной библиотеки IP2Location.
 */
final readonly class VendorGeoLocationService implements GeoLocationService
{
    private Database $db;

    public function __construct(
        private GeoLocationConfig $config,
        private CacheService $cache,
        private LoggerInterface $logger,
    ) {
        $this->db = new Database($this->config->dbPath, Database::FILE_IO);
    }

    /**
     * Получает информацию о геолокации по IP-адресу.
     *
     * @param string $ip IP-адрес для определения геолокации
     * @return GeoLocationData|null Данные о геолокации или null, если не удалось определить
     */
    public function getLocationByIp(string $ip): ?GeoLocationData
    {
        // Пропускаем локальные и приватные IP-адреса
        if ($this->isLocalIp($ip) || $ip === 'unknown') {
            return null;
        }

        // Проверяем кеш
        $cacheKey = "geo_ip:{$ip}";
        if ($this->cache->isAvailable() && $this->cache->has($cacheKey)) {
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData instanceof GeoLocationData) {
                return $cachedData;
            }
        }

        try {
            // Получаем данные из библиотеки IP2Location
            /** @var array<string, string|float|null> $record */
            $record = $this->db->lookup($ip, Database::ALL);

            // Проверяем, что получены валидные данные
            if (!isset($record['countryCode'], $record['countryName'])
                || $record['countryCode'] === '-'
                || $record['countryName'] === '-'
                || empty($record['countryName'])
            ) {
                $this->logger->debug('No valid geolocation data found for IP', ['ip' => $ip]);

                return null;
            }

            try {
                // Преобразуем null в пустые строки для всех строковых полей
                $geoData = new GeoLocationData(
                    country: (string) $record['countryName'],
                    countryCode: (string) $record['countryCode'],
                    region: isset($record['regionName']) ? (string) $record['regionName'] : '',
                    city: isset($record['cityName']) ? (string) $record['cityName'] : '',
                    zip: isset($record['zipCode']) ? (string) $record['zipCode'] : '',
                    lat: isset($record['latitude']) ? (float) $record['latitude'] : 0.0,
                    lon: isset($record['longitude']) ? (float) $record['longitude'] : 0.0,
                    timezone: isset($record['timeZone']) ? (string) $record['timeZone'] : '',
                );

                // Кешируем данные
                if ($this->cache->isAvailable()) {
                    $this->cache->set($cacheKey, $geoData, $this->config->cacheTtl);
                }

                return $geoData;
            } catch (\Throwable $e) {
                $this->logger->error('Error creating GeoLocationData object', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                    'record' => $record,
                ]);

                return null;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error getting geolocation data', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Проверяет, является ли IP-адрес локальным или приватным.
     *
     * @param string $ip IP-адрес для проверки
     * @return bool true, если IP локальный или приватный
     */
    private function isLocalIp(string $ip): bool
    {
        // Проверка на localhost
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        // Проверка на приватные диапазоны IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $longIp = ip2long($ip);
            if ($longIp === false) {
                return true; // Невалидный IP считаем локальным
            }

            // Проверка на приватные диапазоны
            // 10.0.0.0/8
            if (($longIp & 0xFF000000) === 0x0A000000) {
                return true;
            }
            // 172.16.0.0/12
            if (($longIp & 0xFFF00000) === 0xAC100000) {
                return true;
            }
            // 192.168.0.0/16
            if (($longIp & 0xFFFF0000) === 0xC0A80000) {
                return true;
            }
        }

        // Проверка на приватные IPv6 (упрощенно)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // fc00::/7 - Unique Local Addresses
            if (strpos($ip, 'fc') === 0 || strpos($ip, 'fd') === 0) {
                return true;
            }
            // fe80::/10 - Link-Local Addresses
            if (strpos($ip, 'fe8') === 0 || strpos($ip, 'fe9') === 0
                || strpos($ip, 'fea') === 0 || strpos($ip, 'feb') === 0) {
                return true;
            }
        }

        return false;
    }
}
