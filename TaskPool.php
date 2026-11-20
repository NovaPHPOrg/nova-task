<?php

declare(strict_types=1);

namespace nova\plugin\task;

use function nova\framework\config;

use nova\framework\core\Logger;

use Throwable;

/**
 * 基于 nova\plugin\task\go 的简易并发池：把 items 分块，每块一个后台任务，全部结束后回调 $finish。
 */
class TaskPool
{
    /** 按 CPU 自动计算时的默认并发上限 */
    public const int MAX_CONCURRENCY = 16;

    /**
     * @param string        $name        任务面板展示名称
     * @param array         $items       待处理数据
     * @param callable      $worker      function(mixed $item, int $index): void
     * @param callable|null $finish      function(): void
     * @param int           $timeout     每个子任务最长运行秒数
     * @param int           $concurrency 并发数，0 表示按 CPU 自动计算
     */
    public static function run(
        string $name,
        array $items,
        callable $worker,
        ?callable $finish = null,
        int $timeout = 24 * 3600,
        int $concurrency = 0
    ): void {
        $total = count($items);
        if ($total > 0) {
            if ($concurrency <= 0) {
                $concurrency = self::defaultConcurrency();
            }
            $concurrency = max(1, min($concurrency, $total));
            $items = array_values($items);

            // 单条不另起进程，避免无意义的 fork 和等待轮询
            if ($total === 1) {
                self::runWorker($worker, $items[0], 0);
            } else {
                // 轮询分桶，避免慢任务集中在同一个子任务里
                $buckets = array_fill(0, $concurrency, []);
                foreach ($items as $i => $item) {
                    $buckets[$i % $concurrency][$i] = $item;
                }
                $tasks = [];
                $fallback = [];
                foreach ($buckets as $n => $chunk) {
                    $task = go("$name #" . ($n + 1), function () use ($chunk, $worker) {
                        foreach ($chunk as $i => $item) {
                            self::runWorker($worker, $item, $i);
                        }
                    }, $timeout);
                    if ($task === null) {
                        Logger::error("TaskPool 无法启动子任务 {$name} #" . ($n + 1) . "，改为当前进程执行");
                        $fallback[] = $chunk;
                    } else {
                        $tasks[] = $task;
                    }
                }
                // 启动失败的桶在当前进程跑，与已启动的子任务并行
                foreach ($fallback as $chunk) {
                    foreach ($chunk as $i => $item) {
                        self::runWorker($worker, $item, $i);
                    }
                }
                foreach ($tasks as $task) {
                    go_wait($task);
                }
            }
        }
        if ($finish !== null) {
            $finish();
        }
    }

    /**
     * 默认并发：cpu_cores * 2，且不超过 MAX_CONCURRENCY。
     */
    public static function defaultConcurrency(): int
    {
        $cores = max(1, (int)(config("cpu_cores") ?? 1));
        return max(1, min($cores * 2, self::MAX_CONCURRENCY));
    }

    /**
     * @param callable $worker function(mixed $item, int $index): void
     */
    private static function runWorker(callable $worker, mixed $item, int $index): void
    {
        try {
            $worker($item, $index);
        } catch (Throwable $e) {
            Logger::error("TaskPool 条目失败：" . $e->getMessage());
        }
    }
}
