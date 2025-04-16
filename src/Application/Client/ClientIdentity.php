<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Session\Session;

/**
 * Представляет идентификационные данные клиента, извлеченные из fingerprint.
 */
final readonly class ClientIdentity
{
    /**
     * @param string $id Уникальный идентификатор клиента
     * @param string $ipAddress IP-адрес клиента
     * @param string|null $userAgent User-Agent заголовок клиента
     * @param array<string, string> $attributes Дополнительные атрибуты клиента
     */
    public function __construct(
        public string $id,
        public string $ipAddress,
        public ?string $userAgent = null,
        public array $attributes = [],
    ) {}

    /**
     * Создает объект ClientIdentity из данных сессии.
     *
     * @param Session $session Сессия, из которой извлекаются данные
     */
    public static function fromSession(Session $session): self
    {
        $payload = json_decode($session->payload, true);

        if (!\is_array($payload)) {
            // Если невозможно распарсить - создаем пустой идентификатор
            return new self(
                id: $session->id,
                ipAddress: 'unknown',
            );
        }

        // Поля могут иметь разные имена в зависимости от сериализации ClientData
        // Проверяем оба формата - полный путь к свойству и короткое имя
        $ipAddress = 'unknown';
        if (isset($payload['ip']) && \is_string($payload['ip'])) {
            $ipAddress = $payload['ip'];
        } elseif (isset($payload['ipAddress']) && \is_string($payload['ipAddress'])) {
            $ipAddress = $payload['ipAddress'];
        }

        $userAgent = null;
        if (isset($payload['userAgent']) && \is_string($payload['userAgent'])) {
            $userAgent = $payload['userAgent'];
        } elseif (isset($payload['user_agent']) && \is_string($payload['user_agent'])) {
            $userAgent = $payload['user_agent'];
        }

        // Извлекаем все остальные атрибуты для сопоставления
        /** @var array<string, string> $attributes */
        $attributes = [];

        // Добавляем acceptLanguage если есть
        if (isset($payload['acceptLanguage']) && \is_string($payload['acceptLanguage'])) {
            $attributes['acceptLanguage'] = $payload['acceptLanguage'];
        } elseif (isset($payload['accept_language']) && \is_string($payload['accept_language'])) {
            $attributes['acceptLanguage'] = $payload['accept_language'];
        }

        // Добавляем acceptEncoding если есть
        if (isset($payload['acceptEncoding']) && \is_string($payload['acceptEncoding'])) {
            $attributes['acceptEncoding'] = $payload['acceptEncoding'];
        } elseif (isset($payload['accept_encoding']) && \is_string($payload['accept_encoding'])) {
            $attributes['acceptEncoding'] = $payload['accept_encoding'];
        }

        // Добавляем xForwardedFor если есть
        if (isset($payload['xForwardedFor']) && \is_string($payload['xForwardedFor'])) {
            $attributes['xForwardedFor'] = $payload['xForwardedFor'];
        } elseif (isset($payload['x_forwarded_for']) && \is_string($payload['x_forwarded_for'])) {
            $attributes['xForwardedFor'] = $payload['x_forwarded_for'];
        }

        // Добавляем extraAttributes если они есть
        if (isset($payload['extraAttributes']) && \is_array($payload['extraAttributes'])) {
            foreach ($payload['extraAttributes'] as $key => $value) {
                if (\is_string($key) && \is_scalar($value)) {
                    $attributes[$key] = (string) $value;
                }
            }
        }

        return new self(
            id: $session->id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            attributes: $attributes,
        );
    }

    /**
     * Проверяет соответствие текущего идентификатора с другим идентификатором
     *
     * @param self $other Другой идентификатор для сравнения
     * @param bool $strictIpMatch Требовать строгое соответствие IP
     */
    public function matches(self $other, bool $strictIpMatch = false): bool
    {
        // Если ID совпадают, это точно один и тот же клиент
        if ($this->id === $other->id) {
            return true;
        }

        // Проверяем IP-адрес
        $ipMatches = $strictIpMatch
            ? $this->ipAddress === $other->ipAddress
            : $this->ipAddress !== 'unknown' && $this->ipAddress === $other->ipAddress;

        // Проверяем User-Agent
        $uaMatches = $this->userAgent !== null && $this->userAgent === $other->userAgent;

        // Минимальное совпадение: либо IP + один атрибут, либо User-Agent + IP
        return ($ipMatches && $uaMatches)
               || ($ipMatches && \count(array_intersect_assoc($this->attributes, $other->attributes)) > 0);
    }
}
