<?php
/**
 * Created by PhpStorm.
 * User: nightdays
 * Date: 2019/1/14
 * Time: 22:05
 */
$redis = new Redis('127.0.0.1',6379);
$redis->connect();
$weibo_info = array(
    'uid'   =>  123,
    'content'=>'content131',
    'timestamp' =>  time()
);
$redis->lPush('weibo_list',json_encode($weibo_info));
$redis->close();


//存入
$redis = new Redis('127.0.0.1',6379);
$redis->connect();
$weibo = new Weibo();
while(TRUE){
    if($redis->lsize('weibo_list')>0){
        $info = $redis->rpop('weibo_list');
        $info = json_dncode($info);
        $weibo->post($info->uid,$info->connent,$info->timestamp);
    }else{
        sleep(1);
    }
}
$redis->close();