<?php

declare(strict_types=1);

return [
    // Storage name in RoadRunner KV (must match the name in .rr.yaml)
    'engine' => 'redis',

    // RPC connection address for RoadRunner
    'address' => 'tcp://127.0.0.1:6001',

    // Key prefix - helps avoid conflicts when using shared Redis
    'default_prefix' => 'app:',

    // Default cache TTL in seconds (1 hour)
    'default_ttl' => 3600,

    // Enable data compression for large values (to save memory)
    'compression' => true,

    // Compression threshold in bytes (compress values larger than this size)
    'compression_threshold' => 1024,

    // Maximum key storage time (7 days) - Redis limitation
    'max_ttl' => 604800,
];
