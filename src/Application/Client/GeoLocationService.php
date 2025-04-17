<?php

declare(strict_types=1);

namespace App\Application\Client;

/**
 * Интерфейс сервиса геолокации по IP.
 */
interface GeoLocationService
{
    /**
     * Получает информацию о геолокации по IP-адресу.
     *
     * @param string $ip IP-адрес для определения геолокации
     * @return GeoLocationData|null Данные о геолокации или null, если не удалось определить
     */
    public function getLocationByIp(string $ip): ?GeoLocationData;
}
