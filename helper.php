<?php
namespace nova\plugin\task;
//闭包序列化
use Closure;
use nova\framework\log\Logger;
use nova\plugin\task\closure\Exceptions\PhpVersionNotSupportedException;
use nova\plugin\task\closure\SerializableClosure;

function traversalClosure($array, $callback)
{
    if ($array instanceof Closure) {
        $callback($array);
        return $array;
    }
    if (is_string($array) && str_starts_with($array, "__SerializableClosure__")) {
        $item = substr($array, 23);
        $callback($item);
        return $array;
    }
    if (is_array($array) || (is_object($array))) {
        foreach ($array as &$item) {
            if (is_array($item) || (is_object($item) && !$item instanceof Closure)) {
                $item = traversalClosure($item, $callback);
            } elseif ($item instanceof Closure) {
                $callback($item);
            } elseif (is_string($item) && str_starts_with($item, "__SerializableClosure__")) {
                $item = substr($item, 23);
                $callback($item);
            }
        }
    }
    return $array;
}

/**
 * Serialize
 *
 * @param mixed $data
 * @return string
 */
function __serialize(mixed $data): string
{

    return serialize(traversalClosure($data, function (&$item) {
        try {
            $item = "__SerializableClosure__" . serialize(new SerializableClosure($item));
        } catch (PhpVersionNotSupportedException $e) {
            Logger::error("PhpVersionNotSupportedException: ". $e->getMessage());
            $item = "";
        }
    }));
}

/**
 * unSerialize
 *
 * @param string|null $data
 * @return mixed
 */
function __unserialize(?string $data): mixed
{
    if (empty($data)) return null;
    $result = unserialize($data);
    traversalClosure($result, function (&$item) {
        $item = unserialize($item)->getClosure();
    });
    return $result;
}

/**
 * 启动一个异步任务
 * @param Closure $function 任务函数
 * @param int $timeout 异步任务的最长运行时间,单位为秒
 */
function go(Closure $function, int $timeout = 300): ?TaskObject
{
    return Task::start($function, $timeout);
}


