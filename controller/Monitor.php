<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 */

namespace nova\plugin\task\controller;

use nova\framework\http\Response;
use nova\plugin\login\controller\BaseAPIController;
use nova\plugin\task\TaskLogger;

/**
 * 后台任务监控控制器：列出任务、查看单任务日志。
 */
class Monitor extends BaseAPIController
{
    public function list(): Response
    {
        $data = TaskLogger::list();
        return Response::asJson([
            'code'  => 200,
            'count' => count($data),
            'data'  => $data,
        ]);
    }

    public function detail(): Response
    {
        $id = (string)$this->request->get('id', '');
        $record = TaskLogger::get($id);
        if ($record === null) {
            return Response::asJson(['code' => 404, 'msg' => '任务不存在']);
        }
        return Response::asJson(['code' => 200, 'data' => $record]);
    }
}
