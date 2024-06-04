<?php
namespace nova\plugin\task;
class TaskObject
{
    const START = 0;
    const END = 1;
    const WAIT = 2;
    public string $key = "";
    public int $timeout = 0;//超时时间
    public  $function ;
    public int $state = 0;
}