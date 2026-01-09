<?php
namespace bingher\crontab\http;

use bingher\crontab\exception\HttpException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

/**
 * HTTP 处理器类
 *
 * 处理 HTTP 连接和消息
 *
 * @package bingher\crontab\http
 */
class HttpHandler
{
    /**
     * HTTP 控制器
     * @var HttpServer
     */
    private $controller;

    /**
     * Worker服务
     * @var Worker
     */
    private $worker;

    /**
     * 构造函数
     *
     * @param HttpServer $controller HTTP 控制器实例
     */
    public function __construct(HttpServer $controller, Worker &$worker)
    {
        $this->controller = $controller;
        $this->worker     = $worker;

        $this->worker->onConnect     = [$this, 'onConnect'];
        $this->worker->onMessage     = [$this, 'onMessage'];
        $this->worker->onClose       = [$this, 'onClose'];
        $this->worker->onBufferFull  = [$this, 'onBufferFull'];
        $this->worker->onBufferDrain = [$this, 'onBufferDrain'];
        $this->worker->onError       = [$this, 'onError'];
    }

    /**
     * 客户端连接建立回调
     *
     * @param TcpConnection $connection TCP 连接实例
     * @return void
     */
    public function onConnect($connection)
    {
        // 可以在这里处理连接建立逻辑
    }

    /**
     * 客户端连接断开回调
     *
     * @param TcpConnection $connection TCP 连接实例
     * @return void
     */
    public function onClose($connection)
    {
        // 可以在这里处理连接断开逻辑
    }

    /**
     * 收到客户端消息回调
     *
     * @param TcpConnection $connection TCP 连接实例
     * @param Request $request HTTP 请求对象
     * @return void
     */
    public function onMessage($connection, $request)
    {
        if ($request instanceof Request) {
            // 调试信息
            $this->controller->log("Request path: " . $request->path() . ", method: " . $request->method());

            try {
                $routeInfo = $this->controller->dispatch($request->method(), $request->path());

                // 检查是否为根路由，根路由不需要验证安全密钥
                $isRootPath = $request->path() === '/';

                $this->controller->log("Is root path: " . ($isRootPath ? 'YES' : 'NO'));

                // 如果不是根路由，进行安全密钥验证
                if (! $isRootPath && ! $this->controller->verifySafeKey($request)) {
                    $this->controller->log("Safe key verification failed for path: " . $request->path());
                    $connection->send($this->controller->response('', 'Connection Not Allowed', 403));
                    return;
                }

                // 执行路由处理
                $result = call_user_func($routeInfo[1], $request);

                $this->controller->log("Route handler executed, result type: " . gettype($result));

                // 如果返回的是 Response 对象，直接发送
                if ($result instanceof \Workerman\Protocols\Http\Response) {
                    $this->controller->log("Sending Response object directly");
                    $connection->send($result);
                } else {
                    // 否则包装为 JSON 响应
                    $this->controller->log("Wrapping in JSON response");
                    $connection->send($this->controller->response($result));
                }
            } catch (HttpException $e) {
                $this->controller->log("HttpException: " . $e->getMessage());
                $connection->send($this->controller->response('', $e->getMessage(), $e->getStatusCode()));
            } catch (\Throwable $e) {
                $this->controller->log("Exception: " . $e->getMessage());
                $connection->send($this->controller->response('', $e->getMessage(), 500));
            }
        }
    }

    /**
     * 发送缓冲区满回调
     *
     * @param TcpConnection $connection TCP 连接实例
     * @return void
     */
    public function onBufferFull($connection)
    {
        // 可以在这里处理缓冲区满的逻辑
    }

    /**
     * 发送缓冲区数据发送完毕回调
     *
     * @param TcpConnection $connection TCP 连接实例
     * @return void
     */
    public function onBufferDrain($connection)
    {
        // 可以在这里处理缓冲区数据发送完毕的逻辑
    }

    /**
     * 连接错误回调
     *
     * @param TcpConnection $connection TCP 连接实例
     * @param int $code 错误码
     * @param string $msg 错误信息
     * @return void
     */
    public function onError($connection, $code, $msg)
    {
        // TODO: 记录错误日志或处理错误
        // 可在此处实现自定义的错误处理逻辑
    }
}
