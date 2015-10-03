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

};

$global_uid = 0;
$fight_info = array();
$fight_info['state'] = 'ready';// ready fighting end
$fight_info['count'] = 0;

$fight_list = array();// 战斗记录

// 客户端联接时,保存客户端用户数据
$worker->onConnect = function($connection)
{
    global $global_uid;
    $connection->uid = ++$global_uid;

    echo "用户ID:";
    var_dump($connection->uid);

    $info['msg'] = '用户ID:'.$connection->uid.'进入';
    $json_info = json_encode($info, true);
    var_dump($json_info);
//    $connection->send($json_info);

//   为什么这里不能群发呢?
//   // todo 群发通知用户登陆登出;
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
    global $fight_info, $timer_id;
    $uid = $connection->uid;

    // 未战斗,则初始化.
    // 用户验证 以及数据初始化
    $fight_info = user_init($data, $fight_info, $uid);

    if ($fight_info['state'] == 'ready' && $fight_info['count']<2) {
        $fight_info['fight_sort'] = 0;//战斗回合.
        $fight_info['msg'] = "正在等待玩家进入";
    }

    // 触发战斗
    if ($fight_info['state'] == 'ready' && $fight_info['count']==2) {
        $fight_info['state'] = 'fighting';
        $fight_info['msg'] = "玩家就绪,战斗开始";
        $fight_info['A']['direct'] = 'middle';
        $fight_info['B']['direct'] = 'middle';
        echo "战斗开始.";

        // 改变图片.群发一次.
        $fight_info['img'] = 'images/sword_man_stand.jpg';
        $json_info = json_encode($fight_info, true);
        foreach($connection->worker->connections as $con)
        {
            $con->send($json_info);
        }

        do_fight();

        // 启动战斗定时器.3秒1回合.
        $timer_id = Timer::add(3, function()
        {
            do_fight();
        });
    }

    // 战斗中的双方操作
    if($fight_info['state'] == 'fighting'){

        if(isset($fight_info['A']) && $fight_info['A']['uid'] == $uid) {
            // 只需要操作上下.
            if ($data!='up' && $data != 'down'){
                $fight_info['A']['direct'] = 'middle';
            } else {
                $fight_info['A']['direct'] = $data;
            }

        }

        if(isset($fight_info['B']) && $fight_info['B']['uid'] == $uid) {
            if ($data!='up' && $data != 'down'){
                $fight_info['B']['direct'] = 'middle';
            } else {
                $fight_info['B']['direct'] = $data;
            }
        }

    }

    $fight_info['img'] = "";
    $json_info = json_encode($fight_info, true);
    // $connections->send($json_info);

    // 群发;
    foreach($connection->worker->connections as $con)
    {
        $con->send($json_info);
    }

};


