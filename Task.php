<?php

namespace nova\plugin\task;

use Closure;
use Exception;
use nova\framework\App;
use nova\framework\cache\Cache;
use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\log\Logger;
use nova\framework\request\Request;
use nova\framework\request\Response;

class Task
{
    const int TIMEOUT = 300;

    public static function register(): void
    {
        if (function_exists("nova\\plugin\\task\\go")) {
            return;
        }
        include __DIR__ . "/helper.php";
        EventManager::addListener("route.before", function ($event, &$data) {
            if ($data == "/task/start") {
                Task::response();
            }
        });
    }

    /**
     * @throws AppExitException
     */
    private static function response(): void
    {
        self::noWait();
        $key = App::getInstance()->getReq()->getHeaderValue("Token") ?? "";
        Logger::info("Tasker Key：" . $key);
        $task = self::getTask($key);

        if (empty($task)) {
            throw new AppExitException(Response::asText("task not found"), "Response Task Fail");
        }

        $function = $task->function;
        $timeout = $task->timeout ?? 60;
        Logger::info("Response Tasker Key：" . $key . " Timeout：" . $timeout);
        set_time_limit($timeout);
        if (!empty($function) && $function instanceof Closure) {
            $function();
        }
        $cache = new Cache();
        $cache->delete($key);
        throw new AppExitException(Response::asText("task success"), "Response Task Success");
    }

    public static function noWait(int $time = 0): void
    {
        session_write_close();
        ignore_user_abort(true);
        set_time_limit($time);
        ob_end_clean();
        ob_start();
        header("Connection: close");
        header("HTTP/1.1 200 OK");
        header("Content-Length: 0");
        ob_end_flush();
        flush();
        if (function_exists("fastcgi_finish_request")) {
            fastcgi_finish_request();
        }

    }

    private static function getTask($key): ?TaskObject
    {
        try {
            $cache = new Cache();
            $result = $cache->get($key);
            return __unserialize($result);
        } catch (Exception $exception) {
            Logger::error("Tasker Error：" . $exception->getMessage());
            return null;
        }
    }

    public static function start(Closure $function, int $timeout = 300, int $tries = 0): ?TaskObject
    {
        if ($tries > 10) {
            Logger::error("Tasker Error：Tasker Start Fail, tries > 10");
            return null;
        }
        $key = uniqid("task_");
        Logger::info("Tasker Key：" . $key . " Timeout：" . $timeout);
        $taskObject = new TaskObject();
        $taskObject->timeout = $timeout;
        $taskObject->function = $function;
        $taskObject->key = $key;

        self::putTask($taskObject);

        try {
            if (php_sapi_name() == "cli") {
                // 命令行环境下的任务启动
                return self::startTaskCli($taskObject);
            } else {
                // Web 环境下的任务启动
                return self::startTaskWeb($taskObject);
            }
        } catch (Exception $exception) {
            Logger::error("Tasker Error：" . $exception->getMessage());
            return self::start($function, $timeout, $tries + 1);
        }

    }

    private static function putTask(TaskObject $task): void
    {
        try {
            $data = __serialize($task);
            $cache = new Cache();
            $cache->set($task->key, $data, $task->timeout);
        } catch (Exception $exception) {
            Logger::error("Tasker Error：" . $exception->getMessage());
        }
    }

    private static function startTaskCli(TaskObject $taskObject): ?TaskObject
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            Logger::error("Tasker Error：Unable to fork process");
            return null;
        } elseif ($pid) {
            // 父进程逻辑
            Logger::info("Tasker Start：Parent process, PID $pid");
            return $taskObject;
        } else {
            // 子进程逻辑
            Logger::info("Tasker Start：Child process, executing task");
            try {
                $obj = self::getTask($taskObject->key);
                $function = $obj->function;
                $function();
                self::cleanupTask($obj->key);
                exit(0);
            } catch (Exception $exception) {
                Logger::error("Tasker Error：" . $exception->getMessage());
                exit(1);
            }
        }
    }

    private static function cleanupTask($key): void
    {
        $cache = new Cache();
        $cache->delete($key);
        Logger::info("Tasker Cleanup：Task with key $key has been cleaned up");
    }

    private static function startTaskWeb(TaskObject $taskObject): ?TaskObject
    {
        $req = new Request();
        $url = $req->getBasicAddress() . "/task/start";

        Logger::info("Tasker Start：" . $url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $dns = [
            $req->getDomainNoPort() . ':' . $req->port() . ':' . $req->getServerIp(),
        ];
        Logger::info("Tasker DNS：" . json_encode($dns));
        curl_setopt($ch, CURLOPT_RESOLVE, $dns);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Token: ' . $taskObject->key,
            'Connection: Close'
        ]);
        curl_exec($ch);
        sleep(1);
        // 获取curl响应码
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        Logger::info("Tasker Result $httpCode ：" . curl_error($ch));
        curl_close($ch);
        if ($httpCode !== 200) {
            throw new Exception("Tasker Start Fail");
        }
        return $taskObject;
    }

    public static function wait(TaskObject $taskObject): void
    {
        $time = 0;
        $cache = new Cache();

        while (true) {
            if ($cache->get($taskObject->key) === null) {
                break;
            }
            sleep(1);
            $time++;
            if ($time > 60 * 60 * 24) {
                break;
            }
        }
    }

}