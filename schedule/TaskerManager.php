<?php
/*******************************************************************************
 * Copyright (c) 2022. CleanPHP. All Rights Reserved.
 ******************************************************************************/

namespace nova\plugin\task\schedule;

use nova\framework\App;
use nova\framework\cache\Cache;
use nova\framework\exception\AppExitException;
use nova\framework\log\Logger;
use nova\plugin\task\schedule\Cron\CronExpression;
use Throwable;
use function nova\plugin\task\__serialize;
use function nova\plugin\task\go;

/**
 * Class Tasker
 * @package extend\net_ankio_tasker\core
 * Date: 2020/12/23 23:46
 * Author: ankio
 * Description: 定时任务管理器
 */
class TaskerManager
{
    const string TASK_LIST = "tasker_list";

    /**
     * 清空所有定时任务
     * @return void
     */
    public static function clean(): void
    {
        (new Cache())->delete(self::TASK_LIST);
    }

    /**
     * 判断是否存在指定的定时任务
     * @param $key
     * @return bool
     */
    public static function has($key): bool
    {
        $list = self::list();
        /**
         * @var $value TaskInfo
         */
        foreach ($list as $value) {
            if ($key === $value->key || $key === $value->name) {
                return true;
            }
        }
        return false;
    }

    /**
     * 删除指定ID的定时任务
     * @param $key
     * @return void
     */
    public static function del($key): void
    {
        $list = self::list();
        /**
         * @var $value TaskInfo
         */
        $new = [];
        foreach ($list as $value) {
            if ($key !== $value->key && $key !== $value->name) {
                $new[] = $value;
            }
        }
        (new Cache())->set(self::TASK_LIST, $new);
    }

    /**
     * 添加一个定时任务，与linux定时任务语法完全一致
     * @param string $cron 定时任务时间包，使用{@link TaskerTime}来指定或手写cron字符串（不含秒数位，不支持问号）
     * @param TaskerAbstract $taskerAbstract 需要运行的定时任务，需要继承{@link TaskerAbstract}类并实现{@link TaskerAbstract::onStart()}方法
     * @param string $name 定时任务名称
     * @param int $times 定时任务的执行次数，当times=-1的时候为循环任务
     * 返回定时任务ID
     * @return string
     */
    public static function add(string $cron, TaskerAbstract $taskerAbstract, string $name, int $times = 1): string
    {
        if ($cron === "") {
            Logger::info("Tasker: 该任务：$name 立即执行");
            //属于立即执行
            go(function () use ($taskerAbstract) {
                try {
                    $taskerAbstract->onStart();
                } catch (Throwable $exception) {
                    $taskerAbstract->onAbort($exception);
                    if ($exception instanceof AppExitException) {
                        throw $exception;
                    }
                } finally {
                    $taskerAbstract->onStop();
                }

            }, $taskerAbstract->getTimeOut());
            return '';
        }

        $task = new TaskInfo();
        $task->name = $name;
        $task->cron = $cron;
        $task->times = $times;
        $task->loop = $times == -1;
        $task->key = uniqid("task_");

        $task->next = CronExpression::factory($cron)->getNextRunDate()->getTimestamp();
        $task->closure = $taskerAbstract;
        $list = self::list();
        $list[] = $task;

        (new Cache())->set(self::TASK_LIST, $list);
        if (App::getInstance()->debug) {
            Logger::info("Tasker 添加定时任务：$name => " . get_class($taskerAbstract));
            Logger::info("Tasker 初次添加后，执行时间为：" . date("Y-m-d H:i:s", $task->next));
        }
        return $task->key;
    }

    /**
     * 执行一次遍历数据库
     * @return void
     */
    public static function run(): void
    {

        $data = self::list();
        /**
         * @var $value TaskInfo
         */
        foreach ($data as $k => $value) {
            //次序=0
            if ($value->times === 0) {
                App::getInstance()->debug && Logger::info("Tasker 该ID ({$value->name})[{$value->key}] 的定时任务执行完毕");
                unset($data[$k]);
            } elseif ($value->next <= time()) {
                $time = CronExpression::factory($value->cron)->getNextRunDate()->getTimestamp();
                $value->next = $time;
                $value->times--;
                App::getInstance()->debug && Logger::info("Tasker 执行完成后，下次执行时间为：" . date("Y-m-d H:i:s", $time));
                /**
                 * @var  TaskerAbstract $task
                 */
                $task = $value->closure;
                $timeout = $task->getTimeOut();

                go(function () use ($task) {
                    try {
                        App::getInstance()->debug && Logger::info("Tasker 异步执行：" . __serialize($task));
                        $task->onStart();
                    } catch (Throwable $exception) {
                        $task->onAbort($exception);
                        if ($exception instanceof AppExitException) {
                            throw $exception;
                        }
                    } finally {
                        App::getInstance()->debug && Logger::info("Tasker 异步执行结束：");
                        $task->onStop();
                    }

                }, $timeout);
            }
        }
        (new Cache())->set(self::TASK_LIST, $data);

    }

    /**
     * 获取执行时间
     * @param $key
     * @return int
     */
    private static function getTimes($key): int
    {
        $task = self::get($key);
        if (!$task) {
            return 1 - $task->times;
        }
        return 0;
    }

    /**
     * 获取指定的定时任务
     * @param $key
     * @return TaskInfo|null
     */
    private static function get($key): ?TaskInfo
    {
        $list = self::list();
        /**
         * @var $value TaskInfo
         */
        foreach ($list as $value) {
            if ($key === $value->key) return $value;
        }
        return null;
    }

    /**
     * 获取定时任务列表
     * @return array
     */
    public static function list(): array
    {
        return (new Cache())->get(self::TASK_LIST, []) ?: [];
    }


}