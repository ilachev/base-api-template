<?php

declare(strict_types=1);

return [
    'db_path' => __DIR__ . '/../db/geoip/IP2LOCATION-LITE-DB11.BIN',
    'download_token' => getenv('IP2LOCATION_TOKEN') ?: '',
    'download_url' => 'https://www.ip2location.com/download',
    'database_code' => 'DB11LITEBIN',
    'cache_ttl' => 3600, // 1 час
];
