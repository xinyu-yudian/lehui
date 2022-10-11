<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use addons\shopro\library\traits\model\order\OrderAftersaleScope;
use think\Db;
use traits\model\SoftDelete;

/**
 * 订单售后单
 */
class OrderAftersale extends Model
{
    use SoftDelete, OrderAftersaleScope;

    // 表名,不含前缀
    protected $name = 'shopro_order_aftersale';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['deletetime'];

    protected $append = [
        'type_text',
        'dispatch_status_text',
        'aftersale_status_text',
        'aftersale_status_desc',
        'btns',
        'refund_status_text'
    ];

    // 发货状态
    const DISPATCH_STATUS_NOSEND = 0;       // 未发货
    const DISPATCH_STATUS_SENDED = 1;       // 已发货
    const DISPATCH_STATUS_GETED = 2;        // 已收货

    // 售后状态
    const AFTERSALE_STATUS_CANCEL = -2;       // 售后取消
    const AFTERSALE_STATUS_REFUSE = -1;       // 拒绝
    const AFTERSALE_STATUS_NOOPER = 0;       // 未处理
    const AFTERSALE_STATUS_AFTERING = 1;       // 处理中
    const AFTERSALE_STATUS_OK = 2;        // 售后完成


    // 退款状态
    const REFUND_STATUS_REFUSE = -1;       // 拒绝退款(不用了)
    const REFUND_STATUS_NOREFUND = 0;       // 未退款
    const REFUND_STATUS_FINISH = 1;       // 同意

    public static function getSn($user_id)
    {
        $rand = $user_id < 9999 ? mt_rand(100000, 99999999) : mt_rand(100, 99999);
        $order_sn = date('Yhis') . $rand;

        $id = str_pad($user_id, (24 - strlen($order_sn)), '0', STR_PAD_BOTH);

        return 'A' . $order_sn . $id;
    }


    // 获取售后列表
    public static function getList($params) {
        $user = User::info();
        $type = $params['type'] ?? 'all';

        $aftersale = (new self())->where('user_id', $user->id);

        if ($type != 'all') {
            $aftersale = $aftersale->{$type}();
        }

        $aftersale = $aftersale->order('id', 'desc')->paginate(10);

        return $aftersale;
    }


    public static function detail($params) {
        $user = User::info();
        $id = $params['id'] ?? 0;

        $aftersale = (new self())->where('user_id', $user->id)->with('logs')->where('id', $id)->find();

        if (!$aftersale) {
            new Exception('售后单不存在');
        }

        return $aftersale;
    }


    public static function aftersale($params) {
        $user = User::info();

        $orderAftersale = Db::transaction(function () use ($user, $params) {
            $type = $params['type'];
            $order_id = $params['order_id'];
            $order_item_id = $params['order_item_id'];
            $phone = $params['phone'];
            $reason = $params['reason'] ?? '用户申请售后';
            $content = $params['content'] ?? '';
            $images = $params['images'] ?? [];

            $order = Order::canAftersale()->where('user_id', $user->id)->where('id', $order_id)->lock(true)->find();
            if (!$order) {
                new Exception('订单不存在或不可售后');
            }

            $item = OrderItem::where('user_id', $user->id)->where('id', $order_item_id)->find();

            if (!$item) {
                new Exception('参数错误');
            }

            if (!in_array($item->aftersale_status, [
                OrderItem::AFTERSALE_STATUS_REFUSE,
                OrderItem::AFTERSALE_STATUS_NOAFTER
            ])) {
                new Exception('当前订单商品不可申请售后');
            }

            $data['aftersale_sn'] = self::getSn($user->id);
            $data['user_id'] = $user->id;
            $data['type'] = $type;
            $data['phone'] = $phone;
            $data['activity_id'] = $item['activity_id'];
            $data['activity_type'] = $item['activity_type'];
            $data['order_id'] = $order_id;
            $data['order_item_id'] = $order_item_id;
            $data['goods_id'] = $item['goods_id'];
            $data['goods_sku_price_id'] = $item['goods_sku_price_id'];
            $data['goods_sku_text'] = $item['goods_sku_text'];
            $data['goods_title'] = $item['goods_title'];
            $data['goods_image'] = $item['goods_image'];
            $data['goods_original_price'] = $item['goods_original_price'];
            $data['discount_fee'] = $item['discount_fee'];
            $data['goods_price'] = $item['goods_price'];
            $data['goods_num'] = $item['goods_num'];
            $data['dispatch_status'] = $item['dispatch_status'];
            $data['dispatch_fee'] = $item['dispatch_fee'];
            $data['aftersale_status'] = self::AFTERSALE_STATUS_NOOPER;
            $data['refund_status'] = self::REFUND_STATUS_NOREFUND;      // 未退款
            $data['refund_fee'] = 0;

            $orderAftersale = new self();
            $orderAftersale->allowField(true)->save($data);
            // 增加售后单变动记录、
            OrderAftersaleLog::operAdd($order, $orderAftersale, $user, 'user', [
                'reason' => '您的售后服务单已申请成功，等待售后处理',
                'content' => "申请原因：$reason <br>相关描述： $content",
                'images' => $images
            ]);

            $ext = $item->ext_arr ? : [];
            $ext['aftersale_id'] = $orderAftersale->id;
            // 修改订单 item 状态，申请售后
            $item->aftersale_status = OrderItem::AFTERSALE_STATUS_AFTERING;
            $item->ext = json_encode($ext);
            $item->save();
            OrderAction::operAdd($order, $item, $user, 'user', '用户申请售后');

            return $orderAftersale;
        });

        return $orderAftersale;
    }


