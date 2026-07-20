<?php

return [
    'disk' => 'public',
    'max_size' => (int) env('UPLOAD_MAX_SIZE', 20 * 1024 * 1024),
    'max_pixels' => (int) env('UPLOAD_MAX_PIXELS', 100000000),
    // SVG 可能携带脚本，因此默认只允许可安全直出的位图格式。
    'allowed_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ],
];
