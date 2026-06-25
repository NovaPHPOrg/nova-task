<?php

declare(strict_types=1);

namespace nova\plugin\task;

use function nova\framework\config;

use nova\framework\core\Context;
use nova\framework\core\Instance;
use nova\framework\core\Logger;

class PoolManager extends Instance
{
    public const string SERVER_KEY = "task_pool_server";

    protected int $concurrency;
    protected int $timeout;

    public function __construct(int $concurrency = 0, int $timeout = 24 * 3600)
    {
        // concurrency <= 0 时按 CPU 核心数自动推导
        if ($concurrency <= 0) {
            $concurrency = (int)(config("cpu_cores") ?? 1) * 4;
        }
        $this->concurrency = max(1, $concurrency);
        $this->timeout = $timeout;
    }

    /**
     * 推送一个新阶段任务
     *
     * @param array         $items
     * @param callable      $worker function(mixed $item, int $index, PoolManager $mgr): void
     * @param callable|null $finish function(): void
     */
    public static function pushStage(array $items, callable $worker, callable $finish = null): void
    {
        Context::instance()->cache->set("task_pool/" . uniqid(), __serialize([
            'items' => $items,
            'worker' => $worker,
            'finish' => $finish ?? function () {
            },
        ]));
    }

    /** 启动执行所有排队的阶段 */
    public function run(): void
    {
        $queues = Context::instance()->cache->getAll("task_pool/");
        foreach ($queues as $key => $queue) {
            try {
                ['items' => $items, 'worker' => $worker, 'finish' => $finish] = __unserialize($queue);
                $this->runPool($items, $worker, $finish);
            } catch (\Throwable $e) {
                Logger::error($e->getMessage(), $e->getTrace());
            } finally {
                Context::instance()->cache->delete($key);
                Context::instance()->cache->set(self::SERVER_KEY, getmypid(), 20);
            }
        }
    }

    /** 内部执行单阶段的并发逻辑 */
    public function runPool(array $items, callable $worker, callable $finish): void
    {
        $total = count($items);
        if ($total === 0) {
            $finish();
            return;
        }

        $chunkSize = (int)ceil($total / $this->concurrency);
        Logger::info("并发任务拆分：分组 $chunkSize / 总任务 $total / 并发 {$this->concurrency}");

        $processes = [];
        $startIndex = 0;
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            if (Context::instance()->cache->get(self::SERVER_KEY) == null) {
                break;
            }
            $base = $startIndex;
            $processes[] = go(function () use ($chunk, $worker, $base) {
                foreach ($chunk as $i => $item) {
                    if (Context::instance()->cache->get(self::SERVER_KEY) == null) {
                        break;
                    }
                    // 把自己传进去，worker 内可以再 call $mgr->pushStage(...)
                    $worker($item, $base + $i, $this);
                }
            }, $this->timeout);
            $startIndex += $chunkSize;
        }

        // 等待全部结束
        foreach ($processes as $p) {
            go_wait($p);
        }

        // 本阶段完成回调
        $finish();
    }

    /** 启动任务扫描服务 */
    public static function start(): void
    {
        $cache = Context::instance()->cache;

        if ($cache->get(self::SERVER_KEY) === null) {
            Logger::info("No PoolServer is running, start a new one");
            $cache->set(self::SERVER_KEY, getmypid(), 60);
            go(function () {
                $key = self::SERVER_KEY;
                $cache = Context::instance()->cache;
                $pid = getmypid();
                do {
                    $cache->set($key, $pid, 15);
                    PoolManager::getInstance()->run();
                    sleep(10);
                    Logger::info("PoolServer({$pid}) is running in the background");
                } while ($cache->get($key) === $pid);
                Logger::info("PoolServer({$pid}) is stopped.");
            }, 0);
        }
    }

    /** 停止任务 */
    public static function stop(): void
    {
        Context::instance()->cache->set(self::SERVER_KEY, getmypid());
    }
}
