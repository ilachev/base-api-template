<?php

declare(strict_types=1);

namespace Tests\Integration\GeoLocation;

use App\Application\Client\GeoLocationConfig;
use App\Application\Client\GeoLocationService;
use Tests\Integration\IntegrationTestCase;

final class GeoLocationIntegrationTest extends IntegrationTestCase
{
    private GeoLocationService $geoLocationService;

    private GeoLocationConfig $geoLocationConfig;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GeoLocationService $geoLocationService */
        $geoLocationService = $this->container->get(GeoLocationService::class);
        $this->geoLocationService = $geoLocationService;

        /** @var GeoLocationConfig $geoLocationConfig */
        $geoLocationConfig = $this->container->get(GeoLocationConfig::class);
        $this->geoLocationConfig = $geoLocationConfig;

        // Проверяем наличие базы данных
        if (!file_exists($this->geoLocationConfig->dbPath)) {
            self::markTestSkipped(
                "База данных геолокации не найдена по пути: {$this->geoLocationConfig->dbPath}",
            );
        }
    }

    /**
     * Проверяет, что сервис геолокации корректно определяет местоположение по IP-адресу.
     */
    public function testGeoLocationServiceReturnsLocationData(): void
    {
        // Тестовые IP-адреса
        $testIps = [
            '8.8.8.8',        // Google DNS (США)
            '77.88.55.77',    // Яндекс (Россия)
            '195.82.146.214', // Mail.ru (Россия)
        ];

        foreach ($testIps as $ip) {
            $location = $this->geoLocationService->getLocationByIp($ip);

            // Проверяем, что получили данные о местоположении
            self::assertNotNull($location, "Не удалось определить геолокацию для IP: {$ip}");

            // Проверяем, что основные поля заполнены
            self::assertNotEmpty($location->country, "Страна не определена для IP: {$ip}");
            self::assertNotEmpty($location->countryCode, "Код страны не определен для IP: {$ip}");

            // Координаты должны быть в разумных пределах
            self::assertGreaterThanOrEqual(-90, $location->lat, "Широта должна быть >= -90 для IP: {$ip}");
            self::assertLessThanOrEqual(90, $location->lat, "Широта должна быть <= 90 для IP: {$ip}");
            self::assertGreaterThanOrEqual(-180, $location->lon, "Долгота должна быть >= -180 для IP: {$ip}");
            self::assertLessThanOrEqual(180, $location->lon, "Долгота должна быть <= 180 для IP: {$ip}");
        }
    }

    /**
     * Проверяет, что сервис геолокации возвращает null для локальных IP-адресов.
     */
    public function testGeoLocationServiceReturnsNullForLocalIps(): void
    {
        $localIps = [
            '127.0.0.1',
            '192.168.1.1',
            '10.0.0.1',
            '172.16.0.1',
            '::1',
            'unknown',
        ];

        foreach ($localIps as $ip) {
            $location = $this->geoLocationService->getLocationByIp($ip);
            self::assertNull($location, "Локальный IP {$ip} должен возвращать null");
        }
    }

    /**
     * Проверяет, что сервис геолокации корректно определяет страну для известных IP-адресов.
     */
    public function testGeoLocationServiceReturnsCorrectCountry(): void
    {
        // Тестовые IP-адреса с ожидаемыми странами
        $testCases = [
            ['8.8.8.8', 'US'],        // Google DNS (США)
            ['77.88.55.77', 'RU'],    // Яндекс (Россия)
            ['104.16.85.20', 'US'],   // Cloudflare (США)
        ];

        foreach ($testCases as [$ip, $expectedCountryCode]) {
            $location = $this->geoLocationService->getLocationByIp($ip);

            self::assertNotNull($location, "Не удалось определить геолокацию для IP: {$ip}");
            self::assertEquals(
                $expectedCountryCode,
                $location->countryCode,
                "Неверно определена страна для IP: {$ip}",
            );
        }
    }

    /**
     * Проверяет, что сервис геолокации корректно обрабатывает невалидные IP-адреса.
     */
    public function testGeoLocationServiceHandlesInvalidIps(): void
    {
        $invalidIps = [
            '999.999.999.999',
            'not-an-ip',
            '8.8.8',
            '',
        ];

        foreach ($invalidIps as $ip) {
            // Не должно быть исключений при обработке невалидных IP
            $location = $this->geoLocationService->getLocationByIp($ip);
            self::assertNull($location, "Невалидный IP {$ip} должен возвращать null");
        }
    }
}
