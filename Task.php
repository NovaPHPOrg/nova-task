<?php
namespace nova\plugin\task;
use Closure;
use nova\framework\App;
use nova\framework\cache\Cache;
use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\log\Logger;
use nova\framework\request\Response;
use nova\framework\request\Route;
use nova\framework\request\RouteObject;

class Task
{
    const TIMEOUT = 50;
    public static function register(): void
    {
        include __DIR__."/helper.php";
        EventManager::addListener("onBeforeRoute", function ($event, &$data) {
            if (Route::$uri == "/task/start") {
                Task::response();
            }
        });
    }

    public static function start(Closure $function, int $timeout = 300): ?TaskObject
    {
        $key = uniqid("task_");

        $taskObject = new TaskObject();
        $taskObject->timeout = $timeout;
        $taskObject->function = $function;
        $taskObject->key = $key;

        self::putTask($taskObject);

        $url = Route::$root."/task/start";

        Logger::info("Tasker Start：".$url);
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Token: '.$key,
                'Connection: Close'
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $exception) {
           Logger::error("Tasker Error：".$exception->getMessage());
            return null;
        }


        return $taskObject;
    }
    
    private static function putTask(TaskObject $task): void
    {
        try {
            $data = __serialize($task);
            $cache = new Cache();
            $cache->set($task->key, $data,$task->timeout);
        }catch (\Exception $exception){
            Logger::error("Tasker Error：".$exception->getMessage());
        }
    }
    
    
    private static function getTask($key):?TaskObject
    {
       try{
           $cache = new Cache();
           $result = $cache->get($key);
           $cache->delete($key);
           return __unserialize($result);
       }catch (\Exception $exception){
           Logger::error("Tasker Error：".$exception->getMessage());
           return null;
       }
    }

    /**
     * @throws AppExitException
     */
    private static function response(): void
    {
        self::noWait();
        $key = App::getInstance()->getReq()->getHeaderValue("Token") ?? "";
        $task =  self::getTask($key);
        if (empty($task)) {
            throw new AppExitException(Response::asText("task not found"));
        }

        $function = $task->function;
        $timeout = $task->timeout ?? 60;

        set_time_limit($timeout);
        if (!empty($function) && $function instanceof Closure) {
            $function();
        }
        throw new AppExitException(Response::asText("task success"));
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

}