<?php

namespace nova\plugin\task;

use nova\plugin\task\schedule\TaskerServer;

class Schedule
{
    static function register(): void
    {
        TaskerServer::start();
    }
}