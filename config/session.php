<?php

declare(strict_types=1);

return [
    'cookie_name' => 'session',
    'cookie_ttl' => 86400,    // 24 hours
    'session_ttl' => -1,      // -1 for unlimited sessions
    'use_fingerprint' => true,
    'browser_new_session' => true, // Create new session for browsers without cookie
];
