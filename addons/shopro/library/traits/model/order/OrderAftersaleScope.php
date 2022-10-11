<?php

namespace addons\shopro\library\traits\model\order;

use addons\shopro\library\Wechat;
use addons\shopro\model\Aftersale;
use addons\shopro\model\OrderAftersale;
use think\Cache;

trait OrderAftersaleScope
{
    // 已取消
    public function scopeCancel($query)
    {
        return $query->where('aftersale_status', OrderAftersale::AFTERSALE_STATUS_CANCEL);
    }

    // 已拒绝
    public function scopeRefuse($query)
    {
        return $query->where('aftersale_status', OrderAftersale::AFTERSALE_STATUS_REFUSE);
    }

    public function scopeNooper($query)
    {
        return $query->where('aftersale_status', OrderAftersale::AFTERSALE_STATUS_NOOPER);
    }

    // 处理中
    public function scopeIng($query)
    {
        return $query->where('aftersale_status', OrderAftersale::AFTERSALE_STATUS_AFTERING);
    }


    // 处理完成
    public function scopeFinish($query)
    {
        return $query->where('aftersale_status', OrderAftersale::AFTERSALE_STATUS_OK);
    }


    // 可以取消
    public function scopeCanCancel($query)
    {
        // 未处理，处理中，可以取消
        return $query->where('aftersale_status', 'in', [
            OrderAftersale::AFTERSALE_STATUS_NOOPER,
            OrderAftersale::AFTERSALE_STATUS_AFTERING
        ]);
    }


    // 可以操作
    public function scopeCanOper($query)
    {
        // 未处理，处理中，可以 操作退款，拒绝，完成
        return $query->where('aftersale_status', 'in', [
            OrderAftersale::AFTERSALE_STATUS_NOOPER,
            OrderAftersale::AFTERSALE_STATUS_AFTERING
        ]);
    }

    // 可以删除
    public function scopeCanDelete($query)
    {
        // 取消，拒绝，完成可以删除
        return $query->where('aftersale_status', 'in', [
            OrderAftersale::AFTERSALE_STATUS_CANCEL,
            OrderAftersale::AFTERSALE_STATUS_REFUSE,
            OrderAftersale::AFTERSALE_STATUS_OK
        ]);
    }


    public static function getScopeWhere($scope) {
        $where = [];
        switch($scope) {
            case 'cancel':
                $where['aftersale_status'] = OrderAftersale::AFTERSALE_STATUS_CANCEL;
                break;
            case 'refuse':
                $where['aftersale_status'] = OrderAftersale::AFTERSALE_STATUS_REFUSE;
                break;
            case 'nooper':
                $where['aftersale_status'] = OrderAftersale::AFTERSALE_STATUS_NOOPER;
                break;
            case 'ing':
                $where['aftersale_status'] = OrderAftersale::AFTERSALE_STATUS_AFTERING;
                break;
            case 'finish':
                $where['aftersale_status'] = OrderAftersale::AFTERSALE_STATUS_OK;
                break;
        }

        return $where;
    }
}