function do_fight(){
    global $fight_info, $worker, $timer_id, $fight_list;
    if ($fight_info['state']=='fighting'){

        $num = $fight_info['fight_sort'];

        // 判断回合数,第0回合,速度快的攻击.以后回合.单数A攻击,双数B攻击.
        if($fight_info['fight_sort']==0)
        {
            $fight_info['A']['act'] = 'attack';
            $fight_info['B']['act'] = 'defend';

        } else {
            if ($fight_info['fight_sort']%2 == 0){
                $fight_info['A']['act'] = 'defend';
                $fight_info['B']['act'] = 'attack';
            } else {
                $fight_info['A']['act'] = 'attack';
                $fight_info['B']['act'] = 'defend';
            }
        }

        $fight_list[$num]['A'] = $fight_info['A'];
        $fight_list[$num]['B'] = $fight_info['B'];


        // 战斗.根据上一回合显示的攻防状态,判断.
        $fight_msg = '';
        $fight_img = '';
        if (isset($fight_list[$num-1])) {
            echo "进入战斗循环";
            var_dump($fight_list[$num-1]);
            if ( $fight_list[$num-1]['A']['act'] == 'attack') {
                // 如果防守方没有防御住.
                if ($fight_info['A']['direct'] !== $fight_info['B']['direct']) {
                    // 战斗公式
                    $hurt = ceil($fight_info['A']['ap'] - $fight_info['B']['dp']/2);
                    $fight_list[$num]['hurt'] = $hurt;
                    $fight_info['B']['hp'] -= $hurt;
                    $fight_msg = $fight_info['A']['name'].'砍了'.$fight_info['B']['name'].'一刀';
                } else {
                    $hurt = 0;
                    $fight_list[$num]['hurt'] = $hurt;
                    $fight_msg = $fight_info['B']['name'].'挡住了'.$fight_info['A']['name'].'的攻击';
                }

                $fight_img = "images/sword_man_a_attack_".$fight_info['A']['direct']."_b_defend_".$fight_info['B']['direct'].".jpg";

            } else {
                // 如果防守方没有防御住.
                if ($fight_info['A']['direct'] !== $fight_info['B']['direct']) {
                    // 战斗公式
                    $hurt = ceil($fight_info['B']['ap'] - $fight_info['A']['dp']/2);
                    $fight_list[$num]['hurt'] = $hurt;
                    $fight_info['A']['hp'] -= $hurt;
                    $fight_msg = $fight_info['B']['name'].'砍了'.$fight_info['A']['name'].'一刀';
                } else {
                    $hurt = 0;
                    $fight_list[$num]['hurt'] = $hurt;
                    $fight_msg = $fight_info['A']['name'].'挡住了'.$fight_info['B']['name'].'的攻击';
                }
                $fight_img = "images/sword_man_b_attack_".$fight_info['B']['direct']."_a_defend_".$fight_info['A']['direct'].".jpg";
            }
        }

        // 群发数据
        $fight_info['msg'] = '第'.($num-1).'回合<br />'.$fight_msg.'<br />  第'.$fight_info['fight_sort'].'回合.<br />';
        $fight_info['img'] = $fight_img;
        $fight_list[$num]['msg'] = $fight_info['msg'];
        $fight_list[$num]['img'] = $fight_info['img'];
        $json_info = json_encode($fight_info, true);
        foreach($worker->connections as $con)
        {
            $con->send($json_info);
        }




        // 更新双方数据.

        // 判断是否结束.
        if ($fight_info['A']['hp']<=0 || $fight_info['B']['hp']<=0){

            if($fight_info['A']['hp'] > $fight_info['B']['hp']) {
                $winner = $fight_info['A']['name'];
            } else {
                $winner = $fight_info['B']['name'];
            }
            $fight_info['msg'] .= '<br /><font color=red>战斗于第'.$fight_info['fight_sort'].'回合结束.<br />'.$winner."获得胜利!</font>";

            Timer::del($timer_id);

            // 结束战斗
            if($fight_info['state'] == 'fighting'){
                $fight_info['state'] = 'end';
            }

            // 群发数据
            $json_info = json_encode($fight_info, true);
            foreach($worker->connections as $con)
            {
                $con->send($json_info);
            }

            // todo 这里想通知不同的用户不同的战斗结果.
            // 怎样获取用户的映射关系呢?

            echo "所有战斗记录:";
            var_dump($fight_list);
            echo "战斗结束.";
        } else {
            // 回合加1.
            $fight_info['fight_sort'] += 1;

        }



    }
}

//$worker->onClose = function($connection)
//{
//    global $fight_info;
//
//    $info['msg'] = '用户ID:'.$connection->uid.'退出';
//    $json_info = json_encode($info, true);
//    // $connections->send($json_info);
//
//
//    $fight_info['state'] = 'end';
//
//    // 群发;
//    foreach($connection->worker->connections as $con)
//    {
//        $con->send($json_info);
//    }
//};

// 运行worker
Worker::runAll();

/**
 * 处理用户登陆,获取用户数据
 *
 * @param $data
 * @param $fight_info
 * @param $uid
 * @return mixed
 */
function user_init($data, $fight_info, $uid)
{
    // 这里只是简单的赋值,并没有真的验证用户和获取用户数据.
    if ($data == '玩家A') {
        if (!isset($fight_info['A'])) {
            $fight_info['A']['uid'] = $uid;
            $fight_info['A']['name'] = "啦啦啦";
            $fight_info['A']['hp'] = rand(50, 99);
            $fight_info['A']['ap'] = rand(20, 50);
            $fight_info['A']['dp'] = rand(5, 10);
            $fight_info['A']['sp'] = rand(50, 99); // 速度
            $fight_info['count'] += 1;
        }
    }

    if ($data == '玩家B') {
        if (!isset($fight_info['B'])) {
            $fight_info['B']['uid'] = $uid;
            $fight_info['B']['name'] = "嘟嘟嘟";
            $fight_info['B']['hp'] = rand(50, 99);
            $fight_info['B']['ap'] = rand(20, 50);
            $fight_info['B']['dp'] = rand(5, 10);
            $fight_info['B']['sp'] = rand(50, 99); // 速度
            $fight_info['count'] += 1;
        }
    }

    return $fight_info;
}
