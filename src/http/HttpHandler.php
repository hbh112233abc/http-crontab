<?php
namespace bingher\crontab\http;

use bingher\crontab\exception\HttpException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

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
     * 构造函数
     *
     * @param HttpServer $controller HTTP 控制器实例
     */
    public function __construct(HttpServer $controller)
    {
        $this->controller = $controller;
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
            // 安全密钥验证
            if (! $this->controller->verifySafeKey($request)) {
                $connection->send($this->controller->response('', 'Connection Not Allowed', 403));
            } else {
                try {
                    $routeInfo = $this->controller->dispatch($request->method(), $request->path());
                    $connection->send($this->controller->response(call_user_func($routeInfo[1], $request)));
                } catch (HttpException $e) {
                    $connection->send($this->controller->response('', $e->getMessage(), $e->getStatusCode()));
                }
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
