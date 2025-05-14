<?php

declare(strict_types=1);

namespace App\Domain\Session;

use App\Application\Client\GeoLocationData;
use ProtoPhpGen\Attributes\ProtoField;
use ProtoPhpGen\Attributes\ProtoMapping;

/**
 * DTO for storing client data in the session.
 */
#[ProtoMapping(class: 'App\Api\V1\SessionPayload')]
final readonly class SessionPayload
{
    /**
     * @param string $ip Client IP address
     * @param string|null $userAgent Client User-Agent
     * @param string|null $acceptLanguage Client Accept-Language
     * @param string|null $acceptEncoding Client Accept-Encoding
     * @param string|null $xForwardedFor Client X-Forwarded-For
     * @param string|null $referer Client Referer
     * @param string|null $origin Client Origin
     * @param string|null $secChUa Client Sec-CH-UA
     * @param string|null $secChUaPlatform Client Sec-CH-UA-Platform
     * @param string|null $secChUaMobile Client Sec-CH-UA-Mobile
     * @param string|null $dnt Do Not Track header
     * @param string|null $secFetchDest Sec-Fetch-Dest header
     * @param string|null $secFetchMode Sec-Fetch-Mode header
     * @param string|null $secFetchSite Sec-Fetch-Site header
     * @param GeoLocationData|null $geoLocation Geolocation data
     */
    public function __construct(
        #[ProtoField(name: 'ip')]
        public string $ip,
        #[ProtoField(name: 'user_agent')]
        public ?string $userAgent,
        #[ProtoField(name: 'accept_language')]
        public ?string $acceptLanguage,
        #[ProtoField(name: 'accept_encoding')]
        public ?string $acceptEncoding,
        #[ProtoField(name: 'x_forwarded_for')]
        public ?string $xForwardedFor,
        #[ProtoField(name: 'referer')]
        public ?string $referer,
        #[ProtoField(name: 'origin')]
        public ?string $origin,
        #[ProtoField(name: 'sec_ch_ua')]
        public ?string $secChUa,
        #[ProtoField(name: 'sec_ch_ua_platform')]
        public ?string $secChUaPlatform,
        #[ProtoField(name: 'sec_ch_ua_mobile')]
        public ?string $secChUaMobile,
        #[ProtoField(name: 'dnt')]
        public ?string $dnt,
        #[ProtoField(name: 'sec_fetch_dest')]
        public ?string $secFetchDest,
        #[ProtoField(name: 'sec_fetch_mode')]
        public ?string $secFetchMode,
        #[ProtoField(name: 'sec_fetch_site')]
        public ?string $secFetchSite,
        #[ProtoField(name: 'geo_location', type: 'json')]
        public ?GeoLocationData $geoLocation = null,
    ) {}

    /**
     * Определяет, является ли клиент браузером на основе заголовков.
     */
    public function isBrowser(): bool
    {
        // Проверка по заголовкам Sec-CH-UA и Sec-CH-UA-Mobile (Client Hints)
        if ($this->secChUa !== null || $this->secChUaMobile !== null) {
            return true;
        }

        // Проверка по Sec-Fetch-* заголовкам (характерны для браузеров)
        if ($this->secFetchDest !== null || $this->secFetchMode !== null || $this->secFetchSite !== null) {
            return true;
        }

        // Проверка по заголовку DNT (Do Not Track) - обычно присутствует только в браузерах
        if ($this->dnt !== null) {
            return true;
        }

        // Если нет User-Agent, и не сработали проверки выше, то скорее всего не браузер
        if ($this->userAgent === null) {
            return false;
        }

        // Типичные паттерны, указывающие на браузер (проверка по User-Agent)
        $browserPatterns = [
            'Mozilla/',
            'Chrome/',
            'Safari/',
            'Firefox/',
            'Edge/',
            'MSIE',
            'Trident/',
            'Opera',
            'OPR/',
        ];

        foreach ($browserPatterns as $pattern) {
            if (str_contains($this->userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Определяет тип устройства на основе заголовков.
     *
     * @return string Тип устройства (desktop, mobile, tablet, unknown)
     */
    public function getDeviceType(): string
    {
        // Константы для типа устройства
        $deviceDesktop = 'desktop';
        $deviceMobile = 'mobile';
        $deviceTablet = 'tablet';
        $deviceUnknown = 'unknown';

        // Проверка по заголовку Sec-CH-UA-Mobile (самый точный индикатор для новых браузеров)
        if ($this->secChUaMobile === '?1') {
            return $deviceMobile;
        }
        if ($this->secChUaMobile === '?0') {
            // Если не мобильный, проверяем, может это планшет
            if ($this->secChUaPlatform !== null
                && (str_contains($this->secChUaPlatform, 'iPad')
                 || str_contains($this->secChUaPlatform, 'Android'))) {
                return $deviceTablet;
            }

            return $deviceDesktop;
        }

        // Если нет Client Hints, то используем User-Agent
        if ($this->userAgent === null) {
            return $deviceUnknown;
        }

        // Паттерны для мобильных устройств
        $mobilePatterns = [
            'Mobile',
            'Android',
            'iPhone',
            'Windows Phone',
            'BlackBerry',
            'Opera Mini',
            'Opera Mobi',
            'webOS',
        ];

        // Паттерны для планшетов
        $tabletPatterns = [
            'iPad',
            'Tablet',
            'Android(?!.*Mobile)', // Android без Mobile
            'Silk',                // Amazon Kindle
        ];

        // Проверка на планшет
        foreach ($tabletPatterns as $pattern) {
            if (preg_match("/({$pattern})/i", $this->userAgent)) {
                return $deviceTablet;
            }
        }

        // Проверка на мобильное устройство
        foreach ($mobilePatterns as $pattern) {
            if (str_contains($this->userAgent, $pattern)) {
                return $deviceMobile;
            }
        }

        // Если ничего не подошло, скорее всего это десктоп
        return $deviceDesktop;
    }
}
