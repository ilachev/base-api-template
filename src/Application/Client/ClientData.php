<?php

declare(strict_types=1);

namespace App\Application\Client;

final readonly class ClientData
{
    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_TABLET = 'tablet';
    public const DEVICE_UNKNOWN = 'unknown';

    /**
     * @param array<string, string> $extraAttributes Дополнительные атрибуты клиента
     * @param array<string, string> $headers Все HTTP заголовки запроса
     */
    public function __construct(
        public string $ip,
        public ?string $userAgent,
        public ?string $acceptLanguage,
        public ?string $acceptEncoding,
        public ?string $xForwardedFor,
        public ?string $referer,
        public ?string $origin,
        public ?string $secChUa,
        public ?string $secChUaPlatform,
        public ?string $secChUaMobile,
        public ?string $dnt,
        public ?string $secFetchDest,
        public ?string $secFetchMode,
        public ?string $secFetchSite,
        public array $extraAttributes = [],
        public array $headers = [],
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

        // Дополнительная проверка по наличию заголовка Accept с HTML-mime типами
        if (isset($this->extraAttributes['Accept'])
            && (str_contains($this->extraAttributes['Accept'], 'text/html')
             || str_contains($this->extraAttributes['Accept'], 'application/xhtml+xml'))) {
            return true;
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
        // Проверка по заголовку Sec-CH-UA-Mobile (самый точный индикатор для новых браузеров)
        if ($this->secChUaMobile === '?1') {
            return self::DEVICE_MOBILE;
        }
        if ($this->secChUaMobile === '?0') {
            // Если не мобильный, проверяем, может это планшет
            if ($this->secChUaPlatform !== null
                && (str_contains($this->secChUaPlatform, 'iPad')
                 || str_contains($this->secChUaPlatform, 'Android'))) {
                return self::DEVICE_TABLET;
            }

            return self::DEVICE_DESKTOP;
        }

        // Если нет Client Hints, то используем User-Agent
        if ($this->userAgent === null) {
            return self::DEVICE_UNKNOWN;
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
                return self::DEVICE_TABLET;
            }
        }

        // Проверка на мобильное устройство
        foreach ($mobilePatterns as $pattern) {
            if (str_contains($this->userAgent, $pattern)) {
                return self::DEVICE_MOBILE;
            }
        }

        // Если ничего не подошло, скорее всего это десктоп
        return self::DEVICE_DESKTOP;
    }
}
