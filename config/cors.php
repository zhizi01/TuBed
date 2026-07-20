<?php

$originValue = (string) env(
    'CORS_ALLOW_ORIGINS',
    env('CORS_ALLOW_ORIGIN', env('FRONTEND_URL', 'http://localhost:5173'))
);

return [
    'allow_origins' => array_values(array_unique(array_filter(array_map(
        static fn ($origin) => rtrim(trim($origin), '/'),
        explode(',', $originValue)
    )))),
    'allow_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'allow_headers' => 'Content-Type, Authorization, X-API-Key, X-Requested-With',
    'expose_headers' => 'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Window, '
        . 'X-Usage-Limit, X-Usage-Remaining, Retry-After',
    'max_age' => 86400,
];
