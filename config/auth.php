<?php

return [
    'allow_register' => env('AUTH_ALLOW_REGISTER', true),
    'first_user_admin' => env('AUTH_FIRST_USER_ADMIN', true),
    'token_ttl_days' => (int) env('AUTH_TOKEN_TTL_DAYS', 30),
];
