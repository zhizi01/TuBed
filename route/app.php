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
use app\middleware\AdminMiddleware;
use app\middleware\AuthMiddleware;
use app\middleware\OpenApiMiddleware;
use think\facade\Route;

Route::group('api/v1', function () {
    Route::get('health', 'HealthController/index');
    Route::post('auth/register', 'AuthController/register');
    Route::post('auth/login', 'AuthController/login');
});

Route::group('api/v1', function () {
    Route::get('auth/me', 'AuthController/me');
    Route::post('auth/logout', 'AuthController/logout');

    Route::get('stats/overview', 'StatsController/overview');

    Route::get('albums', 'AlbumController/index');
    Route::post('albums', 'AlbumController/create');
    Route::rule('albums/:id', 'AlbumController/update', 'PUT|PATCH');
    Route::delete('albums/:id', 'AlbumController/delete');

    Route::get('images', 'ImageController/index');
    Route::post('images', 'ImageController/upload');
    Route::get('images/:id', 'ImageController/show');
    Route::rule('images/:id', 'ImageController/update', 'PUT|PATCH');
    Route::delete('images/:id', 'ImageController/delete');

    Route::get('api-keys', 'ApiKeyController/index');
    Route::post('api-keys', 'ApiKeyController/create');
    Route::patch('api-keys/:id', 'ApiKeyController/update');
    Route::post('api-keys/:id/regenerate', 'ApiKeyController/regenerate');
    Route::delete('api-keys/:id', 'ApiKeyController/delete');
})->middleware(AuthMiddleware::class);

Route::group('api/v1/admin', function () {
    Route::get('overview', 'AdminController/overview');
    Route::get('users', 'AdminController/users');
    Route::patch('users/:id', 'AdminController/updateUser');
    Route::get('images', 'AdminController/images');
    Route::delete('images/:id', 'AdminController/deleteImage');
    Route::get('api-settings', 'AdminController/apiSettings');
    Route::put('api-settings', 'AdminController/updateApiSettings');
    Route::get('api-keys', 'AdminController/apiKeys');
    Route::patch('api-keys/:id', 'AdminController/updateApiKey');
})->middleware([AuthMiddleware::class, AdminMiddleware::class]);

Route::group('api/open/v1', function () {
    Route::get('ping', function () {
        $request = request();

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'key_prefix' => $request->apiKey->key_prefix,
                'user_id' => $request->user->id,
            ],
        ]);
    });
    Route::post('images', 'ImageController/upload');
})->middleware(OpenApiMiddleware::class);
