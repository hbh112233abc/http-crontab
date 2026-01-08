<?php
namespace bingher\crontab;

use bingher\crontab\constant\YesNoConstant;
use think\facade\Config;
use think\facade\Db as ThinkDb;

/**
 * 数据库操作类
 *
 * @phpstan-type Task array{
 *     id: int,
 *     title: string,
 *     type: int,
 *     frequency: string,
 *     shell: string,
 *     running_times: int,
 *     last_running_time: int,
 *     remark: string,
 *     sort: int,
 *     status: int,
 *     create_time: int,
 *     update_time: int
 * }
 *
 * @phpstan-type TaskLog array{
 *     id: int,
 *     sid: int,
 *     command: string,
 *     output: string,
 *     return_var: int,
 *     running_time: float,
 *     create_time: int,
 *     update_time: int
 * }
 *
 * @phpstan-type TaskLock array{
 *     id: int,
 *     sid: int,
 *     is_lock: int,
 *     create_time: int,
 *     update_time: int
 * }
 */
class Db
{
    /**
     * 数据库配置
     * @var array
     */
    private $dbConfig;

    /**
     * 定时任务表
     * @var string
     */
    private $taskTable = 'crontab_task';

    /**
     * 定时任务日志表
     * @var string
     */
    private $taskLogTable = 'crontab_task_log';

    /**
     * 定时任务锁表
     * @var string
     */
    private $taskLockTable = 'crontab_task_lock';

    /**
     * 定时任务日志表后缀 按月分表
     * @var string|null
     */
    private $taskLogTableSuffix;

    /**
     * 当前定时任务日志表
     * @var string|null
     */
    private $currentTaskLogTable;

    /**
     * 数据库连接对象
     * @var \think\db\Connection
     */
    protected $db;

    /**
     * 构造函数
     */
    public function __construct($config = [])
    {
        $this->dbConfig = config('crontab.database');
        if (! empty($config)) {
            $this->dbConfig = array_merge($this->dbConfig, $config);
        }
        $connections = [
            'crontab' => $this->dbConfig,
        ];
        $config['connections'] = array_merge(Config::get('database.connections'), $connections);
        Config::set($config, 'database');
        $this->db = ThinkDb::connect('crontab');
    }
    /**
     * 获取定时任务id
     * @return list<int>
     */
    public function getTaskIds()
    {
        return $this->db->table($this->taskTable)
            ->where("status", "=", YesNoConstant::YES)
            ->order("sort DESC")
            ->column('id');
    }

    /**
     * 获取任务信息
     * @param int $id
     * @return Task|null
     */
    public function getTask($id)
    {
        return $this->db->table($this->taskTable)
            ->where('id', '=', $id)
            ->find();
    }

    /**
     * 获取任务列表
     * @param string $whereStr
     * @param array $bindValues
     * @param int $page
     * @param int $limit
     * @return array{list: list<Task>, count: int}
     */
    public function getTaskList($whereStr, $bindValues, $page, $limit)
    {
        $list = $this->db->table($this->taskTable)
            ->whereRaw($whereStr, $bindValues)
            ->order("sort DESC")
            ->limit($limit)
            ->page($page)
            ->select()->toArray();

        $count = $this->db->table($this->taskTable)
            ->whereRaw($whereStr, $bindValues)
            ->count();

        return ['list' => $list, 'count' => $count];
    }

    /**
     * 新增任务
     * @param Task $data
     * @return int
     */
    public function insertTask(array $data)
    {
        return $this->db->table($this->taskTable)
            ->insertGetId($data);
    }

    /**
     * 更新任务信息
     * @param int $id
     * @param Task $data
     * @return int
     */
    public function updateTask($id, array $data)
    {
        return $this->db->table($this->taskTable)
            ->where('id', '=', $id)
            ->update($data);
    }

    /**
     * 删除任务
     * @param int|string $id
     * @return int
     */
    public function deleteTask($id)
    {
        return $this->db->table($this->taskTable)
            ->whereIn('id', explode(',', $id))
            ->delete();
    }

    /**
     * 任务是否启用
     * @param int $status
     * @return bool
     */
    public function isTaskEnabled($status)
    {
        return $status == YesNoConstant::YES;
    }

    /**
     * 获取执行日志列表
     * @param string $suffix
     * @param string $whereStr
     * @param array $bindValues
     * @param int $page
     * @param int $limit
     * @return array{list: list<TaskLog>, count: int}
     */
    public function getTaskLogList($suffix, $whereStr, $bindValues, $page, $limit)
    {
        $tableName = $suffix ? $this->taskLogTable . '_' . str_replace('-', '', $suffix) : $this->currentTaskLogTable;

        if ($this->isTableExist($tableName)) {
            $list = $this->db->table($tableName)
                ->whereRaw($whereStr, $bindValues)
                ->order("id DESC")
                ->limit($limit)
                ->page($page)
                ->select()->toArray();

            $count = $this->db->table($tableName)
                ->whereRaw($whereStr, $bindValues)
                ->count();
        } else {
            $list  = [];
            $count = 0;
        }

        return ['list' => $list, 'count' => $count];
    }

    /**
     * 插入执行日志
     * @param int $taskId
     * @param TaskLog $data
     * @return int
     */
    public function insertTaskLog($taskId, array $data)
    {
        $data['sid'] = $taskId;
        return $this->db->table($this->currentTaskLogTable)
            ->insertGetId($data);
    }

