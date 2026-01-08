# HTTP 服务模块

本目录包含 HTTP 服务相关的所有代码,已从 `CrontabServer.php` 中分离出来。

## 目录结构

```
src/http/
├── HttpServer.php    # HTTP 控制器 - 处理所有 HTTP 接口请求
├── HttpHandler.php   # HTTP 处理器 - 处理 HTTP 连接和消息
├── Route.php         # 路由类
└── README.md         # 本文件
```

## 核心组件

### HttpController

**职责:** 处理所有 HTTP 接口请求的业务逻辑

**主要方法:**
- `crontabIndex()` - 获取定时任务列表
- `crontabAdd()` - 创建定时任务
- `crontabRead()` - 读取定时任务详情
- `crontabEdit()` - 编辑定时任务
- `crontabModify()` - 修改定时器属性
- `crontabDelete()` - 删除定时任务
- `crontabReload()` - 重启定时任务
- `crontabFlow()` - 获取执行日志列表
- `crontabPool()` - 获取定时任务池信息
- `crontabPing()` - 心跳检测
- `response()` - 构造 HTTP 响应

**依赖:**
- `Db` - 数据库操作实例
- `crontabPool` - 任务池引用(用于读取任务状态)
- `crontabDestroyCallback` - 任务销毁回调
- `crontabRunCallback` - 任务运行回调

### HttpHandler

**职责:** 处理 HTTP 连接和消息的底层逻辑

**主要方法:**
- `onConnect()` - 客户端连接建立回调
- `onClose()` - 客户端连接断开回调
- `onMessage()` - 收到客户端消息回调
- `onBufferFull()` - 发送缓冲区满回调
- `onBufferDrain()` - 发送缓冲区数据发送完毕回调
- `onError()` - 连接错误回调

**依赖:**
- `Route` - 路由实例
- `HttpController` - HTTP 控制器实例
- `safeKey` - 安全秘钥

## 架构设计

```
CrontabServer (核心类)
    ├── Crontab 服务 (定时任务管理)
    └── HTTP 服务 (可选)
        ├── Route (路由)
        ├── HttpHandler (连接处理)
        └── HttpController (业务逻辑)
```

## 使用示例

### 启用 HTTP 服务 (默认)

```php
use bingher\crontab\CrontabServer;

// 创建实例时启用 HTTP 服务
$server = new CrontabServer('http://127.0.0.1:2345', [], true);
$server->run();
```

### 禁用 HTTP 服务 (仅运行 Crontab)

```php
use bingher\crontab\CrontabServer;

// 创建实例时禁用 HTTP 服务
$server = new CrontabServer('', [], false);
$server->run();
```

### 通过配置文件控制

在 `config/crontab.php` 中:

```php
return [
    'enable_http' => false,  // 禁用 HTTP 服务
    // ...其他配置
];
```

或者在 `.env` 文件中:

```env
CRON_CRONTAB_ENABLE_HTTP=false
```

## 接口列表

当 HTTP 服务启用时,提供以下接口:

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/crontab/index` | 获取任务列表 |
| POST | `/crontab/add` | 添加任务 |
| GET | `/crontab/read` | 读取任务详情 |
| POST | `/crontab/edit` | 编辑任务 |
| POST | `/crontab/modify` | 修改任务属性 |
| POST | `/crontab/delete` | 删除任务 |
| POST | `/crontab/reload` | 重启任务 |
| GET | `/crontab/flow` | 获取执行日志 |
| GET | `/crontab/pool` | 获取任务池 |
| GET | `/crontab/ping` | 心跳检测 |

## 安全说明

- 默认需要携带 `key` 请求头进行身份验证
- 生产环境建议配置强密码的 `safe_key`
- 建议使用 HTTPS 加密传输

## 扩展开发

如需扩展 HTTP 功能:

1. 在 `HttpController` 中添加新的业务方法
2. 在 `CrontabServer::registerRoute()` 中注册新路由
3. 添加对应的调度方法到 `CrontabServer`

示例:

```php
// 1. 在 HttpController 中添加方法
public function crontabCustom($request)
{
    // 自定义业务逻辑
    return ['data' => 'custom response'];
}

// 2. 在 CrontabServer::registerRoute() 中注册路由
$this->route->addRoute('GET', '/crontab/custom', [$this, 'dispatchHttpCustom']);

// 3. 添加调度方法
public function dispatchHttpCustom($request)
{
    return $this->httpController->crontabCustom($request);
}
```
