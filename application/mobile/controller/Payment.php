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
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace app\mobile\controller;

use think\Db;
use think\Request;

class Payment extends MobileBase
{

    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code

    /**
     * 析构流函数
     */
        public function  __construct() {
            parent::__construct();

            // tpshop 订单支付提交
            $pay_radio = $_REQUEST['pay_radio'];
            if(!empty($pay_radio))
            {
                $pay_radio = parse_url_param($pay_radio);
                $this->pay_code = $pay_radio['pay_code']; // 支付 code
            }
            else // 第三方 支付商返回
            {
                //$_GET = I('get.');
                //file_put_contents('./a.html',$_GET,FILE_APPEND);
                $this->pay_code = I('get.pay_code');
                unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
            }
            //获取通知的数据
            $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
            if(empty($this->pay_code))
                exit('pay_code 不能为空');
            // 导入具体的支付类文件
            include_once  "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php"; // D:\wamp\www\svn_tpshop\www\plugins\payment\alipay\alipayPayment.class.php

            $code = '\\'.$this->pay_code; // \alipay
            $this->payment = new $code();
        }

    /**
     * tpshop 提交支付方式
     */
    public function getCode()
    {
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); // 订单id
        // 修改订单的支付方式
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        M('order')->where("order_id", $order_id)->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
        $order = M('order')->where("order_id", $order_id)->find();
        if ($order['pay_status'] == 1) {
            $this->error('此订单，已完成支付!');
        }
        //tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数

        //微信JS支付
        if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $code_str = $this->payment->getJSAPI($order);
            exit($code_str);
        } else {
            $order_sn = M('order')->where(array('order_id' => $order_id))->getField('order_sn');
            $user_data = M('users')->where(['user_id' => $order['user_id']])->find();

            if ($user_data['user_money'] < $order['order_amount']) {
                $this->error('余额不足请先充值或直接使用微信支付');
            }
            $this->my_accountLog_dec($order['user_id'], $order['order_amount'], 0, '余额购买商品', $order['id'], $order['order_sn']);
            $this->update_pay_status($order_sn);
            $code_str = $this->payment->get_code($order, $config_value);
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);


        return $this->fetch('payment');  // 分跳转 和不 跳转
    }

    public function my_accountLog_dec($user_id, $user_money = 0, $pay_points = 0, $desc = '', $distribut_money = 0, $order_id = 0, $order_sn = '')
    {
        /* 插入帐户变动记录 */
        $account_log = array(
            'user_id' => $user_id,
            'user_money' => $user_money,
            'pay_points' => $pay_points,
            'change_time' => time(),
            'desc' => $desc,
            'order_id' => $order_id,
            'order_sn' => $order_sn
        );
        /* 更新用户信息 */
//    $sql = "UPDATE __PREFIX__users SET user_money = user_money + $user_money," .
//        " pay_points = pay_points + $pay_points, distribut_money = distribut_money + $distribut_money WHERE user_id = $user_id";
        $update_data = array(
            'user_money' => ['exp', 'user_money-' . $user_money],
            'pay_points' => ['exp', 'pay_points-' . $pay_points],
            'distribut_money' => ['exp', 'distribut_money-' . $distribut_money],
        );
        if (($user_money + $pay_points + $distribut_money) == 0)
            return false;

        $update = Db::name('users')->where('user_id', $user_id)->setDec('user_money', $user_money);

        if ($update) {
            M('account_log')->add($account_log);
            return true;
        } else {
            return false;
        }
    }

    public function update_pay_status($order_sn, $ext = array())
    {


        $order = M('order')->where("order_sn", $order_sn)->find();

        if (stripos($order_sn, 'recharge') !== false) {
            //用户在线充值
            $count = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
            if ($count == 0) return false;
            $order = M('recharge')->where("order_sn", $order_sn)->find();
            M('recharge')->where("order_sn", $order_sn)->save(array('pay_status' => 1, 'pay_time' => time()));
            accountLog($order['user_id'], $order['account'], 0, '会员在线充值');
        } else {
            // 如果这笔订单已经处理过了
            $count = M('order')->where("order_sn = {$order_sn} and pay_status = 0 OR pay_status = 2")->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
            if ($count == 0) return false;
            // 找出对应的订单
            $order = M('order')->where("order_sn", $order_sn)->find();
            //预售订单

            if ($order['order_prom_type'] == 4) {
                $orderGoodsArr = M('OrderGoods')->where(array('order_id' => $order['order_id']))->find();
                // 预付款支付 有订金支付 修改支付状态  部分支付
                if ($order['total_amount'] != $order['order_amount'] && $order['pay_status'] == 0) {
                    //支付订金
                    M('order')->where("order_sn", $order_sn)->save(array('order_sn' => date('YmdHis') . mt_rand(1000, 9999), 'pay_status' => 2, 'pay_time' => time(), 'paid_money' => $order['order_amount']));
                    M('goods_activity')->where(array('act_id' => $order['order_prom_id']))->setInc('act_count', $orderGoodsArr['goods_num']);
                } else {
                    //全额支付 无订金支付 支付尾款
                    M('order')->where("order_sn", $order_sn)->save(array('pay_status' => 1, 'pay_time' => time()));
                    $pre_sell = M('goods_activity')->where(array('act_id' => $order['order_prom_id']))->find();
                    $ext_info = unserialize($pre_sell['ext_info']);
                    //全额支付 活动人数加一
                    if (empty($ext_info['deposit'])) {
                        M('goods_activity')->where(array('act_id' => $order['order_prom_id']))->setInc('act_count', $orderGoodsArr['goods_num']);
                    }
                }
            } else {

                $this->fx_pay($order['user_id'], $order['order_sn']);

                // 修改支付状态  已支付
                $updata = array('pay_status' => 1, 'pay_time' => time());
                if (isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];
                M('order')->where("order_sn", $order_sn)->save($updata);
            }
            // 减少对应商品的库存
            minus_stock($order['order_id']);
            // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
            update_user_level($order['user_id']);
            // 记录订单操作日志
            if (array_key_exists('admin_id', $ext)) {
                logOrder($order['order_id'], $ext['note'], '付款成功', $ext['admin_id']);
            } else {
                logOrder($order['order_id'], '订单付款成功', '付款成功', $order['user_id']);
            }
            //分销设置
            M('rebate_log')->where("order_id", $order['order_id'])->save(array('status' => 1));
            // 成为分销商条件
            $distribut_condition = tpCache('distribut.condition');
            if ($distribut_condition == 1)  // 购买商品付款才可以成为分销商
                M('users')->where("user_id", $order['user_id'])->save(array('is_distribut' => 1));

            //用户支付, 发送短信给商家
            $res = checkEnableSendSms("4");
            if (!$res || $res['status'] != 1) return;

            $sender = tpCache("shop_info.mobile");
            if (empty($sender)) return;
            $params = array('order_sn' => $order_sn);
            sendSms("4", $sender, $params);
        }

    }

