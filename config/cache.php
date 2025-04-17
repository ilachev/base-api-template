<?php

declare(strict_types=1);

return [
    // Название хранилища в RoadRunner KV (должно соответствовать имени в .rr.yaml: local-memory, redis)
    'engine' => 'local-memory',

    // Адрес RPC соединения с RoadRunner
    'address' => 'tcp://127.0.0.1:6001',

    // Префикс для ключей - помогает избежать конфликтов при использовании общего Redis
    'default_prefix' => 'app:',

    // Время жизни кеша по умолчанию в секундах (1 час)
    'default_ttl' => 3600,
];
