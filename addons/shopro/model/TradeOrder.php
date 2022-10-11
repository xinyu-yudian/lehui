<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use think\Db;
use think\Queue;
use traits\model\SoftDelete;
use addons\shopro\library\traits\model\order\TradeOrderStatus;

/**
 * 交易订单模型
 */
class TradeOrder extends Model
{
    use TradeOrderStatus, SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_trade_order';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['updatetime', 'deletetime'];
    // //列表动态隐藏字段
    // protected static $list_hidden = ['content', 'params', 'images', 'service_ids'];

    // // 追加属性
    protected $append = [
        'status_code',
        'status_name',
        'status_desc',
        'pay_type_text',
        'pay_time_text',
        'platform_text',
        'ext_arr'
    ];

    // 订单状态
    const STATUS_INVALID = -2;
    const STATUS_CANCEL = -1;
    const STATUS_NOPAY = 0;
    const STATUS_PAYED = 1;
    const STATUS_FINISH = 2;


    // 获取订单号
    public static function getSn($user_id)
    {
        $rand = $user_id < 9999 ? mt_rand(100000, 99999999) : mt_rand(100, 99999);
        $order_sn = date('Yhis') . $rand;

        $id = str_pad($user_id, (24 - strlen($order_sn)), '0', STR_PAD_BOTH);

        return 'TO' . $order_sn . $id;
    }


    public static function getList($params)
    {
        $user = User::info();
        extract($params);

        $orders = (new self())->where('user_id', $user->id)->payed()
            ->order('id', 'desc')->paginate(10);

        return $orders;
    }


    // 订单详情
    public static function detail($params)
    {
        $user = User::info();
        extract($params);

        $order = (new self())->where('user_id', $user->id);

        if (isset($order_sn)) {
            $order = $order->where('order_sn', $order_sn);
        }
        if (isset($id)) {
            $order = $order->where('id', $id);
        }

        $order = $order->find();

        if (!$order) {
            new Exception('订单不存在');
        }

        return $order;
    }


    public static function recharge($params)
    {
        $user = User::info();
        
        // 入参
        extract($params);
        $recharge_money = floatval($recharge_money);
		
        $withdrawConfig = \addons\shopro\model\UserWalletApply::getWithdrawConfig();
        $rechargeConfig = $withdrawConfig['recharge'] ?? [];

        if (!isset($rechargeConfig['enable']) || !$rechargeConfig['enable']) {
            new Exception('充值入口已关闭');
        }
		
        if ($recharge_money < 0.01) {
            new Exception('请输入正确的充值金额');
        }

        $close_time = self::getCloseTime();
     

        $orderData = [];
        $orderData['type'] = 'recharge';
        $orderData['order_sn'] = self::getSn($user->id);
        $orderData['user_id'] = $user->id;
        $orderData['status'] = 0;
        $res = Db::table('fa_rechargerule')->where(['rechargenum' => $recharge_money, 'status' => 1])->find();
        if($res){
            $orderData['total_amount'] = $recharge_money + $res['givenum'];
            $remark='充值：'.$recharge_money.'，赠送：'.$res['givenum'];
        }else{
            $orderData['total_amount'] = $recharge_money;
        }

        $orderData['total_fee'] = $recharge_money;
        $orderData['remark'] = $remark ?? null;
        $orderData['platform'] = request()->header('platform');
        $orderData['ext'] = json_encode(['expired_time' => time() + ($close_time * 60)]);
		
        $order = new TradeOrder();
        $order->allowField(true)->save($orderData);
        \think\Queue::later(($close_time * 60), '\addons\shopro\job\TradeOrderAutoOper@autoClose', ['order' => $order], 'shopro');

        return $order;
    }


    /**
     * 订单过期时间
     *
     * @return float
     */
    public static function getCloseTime()
    {
        // 添加自动关闭队列
        $config = Config::where('name', 'order')->cache(300)->find();        // 读取配置自动缓存 5 分钟
        $config = isset($config) ? json_decode($config['value'], true) : [];

        $close_minue = (isset($config['order_auto_close']) && $config['order_auto_close'] > 0)
            ? $config['order_auto_close'] : 15;

        return $close_minue;
    }

    /**
     * 订单支付成功
     *
     * @param [type] $order
     * @param [type] $notify
     * @return void
     */
    public function paymentProcess($order, $notify)
    {
        $order->status = Order::STATUS_PAYED;
        $order->paytime = time();
        $order->transaction_id = $notify['transaction_id'];
        $order->payment_json = $notify['payment_json'];
        $order->pay_type = $notify['pay_type'];
        $order->pay_fee = $notify['pay_fee'];
        $order->save();

        $user = User::where('id', $order->user_id)->find();

        if ($order['type'] == 'recharge') {
            // 用户充值，给用户增加余额
            User::money($order->total_fee, $user->id, 'recharge', 0, '用户在线充值', [
                'order_id' => $order->id,
                'order_sn' => $order->order_sn,
            ]);

            if($order->total_amount>$order->total_fee){
                User::money($order->total_amount - $order->total_fee, $user->id, 'recharge_append', 0, $order->remark, [
                    'order_id' => $order->id,
                    'order_sn' => $order->order_sn,
                ]);
            }
        }

        return $order;
    }
}
