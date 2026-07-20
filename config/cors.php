<?php

return [
    'allow_origin' => env('CORS_ALLOW_ORIGIN', '*'),
    'allow_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'allow_headers' => 'Content-Type, Authorization, X-Requested-With',
    'expose_headers' => '',
    'max_age' => 86400,
];
