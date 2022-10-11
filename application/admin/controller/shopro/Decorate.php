<?php

namespace app\admin\controller\shopro;

use app\common\controller\Backend;
use app\admin\model\shopro\DecorateContent;
use think\Db;
use fast\Http;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;

/**
 * 店铺装修
 *
 * @icon fa fa-circle-o
 */
class Decorate extends Backend
{
    /**
     * Decorate模型对象
     * @var \app\admin\model\shopro\Decorate
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\Decorate;
        $this->assignconfig('shoproConfig', $this->getShoproConfig());
    }

    public function lists($type = '')
    {
        if ($this->request->isAjax()) {
            $data = $this->model->where('type', $type)->order('id', 'desc')->select();
            $this->success('模板列表', null, $data);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->allowField(true)->save($params);
                    //添加默认数据
                    if ($params['type'] === 'shop') {
                        DecorateContent::create([
                            'type' => 'banner',
                            'category' => 'home',
                            'name' => '轮播图',
                            'content' => '{"name":"","style":1,"height":530,"radius":0,"x":0,"y":0,"list":[]}',
                            'decorate_id' => $this->model->id
                        ]);
                        DecorateContent::create([
                            'type' => 'user',
                            'category' => 'user',
                            'name' => '用户卡片',
                            'content' => '{"name":"用户卡片","image":"","style":1,"color":"#eeeeee"}',
                            'decorate_id' => $this->model->id
                        ]);
                    }
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    //添加默认模板数据
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($id = null)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $params = $this->preExcludeFields($params);
                //检查是否有同平台冲突的已发布模板
                if ($row->status === 'normal' && $row['type'] === 'shop') {
                    $platformArray = explode(',', $params['platform']);
                    $where = ['deletetime' => null, 'status' => 'normal', 'type' => 'shop', 'id' => ['neq', $id]];
                    foreach ($platformArray as $v) {
                        $publishDecorate = $this->model->where('find_in_set(:platform,platform)', ['platform' => $v])->where($where)->find();
                        if ($publishDecorate) {
                            $this->error(__($v) . ' 已经被使用');
                        }
                    }
                }
                $result = false;
                Db::startTrans();
                try {
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 模板管理 发布
     * @param string $id
     * @param int $force
     */
    public function publish($id, $force = 0)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (empty($row->platform)) {
            $this->error('请勾选发布平台', null, 0);
        }
        $platformArray = explode(',', $row->platform);
        $where = ['deletetime' => null, 'status' => 'normal', 'type' => 'shop'];
        $existPublish = [];
        foreach ($platformArray as $v) {
            $publishDecorate = $this->model->where('find_in_set(:platform,platform)', ['platform' => $v])->where($where)->find();
            if ($publishDecorate) {
                if ($force == 1) {
                    $platform = array_diff(explode(',', $publishDecorate->platform), [$v]);
                    $publishDecorate->platform = implode(',', $platform);
                    if ($publishDecorate->platform == '') {
                        $publishDecorate->status = 'hidden';
                    }
                    $publishDecorate->save();
                } else {
                    $existPublish[$publishDecorate->name][] = __($v);
                }
            }
        }

