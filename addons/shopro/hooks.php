<?php

$defaultHooks = [
  // 订单创建
  'order_create_before' => [       // 订单创建前
    'addons\\shopro\\listener\\order\\Create'
  ],
  'order_create_after' => [        // 订单创建后
    'addons\\shopro\\listener\\order\\Create'
  ],
  'order_payed_after' => [        // 订单支付成功
    'addons\\shopro\\listener\\order\\Payed'
  ],

  // 订单关闭
  'order_close_before' => [       // 订单关闭前
  ],
  'order_close_after' => [        // 订单关闭后
    'addons\\shopro\\listener\\order\\Invalid'
  ],

  // 订单取消
  'order_cancel_before' => [        // 订单取消前
  ],
  'order_cancel_after' => [         // 订单取消后
    'addons\\shopro\\listener\\order\\Invalid'
  ],

  // 订单发货
  'order_send_before' => [       // 订单发货前
  ],
  'order_send_after' => [        // 订单发货后
    'addons\\shopro\\listener\\order\\Send'
  ],

  // 订单确认收货
  'order_confirm_before' => [       // 订单确认收货前
  ],
  'order_confirm_after' => [        // 订单确认收货后
    'addons\\shopro\\listener\\order\\Confirm'
  ],
  'order_confirm_finish' => [       // 订单确认收货完成
  ],

  // 订单完成事件
  'order_finish' => [],

  // 订单评价
  'order_comment_before' => [       // 订单评价前
  ],
  'order_comment_after' => [        // 订单评价后
    'addons\\shopro\\listener\\order\\Comment'
  ],

  // 订单退款
  'order_refund_before' => [       // 订单退款前
    'addons\\shopro\\listener\\order\\Refund'
  ],
  'order_refund_after' => [        // 订单退款后
    'addons\\shopro\\listener\\order\\Refund'
  ],

  // 售后完成
  'aftersale_finish_before' => [        // 售后完成前
  ],
  'aftersale_finish_after' => [        // 售后完成后
  ],

  // 售后拒绝
  'aftersale_refuse_before' => [        // 售后拒绝前
  ],
  'aftersale_refuse_after' => [        // 售后拒绝后
  ],

  // 售后变动，（包含完成，拒绝）
  'aftersale_change' => [               // 售后变动
    'addons\\shopro\\listener\\order\\Aftersale'
  ],

  // 活动更新
  'activity_update_after' => [        // 活动更新后
    'addons\\shopro\\listener\\activity\\Update'
  ],
  'activity_delete_after' => [        // 活动删除之后
    'addons\\shopro\\listener\\activity\\Update'
  ],

  // 拼团
  'activity_groupon_finish' => [        // 拼团成功
    'addons\\shopro\\listener\\activity\\Groupon'
  ],
  'activity_groupon_fail' => [        // 拼团失败，超时，后台手动解散等
    'addons\\shopro\\listener\\activity\\Groupon'
  ]
];

// 分销相关钩子
$commissionHooks = [
  'order_payed_after' => [        // 订单支付成功
    'addons\\shopro\\listener\\commission\\CommissionHook'
  ],
  'share_after' => [            //分享后
    'addons\\shopro\\listener\\commission\\CommissionHook'
  ],
  'order_confirm_after' => [        // 订单确认收货后
    'addons\\shopro\\listener\\commission\\CommissionHook'
  ],
  'order_refund_after' => [        // 订单退款后
    'addons\\shopro\\listener\\commission\\CommissionHook'
  ],
  'order_finish' => [   // 订单完成事件
    'addons\\shopro\\listener\\commission\\CommissionHook'
  ],
  

];

if (file_exists(ROOT_PATH . 'addons/shopro/listener/commission')) {
  $defaultHooks = array_merge_recursive($defaultHooks, $commissionHooks);
}

return $defaultHooks;
