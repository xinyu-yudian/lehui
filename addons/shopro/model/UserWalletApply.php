<?php

namespace addons\shopro\model;

use think\Db;
use think\Model;
use traits\model\SoftDelete;
use addons\shopro\exception\Exception;

/**
 * 钱包
 */
class UserWalletApply extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_user_wallet_apply';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    protected $hidden = ['actual_money', 'log', 'payment_json', 'updatetime'];

    // 追加属性
    protected $append = [
        'status_text',
        'apply_type_text',
    ];


    /**
     * 获取提现单号
     *
     * @param int $user_id
     * @return string
     */
    public static function getSn($user_id)
    {
        $rand = $user_id < 9999 ? mt_rand(100000, 99999999) : mt_rand(100, 99999);
        $order_sn = date('Yhis') . $rand;

        $id = str_pad($user_id, (24 - strlen($order_sn)), '0', STR_PAD_BOTH);

        return 'W' . $order_sn . $id;
    }

    // 提现记录
    public static function getList()
    {
        $user = User::info();

        $walletApplys = self::where(['user_id' => $user->id])->order('id desc')->paginate(10);

        return $walletApplys;
    }

    /**
     * 申请提现
     *
     * @param int $type 提现方式 wechat|alipay|bank
     * @param int $money 提现金额
     */
    public static function apply($type, $money)
    {
        $user = User::info();
        $config = self::getWithdrawConfig();
        if (!in_array($type, $config['methods'])) {
            throw \Exception('暂不支持该提现方式');
        }

        $min = round(floatval($config['min']), 2);
        $max = round(floatval($config['max']), 2);
        $service_fee = round(floatval($config['service_fee']), 3);      // 三位小数

        // 检查最小提现金额
        if ($money < $min || $money <= 0) {
            throw \Exception('提现金额不能少于 ' . $min . '元');
        }
        // 检查最大提现金额
        if ($max && $money > $max) {
            throw \Exception('提现金额不能大于 ' . $max . '元');
        }

        // 计算手续费
        $charge = $money * $service_fee;
        if ($user->money < $charge + $money) {
            throw \Exception('可提现余额不足');
        }
        
        // 检查每日最大提现次数
        if (isset($config['perday_num']) && $config['perday_num'] > 0) {
            $num = self::where(['user_id' => $user->id, 'createtime' => ['egt', strtotime(date("Y-m-d", time()))]])->count();
            if ($num >= $config['perday_num']) {
                throw \Exception('每日提现次数不能大于 ' . $config['perday_num'] . '次');
            }
        }

        // 检查每日最大提现金额
        if (isset($config['perday_amount']) && $config['perday_num'] > 0) {
            $amount = self::where(['user_id' => $user->id, 'createtime' => ['egt', strtotime(date("Y-m-d", time()))]])->sum('money');
            if ($amount >= $config['perday_amount']) {
                throw \Exception('每日提现金额不能大于 ' . $config['perday_amount'] . '元');
            }
        }

        // 检查提现账户信息
        $bank = \addons\shopro\model\UserBank::info($type, false);

        // 添加提现记录
        $platform = request()->header('platform');

        $apply = new self();
        $apply->apply_sn = self::getSn($user->id);
        $apply->user_id = $user->id;
        $apply->money = $money;
        $apply->charge_money = $charge;
        $apply->service_fee = $service_fee;
        $apply->apply_type = $type;
        $apply->platform = $platform;
        switch ($type) {
            case 'wechat':
                $applyInfo = [
                    '微信用户' => $bank['real_name'],
                    '微信ID'  => $bank['card_no'],
                ];
                break;
            case 'alipay':
                $applyInfo = [
                    '真实姓名' => $bank['real_name'],
                    '支付宝账户' => $bank['card_no']
                ];
                break;
            case 'bank':
                $applyInfo = [
                    '真实姓名' => $bank['real_name'],
                    '开户行' => $bank['bank_name'],
                    '银行卡号' => $bank['card_no']
                ];
                break;
        }
        if (!isset($applyInfo)) {
            throw \Exception('您的提现信息有误');
        }
        $apply->apply_info = $applyInfo;

        $apply->status = 0;
        $apply->save();
        self::handleLog($apply, '用户发起提现申请');
        // 扣除用户余额
        User::money(- ($money + $charge), $user->id, 'cash', $apply->id);

        // 检查是否执行自动打款
        $autoCheck = false;
        if ($type !== 'bank' && $config['wechat_alipay_auto']) {
            $autoCheck = true;
        }

        if ($autoCheck) {
            $apply = self::handleAgree($apply);
            $apply = self::handleWithdraw($apply);
        }

        return $apply;
    }

    public static function handleLog($apply, $oper_info)
    {
        $log = $apply->log;
        $oper = \addons\shopro\library\Oper::set();
        $log[] = [
            'oper_type' => $oper['oper_type'],
            'oper_id' => $oper['oper_id'],
            'oper_info' => $oper_info,
            'oper_time' => time()
        ];
        $apply->log = $log;
        $apply->save();
        return $apply;
    }

    // 同意
    public static function handleAgree($apply)
    {
        if ($apply->status != 0) {
            throw \Exception('请勿重复操作');
        }
        $apply->status = 1;
        $apply->save();
        return self::handleLog($apply, '同意提现申请');
    }

    // 处理打款
    public static function handleWithdraw($apply)
    {
        $withDrawStatus = false;
        if ($apply->status != 1) {
            throw \Exception('请勿重复操作');
        }
        if ($apply->apply_type !== 'bank') {
            $withDrawStatus = self::handleTransfer($apply);
        } else {
            $withDrawStatus = true;
        }
        if ($withDrawStatus) {
            $apply->status = 2;
            $apply->actual_money = $apply->money;
            $apply->save();
            return self::handleLog($apply, '已打款');
        }
        return $apply;
    }

    // 拒绝
    public static function handleReject($apply, $rejectInfo)
    {
        if ($apply->status != 0 && $apply->status != 1) {
            throw \Exception('请勿重复操作');
        }
        $apply->status = -1;
        $apply->save();
        User::money($apply->money + $apply->charge_money, $apply->user_id, 'cash_error', $apply->id);
        return self::handleLog($apply, '拒绝:' . $rejectInfo);
    }

    // 企业付款提现
    private static function handleTransfer($apply)
    {
        $type = $apply->apply_type;
        $platform = $apply->platform;
        // 1.企业自动付款
        $pay = new \addons\shopro\library\PayService($type, $platform, '', 'transfer');

        // 2.组装数据
        try {
            if ($type == 'wechat') {
                $payload = [
                    'partner_trade_no' => $apply->apply_sn,
                    'openid' => $apply->apply_info['微信ID'],
                    'check_name' => 'NO_CHECK',
                    'amount' => $apply->money,
                    'desc' => "用户[{$apply->apply_info['微信用户']}]提现"
                ];
            } elseif ($type == 'alipay') {
                $payload = [
                    'out_biz_no' => $apply->apply_sn,
                    'trans_amount' => $apply->money,
                    'product_code' => 'TRANS_ACCOUNT_NO_PWD',
                    'biz_scene' => 'DIRECT_TRANSFER',
                    // 'order_title' => '余额提现到',
                    'remark' => '用户提现',
                    'payee_info' => [
                        'identity' => $apply->apply_info['支付宝账户'],
                        'identity_type' => 'ALIPAY_LOGON_ID',
                        'name' => $apply->apply_info['真实姓名'],
                    ]
                ];
            }
        } catch (\Exception $e) {
            throw \Exception('提现信息不正确');
        }

        // 3.发起付款 
        try {
            list($code, $response) = $pay->transfer($payload);

            if ($code === 1) {
                $apply->payment_json = json_encode($response, JSON_UNESCAPED_UNICODE);
                $apply->save();
                return true;
            }
        } catch (\Exception $e) {
            \think\Log::error('提现失败：' . ' 行号：' . $e->getLine() . '文件：' . $e->getFile() . '错误信息：' . $e->getMessage());
            throw \Exception($e->getMessage());
        }
        return false;
    }


    /**
     * 提现类型列表
     */
    public function getApplyTypeList()
    {
        return ['bank' => '银行卡', 'wechat' => '微信零钱', 'alipay' => '支付宝账户'];
    }


    /**
     * 提现类型中文
     */
    public function getApplyTypeTextAttr($value, $data)
    {
        $value = isset($data['apply_type']) ? $data['apply_type'] : '';
        $list = $this->getApplyTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    /**
     * 提现信息
     */
    public function getApplyInfoAttr($value, $data)
    {
        $value = isset($data['apply_info']) ? $data['apply_info'] : $value;
        return json_decode($value, true);
    }

    /**
     * 提现信息 格式转换
     */
    public function setApplyInfoAttr($value, $data)
    {
        $value = isset($data['apply_info']) ? $data['apply_info'] : $value;
        $applyInfo = json_encode($value, JSON_UNESCAPED_UNICODE);
        return $applyInfo;
    }

    public function getStatusTextAttr($value, $data)
    {
        switch ($data['status']) {
            case 0:
                $status_name = '审核中';
                break;
            case 1:
                $status_name = '处理中';
                break;
            case 2:
                $status_name = '已处理';
                break;
            case -1:
                $status_name = '已拒绝';
                break;
            default:
                $status_name = '';
        }

        return $status_name;
    }


    public static function getWithdrawConfig()
    {
        $config = \addons\shopro\model\Config::where('name', 'withdraw')->find();
        return json_decode($config['value'], true);
    }

    /**
     * 获取日志字段数组
     */
    public function getLogAttr($value, $data)
    {
        $value = array_filter((array)json_decode($value, true));
        return (array)$value;
    }

    /**
     * 设置日志字段
     * @param mixed $value
     * @return string
     */
    public function setLogAttr($value)
    {
        $value = is_object($value) || is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        return $value;
    }
}
