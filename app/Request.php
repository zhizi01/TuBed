<?php
namespace app;

use app\model\UserDbModel;
use app\model\UserTokenDbModel;

// 应用请求对象类
class Request extends \think\Request
{
    // 由认证中间件注入，控制器无需重复查询当前用户。
    public ?UserDbModel $user = null;
    public ?UserTokenDbModel $accessToken = null;
}
