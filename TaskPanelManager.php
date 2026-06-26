<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 */

namespace nova\plugin\task;

use Closure;
use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\framework\core\StaticRegister;
use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;

use function nova\framework\isWorkerman;

use nova\framework\route\RouteTrait;
use nova\plugin\login\AdminPage;
use nova\plugin\login\route\Permission;

/**
 * 后台任务面板路由注册。
 *
 * 面板使用 /tasks 前缀，与任务内部自调端点 /task/start 完全隔离，互不影响。
 */
class TaskPanelManager extends StaticRegister
{
    use RouteTrait;

    public function __construct()
    {
        $this->controllerNamespace = 'nova\\plugin\\task\\controller\\';
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->get('/tasks/api/list', $this->map('monitor', 'list'));
        $this->get('/tasks/api/detail', $this->map('monitor', 'detail'));
    }

    public static function registerInfo(): void
    {
        include_once __DIR__ . "/helper.php";

        EventManager::addListener("route.before", function ($event, &$data) {
            if ($data === "/task/start") {
                self::response();
            }
        });
        Permission::getInstance()->registerPermissions('后台任务', 'task_manage', [
            'ANY /tasks*',
        ]);

        self::getInstance()->bindPrefixDispatch('/tasks');
        AdminPage::bind(TaskPanelTpl::getInstance());
    }
    private static function response(): void
    {
        self::noWait();
        $key = Context::instance()->request()->getHeaderValue("Token") ?? "";
        Logger::info("Tasker Key：" . $key);
        $task = Task::getTask($key);

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
        $cache = Context::instance()->cache;
        $cache->delete($key);
        throw new AppExitException(Response::asText("task success"), "Response Task Success");
    }

    public static function noWait(int $time = 0): void
    {

        // 传统 PHP-FPM 环境下的处理
        session_write_close();
        ignore_user_abort(true);
        set_time_limit($time);
        if (isWorkerman()) {
            // WorkermanApp::instance()->sendResponse();
            return;
        }
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
