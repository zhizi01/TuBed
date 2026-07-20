<?php

return [
    'allow_register' => env('AUTH_ALLOW_REGISTER', true),
    'token_ttl_days' => (int) env('AUTH_TOKEN_TTL_DAYS', 30),
];
