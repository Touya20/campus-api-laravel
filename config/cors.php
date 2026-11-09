<?php

$origins = array_filter(array_map('trim', explode(',', (string) env('CLIENT_ORIGINS', env('CLIENT_ORIGIN', 'http://localhost:5173')))));

return [
    'paths' => ['api/*', 'health'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values($origins),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Request-ID'],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => 0,
    'supports_credentials' => false,
];
