<?php
namespace bingher\crontab\test;

use bingher\ThinkTest\ThinkTest;
use bingher\crontab\Db;

class DbTest extends ThinkTest
{
    private $db;

    protected function setUp(): void
    {
        parent::setUp();
        // 初始化数据库配置，这里使用内存SQLite数据库进行测试
        $config = [
            'type' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'charset' => 'utf8',
            'debug' => true,
        ];
        $this->db = new Db($config);
        // 确保表存在
        $this->db->checkTaskTables();
    }

    public function testAddTask()
    {
        $task = [
            'title' => 'test_task', // 注意：字段名应与数据库表结构一致
            'type' => 0, // 0表示URL类型
            'frequency' => '* * * * *',
            'shell' => 'http://example.com',
            'running_times' => 0,
            'last_running_time' => 0,
            'remark' => 'Test task',
            'sort' => 0,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $result = $this->db->insertTask($task);
        $this->assertTrue($result > 0);
    }

    public function testGetTaskList()
    {
        $whereStr = '1=1';
        $bindValues = [];
        $page = 1;
        $limit = 10;
        $list = $this->db->getTaskList($whereStr, $bindValues, $page, $limit);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('list', $list);
        $this->assertArrayHasKey('count', $list);
    }

    public function testGetTaskById()
    {
        $task = [
            'title' => 'test_task2',
            'type' => 2, // 2表示shell类型
            'frequency' => '* * * * *',
            'shell' => 'echo "hello"',
            'running_times' => 0,
            'last_running_time' => 0,
            'remark' => 'Test task 2',
            'sort' => 0,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $id = $this->db->insertTask($task);
        $result = $this->db->getTask($id);
        $this->assertEquals($task['title'], $result['title']);
        $this->assertEquals($task['type'], $result['type']);
    }

    public function testUpdateTask()
    {
        $task = [
            'title' => 'test_task3',
            'type' => 0,
            'frequency' => '*/5 * * * *',
            'shell' => 'http://example2.com',
            'running_times' => 0,
            'last_running_time' => 0,
            'remark' => 'Test task 3',
            'sort' => 0,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $id = $this->db->insertTask($task);
        $updateData = [
            'title' => 'updated_task3',
            'shell' => 'http://updated-example.com',
            'update_time' => time(),
        ];
        $result = $this->db->updateTask($id, $updateData);
        $this->assertTrue($result);
        $updatedTask = $this->db->getTask($id);
        $this->assertEquals($updateData['title'], $updatedTask['title']);
        $this->assertEquals($updateData['shell'], $updatedTask['shell']);
    }

    public function testDeleteTask()
    {
        $task = [
            'title' => 'test_task4',
            'type' => 2,
            'frequency' => '0 0 * * *',
            'shell' => 'ls -la',
            'running_times' => 0,
            'last_running_time' => 0,
            'remark' => 'Test task 4',
            'sort' => 0,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $id = $this->db->insertTask($task);
        $result = $this->db->deleteTask($id);
        $this->assertTrue($result > 0 || $result === true); // 根据实际返回值调整

        // 重新查询确认任务已被删除（在实际应用中可能需要特殊标记而不是物理删除）
        $deletedTask = $this->db->getTask($id);
        // 由于是删除操作，如果使用软删除，则检查状态；如果是硬删除，则检查是否为空
        if ($deletedTask) {
            $this->assertNotEquals($task['title'], $deletedTask['title']);
        } else {
            $this->assertNull($deletedTask);
        }
    }

    public function testGetRunLogList()
    {
        $suffix = null; // 使用当前月份后缀
        $whereStr = '1=1';
        $bindValues = [];
        $page = 1;
        $limit = 10;
        $list = $this->db->getTaskLogList($suffix, $whereStr, $bindValues, $page, $limit);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('list', $list);
        $this->assertArrayHasKey('count', $list);
    }

    public function testAddRunLog()
    {
        $taskId = 1;
        $log = [
            'command' => 'echo "test"',
            'output' => 'test output',
            'return_var' => 0,
            'running_time' => '0.1',
            'create_time' => time(),
            'update_time' => time(),
        ];
        $result = $this->db->insertTaskLog($taskId, $log);
        $this->assertTrue($result > 0);
    }
}
