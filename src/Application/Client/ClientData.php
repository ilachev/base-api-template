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

    /**
     * Определяет, является ли клиент браузером на основе User-Agent.
     */
    public function isBrowser(): bool
    {
        if ($this->userAgent === null) {
            return false;
        }

        // Типичные паттерны, указывающие на браузер
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
}
