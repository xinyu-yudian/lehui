<?php

namespace addons\shopro\library;

use fast\Http;

class Express
{
    // 查询接口
    const REQURL = "https://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx";
    // 订阅接口
    const SUBURL = "https://api.kdniao.com/api/dist";
    // 电子面单下单接口
    const API_EORDER = "https://api.kdniao.com/api/EOrderService";
    protected $config = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $config = \addons\shopro\model\Config::get(['name' => 'services']);
        $config = ($config && $config->value) ? json_decode($config->value, true) : [];

        $expressConfig = $config['express'] ?? [];
        if (!$expressConfig || !$expressConfig['ebusiness_id'] || !$expressConfig['appkey']) {
            throw new \Exception('请配置快递接口');
        }

        $this->config = $expressConfig;
    }


    /**
     * Json方式  物流信息订阅
     */
    public function subscribe($data = [], $orderExpress = null, $order = null)
    {
        $requestData = $this->getRequestData($data, $orderExpress, $order);

        $datas = [
            'EBusinessID' => $this->config['ebusiness_id'],
            'RequestType' => $this->config['type'] == 'free' ? '1008' : '8008',
            'RequestData' => urlencode($requestData),
            'DataType' => '2',
        ];
        $datas['DataSign'] = $this->encrypt($requestData, $this->config['appkey']);

        $result = Http::sendRequest(self::SUBURL, $datas, 'POST', []);

        if ($result['ret'] == 1) {
            $exResult = json_decode($result['msg'], true);

            if (!$exResult['Success']) {
                throw new \Exception($exResult['Reason']);
            }

            return $exResult;
        } else {
            throw new \Exception($result['msg']);
        }
    }


    // 查询快递信息
    public function search($data = [], $orderExpress = null, $order = null)
    {
        $requestData = $this->getRequestData($data, $orderExpress, $order);
        
        $datas = [
            'EBusinessID' => $this->config['ebusiness_id'],
            'RequestType' => $this->config['type'] == 'free' ? '1002' : '8001',
            'RequestData' => urlencode($requestData),
            'DataType' => '2',
        ];
        $datas['DataSign'] = $this->encrypt($requestData, $this->config['appkey']);
        $result = Http::sendRequest(self::REQURL, $datas, 'POST', []);

        if ($result['ret'] == 1) {
            $exResult = json_decode($result['msg'], true);

            if (!$exResult['Success']) {
                throw new \Exception($exResult['Reason']);
            }

            return $exResult;
        } else {
            throw new \Exception($result['msg']);
        }
    }


    // 组装请求数据
    private function getRequestData($data = [], $orderExpress = null, $order = null) {
        $requestData = [
            'OrderCode' => $order ? $order->order_sn : '',
            'ShipperCode' => $data['express_code'],
            'LogisticCode' => $data['express_no'],
        ];

        if ($data['express_code'] == 'JD') {
            // 京东青龙配送单号
            $requestData['CustomerName'] = $this->config['jd_code'];
        } else if ($data['express_code'] == 'SF') {
            // 收件人手机号后四位
            $requestData['CustomerName'] = substr($order->phone, 7);
        }

        $requestData = json_encode($requestData);

        return $requestData;
    }



    // 差异更新物流信息
    public function checkAndAddTraces ($orderExpress, $express) {
        $traces = $express['Traces'];

        // 查询现有轨迹记录
        $orderExpressLog = \addons\shopro\model\OrderExpressLog::where('order_express_id', $orderExpress->id)->select();

        $log_count = count($orderExpressLog);
        if ($log_count > 0) {
            // 移除已经存在的记录
            array_splice($traces, 0, $log_count);
        }

        // 增加包裹记录
        foreach ($traces as $k => $trace) {
            $orderExpressLog = new \addons\shopro\model\OrderExpressLog();
    
            $orderExpressLog->user_id = $orderExpress['user_id'];
            $orderExpressLog->order_id = $orderExpress['order_id'];
            $orderExpressLog->order_express_id = $orderExpress['id'];
            $orderExpressLog->status = $trace['Action'] ?? $express['State'];
            $orderExpressLog->content = $trace['AcceptStation'];
            $orderExpressLog->changedate = substr($trace['AcceptTime'], 0, 19);     // 快递鸟测试数据 返回的是个 2020-08-03 16:58:272 格式
            $orderExpressLog->location = $trace['Location'] ?? ($express['Location'] ?? null);
            $orderExpressLog->save();
        }
    }

    public function eorder($order, $item_lists)
    {
        if($this->config['type'] !== 'vip') {
            throw new \Exception('请使用快递鸟标准版开通此功能');
        }
        $orderData = [
            "OrderCode" => $order->order_sn,
            "CustomerName" => $this->config['CustomerName'],
            "CustomerPwd" => $this->config['CustomerPwd'],
            "ShipperCode" => $this->config['ShipperCode'],
            "PayType" => $this->config['PayType'],
            "ExpType" => $this->config['ExpType'],
            "IsReturnPrintTemplate" => 0,   //返回打印面单模板
            "TemplateSize" => '130',    // 一联单   
            "Sender" => $this->config['Sender'],
            "Volume" => 0,
            "Remark" => $order->remark ? $order->remark : "小心轻放"
          ];
          $totalCount = 0;
          $totalWeight = 0;
          foreach($item_lists as $k => $item) {
            if($item->goods_sku_text) {
                $goodsName = $item->goods_title . '-' . $item->goods_sku_text;
            }else {
                $goodsName = $item->goods_title;
            }
            $orderData['Commodity'][] = [
                "GoodsName" =>  $goodsName,
                "Goodsquantity" => $item->goods_num,
                "GoodsWeight" => $item->goods_num * $item->goods_weight
            ];
            $totalCount += $item->goods_num;
            $totalWeight += $item->goods_num * $item->goods_weight;
          }
          $orderData['Quantity'] = $totalCount; // 商品数量
          $orderData['Weight'] = $totalWeight;
          $orderData['Receiver'] = [
            "Name" => $order->consignee,
            "Mobile" => $order->phone,
            "ProvinceName" => $order->province_name,
            "CityName" => $order->city_name,
            "ExpAreaName" => $order->area_name,
            "Address" => $order->address
          ];
          $data = json_encode($orderData, JSON_UNESCAPED_UNICODE);
          $datas = [
            'EBusinessID' => $this->config['ebusiness_id'],
            'RequestType' => '1007',
            'RequestData' => urlencode($data),
        ];
        $datas['DataSign'] = $this->encrypt($data, $this->config['appkey']);

        $result = Http::sendRequest(self::API_EORDER, $datas, 'POST', []);
     
        if ($result['ret'] == 1) {
            $exResult = json_decode($result['msg'], true);

            if (!$exResult['Success']) {
                throw new \Exception($exResult['Reason']);
            }

            return $exResult;
        } else {
            throw new \Exception($result['msg']);
        }
    }


    // 组装返回结果
    public function setPushResult($success = false, $reason = '') {
        $result = [
            "EBusinessID" => $this->config['ebusiness_id'],
            "UpdateTime" => date('Y-m-d H:i:s'),
            "Success" => $success,
            "Reason" => $reason
        ];

        return json_encode($result);
    }


    // 加签
    function encrypt($data, $appkey)
    {
        return urlencode(base64_encode(md5($data . $appkey)));
    }
}