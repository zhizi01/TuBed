<?php

namespace app\controller;

use think\facade\App;
use think\facade\Db;
use Throwable;

class HealthController
{
    public function index()
    {
        try {
            Db::query('SELECT 1');
            $database = 'ok';
            $httpStatus = 200;
        } catch (Throwable $exception) {
            $database = 'unavailable';
            $httpStatus = 503;
        }

        return json([
            'code' => $httpStatus,
            'message' => $httpStatus === 200 ? 'success' : '数据库连接不可用',
            'data' => [
                'service' => 'TuBed API',
                'framework' => 'ThinkPHP ' . App::version(),
                'database' => $database,
                'time' => date('c'),
            ],
        ], $httpStatus);
    }
}
