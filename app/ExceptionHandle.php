<?php
namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }

        if (str_starts_with(trim($request->pathinfo(), '/'), 'api/')) {
            $status = 500;
            $message = '服务器内部错误';
            $data = null;

            if ($e instanceof ValidateException) {
                $status = 422;
                $message = '参数验证失败';
                $data = $e->getError();
            } elseif ($e instanceof HttpException) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: match ($status) {
                    404 => '接口不存在',
                    405 => '请求方法不允许',
                    default => '请求失败',
                };
            }

            return json([
                'code' => $status,
                'message' => $message,
                'data' => $data,
            ], $status);
        }

        return parent::render($request, $e);
    }
}
