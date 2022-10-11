<?php

namespace addons\shopro\model;

use addons\shopro\exception\Exception;
use think\Model;
use traits\model\SoftDelete;
/**
 * 快递模型
 */
class Dispatch extends Model
{
    use SoftDelete;

    protected $name = 'shopro_dispatch';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    protected $hidden = ['createtime', 'updatetime', 'deletetime'];

    // 追加属性
    protected $append = [

    ];


    // 计算运费
    public static function getDispatch($dispatch_type, $detail, $data = [])
    {
        // TODO: 1.拿到用户传来的dispatch_type,然后匹配goodsDetail里面的dispatch_type。
        //       2.从goodsDetail里面拿dispatch_ids，遍历匹配dispatch表里的id 如果type和id都对上了 就代表拿到了发货模板

        $address = $data['address'] ?? null;
        $goods_num = $data['goods_num'] ?? 1;

        if (strpos($detail->dispatch_type, $dispatch_type) === false) {
            new Exception('当前所选配送方式不支持');
        }

        $dispatch_ids = explode(',', $detail->dispatch_ids);
        $dispatch = Dispatch::where('type', $dispatch_type)->where('id', 'in', $dispatch_ids)->find();

        if (!$dispatch) {
            new Exception('配送方式不存在');
        }

        $result['dispatch_id'] = $dispatch['id'];

        $dispatch_amount = 0;
        if ($dispatch_type == 'express') {
            if (!$address) {
                // 还没选收货地址
                $result['dispatch_amount'] = $dispatch_amount;
                return $result;
            }
            // 物流快递
            $dispatch_express_ids = explode(',', $dispatch->type_ids);

            $dispatchExpress = DispatchExpress::where('id', 'in', $dispatch_express_ids)
                                    ->order('weigh', 'desc')->order('id', 'asc')->select();

            $finalExpress = null;

            foreach ($dispatchExpress as $key => $express) {
                if (strpos($express->area_ids, strval($address->area_id)) !== false) {
                    $finalExpress = $express;
                    break;
                }

                if (strpos($express->city_ids, strval($address->city_id)) !== false) {
                    $finalExpress = $express;
                    break;
                }

                if (strpos($express->province_ids, strval($address->province_id)) !== false) {
                    $finalExpress = $express;
                    break;
                }
            }

            if ($finalExpress) {
                // 初始费用
                $dispatch_amount = $finalExpress->first_price;

                if ($finalExpress['type'] == 'number') {
                    // 按件计算

                    if ($finalExpress->additional_num && $finalExpress->additional_price) {
                        // 首件之后剩余件数
                        $surplus_num = $goods_num - $finalExpress->first_num;

                        // 多出的计量
                        $additional_mul = ceil(($surplus_num / $finalExpress->additional_num));
                        if ($additional_mul > 0) {
                            $current_dispatch_amount = bcmul($additional_mul, $finalExpress->additional_price, 2);
                            $dispatch_amount = bcadd($dispatch_amount, $current_dispatch_amount, 2);
                        }
                    }
                } else {
                    // 按重量计算

                    if ($finalExpress->additional_num && $finalExpress->additional_price) {
                        // 首重之后剩余重量
                        $surplus_num = ($detail->current_sku_price->weight * $goods_num) - $finalExpress->first_num;

                        // 多出的计量
                        $additional_mul = ceil(($surplus_num / $finalExpress->additional_num));
                        if ($additional_mul > 0) {
                            $current_dispatch_amount = bcmul($additional_mul, $finalExpress->additional_price, 2);
                            $dispatch_amount = bcadd($dispatch_amount, $current_dispatch_amount, 2);
                        }
                    }

                }
            } else {
                new Exception('当前地区不在配送范围');
            }

        } else if ($dispatch_type == 'store') {
            if (!$address) {
                // 还没选收货地址
                $result['dispatch_amount'] = $dispatch_amount;
                return $result;
            }

            // 支持配送该商品的门店
            $dispatch_store_ids = explode(',', $dispatch->type_ids);
            // 一个 store 类型的 dispatch 对应一条 dispatch_selfetch
            $dispatchStore = DispatchStore::where('id', 'in', $dispatch_store_ids)
                            ->order('id', 'asc')->find();

            if (!$dispatchStore) {
                new Exception('暂不支持商家配送');
            }

            $store_ids = $dispatchStore['store_ids'];

            $store = Store::show()->where('store', 1);
            if ($store_ids) {
                // 部分门店
                $store = $store->where('id', 'in', $store_ids);
            }
            if ($address->latitude && $address->longitude) {
                $store = $store->field('*, ' . getDistanceBuilder($address->latitude, $address->longitude))->order('distance', 'asc');
            } else {
                new Exception('请编辑收货地址选择坐标');
            }

            $store = $store->order('id', 'asc')->find();

            if (!$store) {
                new Exception('当前暂不支持商家配送');
            }

            if ($store['service_type'] == 'radius') {
                // 按服务半径，收货地址坐标为空的时候无法下单
                if (!isset($store['distance']) || $store['distance'] > $store['service_radius']) {
                    new Exception('当前收货地址不在配送范围');
                }
            } else if ($store['service_type'] == 'area') {
                // 按行政区域
                $service_province_ids = explode(',', $store['service_province_ids']);
                $service_city_ids = explode(',', $store['service_city_ids']);
                $service_area_ids = explode(',', $store['service_area_ids']);
                if (
                    !in_array($address['province_id'], $service_province_ids)
                    && !in_array($address['city_id'], $service_city_ids)
                    && !in_array($address['area_id'], $service_area_ids)
                ) {
                    new Exception('当前收货地址不在配送范围');
                }
            } else if ($store['service_type'] == 'diyarea') {
                // 自定义区域
                $service_area = $store['service_area'];
                if(empty($service_area) || strlen($service_area) < 5){
                    new Exception('当前收货地址不在配送范围');
                }
                $latitude = $address->latitude;
                $longitude = $address->longitude;
                // 判断是否在区域范围
                $res = inArea($longitude, $latitude, json_decode($service_area, true));
                if(!$res){
                    new Exception('当前收货地址不在配送范围');
                }
            }

            $result['store'] = $store;

            // 这里配送费，商家陪送暂时不要配送费
        } else if ($dispatch_type == 'selfetch') {
            // 判断是否有上门自提的模板
            $dispatch_selfetch_ids = explode(',', $dispatch->type_ids);
            // 一个 selfetch 类型的 dispatch 对应一条 dispatch_selfetch
            $dispatchSelfetch = DispatchSelfetch::where('id', 'in', $dispatch_selfetch_ids)
                ->order('id', 'asc')->find();

            if (!$dispatchSelfetch) {
                new Exception('暂不支持上门自提');
            }

            // 目前不需要处理
        } else if ($dispatch_type == 'autosend') {
            $dispatch_autosend_ids = explode(',', $dispatch->type_ids);
            // 一个 autosend 类型的 dispatch 对应一条 dispatch_autosend
            $dispatchAutosend = DispatchAutosend::where('id', 'in', $dispatch_autosend_ids)
                        ->order('id', 'asc')->find();

            if (!$dispatchAutosend) {
                new Exception('暂不支持自动发货');
            }

            // 目前不需要处理
        } else {
            new Exception('配送方式不支持');
        }

        $result['dispatch_amount'] = $dispatch_amount;
        return $result;
    }
}
