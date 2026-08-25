<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 */

namespace nova\plugin\task;

use nova\framework\core\Instance;
use nova\framework\http\Request;
use nova\framework\http\Response;
use nova\framework\route\Route;
use nova\plugin\login\AdminPageInterface;
use nova\plugin\tpl\ViewResponse;
use function nova\framework\route;

/**
 * 后台任务面板页面（后台管理菜单项）。
 */
class TaskPanelTpl extends Instance implements AdminPageInterface
{
    public function registerRouter(string $model, string $controller): void
    {
        $default = route($model, $controller, 'init');
        Route::getInstance()
            ->get('/tasks/list', $default);
    }

    public function route(ViewResponse $view, Request $request): ?Response
    {
        if ($request->getPath() !== '/tasks/list') {
            return null;
        }

        return $view->asTpl(ROOT_PATH . DS . 'nova/plugin/task/tpl/list');
    }

    public function menu(): array
    {
        return [
            'title' => '后台任务',
            'icon' => 'manage_history',
            'url' => '/tasks/list',
            'pjax' => true,
        ];
    }
}
