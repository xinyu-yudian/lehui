<?php

namespace app\admin\controller\shopro;

use app\common\controller\Backend;

/**
 * 消息管理
 *
 * @icon fa fa-circle-o
 */
class Notification extends Backend
{
    
    /**
     * Notification模型对象
     * @var \app\admin\model\shopro\Notification
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\Notification;
        $this->modelConfig = new \app\admin\model\shopro\notification\Config;

    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index()
    {
        
    }

    public function config()
    {
        if ($this->request->isAjax()) {
            // 检测队列
            checkEnv('queue');

            // 消息类型
            $notificationType = [
                \addons\shopro\notifications\Groupon::class,
                \addons\shopro\notifications\Order::class,
                \addons\shopro\notifications\Refund::class,
                \addons\shopro\notifications\Aftersale::class,
                \addons\shopro\notifications\Wallet::class,
                \addons\shopro\notifications\store\Order::class,
                \addons\shopro\notifications\store\Apply::class
            ];

            // 获取所有要发送的消息
            $fields = [];
            foreach ($notificationType as $key => $class) {
                $currentFields = $class::$returnField;
                if ($currentFields) {
                    $fields = array_merge($fields, $currentFields);
                }
            }

            // 读取数据库相关消息配置项
            $notificationConfig = $this->modelConfig->select();

            // 组合消息类型和设置值
            $newFields = [];
            foreach ($fields as $k => $field) {
                // 组合每个平台的消息默认值和数据库值
                $kConfig = $this->getKconfig($notificationConfig, $k, $field);

                $newFields[] = [
                    'type' => $k,
                    'name' => $field['name'],
                    'wxMiniProgram' => $kConfig['wxMiniProgram'] ?? [],
                    'wxOfficialAccount' => $kConfig['wxOfficialAccount'] ?? [],
                    'wxOfficialAccountBizsend' => $kConfig['wxOfficialAccountBizsend'] ?? [],
                    'sms' => $kConfig['sms'] ?? [],
                    'email' => $kConfig['email'] ?? []
                ];
            }

            $this->success('获取成功', null, $newFields);
        }

        return $this->view->fetch();
    }


    // 配置状态
    public function set_status() {
        $platform = $this->request->post('platform', '');
        $event = $this->request->post('event', '');
        $name = $this->request->post('name', '');
        $status = $this->request->post('status', 0);

        if (!$platform || !$event) {
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $config = $this->modelConfig->where([
            'platform' => $platform,
            'event' => $event
        ])->find();

        if (!$config) {
            $config = $this->modelConfig;
            $config->platform = $platform;
            $config->event = $event;
            $config->name = $name;
        }
        $config->status = intval($status);
        $config->save();

        $this->success('设置成功');
    }


    // 配置模板
    public function set_template()
    {
        $platform = $this->request->post('platform');
        $event = $this->request->post('event');
        $name = $this->request->post('name');
        $content = $this->request->post('content', "");

        if (!$platform || !$event) {
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $config = $this->modelConfig->where([
            'platform' => $platform,
            'event' => $event
        ])->find();

        if (!$config) {
            $config = $this->modelConfig;
            $config->platform = $platform;
            $config->event = $event;
            $config->name = $name;
        }
        $config->content = $content;
        $config->save();

        $this->success('设置成功');
    }



    private function getKConfig($notificationConfig, $k, $field) {
        // 将默认值中追加 template_field  和 value 空字段
        foreach ($field['fields'] as &$f) {
            $f['template_field'] = $f['template_field'] ?? '';
            $f['value'] = $f['value'] ?? '';
        }

        // 初始化defalut
        $kConfig = [
            'wxMiniProgram' => [
                'id' => 0,
                'platform' => 'wxMiniProgram',
                'name' => $field['name'],
                'event' => $k,
                'status' => 0,
                'sendnum' => 0,
                'content_arr' => [
                    'template_id' => '',
                    'fields' => $field['fields']
                ]
            ],
            'wxOfficialAccount' => [
                'id' => 0,
                'platform' => 'wxOfficialAccount',
                'name' => $field['name'],
                'event' => $k,
                'status' => 0,
                'sendnum' => 0,
                'content_arr' => [
                    'template_id' => '',
                    'fields' => $field['fields']
                ]
            ],
            'wxOfficialAccountBizsend' => [
                'id' => 0,
                'platform' => 'wxOfficialAccountBizsend',
                'name' => $field['name'],
                'event' => $k,
                'status' => 0,
                'sendnum' => 0,
                'content_arr' => [
                    'template_id' => '',
                    'fields' => $field['fields']
                ]
            ],
            'sms' => [
                'id' => 0,
                'platform' => 'sms',
                'name' => $field['name'],
                'event' => $k,
                'status' => 0,
                'sendnum' => 0,
                'content_arr' => [
                    'template_id' => '',
                    'fields' => $field['fields']
                ]
            ],
            'email' => [
                'id' => 0,
                'platform' => 'email',
                'name' => $field['name'],
                'event' => $k,
                'status' => 0,
                'sendnum' => 0,
                'content_arr' => [
                    'template_id' => '',
                    'fields' => $field['fields']
                ]
            ]
        ];

        // 合并数据库中的设置
        foreach ($notificationConfig as $config) {
            if ($config['event'] == $k) {
                $currentConfig = $config->toArray();
                
                // 如果数据库中有内容
                if ($currentConfig['content_arr']) {
                    $contentArr = $currentConfig['content_arr'];

                    // 合并,数据库和默认 fields 字段（发送类型增加返回字段时候有用）
                    $contentArrFields = [];
                    if (isset($contentArr['fields']) && $contentArr['fields']) {    // 判断数组是否存在 fields 设置
                        $contentArrFields = array_column($contentArr['fields'], null, 'field');
                    }
                    $kConfigFields = array_column($kConfig[$config['platform']]['content_arr']['fields'], null, 'field');
                    $configField = array_merge($kConfigFields, $contentArrFields);

                    $contentArr['fields'] = array_values($configField);

                    $currentConfig['content_arr'] = $contentArr;
                } else {
                    // 数据库有记录，但内容是空，（先开启了开关）
                    $currentConfig['content_arr'] = $kConfig[$config['platform']]['content_arr'];
                }

                $kConfig[$config['platform']] = $currentConfig;
            }
        }

        return $kConfig;
    }
}
