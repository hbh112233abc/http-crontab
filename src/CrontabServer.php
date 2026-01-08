<?php
namespace bingher\crontab;

use bingher\crontab\http\HttpHandler;
use bingher\crontab\http\HttpServer;
use Workerman\Connection\TcpConnection;
use Workerman\Crontab\Crontab;
use Workerman\Timer;
use Workerman\Worker;

/**
 * HTTP Crontab 定时任务管理类
 *
 * 注意：定时器开始、暂停、重启 都是在下一分钟开始执行
 *
 * @package bingher\crontab
 */
class CrontabServer
{
    /**
     * Worker 实例
     * @var Worker
     */
    private $worker;

    /**
     * 进程名称
     * @var string
     */
    private $workerName = "Crontab Server";

    /**
     * 数据库操作实例
     * @var Db
     */
    private $db;

    /**
     * 任务进程池
     * @var array<int, array{id: int, shell: string, frequency: string, remark: string, create_time: string, crontab: Crontab}>
     */
    private $crontabPool = [];

    /**
     * 调试模式
     * @var bool
     */
    private $debug = false;

    /**
     * 错误信息列表
     * @var list<string>
     */
    private $errorMsg = [];

    /**
     * 安全秘钥
     * @var string|null
     */
    private $safeKey;

    /**
     * HTTP 处理器
     * @var HttpHandler|null
     */
    private $httpHandler;

    /**
     * HTTP 控制器
     * @var HttpServer|null
     */
    private $httpServer;

    /**
     * 是否启用 HTTP 服务
     * @var bool
     */
    private $enableHttp = true;

    /**
     * 最低PHP版本要求
     * @var string
     */
    const LESS_PHP_VERSION = '7.0.0';

    /**
     * 默认监听地址
     * @var string
     */
    const DEFAULT_SOCKET_NAME = 'http://127.0.0.1:2345';

    /**
     * 默认最大发送缓冲区大小 (1MB)
     * @var int
     */
    const DEFAULT_MAX_SEND_BUFFER_SIZE = 1024 * 1024;

    /**
     * 默认最大数据包大小 (10MB)
     * @var int
     */
    const DEFAULT_MAX_PACKAGE_SIZE = 10 * 1024 * 1024;

    /**
     * 默认日志文件路径
     * @var string
     */
    const DEFAULT_LOG_FILE = runtime_path() . "/crontab.log";

    /**
     * 默认标准输出文件路径
     * @var string
     */
    const DEFAULT_STDOUT_FILE = runtime_path() . "/crontab_debug.log";

    /**
     * 定时任务检查间隔 (秒)
     * @var int
     */
    const CRONTAB_CHECK_INTERVAL = 1;

    /**
     * 构造函数
     *
     * @param string $socketName 监听地址，格式为 <协议>://<监听地址>
     *                            协议支持 tcp、udp、unix、http、websocket、text
     *                            不填写表示不监听任何端口
     * @param array $contextOption socket 上下文选项
     *                            参考 http://php.net/manual/zh/context.socket.php
     * @param bool $enableHttp 是否启用 HTTP 服务，默认为 true
     *                          设置为 false 时仅运行 Crontab 定时任务，不提供 HTTP 接口
     */
    public function __construct($socketName = '', array $contextOption = [], $enableHttp = true)
    {
        $this->checkEnv();
        $this->enableHttp = $enableHttp;
        $this->initWorker($socketName, $contextOption);
    }

    /**
     * 检测运行环境
     *
     * 检查 PHP 版本、必需的扩展和函数是否可用
     */
    private function checkEnv()
    {
        $errorMsg = [];

        // 检查 exec 函数
        Tool::isFunctionDisabled('exec') && $errorMsg[] = 'exec函数被禁用';

        // 检查 PHP 版本
        Tool::versionCompare(self::LESS_PHP_VERSION, '<') && $errorMsg[] = 'PHP版本必须≥' . self::LESS_PHP_VERSION;

        // Linux 系统下检查进程控制相关扩展和函数
        if (Tool::isLinux()) {
            $checkExt = ["pcntl", "posix"];
            foreach ($checkExt as $ext) {
                ! Tool::isExtensionLoaded($ext) && $errorMsg[] = $ext . '扩展没有安装';
            }

            $checkFunc = [
                "stream_socket_server",
                "stream_socket_client",
                "pcntl_signal_dispatch",
                "pcntl_signal",
                "pcntl_alarm",
                "pcntl_fork",
                "pcntl_wait",
                "posix_getuid",
                "posix_getpwuid",
                "posix_kill",
                "posix_setsid",
                "posix_getpid",
                "posix_getpwnam",
                "posix_getgrnam",
                "posix_getgid",
                "posix_setgid",
                "posix_initgroups",
                "posix_setuid",
                "posix_isatty",
            ];
            foreach ($checkFunc as $func) {
                Tool::isFunctionDisabled($func) && $errorMsg[] = $func . '函数被禁用';
            }
        }

        if (! empty($errorMsg)) {
            $this->errorMsg = array_merge($this->errorMsg, $errorMsg);
        }
    }

