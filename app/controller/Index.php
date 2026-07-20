<?php

namespace app\controller;

class Index
{
    public function index()
    {
        $lockFile = app()->getRootPath()
            . 'public' . DIRECTORY_SEPARATOR
            . 'install' . DIRECTORY_SEPARATOR
            . 'install.lock';

        if (!is_file($lockFile)) {
            return redirect('/install/install.php');
        }

        return response('', 404);
    }
}