        if ($existPublish !== [] && $force == 0) {
            $str = '';
            foreach ($existPublish as $k => $e) {
                $str .= $k . ',';
            }
            $this->error("${str} 已存在相同的支持平台,确定替换吗?");
        }
        $row->status = 'normal';
        $row->save();
        $this->success('发布成功');
    }

    /**
     * 模板管理 下架
     * @param string $id
     */
    public function down($id)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $where = ['deletetime' => null, 'status' => 'normal', 'type' => 'shop'];
        $publishDecorate = $this->model->where($where)->select();
        if (count($publishDecorate) == 1) {
            $this->error('需要至少保留一个发布模板~');
        }

        $row->status = 'hidden';
        $row->save();
        $this->success('下架成功');
    }

    /**
     * 模板管理 复制
     * @param string $id
     */
    public function copy($id)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $this->model->save([
            'name' => "复制 {$row->name}",
            'type' => $row->type,
            'memo' => $row->memo,
            'image' => $row->image,
            'status' => 'hidden',
            'platform' => $row->platform,
        ]);
        $id = $this->model->id;
        $content = collection(DecorateContent::where('decorate_id', $row->id)
            ->order('id asc')
            ->field("type, category, content, name, $id as decorate_id")
            ->select())->toArray();

        $decorateContent = new DecorateContent();
        $decorateContent->saveAll($content);
        $this->success('复制成功');
    }


    /**
     * 自定义页面
     */
    public function custom()
    {
        return $this->view->fetch();
    }


    /**
     * 页面装修
     * @param string $id
     */
    public function dodecorate($id)
    {
        $content = new DecorateContent();
        $query = $content->where(['decorate_id' => $id]);
        if ($this->request->isPost()) {
            $params = $this->request->post("templateData");
            if ($params) {
                $params = json_decode($params, true);
                $result = false;
                Db::startTrans();
                try {
                    $decorateArray = [];
                    foreach ($params as $p => $a) {
                        foreach ($a as $c => &$o) {
                            if (isset($o['id'])) {
                                unset($o['id']);
                            }
                            $decorateArray[] = [
                                'category' => $p,
                                'content' => json_encode($o['content'], JSON_UNESCAPED_UNICODE),
                                'decorate_id' => $id,
                                'name' => $o['name'],
                                'type' => $o['type']
                            ];
                        }
                    }
                    $query->delete();
                    $result = new \app\admin\model\shopro\DecorateContent;
                    $result->saveAll($decorateArray);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error('请完善装修页面');
        }
        $template = $query->select();
        if ($template) {
            foreach ($template as &$t) {
                $t['content'] = json_decode($t['content'], true);
            }
        } else {
            $template = [];
        }
        $categoryArray = array_column($template, 'category');
        $templateData = [];
        foreach ($categoryArray as $categoryKey => $category) {
            $templateData[$category][] = $template[$categoryKey];
        }
        $this->assignconfig('templateData', $templateData);
        return $this->view->fetch();
    }

    /**
     * 页面装修 保存
     * @param string $id
     */
    public function dodecorate_save($id)
    {
        if ($this->request->isPost()) {
            $decorate = $this->model->get($id);

            if (!$decorate) {
                $this->error(__('No Results were found'));
            }

            $params = $this->request->post("templateData");
            $result = $this->updateDecorateContent($id, $params);
            if ($result) {
                $this->success('保存成功', '', $decorate);
            } else {
                $this->error('保存失败');
            }
        }
    }

    private function updateDecorateContent($id, $params)
    {
        $result = false;

        if ($params) {
            $params = json_decode($params, true);
            Db::startTrans();
            try {
                $decorateArray = [];
                foreach ($params as $p => $a) {
                    foreach ($a as &$o) {
                        if (isset($o['id'])) {
                            unset($o['id']);
                        }
                        $decorateArray[] = [
                            'category' => $p,
                            'content' => json_encode($o['content'], JSON_UNESCAPED_UNICODE),
                            'decorate_id' => $id,
                            'name' => $o['name'],
                            'type' => $o['type']
                        ];
                    }
                }

                DecorateContent::where(['decorate_id' => $id])->delete();
                $result = new DecorateContent();
                $result->saveAll($decorateArray);
                Db::commit();
                return $result;
            } catch (ValidateException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        return $result;
    }


    //店铺装修 保存首页截图
    public function saveDecorateImage($id)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $image = $this->request->post('image');

        if ($image) {
            $row->image = $image;
            $row->save();
        }

        $this->success("更新成功");
    }


    /**
     * 页面装修 预览
     */
    public function preview($id)
    {
        //装修数据
        $decorate = $this->model->get($id);
        if(!$decorate) {
            $this->error('未找到该装修页面');
        }
        //临时预览数据
        $row = [
            'name' => "临时预览 {$decorate->name}",
            'type' => 'preview',
            'memo' => date("Y年m月d日 H:i:s", time()) . ' 创建',
            'status' => 'normal',
            'platform' => $decorate->platform
        ];
        $preview = $this->model->where('type', 'preview')->find();
        if ($preview) {
            DecorateContent::where('decorate_id', $preview->id)->delete();
            $preview->delete(true);
        }
        $this->model->save($row);
        $id = $this->model->id;
        $decorate = $this->model->getData();
        $params = $this->request->post("templateData");
        $this->updateDecorateContent($id, $params);
        $this->success($row['name'], null, $decorate);
    }

    //设计师模板
    public function designer()
    {
        $designerTemplate = Http::get('http://style.shopro.top/api/decorate/designer');
        $res = json_decode($designerTemplate, true);
        if (isset($res['code']) && $res['code'] === 1) {
            $this->assignconfig('designerData', $res['data']);
        }
        return $this->view->fetch();
    }

    //使用设计师模板
    public function use_designer_template($id)
    {
        $decorate = Http::get('http://style.shopro.top/api/decorate/copy?id=' . $id);
        $res = json_decode($decorate, true);
        if (isset($res['code']) && $res['code'] === 1) {
            Db::startTrans();
            try {
                $this->model->save([
                    'type' => 'shop',
                    'status' => 'hidden',
                    'image' => $res['data']['image'],
                    'memo' => $res['data']['memo'],
                    'name' => $res['data']['name'],
                    'platform' => $res['data']['platform']
                ]);
                foreach ($res['data']['content'] as &$v) {
                    $v['decorate_id'] = $this->model->id;
                    unset($v['id']);
                }
                DecorateContent::insertAll($res['data']['content']);
                Db::commit();
            } catch (ValidateException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        } else {
            $this->error('模板选择错误');
        }
        $this->success('模板使用成功');
    }

    /**
     * 真实删除
     */
    public function destroy($ids = "")
    {
        $pk = $this->model->getPk();
        if ($ids) {
            $this->model->where($pk, 'in', $ids);
        }
        $count = 0;
        Db::startTrans();
        try {
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $k => $v) {
                DecorateContent::where('decorate_id', $v->id)->delete();
                $count += $v->delete(true);
            }
            Db::commit();
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        } else {
            $this->error(__('No rows were deleted'));
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    public function select()
    {
        if ($this->request->isAjax()) {
            return $this->index();
        }
        return $this->view->fetch();
    }


    // 获取shopro 配置
    private function getShoproConfig()
    {
        return json_decode(\app\admin\model\shopro\Config::get(['name' => 'shopro'])->value, true);
    }
}