    /**
     * 初始化 Worker
     *
     * @param string $socketName 监听地址
     * @param array $contextOption 上下文选项
     */
    private function initWorker($socketName = '', $contextOption = [])
    {
        // 如果不启用 HTTP 服务，则不监听任何端口
        if (! $this->enableHttp) {
            $socketName = '';
        } else {
            $socketName = $socketName ?: self::DEFAULT_SOCKET_NAME;
        }

        $this->worker       = new Worker($socketName, $contextOption);
        $this->worker->name = $this->workerName;

        // 设置 SSL 传输协议
        if (isset($contextOption['ssl'])) {
            $this->worker->transport = 'ssl'; // 设置当前Worker实例所使用的传输层协议，目前只支持3种(tcp、udp、ssl)。默认为tcp。
        }

        $this->registerCallback();
    }

    /**
     * 注册 Worker 子进程回调函数
     */
    private function registerCallback()
    {
        $this->worker->onWorkerStart  = [$this, 'onWorkerStart'];
        $this->worker->onWorkerReload = [$this, 'onWorkerReload'];
        $this->worker->onWorkerStop   = [$this, 'onWorkerStop'];

        // 仅在启用 HTTP 服务时注册网络相关回调
        if ($this->enableHttp) {
            $this->worker->onConnect     = [$this, 'onConnect'];
            $this->worker->onMessage     = [$this, 'onMessage'];
            $this->worker->onClose       = [$this, 'onClose'];
            $this->worker->onBufferFull  = [$this, 'onBufferFull'];
            $this->worker->onBufferDrain = [$this, 'onBufferDrain'];
            $this->worker->onError       = [$this, 'onError'];
        }
    }

    /**
     * 初始化 HTTP 控制器和处理器
     */
    private function initHttpComponents()
    {
        $this->httpServer = new HttpServer(
            $this->db,
            $this->crontabPool,
            [$this, 'crontabDestroy'],
            [$this, 'crontabRun'],
            $this->debug,
            $this->safeKey
        );

        $this->httpHandler = new HttpHandler($this->httpServer);
    }

    /**
     * 启用安全模式
     *
     * 设置安全秘钥后，所有请求都需要在 header 中携带 key 字段进行验证
     *
     * @param string $key 安全秘钥
     * @return $this
     */
    public function setSafeKey($key)
    {
        $this->safeKey = $key;

        return $this;
    }

    /**
     * 启用调试模式
     *
     * @return $this
     */
    public function setDebug()
    {
        $this->debug = true;

        return $this;
    }

    /**
     * 设置 Worker 实例名称
     *
     * 方便运行 status 命令时识别进程，默认为 "Workerman Http Crontab"
     *
     * @param string $name 进程名称
     * @return $this
     */
    public function setName($name = "Workerman Http Crontab")
    {
        $this->worker->name = $name;

        return $this;
    }

    /**
     * 设置 Worker 运行用户
     *
     * 此属性只有当前用户为 root 时才能生效，建议 $user 设置权限较低的用户
     * 默认以当前用户运行
     * Windows 系统不支持此特性
     *
     * @param string $user 用户名
     * @return $this
     */
    public function setUser($user = "root")
    {
        $this->worker->user = $user;

        return $this;
    }

    /**
     * 以守护进程方式运行
     *
     * Windows 系统不支持此特性
     *
     * @return $this
     */
    public function setDaemon()
    {
        Worker::$daemonize = true;

        return $this;
    }

    /**
     * 设置所有连接的默认应用层发送缓冲区大小
     *
     * 默认 1M，可以动态设置
     *
     * @param float|int $size 缓冲区大小（字节）
     * @return $this
     */
    public function setMaxSendBufferSize($size = self::DEFAULT_MAX_SEND_BUFFER_SIZE)
    {
        TcpConnection::$defaultMaxSendBufferSize = $size;

        return $this;
    }

