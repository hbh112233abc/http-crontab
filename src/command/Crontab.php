<?php
namespace bingher\crontab\command;

use bingher\crontab\Crontab as CrontabServer;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class Crontab extends Command
{
    protected function configure()
    {
        $this->setName('crontab')
            ->addArgument('action', Argument::REQUIRED, 'start|stop|restart|reload|status|connections')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the http crontab server in daemon mode.')
            ->setDescription('Run http crontab server');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = trim($input->getArgument('action'));
        if (! in_array($action, ['start', 'stop', 'restart', 'reload', 'status', 'connections'])) {
            $output->error('action参数值非法');
            return 1;
        }
        $config = config('crontab');

        // 检查 base_uri 格式（仅在启用 HTTP 服务时）
        $enableHttp = $config['enable_http'] ?? true;
        if ($enableHttp && $config['base_uri'] && ! preg_match('/^https?:\/\//', $config['base_uri'])) {
            $output->error('base_uri配置格式非法');
            return 1;
        }

        // 根据 enable_http 配置决定是否启用 HTTP 服务
        $socketName = $enableHttp ? $config['base_uri'] : '';
        $server     = new CrontabServer($socketName, $config['context'], $enableHttp);

        $config['debug'] && $server->setDebug();
        $server->setName($config['name'])
            ->setUser($config['user'])
            ->setSafeKey($config['safe_key'])
            ->run();
    }
}
