<?php
namespace bingher\crontab\http;

use bingher\crontab\Db;
use bingher\crontab\exception\RouteMethodNotAllowException;
use bingher\crontab\exception\RouteNotFoundException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

/**
 * HTTP 控制器类
 *
 * 处理所有 HTTP 接口请求和路由分发
 *
 * @package bingher\crontab\http
 */
class HttpServer
{
    /**
     * 数据库操作实例
     * @var Db
     */
    private $db;

    /**
     * 调试模式
     * @var bool
     */
    private $debug = false;

    /**
     * 路由路径定义
     */
    const INDEX_PATH  = '/crontab/index';  // 任务列表
    const ADD_PATH    = '/crontab/add';    // 添加任务
    const EDIT_PATH   = '/crontab/edit';   // 编辑任务
    const READ_PATH   = '/crontab/read';   // 读取任务
    const MODIFY_PATH = '/crontab/modify'; // 修改任务属性
    const RELOAD_PATH = '/crontab/reload'; // 重启任务
    const DELETE_PATH = '/crontab/delete'; // 删除任务
    const FLOW_PATH   = '/crontab/flow';   // 执行日志
    const POOL_PATH   = '/crontab/pool';   // 任务池
    const PING_PATH   = '/crontab/ping';   // 心跳检测

    /**
     * 任务进程池引用
     * @var array
     */
    private $crontabPool;

    /**
     * 任务销毁回调
     * @var callable
     */
    private $crontabDestroyCallback;

    /**
     * 任务运行回调
     * @var callable
     */
    private $crontabRunCallback;

    /**
     * FastRoute 路由分发器
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * 安全秘钥
     * @var string|null
     */
    private $safeKey;

    /**
     * 构造函数
     *
     * @param Db $db 数据库实例
     * @param array &$crontabPool 任务池引用
     * @param callable $crontabDestroyCallback 任务销毁回调
     * @param callable $crontabRunCallback 任务运行回调
     * @param bool $debug 调试模式
     * @param string|null $safeKey 安全秘钥
     */
    public function __construct(Db $db, &$crontabPool, callable $crontabDestroyCallback, callable $crontabRunCallback, $debug = false, $safeKey = null)
    {
        $this->db                     = $db;
        $this->crontabPool            = &$crontabPool;
        $this->crontabDestroyCallback = $crontabDestroyCallback;
        $this->crontabRunCallback     = $crontabRunCallback;
        $this->debug                  = $debug;
        $this->safeKey                = $safeKey;
        $this->registerRoutes();
    }