    /**
     * 设置每个连接接收的数据包最大大小
     *
     * 默认 10M，超包视为非法数据，连接会断开
     *
     * @param float|int $size 最大包大小（字节）
     * @return $this
     */
    public function setMaxPackageSize($size = self::DEFAULT_MAX_PACKAGE_SIZE)
    {
        TcpConnection::$defaultMaxPackageSize = $size;

        return $this;
    }

    /**
     * 指定日志文件路径
     *
     * 默认为 ./workerman.log
     * 日志文件中仅仅记录 workerman 自身相关启动停止等日志，不包含任何业务日志
     *
     * @param string $path 日志文件路径
     * @return $this
     */
    public function setLogFile($path = self::DEFAULT_LOG_FILE)
    {
        Worker::$logFile = $path;

        return $this;
    }

    /**
     * 指定标准输出文件路径
     *
     * 以守护进程方式(-d启动)运行时，所有向终端的输出(echo var_dump等)
     * 都会被重定向到 stdoutFile 指定的文件中
     * 默认为 ./workerman_debug.log，也就是在守护模式时默认丢弃所有输出
     * Windows 系统不支持此特性
     *
     * @param string $path 输出文件路径
     * @return $this
     */
    public function setStdoutFile($path = self::DEFAULT_STDOUT_FILE)
    {
        Worker::$stdoutFile = $path;

        return $this;
    }

    /**
     * Worker 子进程启动回调
     *
     * 每个 Worker 子进程启动时都会执行
     *
     * @param Worker $worker Worker 实例
     */
    public function onWorkerStart($worker)
    {
        $this->db = new Db();
        $this->db->checkTaskTables();

        // 初始化 HTTP 组件
        if ($this->enableHttp) {
            $this->initHttpComponents();
        }

        $this->crontabInit();
        // 定时检查日志分表
        Timer::add(self::CRONTAB_CHECK_INTERVAL, [$this->db, 'checkTaskLogTable']);
    }

    /**
     * Worker 子进程停止回调
     *
     * @param Worker $worker Worker 实例
     */
    public function onWorkerStop($worker)
    {

    }

    /**
     * Worker 收到 reload 信号后执行的回调
     *
     * 如果在收到 reload 信号后只想让子进程执行 onWorkerReload，不想退出，
     * 可以在初始化 Worker 实例时设置对应的 Worker 实例的 reloadable 属性为 false
     *
     * @param Worker $worker Worker 实例
     */
    public function onWorkerReload($worker)
    {

    }

    /**
     * 客户端连接建立回调
     *
     * 当客户端与 Workerman 建立连接时(TCP 三次握手完成后)触发
     * 每个连接只会触发一次 onConnect 回调
     * 此时客户端还没有发来任何数据
     * 由于 UDP 是无连接的，所以当使用 UDP 时不会触发 onConnect 回调，也不会触发 onClose 回调
     *
     * @param TcpConnection $connection TCP 连接实例
     */
    public function onConnect($connection)
    {
        $this->httpHandler->onConnect($connection);
    }

    /**
     * 客户端连接断开回调
     *
     * 当客户端连接与 Workerman 断开时触发
     * 不管连接是如何断开的，只要断开就会触发 onClose
     * 每个连接只会触发一次 onClose
     * 由于 UDP 是无连接的，所以当使用 UDP 时不会触发 onConnect 回调，也不会触发 onClose 回调
     *
     * @param TcpConnection $connection TCP 连接实例
     */
    public function onClose($connection)
    {
        $this->httpHandler->onClose($connection);
    }

    /**
     * 收到客户端消息回调
     *
     * 当客户端通过连接发来数据时(Workerman 收到数据时)触发
     *
     * @param TcpConnection $connection TCP 连接实例
     * @param mixed $request HTTP 请求对象
     */
    public function onMessage($connection, $request)
    {
        $this->httpHandler->onMessage($connection, $request);
    }

    /**
     * 发送缓冲区满回调
     *
     * 每个连接都有一个单独的应用层发送缓冲区，如果客户端接收速度小于服务端发送速度，数据会在应用层缓冲区暂存
     * 只要发送缓冲区还没满，哪怕只有一个字节的空间，调用 Connection::send($A) 肯定会把 $A 放入发送缓冲区
     * 但是如果已经没有空间了，还继续 Connection::send($B) 数据，则这次 send 的 $B 数据不会放入发送缓冲区，
     * 而是被丢弃掉，并触发 onError 回调
     *
     * @param TcpConnection $connection TCP 连接实例
     */
    public function onBufferFull($connection)
    {
        $this->httpHandler->onBufferFull($connection);
    }

