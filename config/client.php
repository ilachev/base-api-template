<?php

declare(strict_types=1);

/**
 * Настройки для системы идентификации клиентов.
 */
return [
    'similarity_threshold' => 0.6,    // Минимальный порог схожести (0.0-1.0)
    'max_sessions_per_ip' => 5,       // Максимальное число сессий с одного IP
    'ip_match_weight' => 0.3,         // Вес совпадения по IP
    'user_agent_match_weight' => 0.3, // Вес совпадения по User-Agent
    'attributes_match_weight' => 0.4, // Вес совпадения по атрибутам
];
