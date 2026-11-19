<?php

/**
 * Explicit origin allowlist for the separate React development app.
 * Do not use wildcard origins for a privileged API or in production.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-Request-Id'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
