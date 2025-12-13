<?php

namespace nova\plugin\task;

use nova\framework\cache\Cache;
use nova\framework\core\Context;
use nova\framework\core\Logger;
use function nova\framework\config;

class PoolManager
{
    protected int $concurrency;
    protected int $timeout;
    protected Cache $cache;
    protected string $cacheKey;

    public static function instance($timeout = 24 * 3600,):PoolManager
    {
        return Context::instance()->getOrCreateInstance(PoolManager::class,function ()use ($timeout){
            //子进程数量
            return new PoolManager((config("cpu_cores")??1)*4,$timeout);
        });
    }


    public function __construct(
        int $concurrency = 4,
        int $timeout = 24 * 3600,
        string $cacheKey = 'task_pool'
    ) {
        $this->concurrency = max(1, $concurrency);
        $this->timeout     = $timeout;
        $this->cacheKey    = $cacheKey;
    }

    /**
     * 推送一个新阶段任务
     *
     * @param array $items
     * @param callable $worker function(mixed $item, int $index, PoolManager $mgr): void
     * @param callable|null $finish function(): void
     */
    public static function pushStage(array $items, callable $worker, callable $finish = null): void
    {
        Context::instance()->cache->set("pool/task_".uniqid(),__serialize([
            'items'  => $items,
            'worker' => $worker,
            'finish' => $finish ?? function(){},
        ]));
    }

    /** 启动执行所有排队的阶段 */
    public function run(): void
    {
        $queues = Context::instance()->cache->getAll("pool");

        foreach ($queues as $key => $queue) {
            Context::instance()->cache->set("pool.lock",time(),3600);
            try{
                $item = __unserialize($queue);

                ['items' => $items, 'worker' => $worker, 'finish' => $finish] = $item;

            $this->runPool($items, $worker, $finish);

            }catch (\Throwable $e){
                Logger::error($e->getMessage(),$e->getTrace());
            } finally {
                Context::instance()->cache->delete($key);
            }
        }
    }

    /** 内部执行单阶段的并发逻辑 */
    public function runPool(array $items, callable $worker, callable $finish): void
    {
        $total     = count($items);
        if ($total === 0) {
            $finish();
            return;
        }

        $chunkSize = (int) ceil($total / $this->concurrency);
        Logger::info("并发任务拆分：分组 $chunkSize / 总任务 $total / 并发 {$this->concurrency}");

        $processes  = [];
        $startIndex = 0;
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            if (Context::instance()->cache->get(self::SERVER_KEY) == null) break;
            $base = $startIndex;
            $processes[] = go(function () use ($chunk, $worker, $base) {
                foreach ($chunk as $i => $item) {
                    if (Context::instance()->cache->get(self::SERVER_KEY) == null) break;
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

    public const string SERVER_KEY = "pool_server";

    /**
     * 启动任务扫描服务
     * @return void
     */
    public static function start(): void
    {
        $cache = Context::instance()->cache;

        if ($cache->get(self::SERVER_KEY) === null) {
            Logger::info("No PoolServer is running, start a new one");
            $cache->set(self::SERVER_KEY, getmypid(), 60);
            go(function () {
                $key = self::SERVER_KEY;
                $cache = Context::instance()->cache;

                do {
                    $pid = getmypid();
                    $cache->set($key, $pid, 55);
                    PoolManager::instance()->run();
                    sleep(30);
                    Logger::info("PoolServer({$pid}) is running in the background");
                } while ($cache->get($key) === $pid);

            }, 0);
        }
    }

    //停止任务
    public static function stop(): void
    {
        $cache = Context::instance()->cache;
        $cache->set(self::SERVER_KEY, getmypid());
    }
}