//    public function fx_pay($user_id,$order_sn){
//        $user_data = M('users')->where(['user_id'=>$user_id])->find();
//        $user_data['user_tree_text']    =   '1&2&3&4&5&';
//        $user_list = explode('&',$user_data['user_tree_text']);
//        dump($user_list);die;
//    }
    public function fx_pay($user_id, $order_sn)
    {
        $user_data = M('users')->where(['user_id' => $user_id])->find();
//        $user_data['user_tree_text']    =   '1&2&3&4&5&6&7&8&9&';
//        $user_data['user_id']   =   '999';
        $user_list = explode('&', $user_data['user_tree_text']);
//        $user_list[count($user_list) - 1] = $user_data['user_id'];
        unset($user_list[count($user_list) - 1]);
        unset($user_list[count($user_list) - 1]);
        asort($user_list);
        foreach ($user_list as $key => $val) {
            $level = count($user_list) - $key;
            $fx_config = M('system_fx_config')->where(['level' => $level])->find();
            if ($fx_config == null) {
                continue;
            }
            $res = $this->fx_accountLog($val, $user_id, $order_sn, $level, $fx_config);

        }
    }

    /**
     * @user_id    获取用户
     * @buy_user_id 购买人
     * @order_sn    订单
     */
    public function fx_accountLog($user_id, $buy_user_id, $order_sn, $level, $fx_config)
    {
        $order = M('order')->where("order_sn", $order_sn)->find();
        if ($order == null) {
            $this->error('订单不存在');
        }
        $money = $fx_config['discount'] * 0.01 * $order['order_amount'];
        if ($fx_config['flag'] == 1) {
            $res = $this->my_accountLog($user_id, $money, 0, "({$this->get_user($buy_user_id)['nickname']})购买商品返利", 0, $order['id'], $order['order_sn']);
            //加入总分成
            M('users')->where(['user_id' => $user_id])->setInc('distribut_money', $money);
            if ($res == null) {
                return false;
            }
        }

        $data = array(
            'user_id' => $user_id,
            'buy_user_id' => $buy_user_id,
            'nickname' => $this->get_user($buy_user_id)['nickname'],
            'order_sn' => $order['order_sn'],
            'order_id' => $order['order_id'],
            'goods_price' => $order['order_amount'],
            'money' => $money,              //获得的返利金额
            'level' => $level,
            'create_time' => time(),
            'status' => $fx_config['flag'],
        );
        $res = M('rebate_log')->add($data);

        if ($res != null) {
            return true;
        }
    }

    public function get_user($id)
    {
        return M('users')->where(['user_id' => $id])->find();
    }

    /**
     * 记录帐户变动
     * @param   int $user_id 用户id
     * @param   float $user_money 可用余额变动
     * @param   int $pay_points 消费积分变动
     * @param   string $desc 变动说明
     * @param   float   distribut_money 分佣金额
     * @param int $order_id 订单id
     * @param string $order_sn 订单sn
     * @return  bool
     */
    public function my_accountLog($user_id, $user_money = 0, $pay_points = 0, $desc = '', $distribut_money = 0, $order_id = 0, $order_sn = '')
    {
        /* 插入帐户变动记录 */
        $account_log = array(
            'user_id' => $user_id,
            'user_money' => $user_money,
            'pay_points' => $pay_points,
            'change_time' => time(),
            'desc' => $desc,
            'order_id' => $order_id,
            'order_sn' => $order_sn
        );
        /* 更新用户信息 */
//    $sql = "UPDATE __PREFIX__users SET user_money = user_money + $user_money," .
//        " pay_points = pay_points + $pay_points, distribut_money = distribut_money + $distribut_money WHERE user_id = $user_id";
        $update_data = array(
            'user_money' => ['exp', 'user_money+' . $user_money],
            'pay_points' => ['exp', 'pay_points+' . $pay_points],
            'distribut_money' => ['exp', 'distribut_money+' . $distribut_money],
        );
        if (($user_money + $pay_points + $distribut_money) == 0)
            return false;
        $update = Db::name('users')->where('user_id', $user_id)->update($update_data);
        if ($update) {
            M('account_log')->add($account_log);
            return true;
        } else {
            return false;
        }
    }

    public function getPay()
    {

        //手机端在线充值
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON 
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); //订单id
        $user = session('user');
        $data['account'] = I('account');
        if ($order_id > 0) {
            M('recharge')->where(array('order_id' => $order_id, 'user_id' => $user['user_id']))->save($data);
        } else {
            $data['user_id'] = $user['user_id'];
            $data['nickname'] = $user['nickname'];
            $data['order_sn'] = 'recharge' . get_rand_str(10, 0, 1);
            $data['ctime'] = time();
            $order_id = M('recharge')->add($data);
        }
        if ($order_id) {
            $order = M('recharge')->where("order_id", $order_id)->find();

            if (is_array($order) && $order['pay_status'] == 0) {
                $order['order_amount'] = $order['account'];
                $pay_radio = $_REQUEST['pay_radio'];
                $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
                $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
                M('recharge')->where("order_id", $order_id)->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
                //微信JS支付
                if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $code_str = $this->payment->getJSAPI($order);
                    exit($code_str);
                } else {

                    $code_str = $this->payment->get_code($order, $config_value);
                }
            } else {
                $this->error('此充值订单，已完成支付!');
            }
        } else {
            $this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        return $this->fetch('recharge'); //分跳转 和不 跳转
    }

    // 服务器点对点 // http://www.tp-shop.cn/index.php/Home/Payment/notifyUrl
    public function notifyUrl()
    {
        $this->payment->response();
        exit();
    }

    // 页面跳转 // http://www.tp-shop.cn/index.php/Home/Payment/returnUrl
    public function returnUrl()
    {
        $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';


        if (stripos($result['order_sn'], 'recharge') !== false) {

            $order = M('recharge')->where("order_sn", $result['order_sn'])->find();
            $this->assign('order', $order);
            if ($result['status'] == 1)
                return $this->fetch('recharge_success');
            else
                return $this->fetch('recharge_error');
            exit();
        }

        $order = M('order')->where("order_sn", $result['order_sn'])->find();
        $this->assign('order', $order);
        if ($result['status'] == 1)
            return $this->fetch('success');
        else
            return $this->fetch('error');
    }
}
