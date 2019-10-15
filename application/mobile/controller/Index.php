<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * $Author: 当燃 2016-01-09
 */
namespace app\mobile\controller;
use app\home\logic\UsersLogic;
use Think\Db;
use think\Request;

class Index extends MobileBase {
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $mobile_flag = M('users')->where(['user_id'=>session('user')['user_id']])->getField('mobile');
        if($mobile_flag == null){
            $this->redirect(U('Mobile/User/userinfo',array('action'=>'mobile')));
        }

        $guanzhu_flag = M('users')->where(['user_id'=>session('user')['user_id']])->getField('guanzhu_flag');

        if($guanzhu_flag == null){
            M('users')->where(['user_id'=>session('user')['user_id']])->setField('guanzhu_flag',1);

            header('Location:'.'http://'.$_SERVER['SERVER_NAME'].U('Mobile/Index/erweima'));
            die;
        }
        $is_lock = M('users')->where(['user_id'=>session('user')['user_id']])->getField('is_lock');

        if($is_lock ==1){
            echo "<script>alert('您的账户已经被锁定')</script>";
            die;
        }
    }
    public function youxi1(){

//        $this->redirect("http://ck.dichangshangmao.com/youxi/sanxiao/index.html");

        return $this->fetch();
    }
    public function index(){
        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;        
            // 微信Jssdk 操作类 用分享朋友圈 JS            
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();              
            print_r($signPackage);
        */
        $hot_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_category')->where('level=1')->order('sort_order')->limit(9)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('thems',$thems);
        $this->assign('hot_goods',$hot_goods);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

        //秒杀商品
        $now_time = time();  //当前时间
        if(is_int($now_time/7200)){      //双整点时间，如：10:00, 12:00
            $start_time = $now_time;
        }else{
            $start_time = floor($now_time/7200)*7200; //取得前一个双整点时间
        }
        $end_time = $start_time+7200;   //结束时间
        $flash_sale_list = M('goods')->alias('g')
            ->field('g.goods_id,f.price')
            ->join('flash_sale f','g.goods_id = f.goods_id','LEFT')
            ->where("start_time = $start_time and end_time = $end_time")
            ->limit(3)->select();
        $this->assign('flash_sale_list',$flash_sale_list);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('favourite_goods',$favourite_goods);
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 模板列表
     */
    public function mobanlist(){
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach($arr as $key => $val)
        {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";            
        }        
    }
    
    /**
     * 商品列表页
     */
    public function goodsList(){
        $id = I('get.id/d',0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    
    public function ajaxGetMore(){
    	$p = I('p/d',1);
    	$favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->page($p,C('PAGESIZE'))->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
    	$this->assign('favourite_goods',$favourite_goods);
    	return $this->fetch();
    }
    
    //微信Jssdk 操作类 用分享朋友圈 JS
    public function ajaxGetWxConfig(){
    	$askUrl = I('askUrl');//分享URL
    	$weixin_config = M('wx_user')->find(); //获取微信配置
    	$jssdk = new \app\mobile\logic\Jssdk($weixin_config['appid'], $weixin_config['appsecret']);
    	$signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
    	if($signPackage){
    		$this->ajaxReturn($signPackage,'JSON');
    	}else{
    		return false;
    	}
    }
    public function erweima(){
        return $this->fetch();
    }



        /*
     * 抽奖
     * 1.抽奖前判断抽奖次数是否大于最近一次的抽奖钱数
     *  大于
     *      默认是抽不中
     *  等于
     *      开始抽奖
     */
    public function duobao()
    {
        $user_data = M('users')->where(array('user_id' => $_SESSION['user']['user_id']))->find();


        $this->assign(array(
            'user_data' =>  $user_data,
            'user_id'   =>  $_SESSION['user']['user_id'],
        ));
        return $this->fetch();
    }

    public $prize_arr = array(
        '0' => array('id' => 1, 'prize' => '恭喜您，抽到了10元奖金', 'v' => 1,'money'=>10),
        '1' => array('id' => 2, 'prize' => '恭喜您，抽到了8元奖金', 'v' => 5,'money'=>8),
        '2' => array('id' => 3, 'prize' => '恭喜您，抽到了6元奖金', 'v' => 10,'money'=>6),
        '3' => array('id' => 4, 'prize' => '恭喜您，抽到了3元奖金', 'v' => 12,'money'=>3),
        '4' => array('id' => 5, 'prize' => '恭喜您，抽到了2元奖金', 'v' => 22,'money'=>2),
        '5' => array('id' => 6, 'prize' => '下次没准就能中哦', 'v' => 50,'money'=>0),
    );

    public function rand_money()
    {
        $res_arr = array(
            'flag'  =>  0,
            'msg'   =>  '失败',
        );
        $user_id = $_SESSION['user']['user_id'];
        if($user_id == null){
                    $res_arr['msg'] = '错误';
            die(json_encode($res_arr));

        }
        $user_row = M('users')->where(array('user_id' => $user_id))->find();
        if($user_row['user_money'] < 1){
            $res_arr['msg'] = '余额不足请先充值余额';
            die(json_encode($res_arr));
        }
//        $data = array(
//            'user_id' => $user_row['user_id'],
//            'user_money' => -1,
//            'change_time' => time(),
//            'change_desc' => '1元夺宝消费',
//        );
//        M('account_log')->add($data);
    		accountLog($user_row['user_id'],-1,0,'1元夺宝消费',0);

        $duobao_list = M('duobao')->order('id desc')->where('money != 0')->find();
        if($duobao_list == null){
            $duobao_id = 0;
        }else{
            $duobao_id = $duobao_list['id'];
        }
        $now_money = count(M('duobao')->where('id>'.$duobao_id)->select());
        $cj_flag = 0;
        if($now_money >= $duobao_list['money']){
            $cj_flag = 1;
        }
        if($cj_flag == 0){
            $this->add_duobao(0);
            $res_arr['msg'] = '下次没准就能中哦。';
            $res_arr['flag'] = 1;
            die(json_encode($res_arr));
        }

        foreach ($this->prize_arr as $key => $val) {
            $arr[$val['id']] = $val['v'];
        }
        $res = $this->get_rand($arr);

        $res = $this->prize_arr[$res-1];
        $this->add_duobao($res['money']);
        if($res['money'] != 0){
//            $data = array(
//                'user_id' => $user_row['user_id'],
//                'user_money' => $res['money'],
//                'change_time' => time(),
//                'change_desc' => '1元夺宝收入',
//            );
//            M('ecs_account_log')->add($data);
//            M('ecs_users')->where(array('user_id' => $user_id))->setDec('user_money', $res['money']);

                		accountLog($user_row['user_id'],$res['money'],0,'1元夺宝收入',0);

        }
        $res_arr['msg'] = $res['prize'];
        $res_arr['flag'] = 1;
        die(json_encode($res_arr));
    }

    public function add_duobao($money=0){
        $data = array(
            'money' =>  $money,
        );
        $res =M('duobao')->add($data);
    }

    public function get_rand($proArr)
    {
        $result = '';

        //概率数组的总概率精度
        $proSum = array_sum($proArr);

        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);

        return $result;
    }

}
