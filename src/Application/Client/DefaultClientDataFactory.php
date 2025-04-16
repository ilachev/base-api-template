<?php

declare(strict_types=1);

namespace App\Application\Client;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Фабрика для создания ClientData из HTTP запроса.
 */
final readonly class DefaultClientDataFactory implements ClientDataFactory
{
    /**
     * Создает объект ClientData из HTTP запроса.
     */
    public function createFromRequest(ServerRequestInterface $request): ClientData
    {
        $serverParams = $request->getServerParams();
        $ip = isset($serverParams['REMOTE_ADDR']) && \is_string($serverParams['REMOTE_ADDR'])
            ? $serverParams['REMOTE_ADDR']
            : 'unknown';

        $userAgent = $request->getHeaderLine('User-Agent') ?: null;
        $acceptLanguage = $request->getHeaderLine('Accept-Language') ?: null;
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding') ?: null;
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For') ?: null;

        // Подготавливаем дополнительные атрибуты для более точной идентификации
        /** @var array<string, string> $extraAttributes */
        $extraAttributes = [];

        // Если есть другие заголовки, которые могут помочь идентифицировать клиента,
        // добавляем их в extraAttributes
        $additionalHeaders = [
            'Accept' => $request->getHeaderLine('Accept'),
            'Connection' => $request->getHeaderLine('Connection'),
            'Cache-Control' => $request->getHeaderLine('Cache-Control'),
        ];

        foreach ($additionalHeaders as $name => $value) {
            if (!empty($value)) {
                $extraAttributes[$name] = $value;
            }
        }

        return new ClientData(
            ip: $ip,
            userAgent: $userAgent,
            acceptLanguage: $acceptLanguage,
            acceptEncoding: $acceptEncoding,
            xForwardedFor: $xForwardedFor,
            extraAttributes: $extraAttributes,
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
            extraAttributes: [],
        );
    }
}
