<?php

return [
    'roles' => [
        'admin' => [
            'label' => '管理员',
            'permissions' => ['*'],
        ],
        'user' => [
            'label' => '用户',
            'permissions' => [
                'dashboard.view',
                'images.manage',
                'albums.manage',
                'api_keys.manage',
                'profile.view',
            ],
        ],
    ],
];