    /**
     * 发送缓冲区数据发送完毕回调
     *
     * 在应用层发送缓冲区数据全部发送完毕后触发
     *
     * @param TcpConnection $connection TCP 连接实例
     */
    public function onBufferDrain($connection)
    {
        $this->httpHandler->onBufferDrain($connection);
    }

    /**
     * 连接错误回调
     *
     * 客户端的连接上发生错误时触发
     *
     * @param TcpConnection $connection TCP 连接实例
     * @param int $code 错误码
     * @param string $msg 错误信息
     */
    public function onError($connection, $code, $msg)
    {
        $this->httpHandler->onError($connection, $code, $msg);
    }

    /**
     * 初始化定时任务
     *
     * 从数据库加载所有启用的定时任务并启动
     *
     * @return bool
     */
    private function crontabInit()
    {
        $ids = $this->db->getTaskIds();

        if (! empty($ids)) {
            foreach ($ids as $id) {
                $this->crontabRun($id);
            }
        }

        return true;
    }

    /**
     * 销毁定时器
     *
     * @param int $id 任务 ID
     * @return void
     */
    private function crontabDestroy($id)
    {
        if (isset($this->crontabPool[$id])) {
            $this->crontabPool[$id]['crontab']->destroy();
            unset($this->crontabPool[$id]);
        }
    }

    /**
     * 创建并启动定时器
     *
     * Cron 表达式格式说明：
     * 0   1   2   3   4   5
     * |   |   |   |   |   |
     * |   |   |   |   |   +------ day of week (0 - 6) (Sunday=0)
     * |   |   |   |   +------ month (1 - 12)
     * |   |   |   +-------- day of month (1 - 31)
     * |   |   +---------- hour (0 - 23)
     * |   +------------ min (0 - 59)
     * +-------------- sec (0-59)[可省略，如果没有0位,则最小时间粒度是分钟]
     *
     * @param int $id 任务 ID
     * @return void
     */
    private function crontabRun($id)
    {
        $task = $this->db->getTask($id);

        if (! empty($task) && $this->db->isTaskEnabled($task['status'])) {
            $this->crontabPool[$task['id']] = [
                'id'          => $task['id'],
                'shell'       => $task['shell'],
                'frequency'   => $task['frequency'],
                'remark'      => $task['remark'],
                'create_time' => date('Y-m-d H:i:s'),
                'crontab'     => new Crontab($task['frequency'], function () use (&$task) {
                    $shell = trim($task['shell']);
                    $this->debug && $this->writeln('执行定时器任务#' . $task['id'] . ' ' . $task['frequency'] . ' ' . $shell);
                    $sid = $task['id'];

                    // 防止重复执行
                    if (! $this->db->checkTaskLock($sid)) {
                        // 加锁
                        $this->db->taskLock($sid);

                        $time      = time();
                        $startTime = microtime(true);
                        exec($shell, $output, $code);
                        $endTime = microtime(true);

                        // 更新任务执行统计
                        $task['running_times'] += 1;
                        $this->db->updateTask($task['id'], [
                            'running_times'     => $task['running_times'],
                            'last_running_time' => $time,
                        ]);

                        // 记录执行日志
                        $this->db->insertTaskLog($task['id'], [
                            'command'      => $shell,
                            'output'       => join(PHP_EOL, $output),
                            'return_var'   => $code,
                            'running_time' => round($endTime - $startTime, 6),
                            'create_time'  => $time,
                            'update_time'  => $time,
                        ]);

                        // 解锁
                        $this->db->taskUnlock($sid);
                    }
                }),
            ];
        }
    }

    /**
     * 输出日志到控制台
     *
     * @param string $msg 日志消息
     * @param bool $ok 是否成功
     * @return void
     */
    private function writeln($msg, $ok = true)
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . ($ok ? " [Ok] " : " [Fail] ") . PHP_EOL;
    }

    /**
     * 运行所有 Worker 实例
     *
     * Worker::runAll() 执行后将永久阻塞
     * Worker::runAll() 调用前运行的代码都是在主进程运行的
     * onXXX 回调运行的代码都属于子进程
     *
     * 注意：Windows 版本的 workerman 不支持在同一个文件中实例化多个 Worker
     * Windows 版本的 workerman 需要将多个 Worker 实例初始化放在不同的文件中
     *
     * @return void
     */
    public function run()
    {
        if (empty($this->errorMsg)) {
            $this->writeln("启动系统任务");
            Worker::runAll();
        } else {
            foreach ($this->errorMsg as $v) {
                $this->writeln($v, false);
            }
        }
    }
}
