# ThinkCrontab接口化秒级定时任务

## 概述

**项目来源**: [HttpCrontab](https://github.com/cshaptx4869/http-crontab)

基于 **Workerman** + **Sqlite** 的接口化秒级定时任务管理，兼容 Windows 和 Linux 系统。

> 主要改造点:

- 加入Service配置
- 数据库使用独立的Sqlite数据库
- 安装扩展自动加入配置文件crontab
- 仅作为ThinkPHP>=8的扩展

## 定时器格式说明

```
0   1   2   3   4   5
|   |   |   |   |   |
|   |   |   |   |   +------ day of week (0 - 6) (Sunday=0)
|   |   |   |   +------ month (1 - 12)
|   |   |   +-------- day of month (1 - 31)
|   |   +---------- hour (0 - 23)
|   +------------ min (0 - 59)
+-------------- sec (0-59)[可省略，如果没有0位,则最小时间粒度是分钟]
```



## 使用

```shell
php think crontab start
```

## 帮助


```bash
$ php think crontab -h
Usage:
  crontab [options] [--] <action>

Arguments:
  action                start|stop|restart|reload|status|connections

Options:
  -d, --daemon          Run the http crontab server in daemon mode.
      --name[=NAME]     Crontab name [default: "Crontab Server"]
      --debug           Print log
  -h, --help            Display this help message
  -V, --version         Display this console version
  -q, --quiet           Do not output any message
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

## 配置

配置文件位于 `config/crontab.php`，默认配置如下：

```php
<?php

return [
    // 定时器名称
    'name'     => 'Http Crontab Server',
    // worker进程运行用户
    'user'     => 'root',
    // debug模式
    'debug'    => false,
    // socket 上下文选项
    'context'  => [],
    // 请求地址
    'base_uri' => env('cron.crontab_base_uri', 'http://127.0.0.1:2345'),
    // 安全秘钥
    'safe_key' => env('cron.crontab_safe_key', 'Q85gb1ncuWDsZTVoAEvymrNHhaRtp73M'),
    // 数据库配置
    'database' => [
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
```

### 配置项说明

#### 基础配置

| 配置项 | 类型 | 默认值 | 说明 |
|-------|------|--------|------|
| name | string | 'Http Crontab Server' | 定时器服务名称 |
| user | string | 'root' | Worker 进程运行用户（Linux 系统有效） |
| debug | bool | false | 是否开启调试模式 |
| context | array | [] | Socket 上下文选项，支持 SSL 等配置 |
| base_uri | string | `'http://127.0.0.1:2345'` | 服务监听地址 |
| safe_key | string | 环境变量或默认值 | API 安全秘钥，用于接口访问验证 |

#### 数据库配置

| 配置项 | 类型 | 默认值 | 说明 |
|-------|------|--------|------|
| database.type | string | 'sqlite' | 数据库类型，目前仅支持 sqlite |
| database.database | string | `__DIR__ . '/crontab.db'` | SQLite 数据库文件路径 |
| database.charset | string | 'utf8' | 数据库字符集 |
| database.prefix | string | '' | 数据库表前缀 |
| database.trigger_sql | bool | `env('app_debug', false)` | 是否监听并输出 SQL 语句，用于调试 |
| database.fields_cache | bool | true | 是否开启字段缓存，开启后可提升性能 |

### 配置示例

#### 1. 修改监听地址和端口

```php
return [
    'base_uri' => 'http://0.0.0.0:8080',  // 监听所有网卡的8080端口
    // ...其他配置
];
```

#### 2. 启用 HTTPS (SSL)

```php
return [
    'base_uri' => 'https://0.0.0.0:8443',
    'context'  => [
        'ssl' => [
            'local_cert'  => '/path/to/server.crt',
            'local_pk'    => '/path/to/server.key',
            'verify_peer'  => false,
        ],
    ],
    // ...其他配置
];
```

#### 3. 使用环境变量配置安全秘钥

在 `.env` 文件中添加：

```env
# Crontab 配置
CRON_CRONTAB_BASE_URI=http://0.0.0.0:8080
CRON_CRONTAB_SAFE_KEY=your_custom_secret_key_here
```

配置文件中：

```php
return [
    'base_uri' => env('cron.crontab_base_uri', 'http://127.0.0.1:2345'),
    'safe_key' => env('cron.crontab_safe_key', ''),
    // ...其他配置
];
```

#### 4. 自定义数据库路径

```php
return [
    'database' => [
        'type'     => 'sqlite',
        'database' => runtime_path() . 'crontab/crontab.db',  // 使用 runtime 目录
        // ...其他配置
    ],
];
```

#### 5. 开发环境完整配置

```php
return [
    'name'     => 'Dev Crontab Server',
    'debug'    => true,  // 开启调试模式
    'base_uri' => 'http://127.0.0.1:8080',
    'safe_key' => 'dev_key_for_testing',
    'database' => [
        'type'         => 'sqlite',
        'database'     => __DIR__ . '/crontab.db',
        'trigger_sql'  => true,   // 开启SQL监听
        'fields_cache' => false,  // 关闭字段缓存
    ],
];
```

#### 6. 生产环境优化配置

```php
return [
    'name'     => 'Production Crontab Server',
    'user'     => 'www-data',  // 使用低权限用户运行
    'debug'    => false,
    'base_uri' => 'http://0.0.0.0:2345',
    'safe_key' => env('cron.crontab_safe_key', ''),  // 使用环境变量
    'database' => [
        'type'         => 'sqlite',
        'database'     => '/data/crontab/crontab.db',  // 使用独立数据目录
        'charset'      => 'utf8',
        'trigger_sql'  => false,  // 关闭SQL监听
        'fields_cache' => true,   // 开启字段缓存
    ],
];
```

### 安全说明

1. **修改安全秘钥**：生产环境务必修改 `safe_key` 为强密码
2. **环境变量**：建议使用环境变量存储敏感信息
3. **HTTPS**：生产环境建议使用 HTTPS 加密传输
4. **防火墙**：限制对定时任务接口的访问权限
5. **运行用户**：使用低权限用户（如 `www-data`）而非 root

### 注意事项

1. **数据库权限**：确保 PHP 进程对数据库文件所在目录有读写权限
2. **字段缓存**：生产环境建议开启 `fields_cache` 以提升性能
3. **SQL 监听**：`trigger_sql` 仅在开发调试时使用，生产环境建议关闭
4. **Worker 用户**：`user` 配置仅在 Linux 系统且当前用户为 root 时生效
5. **端口占用**：确保 `base_uri` 指定的端口未被其他程序占用
6. **时间粒度**：定时器开始、暂停、重启 都是在下一分钟开始执行


## 数据库操作

`src/Db` 类提供了完整的定时任务数据库操作方法，支持任务、日志和锁的管理。

### 初始化

```php
use bingher\crontab\Db;

// 使用默认配置初始化
$db = new Db();

// 使用自定义配置初始化
$db = new Db([
    'database' => runtime_path() . 'crontab/crontab.db',
    'trigger_sql' => true,
]);
```

### 表结构

系统会自动创建以下数据表：

#### crontab_task - 任务表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER | 任务ID（主键） |
| title | TEXT | 任务标题 |
| type | INTEGER | 任务类型（0:请求URL, 1:执行SQL, 2:执行Shell） |
| frequency | TEXT | 任务频率（cron表达式） |
| shell | TEXT | 任务脚本 |
| running_times | INTEGER | 已运行次数 |
| last_running_time | INTEGER | 最近运行时间 |
| remark | TEXT | 任务备注 |
| sort | INTEGER | 排序，越大越前 |
| status | INTEGER | 任务状态（0:禁用, 1:启用） |
| create_time | INTEGER | 创建时间 |
| update_time | INTEGER | 更新时间 |

#### crontab_task_log_{YYYYMM} - 执行日志表（按月分表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER | ID（主键） |
| sid | INTEGER | 任务ID |
| command | TEXT | 执行命令 |
| output | TEXT | 执行输出 |
| return_var | INTEGER | 执行返回状态（0:成功, 1:失败） |
| running_time | TEXT | 执行所用时间（秒） |
| create_time | INTEGER | 创建时间 |
| update_time | INTEGER | 更新时间 |

#### crontab_task_lock - 任务锁表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | INTEGER | ID（主键） |
| sid | INTEGER | 任务ID |
| is_lock | INTEGER | 是否锁定（0:否, 1:是） |
| create_time | INTEGER | 创建时间 |
| update_time | INTEGER | 更新时间 |

### 任务操作

#### 1. 获取任务ID列表

获取所有启用的任务ID列表。

```php
// 返回数组: [1, 2, 3, ...]
$taskIds = $db->getTaskIds();
```

#### 2. 获取单个任务信息

```php
// 返回任务数组或 null
$task = $db->getTask(1);
```

返回示例：
```php
[
    'id' => 1,
    'title' => '输出 ThinkPHP 版本',
    'type' => 2,
    'frequency' => '*/3 * * * * *',
    'shell' => 'php think version',
    'running_times' => 10,
    'last_running_time' => 1625636646,
    'remark' => '每3秒执行',
    'sort' => 0,
    'status' => 1,
    'create_time' => 1625636609,
    'update_time' => 1625636609,
]
```

#### 3. 获取任务列表

```php
$whereStr = '1=1';  // WHERE 条件
$bindValues = [];  // 绑定参数
$page = 1;
$limit = 15;

// 返回: ['list' => [...], 'count' => 10]
$result = $db->getTaskList($whereStr, $bindValues, $page, $limit);
```

带过滤条件的示例：
```php
$whereStr = 'title LIKE :title AND status = :status';
$bindValues = [
    ':title' => '%版本%',
    ':status' => 1,
];
$result = $db->getTaskList($whereStr, $bindValues, 1, 15);
```

#### 4. 新增任务

```php
$data = [
    'title' => '新任务',
    'type' => 2,
    'frequency' => '*/5 * * * * *',
    'shell' => 'php think command',
    'remark' => '每5秒执行',
    'sort' => 100,
    'status' => 1,
];

// 返回新增任务的ID
$taskId = $db->insertTask($data);
```

#### 5. 更新任务

```php
$id = 1;
$data = [
    'title' => '更新后的标题',
    'status' => 0,
];

// 返回影响的行数
$affectedRows = $db->updateTask($id, $data);
```

更新特定字段的快捷方法：
```php
$id = 1;

// 启用/禁用任务
$db->updateTask($id, ['status' => 1]);
$db->updateTask($id, ['status' => 0]);

// 更新排序
$db->updateTask($id, ['sort' => 100]);

// 更新备注
$db->updateTask($id, ['remark' => '新的备注']);
```

#### 6. 删除任务

```php
// 删除单个任务
$db->deleteTask(1);

// 删除多个任务（逗号分隔）
$db->deleteTask('1,2,3');
```

### 任务状态判断

#### 1. 判断任务是否启用

```php
$status = $db->getTask(1)['status'];
$isEnabled = $db->isTaskEnabled($status);  // 返回 true 或 false
```

### 日志操作

#### 1. 获取执行日志列表

```php
$suffix = '202601';  // 年月后缀，如 202601 表示 2026年1月
$whereStr = 'sid = :sid';
$bindValues = [':sid' => 1];
$page = 1;
$limit = 15;

// 返回: ['list' => [...], 'count' => 50]
$result = $db->getTaskLogList($suffix, $whereStr, $bindValues, $page, $limit);
```

#### 2. 插入执行日志

```php
$taskId = 1;
$logData = [
    'command' => 'php think version',
    'output' => 'v8.0.0',
    'return_var' => 0,
    'running_time' => '0.123456',
];

// 返回日志ID
$logId = $db->insertTaskLog($taskId, $logData);
```

### 任务锁操作

任务锁用于防止任务并发执行，确保同一时间只有一个实例运行。

#### 1. 获取任务锁信息

```php
$taskId = 1;
// 返回锁信息数组或 null
$lockInfo = $db->getTaskLock($taskId);
```

返回示例：
```php
[
    'id' => 1,
    'sid' => 1,
    'is_lock' => 0,
    'create_time' => 1625636609,
    'update_time' => 1625636609,
]
```

#### 2. 插入任务锁

```php
$taskId = 1;
$isLock = 0;  // 0:未锁定, 1:已锁定

// 返回锁记录ID
$lockId = $db->insertTaskLock($taskId, $isLock);
```

#### 3. 更新任务锁

```php
$taskId = 1;
$data = [
    'is_lock' => 1,
];

// 返回影响的行数
$affectedRows = $db->updateTaskLock($taskId, $data);
```

#### 4. 任务加锁

```php
$taskId = 1;
// 将任务状态设置为锁定
$db->taskLock($taskId);
```

#### 5. 任务解锁

```php
$taskId = 1;
// 将任务状态设置为解锁
$db->taskUnlock($taskId);
```

#### 6. 判断任务是否已锁定

```php
$lockInfo = $db->getTaskLock(1);
$isLocked = $db->isTaskLocked($lockInfo['is_lock']);  // 返回 true 或 false
```

#### 7. 检查并处理任务锁

自动检查任务锁，不存在则创建，返回当前锁定状态。

```php
$taskId = 1;
// 如果锁不存在则创建，返回 false
// 如果锁存在则返回当前锁定状态
$isLocked = $db->checkTaskLock($taskId);
```

使用示例（防止任务并发执行）：
```php
$taskId = 1;

// 检查任务是否已锁定
if ($db->checkTaskLock($taskId)) {
    echo "任务正在执行中，跳过本次执行";
    return;
}

// 加锁
$db->taskLock($taskId);

try {
    // 执行任务逻辑
    executeTask($taskId);
} finally {
    // 解锁
    $db->taskUnlock($taskId);
}
```

### 表管理操作

#### 1. 获取数据库所有表名

```php
// 返回数组: ['crontab_task', 'crontab_task_log_202601', 'crontab_task_lock', ...]
$allTables = $db->getDbTables();
```

#### 2. 检查表是否存在

```php
$tableName = 'crontab_task_log_202601';
$exists = $db->isTableExist($tableName);  // 返回 true 或 false
```

#### 3. 检查并初始化任务相关表

检查任务表、日志表和锁表是否存在，不存在则创建。日志表按月自动分表。

```php
// 每月首次调用时会自动创建新的日志分表
$db->checkTaskTables();
```

#### 4. 检查并创建执行日志分表

仅处理日志分表的创建。

```php
$db->checkTaskLogTable();
```

### 完整使用示例

```php
<?php
use bingher\crontab\Db;

// 初始化
$db = new Db();

// 检查并创建必要的表
$db->checkTaskTables();

// 1. 新增任务
$taskId = $db->insertTask([
    'title' => '清理缓存',
    'type' => 2,
    'frequency' => '0 */30 * * * *',  // 每30分钟执行
    'shell' => 'php think clear',
    'remark' => '自动清理缓存',
    'sort' => 100,
    'status' => 1,
]);

// 2. 查询任务列表
$taskList = $db->getTaskList('status = :status', [':status' => 1], 1, 10);
echo "共有 {$taskList['count']} 个任务";
foreach ($taskList['list'] as $task) {
    echo "任务: {$task['title']} - {$task['frequency']}\n";
}

// 3. 执行任务并记录日志
$task = $db->getTask($taskId);
if ($db->isTaskEnabled($task['status'])) {
    // 检查任务锁
    if ($db->checkTaskLock($taskId)) {
        echo "任务正在执行中\n";
        return;
    }

    // 加锁
    $db->taskLock($taskId);

    $startTime = microtime(true);
    $command = $task['shell'];
    $output = shell_exec($command);
    $endTime = microtime(true);
    $runningTime = $endTime - $startTime;

    // 记录日志
    $db->insertTaskLog($taskId, [
        'command' => $command,
        'output' => $output,
        'return_var' => 0,
        'running_time' => number_format($runningTime, 6),
    ]);

    // 更新任务执行次数
    $db->updateTask($taskId, [
        'running_times' => $task['running_times'] + 1,
        'last_running_time' => time(),
    ]);

    // 解锁
    $db->taskUnlock($taskId);
}

// 4. 查询执行日志
$logResult = $db->getTaskLogList('202601', 'sid = :sid', [':sid' => $taskId], 1, 20);
echo "共执行 {$logResult['count']} 次\n";
foreach ($logResult['list'] as $log) {
    echo "执行时间: " . date('Y-m-d H:i:s', $log['create_time']) . "\n";
    echo "输出: {$log['output']}\n";
    echo "耗时: {$log['running_time']}秒\n\n";
}

// 5. 更新任务
$db->updateTask($taskId, [
    'title' => '清理系统缓存（已更新）',
    'status' => 0,  // 禁用任务
]);

// 6. 删除任务
$db->deleteTask($taskId);
```

### 注意事项

1. **日志表按月分表**：`crontab_task_log_YYYYMM`，每月自动创建新表
2. **任务锁机制**：防止任务并发执行，确保同一时间只有一个实例运行
3. **时间戳**：所有时间字段使用 Unix 时间戳（秒级）
4. **返回值类型**：
   - `insertTask`、`insertTaskLog`、`insertTaskLock` 返回新记录的 ID
   - `updateTask`、`deleteTask`、`updateTaskLock` 返回影响的行数
   - `getTask`、`getTaskLock` 返回记录数组或 null
   - `getTaskList`、`getTaskLogList` 返回 `['list' => [], 'count' => 0]` 结构
5. **排序字段**：`sort` 值越大，任务越优先执行

## 任务操作

 <h1 class="curproject-name"> 定时器接口说明 </h1>

> 默认接口地址: http://127.0.0.1:2345

## PING

<a id=PING> </a>

### 基本信息

**Path：** /crontab/ping

**Method：** GET

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": "pong",
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>


## 修改

<a id=修改> </a>

### 基本信息

**Path：** /crontab/modify

**Method：** POST

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": true,
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称 | 参数类型 | 是否必须 | 示例   | 备注                              |
| -------- | -------- | -------- | ------ | --------------------------------- |
| id       | text     | 是       | 1      |                                   |
| field    | text     | 是       | status | 字段[status; sort; remark; title] |
| value    | text     | 是       | 1      | 值                                |



### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>boolean</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>


## 列表

<a id=列表> </a>

### 基本信息

**Path：** /crontab/index

**Method：** GET

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": {
&nbsp;&nbsp;&nbsp; "list": [
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "title": "输出 tp 版本",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "type": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "frequency": "*/3 * * * * *",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "shell": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_times": 3,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "last_running_time": 1625636646,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "remark": "没3秒执行",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sort": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "status": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636609,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636609
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; }
&nbsp;&nbsp;&nbsp; ],
&nbsp;&nbsp;&nbsp; "count": 1
&nbsp; },
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

**Query**

| 参数名称 | 是否必须 | 示例                     | 备注         |
| -------- | -------- | ------------------------ | ------------ |
| page     | 是       | 1                        | 页码         |
| limit    | 是       | 15                       | 每页条数     |
| filter   | 否       | {"title":"输出 tp 版本"} | 检索字段值   |
| op       | 否       | {"title":"%*%"}          | 检索字段操作 |

### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>object</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> list</span></td><td key=1><span>object []</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5><p key=3><span style="font-weight: '700'">item 类型: </span><span>object</span></p></td></tr><tr key=0-1-0-0><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> id</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-1><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> title</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-2><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> type</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-3><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> frequency</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-4><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> shell</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-5><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> running_times</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-6><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> last_running_time</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-7><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> remark</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-8><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> sort</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-9><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> status</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-10><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> create_time</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-11><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> update_time</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-1><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> count</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>


## 删除

<a id=删除> </a>

### 基本信息

**Path：** /crontab/delete

**Method：** POST

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": true,
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称 | 参数类型 | 是否必须 | 示例 | 备注 |
| -------- | -------- | -------- | ---- | ---- |
| id       | text     | 是       | 1,2  |      |



### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>boolean</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>


## 定时器池

<a id=定时器池> </a>

### 基本信息

**Path：** /crontab/pool

**Method：** GET

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": [
&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "shell": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "frequency": "*/3 * * * * *",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "remark": "没3秒执行",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": "2021-07-07 13:43:29"
&nbsp;&nbsp;&nbsp; }
&nbsp; ],
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>object []</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5><p key=3><span style="font-weight: '700'">item 类型: </span><span>object</span></p></td></tr><tr key=0-1-0><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> id</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-1><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> shell</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-2><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> frequency</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-3><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> remark</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-4><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> create_time</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>


## 日志

<a id=日志> </a>

### 基本信息

**Path：** /crontab/flow

**Method：** GET

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": {
&nbsp;&nbsp;&nbsp; "list": [
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 12,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.115895",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636673,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636673
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 11,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.104641",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636670,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636670
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 10,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.106585",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636667,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636667
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 9,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.10808",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636664,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636664
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 8,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.107653",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636661,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636661
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 7,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.105938",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636658,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636658
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 6,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.10461",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636655,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636655
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 5,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.109786",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636652,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636652
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 4,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.115853",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636649,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636649
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 3,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.16941",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636646,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636646
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 2,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.109524",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636643,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636643
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sid": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "command": "php think version",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "output": "v6.0.7",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "return_var": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_time": "0.108445",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636640,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636640
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; }
&nbsp;&nbsp;&nbsp; ],
&nbsp;&nbsp;&nbsp; "count": 12
&nbsp; },
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

**Query**

| 参数名称 | 是否必须 | 示例        | 备注         |
| -------- | -------- | ----------- | ------------ |
| page     | 是       | 1           | 页码         |
| limit    | 是       | 15          | 每页条数     |
| filter   | 否       | {"sid":"1"} | 检索字段值   |
| op       | 否       | {"sid":"="} | 检索字段操作 |

### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>object</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> list</span></td><td key=1><span>object []</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5><p key=3><span style="font-weight: '700'">item 类型: </span><span>object</span></p></td></tr><tr key=0-1-0-0><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> id</span></td><td key=1><span>number</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-1><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> sid</span></td><td key=1><span>number</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-2><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> command</span></td><td key=1><span>string</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-3><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> output</span></td><td key=1><span>string</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-4><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> return_var</span></td><td key=1><span>number</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-5><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> running_time</span></td><td key=1><span>string</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-6><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> create_time</span></td><td key=1><span>number</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-0-7><td key=0><span style="padding-left: 40px"><span style="color: #8c8a8a">├─</span> update_time</span></td><td key=1><span>number</span></td><td key=2>必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1-1><td key=0><span style="padding-left: 20px"><span style="color: #8c8a8a">├─</span> count</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>


## 添加

<a id=添加> </a>

### 基本信息

**Path：** /crontab/add

**Method：** POST

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": true,
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称  | 参数类型 | 是否必须 | 示例              | 备注                                     |
| --------- | -------- | -------- | ----------------- | ---------------------------------------- |
| title     | text     | 是       | 输出 tp 版本      | 任务标题                                 |
| type      | text     | 是       | 0                 | 任务类型[0请求url; 1执行sql; 2执行shell] |
| frequency | text     | 是       | */3 * * * * *     | 任务频率                                 |
| shell     | text     | 是       | php think version | 任务脚本                                 |
| remark    | text     | 是       | 没3秒执行         | 备注                                     |
| sort      | text     | 是       | 0                 | 排序                                     |
| status    | text     | 是       | 1                 | 状态[0禁用; 1启用]                       |



### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>boolean</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>


## 重启

<a id=重启> </a>

### 基本信息

**Path：** /crontab/reload

**Method：** POST

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": true,
&nbsp; "msg": "信息调用成功！"
}
</code></pre>



### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称 | 参数类型 | 是否必须 | 示例 | 备注 |
| -------- | -------- | -------- | ---- | ---- |
| id       | text     | 是       | 1,2  |      |



### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th><th key=required>是否必须</th><th key=default>默认值</th><th key=desc>备注</th><th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>boolean</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>

​
