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
 * 后台任务监控控制器：列出任务、查看日志、清理已完成任务、强制终止。
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

    public function kill(): Response
    {
        $id = (string)$this->request->post('id', '');
        if ($id === '') {
            return Response::asJson(['code' => 400, 'msg' => '缺少任务 ID']);
        }
        $result = TaskLogger::kill($id);
        return Response::asJson([
            'code' => $result['ok'] ? 200 : 400,
            'msg'  => $result['msg'],
        ]);
    }

    public function remove(): Response
    {
        $id = (string)$this->request->post('id', '');
        if ($id === '') {
            return Response::asJson(['code' => 400, 'msg' => '缺少任务 ID']);
        }
        if (!TaskLogger::remove($id)) {
            return Response::asJson(['code' => 400, 'msg' => '无法清理该任务（不存在或仍在运行）']);
        }
        return Response::asJson(['code' => 200, 'msg' => '已清理']);
    }

    public function cleanup(): Response
    {
        $count = TaskLogger::cleanupFinished();
        return Response::asJson([
            'code'  => 200,
            'count' => $count,
            'msg'   => $count > 0 ? "已清理 {$count} 条已完成任务" : '没有可清理的已完成任务',
        ]);
    }
}
