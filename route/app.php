<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use app\middleware\AuthMiddleware;
use think\facade\Route;

Route::group('api/v1', function () {
    Route::get('health', 'HealthController/index');
    Route::post('auth/register', 'AuthController/register');
    Route::post('auth/login', 'AuthController/login');
});

Route::group('api/v1', function () {
    Route::get('auth/me', 'AuthController/me');
    Route::post('auth/logout', 'AuthController/logout');
})->middleware(AuthMiddleware::class);
