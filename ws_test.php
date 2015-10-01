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

    Timer::add(3, function()
    {
       do_fight();
    });

};


// 客户端联接时,保存客户端用户数据
$ws_worker->onConnect = function($connections)
{
    global $ws_worker, $global_uid;
    $connections->uid = ++$global_uid;

    echo "用户ID:";
    var_dump($connections->uid);

};

$global_uid = 0;
$fight_info = array();
$fight_info['state'] = 'ready';// ready fighting end
$fight_info['count'] = 0;

// 当收到客户端发来的数据后返回hello $data给客户端
$ws_worker->onMessage = function($connections, $data)
{
    // 向客户端发送hello $data
    var_dump($connections->uid, $data);
    global $fight_info, $ws_worker;
    $uid = $connections->uid;

    // 未战斗,则初始化.
    // 用户验证
    if ($data=='玩家A') {
        if(!isset($fight_info['A']) ) {
            $fight_info['A']['uid'] = $uid;
            $fight_info['A']['name'] = "玩家A:啦啦啦";
            $fight_info['A']['hp'] = rand(50,99);
            $fight_info['A']['dp'] = rand(5,10);
        }
    }

    if ($data=='玩家B') {
        if(!isset($fight_info['B']) ) {
            $fight_info['B']['uid'] = $uid;
            $fight_info['B']['name'] = "玩家B:嘟嘟嘟";
            $fight_info['B']['hp'] = rand(50,99);
            $fight_info['B']['dp'] = rand(5,10);
        }
    }

    if ($fight_info['state'] == 'ready' && $fight_info['count']<2) {
        $fight_info['count']+=1;
        $fight_info['fight_sort'] = 0;//战斗回合.
    }

    // 触发战斗
    if ($fight_info['state'] == 'ready' && $fight_info['count']==2) {
        $fight_info['state'] = 'fighting';
        $fight_info['fight_sort'] += 1;

    }

    // 战斗中
    if($fight_info['state'] == 'fighting'){
        $fight_info['fight_sort'] += 1;
        if($fight_info['A']['uid'] == $uid) {
            $fight_info['A']['direct'] = $data;
        }
        else if($fight_info['B']['uid'] == $uid) {
            $fight_info['B']['direct'] = $data;
        }

    }

    // 结束战斗
    if($fight_info['state'] == 'fighting'){

    }

    $info['fight'] = $fight_info;
    $json_info = json_encode($info, true);
    // $connections->send($json_info);

    // 群发;
    foreach($ws_worker->connections as $connection)
    {
        $connection->send($json_info);
    }

};


function do_fight(){
    global $fight_info;
    if ($fight_info['state']=='begin'){

    }
}

// 运行worker
Worker::runAll();


