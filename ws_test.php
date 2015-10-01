<?php
use Workerman\Worker;
use Workerman\Lib\Timer;
require_once __DIR__ . '/Workerman/Autoloader.php';

// 创建一个Worker监听2346端口，使用websocket协议通讯
$ws_worker = new Worker("websocket://0.0.0.0:2346");

// 启动1个进程对外提供服务
$ws_worker->count = 1;

// 进程启动时设置一个定时器，定时向所有客户端连接发送数据
$ws_worker->onWorkerStart = function($ws_worker)
{
    // 定时，每3秒一次
    Timer::add(3, function()use($ws_worker)
    {
        $info['date'] = date("Y-m-d h:i:s");
        $json_info = json_encode($info);
        var_dump($json_info);
        // 遍历当前进程所有的客户端连接，发送当前服务器的时间
        foreach($ws_worker->connections as $connection)
        {
            $connection->send($json_info);
        }
    });
};


//// 当收到客户端发来的数据后返回hello $data给客户端
$ws_worker->onMessage = function($connections, $data)
{
    // 向客户端发送hello $data
    var_dump($data);
    $info['fight'] = $data;
    $json_info = json_encode($info, true);
    $connections->send($json_info);

};

// 运行worker
Worker::runAll();


