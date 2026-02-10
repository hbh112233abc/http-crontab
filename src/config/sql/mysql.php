<?php

return [
    "create_task"      => <<<SQL
CREATE TABLE IF NOT EXISTS `%s` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT, -- 任务ID
  `title` TEXT NOT NULL, -- 任务标题
  `type` INTEGER NOT NULL DEFAULT 0, -- 任务类型[0请求url,1执行sql,2执行shell]
  `frequency` TEXT NOT NULL, -- 任务频率
  `shell` TEXT NOT NULL DEFAULT '', -- 任务脚本
  `running_times` INTEGER NOT NULL DEFAULT 0, -- 已运行次数
  `last_running_time` INTEGER NOT NULL DEFAULT 0, -- 最近运行时间
  `remark` TEXT NOT NULL, -- 任务备注
  `sort` INTEGER NOT NULL DEFAULT 0, -- 排序，越大越前
  `status` INTEGER NOT NULL DEFAULT 0, -- 任务状态[0:禁用;1启用]
  `create_time` INTEGER NOT NULL DEFAULT 0, -- 创建时间
  `update_time` INTEGER NOT NULL DEFAULT 0 -- 更新时间
)
SQL,
    "create_task_log"  => <<<SQL
CREATE TABLE IF NOT EXISTS `%s` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT, -- ID
  `sid` INTEGER NOT NULL, -- 任务ID
  `command` TEXT NOT NULL, -- 执行命令
  `output` TEXT NOT NULL, -- 执行输出
  `return_var` INTEGER NOT NULL, -- 执行返回状态[0成功; 1失败]
  `running_time` TEXT NOT NULL, -- 执行所用时间
  `create_time` INTEGER NOT NULL DEFAULT 0, -- 创建时间
  `update_time` INTEGER NOT NULL DEFAULT 0 -- 更新时间
)
SQL,
    "create_task_lock" => <<<SQL
CREATE TABLE IF NOT EXISTS `%s` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT, -- ID
  `sid` INTEGER NOT NULL, -- 任务ID
  `is_lock` INTEGER NOT NULL DEFAULT 0, -- 是否锁定(0:否,1是)
  `create_time` INTEGER NOT NULL DEFAULT 0, -- 创建时间
  `update_time` INTEGER NOT NULL DEFAULT 0 -- 更新时间
)
SQL,
    "drop_table"       => "DROP TABLE IF EXISTS `%s`",
];
