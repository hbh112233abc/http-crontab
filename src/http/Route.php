<?php
namespace bingher\crontab\http;

use bingher\crontab\exception\RouteMethodNotAllowException;
use bingher\crontab\exception\RouteNotFoundException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

/**
 * 路由类
 *
 * 基于 FastRoute 的简单路由封装
 *
 * @package bingher\crontab
 */
class Route
{
    /**
     * FastRoute 路由分发器
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * 路由列表
     * @var list<array{method: string, route: string, handler: callable}>
     */
    private $routes = [];

    /**
     * 添加路由
     *
     * @param string $httpMethod HTTP 方法（GET/POST/PUT/DELETE 等）
     * @param string $route 路由路径
     * @param callable $handler 处理函数
     * @return $this
     */
    public function addRoute($httpMethod, $route, $handler)
    {
        $this->routes[] = [
            'method'  => strtoupper($httpMethod),
            'route'   => $route,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * 注册路由
     *
     * 将所有路由添加到 FastRoute 分发器
     *
     * @return void
     */
    public function register()
    {
        $this->dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route['method'], $route['route'], $route['handler']);
            }
        });
    }

    /**
     * 路由分发
     *
     * @param string $method HTTP 方法
     * @param string $path 请求路径
     * @return array{0: int, 1: callable, 2: array} 路由信息
     * @throws RouteNotFoundException 路由不存在异常
     * @throws RouteMethodNotAllowException 请求方法不允许异常
     */
    public function dispatch($method, $path)
    {
        $routeInfo = $this->dispatcher->dispatch($method, $path);
        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            throw new RouteNotFoundException();
        } else if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            throw new RouteMethodNotAllowException();
        } else {
            return $routeInfo;
        }
    }
}
