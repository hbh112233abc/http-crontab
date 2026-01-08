<?php
namespace bingher\crontab;

use bingher\crontab\command\Crontab;

class Service extends \think\Service
{
    public function register()
    {
        $this->commands([
            Crontab::class,
        ]);
    }
}