    // 取消售后单
    public static function operCancel($params)
    {
        $user = User::info();
        extract($params);

        $aftersale = self::canCancel()->where('user_id', $user->id)->where('id', $id)->find();

        if (!$aftersale) {
            new Exception('售后单不存在或不可取消');
        }

        $order = Order:: where('user_id', $user->id)->where('id', $aftersale['order_id'])->find();
        if (!$order) {
            new Exception('订单不存在');
        }

        $orderItem = OrderItem::where('id', $aftersale['order_item_id'])->find();
        if (!$orderItem || in_array($orderItem['refund_status'], [OrderItem::REFUND_STATUS_OK, OrderItem::REFUND_STATUS_FINISH])) {
            // 不存在， 或者已经退款
            new Exception('退款商品不存在或已退款');
        }

        $aftersale = Db::transaction(function () use ($aftersale, $order, $orderItem, $user) {
            $aftersale->aftersale_status = self::AFTERSALE_STATUS_CANCEL;        // 取消售后单
            $aftersale->save();

            OrderAftersaleLog::operAdd($order, $aftersale, $user, 'user', [
                'reason' => '用户取消申请售后',
                'content' => '用户取消申请售后',
                'images' => []
            ]);

            // 修改订单 item 为未申请售后
            $orderItem->aftersale_status = OrderItem::AFTERSALE_STATUS_NOAFTER;
            $orderItem->refund_status = OrderItem::REFUND_STATUS_NOREFUND;
            $orderItem->save();

            OrderAction::operAdd($order, $orderItem, $user, 'user', '用户取消申请售后');

            return $aftersale;
        });

        return $aftersale;
    }


    // 删除售后单
    public static function operDelete($params)
    {
        $user = User::info();
        extract($params);

        $aftersale = self::canDelete()->where('user_id', $user->id)->where('id', $id)->find();

        if (!$aftersale) {
            new Exception('售后单不存在或不可删除');
        }

        $order = Order::withTrashed()->where('id', $aftersale['order_id'])->find();
        $aftersale = Db::transaction(function () use ($aftersale, $order, $user) {
            $copyAftersale = $aftersale->toArray();
            $aftersale->delete();        // 删除售后单

            OrderAftersaleLog::operAdd($order, $copyAftersale, $user, 'user', [
                'reason' => '用户删除售后单',
                'content' => '用户删除售后单',
                'images' => []
            ]);

            return $aftersale;
        });

        return $aftersale;
    }


    public function getTypeTextAttr($value, $data)
    {
        $text = '';
        switch ($data['type']) {
            case 'refund':
                $text = '退款';
                break;
            case 'return':
                $text = '退货';
                break;
            case 'other':
                $text = '其他';
                break;
        }

        return $text;
    }


    public function getDispatchStatusTextAttr($value, $data)
    {
        $text = '';
        switch ($data['dispatch_status']) {
            case self::DISPATCH_STATUS_NOSEND:
                $text = '未发货';
                break;
            case self::DISPATCH_STATUS_SENDED:
                $text = '已发货';
                break;
            case self::DISPATCH_STATUS_GETED:
                $text = '已收货';
                break;
        }

        return $text;
    }


    public function getRefundStatusTextAttr ($value, $data) {
        $text = '';
        switch ($data['refund_status']) {
            case self::REFUND_STATUS_REFUSE: 
                $text = '拒绝退款';
                break;
            case self::REFUND_STATUS_NOREFUND: 
                $text = '未退款';
                break;
            case self::REFUND_STATUS_FINISH: 
                $text = '已退款';
                break;
        }

        return $text;
    }


    public function getAftersaleStatusTextAttr($value, $data)
    {
        return $this->getStatus($data, 'status_text');
    }


    public function getAftersaleStatusDescAttr($value, $data)
    {
        return $this->getStatus($data, 'status_desc');
    }

    public function getBtnsAttr($value, $data)
    {
        return $this->getStatus($data, 'btns');
    }


    public function getStatus($data, $type) {
        $text = '';
        $desc = '';
        $btns = [];
        switch ($data['aftersale_status']) {
            case self::AFTERSALE_STATUS_CANCEL:
                $text = '售后取消';
                $desc = '您取消了售后申请';
                $btns = ['delete'];     // 删除
                break;
            case self::AFTERSALE_STATUS_REFUSE:
                $text = '售后拒绝';
                $desc = '您的申请被拒绝，请点击查看详情';
                $btns = ['delete'];     // 删除
                break;
            case self::AFTERSALE_STATUS_NOOPER:
                $text = '提交申请';
                $desc = '您的服务单已申请成功，等待售后处理';
                $btns = ['cancel'];     // 取消申请
                break;
            case self::AFTERSALE_STATUS_AFTERING:
                $text = '处理中';
                $desc = '您的服务单正在处理中，请耐心等待';
                $btns = ['cancel'];     // 取消申请
                break;
            case self::AFTERSALE_STATUS_OK:
                $text = '售后完成';
                $desc = '服务已完成，感谢您的支持';
                $btns = ['delete'];     // 删除
                break;
        }

        return $type == 'status_text' ? $text : ($type == 'status_desc' ? $desc : $btns);
    }



    public function logs () 
    {
        return $this->hasMany(\addons\shopro\model\OrderAftersaleLog::class, 'order_aftersale_id', 'id')->order('id', 'desc');
    }
}