    /**
     * 获取任务锁信息
     * @param int $taskId
     * @return TaskLog|null
     */
    public function getTaskLock($taskId)
    {
        return $this->db->table($this->taskLockTable)
            ->where('sid', '=', $taskId)
            ->find();
    }

    /**
     * 插入任务锁数据
     * @param int $taskId
     * @param int $isLock
     * @return int
     */
    public function insertTaskLock($taskId, $isLock = 0)
    {
        $now = time();
        return $this->db->table($this->taskLockTable)
            ->insertGetId([
                'sid'         => $taskId,
                'is_lock'     => $isLock,
                'create_time' => $now,
                'update_time' => $now,
            ]);
    }

    /**
     * 更新任务锁信息
     * @param int $taskId
     * @param array $data
     */
    public function updateTaskLock($taskId, array $data)
    {
        return $this->db->table($this->taskLockTable)
            ->where('sid', '=', $taskId)
            ->update($data);
    }

    /**
     * 加锁
     * @param int $taskId
     * @return bool
     */
    public function taskLock($taskId)
    {
        return $this->updateTaskLock($taskId, ['is_lock' => YesNoConstant::YES, 'update_time' => time()]);
    }

    /**
     * 解锁
     * @param int $taskId
     * @return bool
     */
    public function taskUnlock($taskId)
    {
        return $this->updateTaskLock($taskId, ['is_lock' => YesNoConstant::NO, 'update_time' => time()]);
    }

    /**
     * 重置锁
     * @return int
     */
    private function taskLockReset()
    {
        return $this->db->table($this->taskLockTable)
            ->update(['is_lock' => YesNoConstant::NO, 'update_time' => time()]);
    }

    /**
     * 任务是否加锁
     * @param int $isLock
     * @return bool
     */
    public function isTaskLocked($isLock)
    {
        return $isLock == 1;
    }

    /**
     * 检查任务锁
     * @param int $taskId
     * @return bool
     */
    public function checkTaskLock($taskId)
    {
        $taskLockInfo = $this->getTaskLock($taskId);
        if (! $taskLockInfo) {
            $this->insertTaskLock($taskId);
            return false;
        } else {
            return $this->isTaskLocked($taskLockInfo['is_lock']);
        }
    }

    /**
     * 检测表是否存在
     */
    public function checkTaskTables()
    {
        $date = date('Ym', time());
        if ($date !== $this->taskLogTableSuffix) {
            $this->taskLogTableSuffix  = $date;
            $this->currentTaskLogTable = $this->taskLogTable . "_" . $this->taskLogTableSuffix;
            $allTables                 = $this->getDbTables();
            ! in_array($this->taskTable, $allTables) && $this->createTaskTable();
            ! in_array($this->currentTaskLogTable, $allTables) && $this->createTaskLogTable();
            if (in_array($this->taskLockTable, $allTables)) {
                $this->taskLockReset();
            } else {
                $this->createTaskLockTable();
            }
        }
    }

    /**
     * 检测执行日志分表
     */
    public function checkTaskLogTable()
    {
        $date = date('Ym', time());
        if ($date !== $this->taskLogTableSuffix) {
            $this->taskLogTableSuffix  = $date;
            $this->currentTaskLogTable = $this->taskLogTable . "_" . $this->taskLogTableSuffix;
            if ($this->isTableExist($this->currentTaskLogTable) === false) {
                $this->createTaskLogTable();
            }
        }
    }

    /**
     * 获取数据库表名
     * @return list<string>
     */
    public function getDbTables()
    {
        $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        return array_column($result, 'name');
    }

    /**
     * 数据表是否存在
     * @param string $tableName
     * @return bool
     */
    public function isTableExist($tableName)
    {
        $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$tableName]);
        return ! empty($result);
    }

    /**
     * 创建定时器任务表
     */
    private function createTaskTable()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->taskTable}` (
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
SQL;

        return $this->db->execute($sql);
    }

    /**
     * 定时器任务流水表
     */
    private function createTaskLogTable()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->currentTaskLogTable}` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT, -- ID
  `sid` INTEGER NOT NULL, -- 任务ID
  `command` TEXT NOT NULL, -- 执行命令
  `output` TEXT NOT NULL, -- 执行输出
  `return_var` INTEGER NOT NULL, -- 执行返回状态[0成功; 1失败]
  `running_time` TEXT NOT NULL, -- 执行所用时间
  `create_time` INTEGER NOT NULL DEFAULT 0, -- 创建时间
  `update_time` INTEGER NOT NULL DEFAULT 0 -- 更新时间
)
SQL;

        return $this->db->execute($sql);
    }

    /**
     * 定时器任务锁表
     */
    private function createTaskLockTable()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->taskLockTable}` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT, -- ID
  `sid` INTEGER NOT NULL, -- 任务ID
  `is_lock` INTEGER NOT NULL DEFAULT 0, -- 是否锁定(0:否,1是)
  `create_time` INTEGER NOT NULL DEFAULT 0, -- 创建时间
  `update_time` INTEGER NOT NULL DEFAULT 0 -- 更新时间
)
SQL;

        return $this->db->execute($sql);
    }
}
