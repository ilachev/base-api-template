<?php

declare(strict_types=1);

namespace App\Application\Client;

final readonly class ClientData
{
    /**
     * @param array<string, string> $extraAttributes Дополнительные атрибуты клиента
     */
    public function __construct(
        public string $ip,
        public ?string $userAgent,
        public ?string $acceptLanguage,
        public ?string $acceptEncoding,
        public ?string $xForwardedFor,
        public array $extraAttributes = [],
    ) {}
}
