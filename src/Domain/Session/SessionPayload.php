<?php

declare(strict_types=1);

namespace App\Domain\Session;

/**
 * DTO для хранения данных о клиенте в сессии.
 */
final readonly class SessionPayload
{
    /**
     * @param string $ip IP-адрес клиента
     * @param string|null $userAgent User-Agent клиента
     * @param string|null $acceptLanguage Accept-Language клиента
     * @param string|null $acceptEncoding Accept-Encoding клиента
     * @param string|null $xForwardedFor X-Forwarded-For клиента
     * @param array<string, string> $extraAttributes Дополнительные атрибуты
     */
    public function __construct(
        public string $ip,
        public ?string $userAgent,
        public ?string $acceptLanguage,
        public ?string $acceptEncoding,
        public ?string $xForwardedFor,
        public array $extraAttributes,
    ) {}
}
