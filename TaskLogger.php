<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 */

namespace nova\plugin\task;

use nova\framework\core\Context;
use nova\framework\core\Logger;
use Throwable;

/**
 * 后台任务日志记录器。
 *
 * 每个后台任务在 cache 里维护一条记录：状态、起止时间、最近 N 条过程日志（环形缓冲）。
 * go() 传入任务名时自动调用 start()，结束/异常时自动 finish()/fail()，并默认写入「开始」「结束」两条日志。
 * 任务体内可直接调用 TaskLogger::log() 写自定义过程日志——它通过进程内静态「当前任务 ID」定位记录，无需手动传 ID。
 *
 * 说明：go() 的每个任务都在独立进程执行（CLI fork / Web 自调 /task/start），
 * 因此 $currentId 静态变量天然按任务进程隔离；记录写共享 cache，面板普通请求即可读取。
 */
class TaskLogger
{
    /** cache key 前缀，与 task_pool/ tasker_list 区分 */
    private const string PREFIX = 'task_record/';

    /** 记录保留时长（秒），运行中每次写日志会续期 */
    private const int TTL = 86400;

    /** 已完成任务过期时长（毫秒），超过此时间自动清理 */
    private const int FINISHED_EXPIRE_MS = 6 * 3600 * 1000;

    /** 单任务日志环形缓冲上限 */
    private const int MAX_LOGS = 500;

    /** 当前进程正在执行的任务 ID（go() 带任务名时设置） */
    private static string $currentId = '';

    /**
     * 开始一个任务记录，返回任务 ID，并设为当前任务。
     *
     * @param int $maxTime 最长运行时间（秒），0 表示常驻任务不限时
     */
    public static function start(string $name, int $maxTime = 0): string
    {
        $id = uniqid('trec_');
        self::$currentId = $id;

        $now = self::now();
        $record = [
            'id'      => $id,
            'name'    => $name !== '' ? $name : '未命名任务',
            'status'  => 'running',
            'pid'     => getmypid() ?: 0,
            'start'   => $now,
            'end'     => 0,
            'maxTime' => $maxTime * 1000,
            'logs'    => [],
        ];
        self::save($id, $record);
        self::log($maxTime > 0 ? "任务开始，最长运行 {$maxTime} 秒" : '任务开始（常驻）');
        return $id;
    }

    /**
     * 写一条过程日志到「当前任务」。无当前任务时静默忽略。
     *
     * @param string $level info|warn|error
     */
    public static function log(string $msg, string $level = 'info'): void
    {
        $id = self::$currentId;
        if ($id === '') {
            return;
        }
        $record = self::get($id);
        if ($record === null) {
            return;
        }

        $record['logs'][] = ['t' => self::now(), 'level' => $level, 'msg' => $msg];
        if (count($record['logs']) > self::MAX_LOGS) {
            $record['logs'] = array_slice($record['logs'], -self::MAX_LOGS);
        }
        self::save($id, $record);
    }

    /**
     * 标记当前任务完成。
     */
    public static function finish(string $msg = '任务结束'): void
    {
        self::close('done', $msg);
    }

    /**
     * 标记当前任务失败。
     */
    public static function fail(Throwable $e): void
    {
        $id = self::$currentId;
        if ($id !== '') {
            self::log('任务异常：' . $e->getMessage(), 'error');
        }
        self::close('failed', '任务失败');
    }

    /**
     * 当前进程正在执行的任务 ID（无则空串）。
     */
    public static function current(): string
    {
        return self::$currentId;
    }

    /**
     * 列出所有正在运行的任务，按开始时间倒序。
     *
     * @return array<int, array<string,mixed>>
     */
    public static function running(): array
    {
        return array_values(array_filter(
            self::list(),
            static fn (array $r): bool => $r['status'] === 'running'
        ));
    }

    /**
     * 列出所有任务记录，按开始时间倒序。已完成超过6小时的记录会被自动清理。
     *
     * @return array<int, array<string,mixed>>
     */
    public static function list(): array
    {
        $all = Context::instance()->cache->getAll(rtrim(self::PREFIX, "/")) ?: [];
        $now = self::now();
        $cache = Context::instance()->cache;

        $records = [];
        foreach ($all as $record) {
            if (!is_array($record) || !isset($record['id'])) {
                continue;
            }
            if ($record['status'] !== 'running' && $record['end'] > 0 && ($now - $record['end']) > self::FINISHED_EXPIRE_MS) {
                $cache->delete(self::PREFIX . $record['id']);
                continue;
            }
            if ($record['status'] === 'running') {
                $record = self::reconcileRunningRecord($record, $now, $cache);
            }
            $records[] = $record;
        }
        usort($records, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);
        return $records;
    }

    /**
     * 取单条任务记录。
     *
     * @return array<string,mixed>|null
     */
    public static function get(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        $record = Context::instance()->cache->get(self::PREFIX . $id);
        return is_array($record) ? $record : null;
    }

    private static function close(string $status, string $msg): void
    {
        $id = self::$currentId;
        if ($id === '') {
            return;
        }
        $record = self::get($id);
        if ($record !== null) {
            $record['status'] = $status;
            $record['end'] = self::now();
            $record['logs'][] = ['t' => self::now(), 'level' => $status === 'failed' ? 'error' : 'info', 'msg' => $msg];
            self::save($id, $record);
        }
        self::$currentId = '';
    }

    /**
     * @param array<string,mixed> $record
     */
    private static function save(string $id, array $record): void
    {
        try {
            Context::instance()->cache->set(self::PREFIX . $id, $record, self::TTL);
        } catch (Throwable $e) {
            Logger::error('[TaskLogger] 写入任务记录失败: ' . $e->getMessage());
        }
    }

    private static function now(): int
    {
        return (int)(microtime(true) * 1000);
    }

    /**
     * 将 running 记录与真实进程对齐：超时或 worker 已死则标为 failed。
     *
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private static function reconcileRunningRecord(array $record, int $now, $cache): array
    {
        if (($record['maxTime'] ?? 0) > 0 && ($now - $record['start']) > $record['maxTime']) {
            $record['status'] = 'failed';
            $record['end'] = $now;
            $record['logs'][] = ['t' => $now, 'level' => 'error', 'msg' => '任务超时'];
            $cache->set(self::PREFIX . $record['id'], $record, self::TTL);

            return $record;
        }

        $pid = (int)($record['pid'] ?? 0);
        $alive = self::isProcessAlive($pid);
        if ($pid > 0 && $alive === false) {
            $record['status'] = 'failed';
            $record['end'] = $now;
            $record['logs'][] = [
                't' => $now,
                'level' => 'error',
                'msg' => "worker 进程已退出(pid={$pid})，任务中断",
            ];
            $cache->set(self::PREFIX . $record['id'], $record, self::TTL);
            Logger::warning('[TaskLogger] 僵尸任务已清理: ' . ($record['name'] ?? '') . " pid={$pid}");
        }

        return $record;
    }

    /**
     * @return bool|null true=存活, false=已退出, null=当前环境无法判断
     */
    private static function isProcessAlive(int $pid): ?bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (PHP_OS_FAMILY === 'Linux') {
            return is_dir("/proc/{$pid}");
        }

        return null;
    }
}
