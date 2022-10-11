FastAdmin是一款基于ThinkPHP+Bootstrap的极速后台开发框架。


## 主要特性

* 基于`Auth`验证的权限管理系统
    * 支持无限级父子级权限继承，父级的管理员可任意增删改子级管理员及权限设置
    * 支持单管理员多角色
    * 支持管理子级数据或个人数据
* 强大的一键生成功能
    * 一键生成CRUD,包括控制器、模型、视图、JS、语言包、菜单、回收站等
    * 一键压缩打包JS和CSS文件，一键CDN静态资源部署
    * 一键生成控制器菜单和规则
    * 一键生成API接口文档
* 完善的前端功能组件开发
    * 基于`AdminLTE`二次开发
    * 基于`Bootstrap`开发，自适应手机、平板、PC
    * 基于`RequireJS`进行JS模块管理，按需加载
    * 基于`Less`进行样式开发
* 强大的插件扩展功能，在线安装卸载升级插件
* 通用的会员模块和API模块
* 共用同一账号体系的Web端会员中心权限验证和API接口会员权限验证
* 二级域名部署支持，同时域名支持绑定到应用插件
* 多语言支持，服务端及客户端支持
* 支持大文件分片上传、剪切板粘贴上传、拖拽上传，进度条显示，图片上传前压缩
* 支持表格固定列、固定表头、跨页选择、Excel导出、模板渲染等功能
* 强大的第三方应用模块支持([CMS](https://www.fastadmin.net/store/cms.html)、[博客](https://www.fastadmin.net/store/blog.html)、[知识付费问答](https://www.fastadmin.net/store/ask.html)、[在线投票系统](https://www.fastadmin.net/store/vote.html)、[B2C商城](https://www.fastadmin.net/store/shopro.html)、[B2B2C商城](https://www.fastadmin.net/store/wanlshop.html))
* 支持CMS、博客、知识付费问答无缝整合[Xunsearch全文搜索](https://www.fastadmin.net/store/xunsearch.html)
* 第三方小程序支持([CMS小程序](https://www.fastadmin.net/store/cms.html)、[预订小程序](https://www.fastadmin.net/store/ball.html)、[问答小程序](https://www.fastadmin.net/store/ask.html)、[点餐小程序](https://www.fastadmin.net/store/unidrink.html)、[B2C小程序](https://www.fastadmin.net/store/shopro.html)、[B2B2C小程序](https://www.fastadmin.net/store/wanlshop.html)、[博客小程序](https://www.fastadmin.net/store/blog.html))
* 整合第三方短信接口(阿里云、腾讯云短信)
* 无缝整合第三方云存储(七牛云、阿里云OSS、又拍云)功能，支持云储存分片上传
* 第三方富文本编辑器支持(Summernote、Kindeditor、百度编辑器)
* 第三方登录(QQ、微信、微博)整合
* 第三方支付(微信、支付宝)无缝整合，微信支持PC端扫码支付
* 丰富的插件应用市场

## 安装使用

https://doc.fastadmin.net

## 在线演示

https://demo.fastadmin.net

用户名：admin

密　码：123456

提　示：演示站数据无法进行修改，请下载源码安装体验全部功能

## 界面截图
![控制台](https://images.gitee.com/uploads/images/2020/0929/202947_8db2d281_10933.gif "控制台")

## 问题反馈

在使用中有任何问题，请使用以下联系方式联系我们

交流社区: https://ask.fastadmin.net

QQ群: [636393962](https://jq.qq.com/?_wv=1027&k=487PNBb)(满) [708784003](https://jq.qq.com/?_wv=1027&k=5ObjtwM)(满) [964776039](https://jq.qq.com/?_wv=1027&k=59qjU2P)(3群) [749803490](https://jq.qq.com/?_wv=1027&k=5tczi88)(满) [767103006](https://jq.qq.com/?_wv=1027&k=5Z1U751)(满) [675115483](https://jq.qq.com/?_wv=1027&k=54I6mts)(6群)

Github: https://github.com/karsonzhang/fastadmin

Gitee: https://gitee.com/karson/fastadmin

## 特别鸣谢

感谢以下的项目,排名不分先后

ThinkPHP：http://www.thinkphp.cn

AdminLTE：https://adminlte.io

Bootstrap：http://getbootstrap.com

jQuery：http://jquery.com

Bootstrap-table：https://github.com/wenzhixin/bootstrap-table

Nice-validator: https://validator.niceue.com

SelectPage: https://github.com/TerryZ/SelectPage

Layer: https://layer.layui.com

DropzoneJS: https://www.dropzonejs.com


## 版权信息

FastAdmin遵循Apache2开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2017-2022 by FastAdmin (https://www.fastadmin.net)

All rights reserved。

chown www:www /www/wwwroot/lehui -R
chmod 555 /www/wwwroot/lehui -R
chmod u+w /www/wwwroot/lehui/runtime -R
chmod u+w /www/wwwroot/lehui/public/uploads -R

chown www:www /www/wwwroot/lehui/application/extra/addons.php -R
chown www:www /www/wwwroot/lehui/public/assets/js/addons.js -R
chmod 755 /www/wwwroot/lehui/application/extra/addons.php -R
chmod 755 /www/wwwroot/lehui/public/assets/js/addons.js -R
chmod 777 /www/wwwroot/lehui/addons/shopro/library/chat/config.php -R

## 启动客服
chmod 777 /www/wwwroot/lehui/addons/shopro/library/chat/log -R

启动
调试模式启动
sudo -u www php think shopro:chat start

正式模式，守护进程方式启动
sudo -u www php think shopro:chat start d
停止
如果调试模式，直接 ctrl + c 即可

正式模式
sudo -u www php think shopro:chat stop
查看状态
正式模式
sudo -u www php think shopro:chat status


## 账号信息
公司名称：乐烩科技（珠海）有限公司
公众服务号
ID：453318889@qq.com
密码：lehui161208

微信小程序
https://mp.weixin.qq.com/
13824123798@139.com
密码：lehui161208
AppID  wx0c11b830bd5f0cc9
AppSecret feda258d2b4825a5b455b1efc58cf32e

微信商户平台
ID：1628206086
操作密码：161208
APIv2秘钥 PumsN5b2sYbj8Kn7NZS78mdL7fv7qcxU
微信商户平台（新的）
商户号：1628358300
操作密码：161208
API密钥：YgmwNRM547RzgTLxtgnTxyVDMGDJQdan


阿里云
账号：乐烩c厨
密码：lehui161208

AccessKey ID：LTAI5t74njKe4vcLu5zGvtNk
AccessKey Secret：wyzNzClNLAFGlawkn73bj5UwdnHqTQ

短信签名 乐烩科技珠海有限公司
SMS_243615266  
您的验证码为：${code}，请勿泄露于他人！

打印机
http://help.feieyun.com/document.php
开发者账号
13703005653@139.com
密码lehui168.


ALTER TABLE `lehui`.`fa_join_lottery_userlist`
DROP COLUMN `username`,
MODIFY COLUMN `is_award` tinyint(1) NULL DEFAULT 2 COMMENT '是否中奖:0=没中奖,1=中奖了,2=未开奖' AFTER `intro`;
ALTER TABLE `lehui`.`fa_join_lottery_userlist`
MODIFY COLUMN `is_award` enum('0','1','2') NULL DEFAULT '2' COMMENT '是否中奖:0=没中奖,1=中奖了,2=未开奖' AFTER `intro`;
ALTER TABLE `lehui`.`fa_join_lottery_userlist`
ADD COLUMN `awardtime` int(10) NULL DEFAULT 0 COMMENT '中奖时间' AFTER `is_award`;
ALTER TABLE `lehui`.`fa_join_lottery_userlist`
MODIFY COLUMN `status` enum('1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '1' COMMENT '发放状态:1=未发放,2=已发放' AFTER `createtime`;
