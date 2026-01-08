<?php

return [
    // 定时器名称
    'name'        => 'Http Crontab Server',
    // worker进程运行用户
    'user'        => 'root',
    // debug模式
    'debug'       => false,
    // 是否启用 HTTP 服务
    // true: 同时运行 Crontab 定时任务和 HTTP 接口服务
    // false: 仅运行 Crontab 定时任务，不提供 HTTP 接口
    'enable_http' => env('crontab.enable_http', false),
    // socket 上下文选项
    'context'     => [],
    // 请求地址
    'base_uri'    => env('cron.crontab_base_uri', 'http://127.0.0.1:2345'),
    // 安全秘钥
    'safe_key'    => env('cron.crontab_safe_key', 'Q85gb1ncuWDsZTVoAEvymrNHhaRtp73M'),
    // 数据库配置
    'database'    => [
        // 数据库类型
        'type'         => 'sqlite',
        // 数据库名
        'database'     => __DIR__ . '/crontab.db',
        // 数据库编码默认采用utf8mb4
        'charset'      => 'utf8',
        // 数据库表前缀
        'prefix'       => '',
        // 监听SQL
        'trigger_sql'  => env('app_debug', false),
        // 开启字段缓存
        'fields_cache' => true,
    ],
];
