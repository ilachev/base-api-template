<?php

declare(strict_types=1);

namespace App\Application\Client;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Фабрика для создания ClientData из HTTP запроса.
 */
final readonly class DefaultClientDataFactory implements ClientDataFactory
{
    public function __construct(
        private GeoLocationService $geoLocationService,
    ) {}

    /**
     * Создает объект ClientData из HTTP запроса.
     */
    public function createFromRequest(ServerRequestInterface $request): ClientData
    {
        $serverParams = $request->getServerParams();
        $ip = isset($serverParams['REMOTE_ADDR']) && \is_string($serverParams['REMOTE_ADDR'])
            ? $serverParams['REMOTE_ADDR']
            : 'unknown';

        // Основные заголовки
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;
        $acceptLanguage = $request->getHeaderLine('Accept-Language') ?: null;
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding') ?: null;
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For') ?: null;

        // Дополнительные заголовки для идентификации
        $referer = $request->getHeaderLine('Referer') ?: null;
        $origin = $request->getHeaderLine('Origin') ?: null;

        // Современные заголовки браузеров для определения клиента (Client Hints)
        $secChUa = $request->getHeaderLine('Sec-CH-UA') ?: null;
        $secChUaPlatform = $request->getHeaderLine('Sec-CH-UA-Platform') ?: null;
        $secChUaMobile = $request->getHeaderLine('Sec-CH-UA-Mobile') ?: null;

        // Заголовки, связанные с приватностью и безопасностью
        $dnt = $request->getHeaderLine('DNT') ?: null;
        $secFetchDest = $request->getHeaderLine('Sec-Fetch-Dest') ?: null;
        $secFetchMode = $request->getHeaderLine('Sec-Fetch-Mode') ?: null;
        $secFetchSite = $request->getHeaderLine('Sec-Fetch-Site') ?: null;

        // Подготавливаем дополнительные атрибуты для более точной идентификации
        /** @var array<string, string> $extraAttributes */
        $extraAttributes = [];

        // Заголовки для extraAttributes - дополнительная информация, не вошедшая в основные поля
        $additionalHeaders = [
            'Accept' => $request->getHeaderLine('Accept'),
            'Connection' => $request->getHeaderLine('Connection'),
            'Cache-Control' => $request->getHeaderLine('Cache-Control'),
            'Pragma' => $request->getHeaderLine('Pragma'),
            'Upgrade-Insecure-Requests' => $request->getHeaderLine('Upgrade-Insecure-Requests'),
            'X-Requested-With' => $request->getHeaderLine('X-Requested-With'),
            'Save-Data' => $request->getHeaderLine('Save-Data'),
            'Via' => $request->getHeaderLine('Via'),
            'X-Real-IP' => $request->getHeaderLine('X-Real-IP'),
            'CF-Connecting-IP' => $request->getHeaderLine('CF-Connecting-IP'),
            'CF-IPCountry' => $request->getHeaderLine('CF-IPCountry'),
            'X-Forwarded-Proto' => $request->getHeaderLine('X-Forwarded-Proto'),
        ];

        foreach ($additionalHeaders as $name => $value) {
            if (!empty($value)) {
                $extraAttributes[$name] = $value;
            }
        }

        // Сохраняем все заголовки запроса для полного профиля клиента
        /** @var array<string, string> $allHeaders */
        $allHeaders = [];
        /**
         * @var string $name
         * @var array<int, string> $values
         */
        foreach ($request->getHeaders() as $name => $values) {
            $allHeaders[$name] = implode(', ', $values);
        }

        // Получаем геолокацию по IP
        $geoLocation = $this->geoLocationService->getLocationByIp($ip);

        return new ClientData(
            ip: $ip,
            userAgent: $userAgent,
            acceptLanguage: $acceptLanguage,
            acceptEncoding: $acceptEncoding,
            xForwardedFor: $xForwardedFor,
            referer: $referer,
            origin: $origin,
            secChUa: $secChUa,
            secChUaPlatform: $secChUaPlatform,
            secChUaMobile: $secChUaMobile,
            dnt: $dnt,
            secFetchDest: $secFetchDest,
            secFetchMode: $secFetchMode,
            secFetchSite: $secFetchSite,
            extraAttributes: $extraAttributes,
            headers: $allHeaders,
            geoLocation: $geoLocation,
        );
    }

    /**
     * Создает объект ClientData с минимальными данными.
     */
    public function createDefault(): ClientData
    {
        return new ClientData(
            ip: 'unknown',
            userAgent: null,
            acceptLanguage: null,
            acceptEncoding: null,
            xForwardedFor: null,
            referer: null,
            origin: null,
            secChUa: null,
            secChUaPlatform: null,
            secChUaMobile: null,
            dnt: null,
            secFetchDest: null,
            secFetchMode: null,
            secFetchSite: null,
            extraAttributes: [],
            headers: [],
            geoLocation: null,
        );
    }
}
