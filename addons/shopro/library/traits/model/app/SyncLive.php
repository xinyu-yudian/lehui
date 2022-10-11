<?php

namespace addons\shopro\library\traits\model\app;

use addons\shopro\library\Wechat;
use addons\shopro\model\LiveGoods;
use addons\shopro\model\LiveLink;
use think\Cache;

trait SyncLive
{
    public static function autoSyncLive()
    {
        $is_sync = Cache::get('live_sync');
        if (!$is_sync) {
            // 同步直播间
            self::syncLive();

            // 设置缓存
            Cache::set('live_sync', 'yes', 240);        // 缓存 4分钟
        }
    }


    public static function autoSyncLiveLink($live)
    {
        $is_sync = Cache::get('live_link_sync');
        if (!$is_sync) {
            // 同步直播间
            self::syncLiveLink($live);

            // 设置缓存
            Cache::set('live_link_sync', 'yes', 240);        // 缓存 4分钟
        }
    }


    // 同步直播间
    public static function syncLive()
    {
        // 拉取直播间
        $rooms = (new Wechat('wxMiniProgram'))->live();

        $room_ids = array_column($rooms, 'roomid');
        // 查询现有直播间
        $lives = self::where('room_id', 'in', $room_ids)->with('goods')->limit(20)->select();

        foreach ($rooms as $key => $room) {
            $liveModel = null;
            foreach ($lives as $k => $live) {
                if ($live['room_id'] == $room['roomid']) {
                    $liveModel = $live;
                    break;
                }
            }

            $data['name'] = $room['name'] ?? '';
            $data['room_id'] = $room['roomid'];
            $data['cover_img'] = $room['cover_img'] ?? '';
            $data['live_status'] = $room['live_status'] ?? '';
            $data['starttime'] = $room['start_time'] ?? '';
            $data['endtime'] = $room['end_time'] ?? '';
            $data['anchor_name'] = $room['anchor_name'] ?? '';
            $data['anchor_img'] = $room['anchor_img'] ?? '';
            $data['share_img'] = $room['share_img'] ?? '';

            if ($liveModel) {
                $data['updatetime'] = time();
                (new self)->where('id', $liveModel['id'])->update($data);

                // 更新商品信息
                self::syncGoods($liveModel['id'], $room['goods'], $liveModel['goods']);
            } else {
                $liveModel = new self();
                $liveModel->save($data);

                // 添加商品信息
                if ($room['goods']) {
                    self::syncGoods($liveModel['id'], $room['goods']);
                }
            }
        }

        return true;
    }



    public static function syncGoods($live_id, $roomGoods, $modelGoods = [])
    {
        foreach ($roomGoods as $key => $goods) {
            $data = [
                'live_id' => $live_id,
                'name' => $goods['name'],
                'url' => $goods['url'],
                'cover_img' => $goods['cover_img'],
            ];

            $price = self::getLiveGoodsPrice($goods);

            $data = array_merge($data, $price);

            if (isset($modelGoods[$key])) {
                (new LiveGoods())->where('id', $modelGoods[$key]['id'])->update($data);
            } else {
                $liveGoodsModel = new LiveGoods();
                $liveGoodsModel->save($data);
            }
        }

        // 删除多余的
        if (count($modelGoods) > count($roomGoods)) {
            foreach ($modelGoods as $key => $goods) {
                if ($key < count($roomGoods)) {
                    continue;
                }

                // 删除
                $goods->delete();
            }
        }
    }


    public static function syncLiveLink ($live) {
        $modelLink = $live->links;

        $liveReplay = (new Wechat('wxMiniProgram'))->liveReplay([
            'room_id' => $live['room_id']
        ]);

        foreach ($liveReplay as $key => $media) {
            $data = [
                'live_id' => $live['id'],
                'media_url' => $media['media_url'],
                'create_time' => $media['create_time'],
                'expire_time' => $media['expire_time'],
            ];

            if (isset($modelLink[$key])) {
                (new LiveLink())->where('id', $modelLink[$key]['id'])->update($data);
            } else {
                $liveGoodsModel = new LiveLink();
                $liveGoodsModel->data($data)->save();
            }
        }

        // 删除多余的
        if (count($modelLink) > count($liveReplay)) {
            foreach ($modelLink as $key => $link) {
                if ($key < count($liveReplay)) {
                    continue;
                }

                // 删除
                $link->delete();
            }
        }
    }


    public static function getLiveGoodsPrice($goods)
    {
        $price = [];
        if ($goods['price_type'] == 1) {
            // 一口价
            $price['max_price'] = 0;
            $price['origin_price'] = 0;
            $price['price'] = round($goods['price'] / 100, 2);
        } else if ($goods['price_type'] == 2) {
            // 价格区间
            $price['origin_price'] = 0;
            $price['price'] = round($goods['price'] / 100, 2);
            $price['max_price'] = round($goods['price2'] / 100, 2);
        } else if ($goods['price_type'] == 3) {
            // 原价现价
            $price['max_price'] = 0;
            $price['origin_price'] = round($goods['price'] / 100, 2);
            $price['price'] = round($goods['price2'] / 100, 2);
        }

        return $price;
    }
}
