<?php

namespace addons\shopro\model\chat;

use think\Model;
use addons\shopro\library\chat\Online;
/**
 * 消息记录表
 */
class Log extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_chat_log';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
    ];


    public function scopeUser($query) {
        $query->where('sender_identify', 'user');
    }


    public function scopeCustomerService($query)
    {
        $query->where('sender_identify', 'customer_service');
    }


    /**
     * 批量处理消息发送人
     *
     * @param [type] $messageData
     * @return void
     */
    public static function formatIdentify($messageData) {
        $userIds = [];
        $customerServiceIds = [];
        foreach ($messageData as $key => $item) {
            if ($item['sender_identify'] == 'customer_service') {
                $customerServiceIds[] = $item['sender_id'];
            } else if ($item['sender_identify'] == 'user') {
                $userIds[] = $item['sender_id'];
            }
        }
        $customerServices = \addons\shopro\model\chat\CustomerService::where('id', 'in', $customerServiceIds)->select();
        $users = \addons\shopro\model\chat\User::where('id', 'in', $userIds)->select();
        $customerServices = array_column($customerServices, null, 'id');
        $users = array_column($users, null, 'id');

        foreach ($messageData as $key => &$item) {
            $identify = null;
            if ($item['sender_identify'] == 'customer_service') {
                $identify = $customerServices[$item['sender_id']] ?? null;
            } else if ($item['sender_identify'] == 'user') {
                $identify = $users[$item['sender_id']] ?? null;
            }

            $item->identify = $identify;
        }

        return $messageData;
    }


    /**
     * 全部拼接 cdnurl
     *
     * @param string $value
     * @param array $data
     * @return string
     */
    public function getMessageAttr($value, $data)
    {
        if ($data['message_type'] == 'image') {
            $message = Online::cdnurl($value);
        } else if (in_array($data['message_type'], ['order', 'goods'])){
            $messageArr = json_decode($value, true);
            if (isset($messageArr['image']) && $messageArr['image']) {
                $messageArr['image'] = Online::cdnurl($messageArr['image']);
            }

            $message = json_encode($messageArr);
        } else if ($data['message_type'] == 'text') {
            // 全文匹配图片拼接 cdnurl
            $url = Online::cdnurl('/uploads');
            $message = str_replace("<img src=\"/uploads", "<img style=\"width: 100%;!important\" src=\"" . $url, $value);
        } else {
            $message = $value;
        }

        return $message;
    }


    // 多对多关联发消息人身份
    public function getIdentifyAttr($value, $data)
    {
        $identify = null;
        if ($data['sender_identify'] == 'customer_service') {
            $identifyClass = \addons\shopro\model\chat\CustomerService::class;
        } else if ($data['sender_identify'] == 'user') {
            $identifyClass = \addons\shopro\model\chat\User::class;
        }
        if ($data['sender_id'] && $identifyClass) {
            $identify = $identifyClass::get($data['sender_id']);
        }

        return $identify;
    }

    /** 当前thinkphp 多态关联有 bug */
    // public function identify()
    // {
    //     return $this->morphTo(['sender_identify', 'sender_id'], [
    //         'user' => \addons\shopro\model\chat\User::class,
    //         'customer_service' => \addons\shopro\model\chat\CustomerService::class,
    //     ]);
    // }
}
