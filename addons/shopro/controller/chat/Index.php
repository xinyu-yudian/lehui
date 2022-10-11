<?php

namespace addons\shopro\controller\chat;

use addons\shopro\controller\Base as AddonsBase;
use addons\shopro\model\chat\Question;

/**
 * Index 
 */
class Index extends AddonsBase
{
    protected $noNeedLogin = ['init'];
    protected $noNeedRight = ['*'];


    /**
     * 客服初始化
     *
     * @return void
     */
    public function init() {
        $config = json_decode(\addons\shopro\model\Config::where(['name' => 'chat'])->value('value'), true);
        // 初始化 ssl 类型, 默认 cert
        $config['system'] = $config['system'] ?? [];
        $config['system']['ssl_type'] = $config['system']['ssl_type'] ?? 'cert';

        // 常见问题
        $question = Question::show()->order('weigh', 'desc')->select();

        $result = [
            'config' => $config,
            'question' => $question,
            'emoji' => json_decode(file_get_contents(ROOT_PATH . 'public/assets/addons/shopro/libs/emoji.json'), true)
        ];

        $this->success('初始化成功', $result);
    }
}