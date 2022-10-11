<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use addons\shopro\library\traits\model\order\OrderItemStatus;
use think\Db;
use traits\model\SoftDelete;

/**
 * 订单模型
 */
class OrderItem extends Model
{
    use OrderItemStatus, SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_order_item';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['deletetime'];
    // //列表动态隐藏字段
    // protected static $list_hidden = ['content', 'params', 'images', 'service_ids'];

    protected $append = [
        'status_code',       // 不自动查询
        'status_name',
        'status_desc',
        'btns',
        'ext_arr'
    ];

    // 发货状态
    const DISPATCH_STATUS_NOSEND = 0;       // 未发货
    const DISPATCH_STATUS_SENDED = 1;       // 已发货
    const DISPATCH_STATUS_GETED = 2;        // 已收货


    // 售后状态
    const AFTERSALE_STATUS_REFUSE = -1;       // 驳回
    const AFTERSALE_STATUS_NOAFTER = 0;       // 未申请
    const AFTERSALE_STATUS_AFTERING = 1;       // 申请退款
    const AFTERSALE_STATUS_OK = 2;        // 售后完成


    // 退款状态
    const REFUND_STATUS_REFUSE = -1;       // 拒绝退款      // 驳回aftersale_status（状态不会出现）
    const REFUND_STATUS_NOREFUND = 0;       // 退款状态 未申请
    const REFUND_STATUS_ING = 1;       // 申请中    // 不需要申请（状态不会出现）
    const REFUND_STATUS_OK = 2;       // 已同意     
    const REFUND_STATUS_FINISH = 3;       // 退款完成

    // 评价状态
    const COMMENT_STATUS_NO = 0;       // 待评价
    const COMMENT_STATUS_OK = 1;       // 已评价

    public function getExtArrAttr($value, $data)
    {
        return (isset($data['ext']) && $data['ext']) ? json_decode($data['ext'], true) : [];
    }



    public function getStatus($data, $type)
    {
        $btns = [];
        $status_name = '';
        $status_desc = '';
        $ext = $this->ext_arr;

        $status_code = $this->status_code;

        $item_code = 'null';        // 有售后的时候，第二状态
        if (strpos($status_code, '|') !== false) {
            $codes = explode('|', $status_code);
            $status_code = $codes[0] ?? 'null';
            $item_code = $codes[1] ?? 'null';
        }

        switch ($status_code) {
            case 'null':
            case 'cancel':
            case 'invalid':
            case 'nopay':
                // 未支付的返回空
                break;
            case 'nosend':
                $status_name = '待发货';
                $status_desc = '等待卖家发货';

                switch ($data['dispatch_type']) {
                    case 'store':
                        $status_name = '待配送';
                        $status_desc = '等待卖家上门配送';
                        break;
                    case 'selfetch':
                        $status_name = '待备货';
                        $status_desc = '等待卖家备货';
                        break;
                }

                $btns[] = 'aftersale';          // 售后
                break;
            case 'noget':
                switch ($data['dispatch_type']) {
                    case 'express':
                        $status_name = '待收货';
                        $status_desc = '等待买家收货';
                        $btns[] = 'get';            // 确认收货
                        break;
                    case 'selfetch':
                        $status_name = '待提货/到店';
                        $status_desc = '等待买家提货/到店';
                        break;
                    case 'store':
                        $status_name = '待取货';
                        $status_desc = '卖家上门配送中';
                        $btns[] = 'get';            // 确认收货
                        break;
                    case 'autosend':        // 正常不需要确认收货
                        $status_name = '待收货';
                        $status_desc = '等待买家收货';
                        $btns[] = 'get';            // 确认收货
                        break;
                }
                $btns[] = 'aftersale';          // 售后
                break;
            case 'nocomment':
                $status_name = '待评价';
                $status_desc = '等待买家评价';
                $btns[] = 'comment';
                $btns[] = 'aftersale';          // 售后
                break;
            case 'commented':
                $status_name = '已评价';
                $status_desc = '订单已评价';
                $btns[] = 'buy_again';
                break;
            case 'refund_finish':
                $status_name = '退款完成';
                $status_desc = '订单退款完成';
                break;
            case 'refund_ing':      // 不需要申请退款（状态不会出现）
                $status_name = '退款处理中';
                $status_desc = '退款处理中';
                break;
            case 'refund_refuse':   // 驳回aftersale_status（状态不会出现）
                $status_name = '拒绝退款';
                $status_desc = '卖家拒绝退款';
                break;
            case 'after_ing':
                $status_name = '售后中';
                $status_desc = '售后处理中';
                break;
            case 'after_refuse':
            case 'after_finish':
                switch ($status_code) {
                    case 'after_refuse':
                        $status_name = '售后拒绝';
                        $status_desc = '售后申请拒绝';
                        $btns[] = 're_aftersale';          // 售后
                        break;
                    case 'after_finish':
                        $status_name = '售后完成';
                        $status_desc = '售后完成';
                        break;
                }

                // 售后拒绝，或者完成的时候，还可以继续操作订单
                switch ($item_code) {
                    case 'noget':
                        if (in_array($data['dispatch_type'], ['express', 'store', 'autosend'])) {       // 除了 自提扫码核销外，都可确认收货
                            $btns[] = 'get';            // 确认收货
                        }
                        break;
                    case 'nocomment':
                        $btns[] = 'comment';
                        break;
                    case 'commented':
                        $btns[] = 'buy_again';
                        break;
                }

                break;
        }

        // 如果有售后id 就显示售后详情按钮，退款中可能是售后退的款
        if (isset($ext['aftersale_id']) && !empty($ext['aftersale_id'])) {
            $btns[] = 'aftersale_info';
        }

        return $type == 'status_name' ? $status_name : ($type == 'btns' ? $btns : $status_desc);
    }

    /**
     * 通过商品ID检查用户是否购买
     * 
     * @return object
     */
    public static function checkUserPayedByGoodsIds($userId, $goodsIds)
    {
        $where = [
            "goods_id" => ["in", $goodsIds],
            "refund_status" => 0
        ];
        return self::hasWhere('order', ['status' => ['gt', 0], 'user_id' => $userId])->where($where)->select();
    }

    public function order()
    {
        return $this->belongsTo(\addons\shopro\model\Order::class, 'order_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(\addons\shopro\model\User::class, 'user_id', 'id');
    }

    /**
     * 只关联这个 用户的记录
     *
     * @return void
     */
    public function agentReward()
    {
        $user = User::info();

        return $this->hasOne(\addons\shopro\model\commission\Reward::class, 'order_item_id', 'id')->where('agent_id', ($user ? $user->id : 0));
    }
}
