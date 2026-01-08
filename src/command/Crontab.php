<?php
namespace bingher\crontab\command;

use bingher\crontab\HttpCrontab;
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
        if ($config['base_uri'] && ! preg_match('/^https?:\/\//', $config['base_uri'])) {
            $output->error('base_uri配置格式非法');
            return 1;
        }
        $server = new HttpCrontab($config['base_uri'], $config['context']);
        $config['debug'] && $server->setDebug();
        $server->setName($config['name'])
            ->setUser($config['user'])
            ->setSafeKey($config['safe_key'])
            ->run();
    }
}