    /**
     * 注册所有路由
     */
    private function registerRoutes()
    {
        $this->dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) {
            $r->addRoute('GET', self::INDEX_PATH, [$this, 'crontabIndex']);
            $r->addRoute('POST', self::ADD_PATH, [$this, 'crontabAdd']);
            $r->addRoute('GET', self::READ_PATH, [$this, 'crontabRead']);
            $r->addRoute('POST', self::EDIT_PATH, [$this, 'crontabEdit']);
            $r->addRoute('POST', self::MODIFY_PATH, [$this, 'crontabModify']);
            $r->addRoute('POST', self::DELETE_PATH, [$this, 'crontabDelete']);
            $r->addRoute('POST', self::RELOAD_PATH, [$this, 'crontabReload']);
            $r->addRoute('GET', self::FLOW_PATH, [$this, 'crontabFlow']);
            $r->addRoute('GET', self::POOL_PATH, [$this, 'crontabPool']);
            $r->addRoute('GET', self::PING_PATH, [$this, 'crontabPing']);
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

    /**
     * 验证安全密钥
     *
     * @param Request $request HTTP 请求对象
     * @return bool
     */
    public function verifySafeKey($request)
    {
        if ($this->safeKey === null) {
            return true;
        }
        return $request->header('key') === $this->safeKey;
    }

    /**
     * 获取定时任务列表
     *
     * @param Request $request HTTP 请求对象
     * @return array{list: list<array>, count: int}
     */
    public function crontabIndex($request)
    {
        list($page, $limit, $where)  = $this->buildTableParames($request->get());
        list($whereStr, $bindValues) = $this->parseWhere($where);

        return $this->db->getTaskList($whereStr, $bindValues, $page, $limit);
    }

    /**
     * 创建定时任务
     *
     * @param Request $request HTTP 请求对象
     * @return bool
     */
    public function crontabAdd($request)
    {
        $data                = $request->post();
        $data['create_time'] = $data['update_time'] = time();
        $id                  = $this->db->insertTask($data);
        $id && call_user_func($this->crontabRunCallback, $id);

        return $id ? true : false;
    }

    /**
     * 读取定时任务详情
     *
     * @param Request $request HTTP 请求对象
     * @return array
     */
    public function crontabRead($request)
    {
        $row = [];
        if ($id = $request->get('id')) {
            $row = $this->db->getTask($id);
        }
        return $row;
    }

    /**
     * 编辑定时任务
     *
     * @param Request $request HTTP 请求对象
     * @return bool
     */
    public function crontabEdit($request)
    {
        if ($id = $request->get('id')) {
            $post = $request->post();
            $row  = $this->db->getTask($id);
            if (empty($row)) {
                return false;
            }

            $rowCount = $this->db->updateTask($id, $post);
            if ($this->db->isTaskEnabled($row['status'])) {
                // 如果频率或脚本发生变化，需要重启定时器
                if ($row['frequency'] !== $post['frequency'] || $row['shell'] !== $post['shell']) {
                    call_user_func($this->crontabDestroyCallback, $id);
                    call_user_func($this->crontabRunCallback, $id);
                }
            }

            return $rowCount ? true : false;
        } else {
            return false;
        }
    }

    /**
     * 修改定时器属性
     *
     * 支持修改 status（状态）和 sort（排序）字段
     *
     * @param Request $request HTTP 请求对象
     * @return bool
     */
    public function crontabModify($request)
    {
        $post = $request->post();
        if (in_array($post['field'], ['status', 'sort'])) {
            $row = $this->db->updateTask($post['id'], [$post['field'] => $post['value']]);

            if ($post['field'] === 'status') {
                if ($this->db->isTaskEnabled($post['value'])) {
                    call_user_func($this->crontabRunCallback, $post['id']);
                } else {
                    call_user_func($this->crontabDestroyCallback, $post['id']);
                }
            }

            return $row ? true : false;
        } else {
            return false;
        }
    }

    /**
     * 删除定时任务
     *
     * @param Request $request HTTP 请求对象
     * @return bool
     */
    public function crontabDelete($request)
    {
        if ($idStr = $request->post('id')) {
            $ids = explode(',', $idStr);

            foreach ($ids as $id) {
                call_user_func($this->crontabDestroyCallback, $id);
            }
            $rows = $this->db->deleteTask($idStr);

            return $rows ? true : false;
        }

        return true;
    }

    /**
     * 重启定时任务
     *
     * @param Request $request HTTP 请求对象
     * @return bool
     */
    public function crontabReload($request)
    {
        $ids = explode(',', $request->post('id'));

        foreach ($ids as $id) {
            $row = $this->db->getTask($id);
            if ($row && $this->db->isTaskEnabled($row['status'])) {
                call_user_func($this->crontabDestroyCallback, $id);
                call_user_func($this->crontabRunCallback, $id);
            }
        }

        return true;
    }

    /**
     * 获取定时任务池信息
     *
     * @return list<array{id: int, shell: string, frequency: string, remark: string, create_time: string}>
     */
    public function crontabPool()
    {
        $data = [];
        foreach ($this->crontabPool as $row) {
            unset($row['crontab']);
            $data[] = $row;
        }

        return $data;
    }

    /**
     * 心跳检测
     *
     * @return string
     */
    public function crontabPing()
    {
        return 'pong';
    }

    /**
     * 获取执行日志列表
     *
     * @param Request $request HTTP 请求对象
     * @return array{list: list<array>, count: int}
     */
    public function crontabFlow($request)
    {
        list($page, $limit, $where, $excludeFields) = $this->buildTableParames($request->get(), ['month']);
        $request->get('sid') && $where[]            = ['sid', '=', $request->get('sid')];
        list($whereStr, $bindValues)                = $this->parseWhere($where);

        $suffix = $excludeFields['month'] ?? '';

        return $this->db->getTaskLogList($suffix, $whereStr, $bindValues, $page, $limit);
    }

    /**
     * 构造 HTTP 响应
     *
     * @param mixed $data 响应数据
     * @param string $msg 响应消息
     * @param int $code HTTP 状态码
     * @return Response
     */
    public function response($data = '', $msg = '信息调用成功！', $code = 200)
    {
        return new Response($code, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode(['code' => $code, 'data' => $data, 'msg' => $msg]));
    }

    /**
     * 构建表格查询参数
     *
     * @param array $get GET 请求参数
     * @param list<string> $excludeFields 忽略构建搜索的字段
     * @return array{0: int, 1: int, 2: list<array>, 3: array}
     *               [页码, 每页数量, 查询条件, 排除字段]
     */
    private function buildTableParames($get, $excludeFields = [])
    {
        $page    = isset($get['page']) && ! empty($get['page']) ? (int) $get['page'] : 1;
        $limit   = isset($get['limit']) && ! empty($get['limit']) ? (int) $get['limit'] : 15;
        $filters = isset($get['filter']) && ! empty($get['filter']) ? $get['filter'] : '{}';
        $ops     = isset($get['op']) && ! empty($get['op']) ? $get['op'] : '{}';

        // JSON 转数组
        $filters  = json_decode($filters, true);
        $ops      = json_decode($ops, true);
        $where    = [];
        $excludes = [];

        foreach ($filters as $key => $val) {
            if (in_array($key, $excludeFields)) {
                $excludes[$key] = $val;
                continue;
            }
            $op = isset($ops[$key]) && ! empty($ops[$key]) ? $ops[$key] : '%*%';

            switch (strtolower($op)) {
                case '=':
                    $where[] = [$key, '=', $val];
                    break;
                case '%*%':
                    $where[] = [$key, 'LIKE', "%{$val}%"];
                    break;
                case '*%':
                    $where[] = [$key, 'LIKE', "{$val}%"];
                    break;
                case '%*':
                    $where[] = [$key, 'LIKE', "%{$val}"];
                    break;
                case 'range':
                    list($beginTime, $endTime) = explode(' - ', $val);
                    $where[]                   = [$key, '>=', strtotime($beginTime)];
                    $where[]                   = [$key, '<=', strtotime($endTime)];
                    break;
                case 'in':
                    $where[] = [$key, 'IN', $val];
                    break;
                default:
                    $where[] = [$key, $op, "%{$val}"];
            }
        }

        return [$page, $limit, $where, $excludes];
    }

    /**
     * 解析查询条件
     *
     * @param list<array> $where 查询条件数组
     * @return array{0: string, 1: array} [WHERE 语句, 绑定参数]
     */
    private function parseWhere($where)
    {
        if (! empty($where)) {
            $whereStr   = '';
            $bindValues = [];
            foreach ($where as $index => $item) {
                if ($item[0] === 'create_time') {
                    $whereStr .= $item[0] . ' ' . $item[1] . ' :' . $item[0] . $index . ' AND ';
                    $bindValues[$item[0] . $index] = $item[2];
                } elseif ($item[1] === 'IN') {
                    // @todo workerman/mysql包对in查询感觉有问题 临时用如下方式进行转化处理
                    $whereStr .= '(';
                    foreach (explode(',', $item[2]) as $k => $v) {
                        $whereStr .= $item[0] . ' = :' . $item[0] . $k . ' OR ';
                        $bindValues[$item[0] . $k] = $v;
                    }
                    $whereStr = rtrim($whereStr, 'OR ');
                    $whereStr .= ') AND ';
                } else {
                    $whereStr .= $item[0] . ' ' . $item[1] . ' :' . $item[0] . ' AND ';
                    $bindValues[$item[0]] = $item[2];
                }
            }
        } else {
            $whereStr   = '1 = 1';
            $bindValues = [];
        }

        $whereStr = rtrim($whereStr, 'AND ');

        return [$whereStr, $bindValues];
    }
}
