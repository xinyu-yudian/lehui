<?php

namespace addons\shopro\job;

use addons\shopro\model\TradeOrder;
use addons\shopro\model\Config;
use think\queue\Job;


/**
 * 交易订单自动操作
 */
class TradeOrderAutoOper extends BaseJob
{

    /**
     * 订单自动关闭
     */
    public function autoClose(Job $job, $data){
        try {
            $order = $data['order'];

            // 重新查询订单
            $order = TradeOrder::get($order['id']);

            if ($order && $order['status'] == 0) {
                \think\Db::transaction(function () use ($order, $data) {
                    // 执行关闭
                    $order->status = TradeOrder::STATUS_INVALID;
                    $order->ext = json_encode($order->setExt($order, ['invalid_time' => time()]));      // 取消时间
                    $order->save();
                });
            }

            // 删除 job
            $job->delete();
        } catch (\Exception $e) {
            // 队列执行失败
            \think\Log::write('queue-' . get_class() . '-autoClose' . '：执行失败，错误信息：' . $e->getMessage());
        }
    }
}