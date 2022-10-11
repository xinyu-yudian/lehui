<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
use addons\shopro\model\Order;
use addons\shopro\model\User;
use think\Db;
use think\Log;

class Express extends Base
{

    protected $noNeedLogin = ['callback'];
    protected $noNeedRight = ['*'];


    /**
     * 物流信息订阅回调接口
     */
    public function callback()
    {
        $requestData = $this->request->post();

        $expressLib = new \addons\shopro\library\Express();

        // 信息记录日志
        // \think\Log::write('expresscallback:'. json_encode($requestData));

        $data = json_decode(html_entity_decode($requestData['RequestData']), true);
        $expressData = $data['Data'];

        foreach ($expressData as $key => $express) {
            // 查找包裹
            $orderExpress = \addons\shopro\model\OrderExpress::with('order')->where('express_code', $express['ShipperCode'])
                ->where('express_no', $express['LogisticCode'])
                ->find();

            if (!$orderExpress) {
                // 包裹不存在,记录日志信息，然后继续下一个
                \think\Log::write('orderExpressNotFound:' . json_encode($express));
                continue;
            }

            if (!$express['Success']) {
                // 失败了
                if (isset($express['Reason']) && ($express['Reason'] == '三天无轨迹' || $express['Reason'] == '七天无轨迹')) {
                    // 需要重新订阅
                    $expressLib->subscribe([
                        'express_code' => $express['ShipperCode'],
                        'express_no' => $express['LogisticCode']
                    ], $orderExpress, $orderExpress->order);
                }

                \think\Log::write('orderExpressReason:' . json_encode($express));
                continue;
            }

            $expressLib->checkAndAddTraces($orderExpress, $express);
        }

        return $expressLib->setPushResult(true);
    }
}
