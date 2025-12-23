<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace nova\plugin\task;

//闭包序列化
use Closure;
use nova\framework\core\Logger;
use nova\plugin\task\closure\Exceptions\PhpVersionNotSupportedException;
use nova\plugin\task\closure\SerializableClosure;

/**
 * 通用递归遍历器
 * 只负责“遍历”，不修改结构、不做任何 prefix 去除动作！
 */
function traversalClosure(&$value, callable $callback): void
{
    // 1. 处理 Closure
    if ($value instanceof Closure) {
        $callback($value);
        return;
    }

    // 2. 处理数组
    if (is_array($value)) {
        foreach ($value as &$item) {
            traversalClosure($item, $callback);
        }
        return;
    }

    // 3. 处理对象（但不触碰 Closure 本身）
    if (is_object($value) && !$value instanceof Closure) {
        foreach ($value as $k => &$v) {
            traversalClosure($v, $callback);
        }
        return;
    }

    // 4. 处理 closure 前缀字符串（序列化后的标记）
    if (is_string($value) && str_starts_with($value, "__SerializableClosure__")) {
        $callback($value);
        return;
    }

    // 其他类型不用处理
}

/**
 * 序列化入口
 */
function __serialize($data): string
{
    traversalClosure($data, function (&$item) {
        if ($item instanceof Closure) {
            try {
                $item = "__SerializableClosure__" . serialize(new SerializableClosure($item));
            } catch (PhpVersionNotSupportedException $e) {
                Logger::error("PhpVersionNotSupportedException: " . $e->getMessage());
                $item = "";
            }
        }
    });

    return serialize($data);
}

/**
 * 反序列化入口
 */
function __unserialize(?string $data)
{
    if (empty($data)) {
        return null;
    }

    $result = unserialize($data);

    traversalClosure($result, function (&$item) {
        if (is_string($item) && str_starts_with($item, "__SerializableClosure__")) {
            $encoded = substr($item, 23); // 去掉前缀
            $closure = unserialize($encoded)->getClosure();
            $item = $closure;
        }
    });

    return $result;
}

/**
 * 启动一个异步任务
 * @param Closure $function 任务函数
 * @param int     $timeout  异步任务的最长运行时间,单位为秒
 */
function go(Closure $function, int $timeout = 300): ?TaskObject
{
    return Task::start($function, $timeout);
}

function go_wait(?TaskObject $taskObj)
{
    if ($taskObj === null) {
        return;
    }
    Task::wait($taskObj);
}

/**
 * 并发跑任务
 *
 * @template T
 * @param array<T> $items   要处理的任务列表
 * @param int      $timeout 超时
 * @param callable $worker  业务处理器：function (T $item, int $index, ...$extra): void
 * @param callable $finish
 */
function run_pool(array $items, int $timeout, callable $worker, callable $finish): void
{
    PoolManager::instance($timeout)->runPool($items, $worker, $finish);
}
