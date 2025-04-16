<?php

declare(strict_types=1);

namespace App\Application\Client;

/**
 * Конфигурация для системы определения клиентов.
 */
final readonly class ClientConfig
{
    /**
     * @param float $similarityThreshold Минимальный порог схожести для определения совпадения (0.0-1.0)
     * @param int $maxSessionsPerIp Максимальное число сессий с одного IP-адреса
     * @param float $ipMatchWeight Вес совпадения по IP-адресу
     * @param float $userAgentMatchWeight Вес совпадения по User-Agent
     * @param float $attributesMatchWeight Вес совпадения по атрибутам
     */
    public function __construct(
        public float $similarityThreshold = 0.6,
        public int $maxSessionsPerIp = 5,
        public float $ipMatchWeight = 0.3,
        public float $userAgentMatchWeight = 0.3,
        public float $attributesMatchWeight = 0.4,
    ) {
        // Проверяем, что сумма весов равна 1.0
        $sum = $this->ipMatchWeight + $this->userAgentMatchWeight + $this->attributesMatchWeight;
        if (abs($sum - 1.0) > 0.001) {
            throw new \InvalidArgumentException(
                "Sum of match weights must be 1.0, got {$sum}",
            );
        }
    }

    /**
     * Создает конфигурацию из массива параметров.
     *
     * @param array{
     *    similarity_threshold?: float,
     *    max_sessions_per_ip?: int,
     *    ip_match_weight?: float,
     *    user_agent_match_weight?: float,
     *    attributes_match_weight?: float,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            similarityThreshold: $config['similarity_threshold'] ?? 0.6,
            maxSessionsPerIp: $config['max_sessions_per_ip'] ?? 5,
            ipMatchWeight: $config['ip_match_weight'] ?? 0.3,
            userAgentMatchWeight: $config['user_agent_match_weight'] ?? 0.3,
            attributesMatchWeight: $config['attributes_match_weight'] ?? 0.4,
        );
    }
}
