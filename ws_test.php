<?php
use Workerman\Worker;
use Workerman\Lib\Timer;
require_once __DIR__ . '/Workerman/Autoloader.php';

// 创建一个Worker监听2346端口，使用websocket协议通讯
$worker = new Worker("websocket://0.0.0.0:2346");

// 启动1个进程对外提供服务
$worker->count = 1;

// 进程启动时设置一个定时器，定时向所有客户端连接发送数据
$worker->onWorkerStart = function($worker)
{
    // 定时，每3秒一次
    Timer::add(10, function()use($worker)
    {
        $info['date'] = date("Y-m-d h:i:s");
        $json_info = json_encode($info);
        var_dump($json_info);
        // 遍历当前进程所有的客户端连接，发送当前服务器的时间
        foreach($worker->connections as $connection)
        {
            $connection->send($json_info);
        }
    });

//    Timer::add(3, function()
//    {
//       do_fight();
//    });

};

$global_uid = 0;
$fight_info = array();
$fight_info['state'] = 'ready';// ready fighting end
$fight_info['count'] = 0;

// 客户端联接时,保存客户端用户数据
$worker->onConnect = function($connection)
{
    global $global_uid;
    $connection->uid = ++$global_uid;

    echo "用户ID:";
    var_dump($connection->uid);

    $info['message'] = '用户ID:'.$connection->uid.'进入';
    $json_info = json_encode($info, true);
    var_dump($json_info);
//    $connection->send($json_info);

//   为什么这里不能群发呢?
//   // 群发;
//    if (is_array($connection->worker->connections)) {
//        foreach($connection->worker->connections as $con)
//        {
//            $con->send($json_info);
//        }
//    }


};


// 当收到客户端发来的数据后返回hello $data给客户端
$worker->onMessage = function($connection, $data)
{
    // 向客户端发送hello $data
    var_dump($connection->uid, $data);
    global $fight_info;
    $uid = $connection->uid;

    // 未战斗,则初始化.
    // 用户验证
    if ($data=='玩家A') {
        if(!isset($fight_info['A']) ) {
            $fight_info['A']['uid'] = $uid;
            $fight_info['A']['name'] = "玩家A:啦啦啦";
            $fight_info['A']['hp'] = rand(50,99);
            $fight_info['A']['dp'] = rand(5,10);
            $fight_info['count']+=1;
        }
    }

    if ($data=='玩家B') {
        if(!isset($fight_info['B']) ) {
            $fight_info['B']['uid'] = $uid;
            $fight_info['B']['name'] = "玩家B:嘟嘟嘟";
            $fight_info['B']['hp'] = rand(50,99);
            $fight_info['B']['dp'] = rand(5,10);
            $fight_info['count']+=1;
        }
    }

    if ($fight_info['state'] == 'ready' && $fight_info['count']<2) {
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
        if(isset($fight_info['A']) && $fight_info['A']['uid'] == $uid) {
            $fight_info['A']['direct'] = $data;
        }

        if(isset($fight_info['B']) && $fight_info['B']['uid'] == $uid) {
            $fight_info['B']['direct'] = $data;
        }

    }

    // 结束战斗
    if($fight_info['state'] == 'fighting'){

    }

    $json_info = json_encode($fight_info, true);
    // $connections->send($json_info);

    // 群发;
    foreach($connection->worker->connections as $con)
    {
        $con->send($json_info);
    }

};


function do_fight(){
    global $fight_info;
    if ($fight_info['state']=='begin'){

    }
}

$worker->onClose = function($connection)
{
    global $fight_info;

    $info['message'] = '用户ID:'.$connection->uid.'退出';
    $json_info = json_encode($info, true);
    // $connections->send($json_info);


    $fight_info['state'] = 'end';

    // 群发;
    foreach($connection->worker->connections as $con)
    {
        $con->send($json_info);
    }
};

// 运行worker
Worker::runAll();


