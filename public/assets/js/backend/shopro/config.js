define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var configIndex = new Vue({
                el: "#configIndex",
                data() {
                    return {
                        activeName: "basic",
                        configData: {
                            basic: [{
                                id: 'shopro',
                                title: '商城信息',
                                tip: '配置商城基本信息',
                                message: '商城名称、H5域名、Logo',
                                icon: 'shopro-icon',
                                leaf: '#6ACAA5',
                                background: 'linear-gradient(180deg, #BAF0DD 0%, #51BC99 100%)',
                                url: "{:url(shopro.config/platform?type=shopro')}",
                                button: {
                                    background: '#E0F1EB',
                                    color: '#13986C'
                                },
                            }, {
                                id: 'user',
                                title: '会员配置',
                                tip: '配置默认会员信息',
                                message: '默认昵称、头像、分组、余额、积分',
                                icon: 'user-icon',
                                leaf: '#E0B163',
                                background: 'linear-gradient(180deg, #FCE6B7 0%, #E9A848 100%)',
                                button: {
                                    background: '#F7EDDD',
                                    color: '#B07318'
                                },
                            }, {
                                id: 'share',
                                title: '分享配置',
                                tip: '配置默认分享信息',
                                message: '分享标题、图片、海报背景',
                                icon: 'share-icon',
                                leaf: '#915CF9',
                                background: 'linear-gradient(180deg, #D5B8FA 0%, #8F62C9 100%)',
                                button: {
                                    background: '#E7DEF6',
                                    color: '#6625CF'
                                },
                            }, {
                                id: 'score',
                                title: '积分配置',
                                tip: '配置默认积分规则',
                                message: '签到积分、连续签到规则',
                                icon: 'score-icon',
                                leaf: '#EC9371',
                                background: 'linear-gradient(180deg, #FADDC0 0%, #E47F6D 100%)',
                                button: {
                                    background: '#F6E5E1',
                                    color: '#D75125'
                                },
                            }, {
                                id: 'withdraw',
                                title: '充值提现',
                                tip: '配置默认充值提现规则',
                                message: '手续费、最小最大金额',
                                icon: 'withdraw-icon',
                                leaf: '#EA6670',
                                background: 'linear-gradient(180deg, #FCB7BE 0%, #D36068 100%)',
                                button: {
                                    background: '#F3DCDE',
                                    color: '#D61226'
                                },
                            }, {
                                id: 'order',
                                title: '商城配置',
                                tip: '配置默认商城规则',
                                message: '库存预警，订单自动关闭，订单自动收货，订单自动评价',
                                icon: 'order-icon',
                                leaf: '#6991E7',
                                background: 'linear-gradient(180deg, #AFCBFD 0%, #5A7CCF 100%)',
                                button: {
                                    background: '#DBE2F6',
                                    color: '#1B5EE7'
                                },
                            }, {
                                id: 'services',
                                title: '第三方服务',
                                tip: '配置物流及位置信息',
                                message: '快递鸟物流、高德地图',
                                icon: 'services-icon',
                                leaf: '#14B0F3',
                                background: 'linear-gradient(180deg, #A5E2FC 0%, #158CBF 100%)',
                                button: {
                                    background: '#DBE2F6',
                                    color: '#00A3D7'
                                },
                            }, {
                                id: 'chat',
                                title: '客服配置',
                                tip: '配置客服信息',
                                message: '配置客服信息，快速沟通',
                                icon: 'chat-icon',
                                leaf: '#27C9C3',
                                background: 'linear-gradient(180deg, #A1F3DE 0%, #3DA0A7 100%)',
                                button: {
                                    background: '#D6E9EA',
                                    color: '#159198'
                                },
                            }, {
                                id: 'store',
                                title: '门店配置',
                                tip: '配置门店协议',
                                message: '配置门店协议',
                                icon: 'store-icon',
                                leaf: '#487EE5',
                                background: 'linear-gradient(180deg, #84C4FF 0%, #3C68BE 100%)',
                                button: {
                                    background: '#DFE7EE',
                                    color: '#1C54BD'
                                },
                            }],
                            platform: [{
                                id: 'wxOfficialAccount',
                                title: '微信公众号',
                                tip: '配置微信公众号',
                                message: 'AppId、AppSecret、自动登录',
                                icon: 'wxOfficialAccount-icon',
                                leaf: '#6ACAA4',
                                background: 'linear-gradient(180deg, #AAF0D7 0%, #5CC09F 100%)',
                                buttonMessage: '公众号设置',
                                button: {
                                    background: '#DEF0EA',
                                    color: '#0EA753'
                                },
                            }, {
                                id: 'wxMiniProgram',
                                title: '小程序',
                                tip: '配置小程序',
                                message: 'AppId、AppSecret、自动登录',
                                icon: 'wxMiniProgram-icon',
                                leaf: '#6962F7',
                                background: 'linear-gradient(180deg, #C1BFFF 0%, #6563C9 100%)',
                                buttonMessage: '小程序设置',
                                button: {
                                    background: '#D8D8F1',
                                    color: '#3932BF'
                                },
                            }, {
                                id: 'H5',
                                title: 'H5',
                                tip: '配置H5平台',
                                message: '微信H5支付信息',
                                icon: 'h5-icon',
                                leaf: '#EC9371',
                                background: 'linear-gradient(180deg, #FADDC0 0%, #E5806D 100%)',
                                buttonMessage: 'H5设置',
                                button: {
                                    background: '#F7E6E1',
                                    color: '#D75E37'
                                },
                            }, {
                                id: 'App',
                                title: 'App',
                                tip: '配置App平台',
                                message: '生成App实现多端同步使用',
                                icon: 'App-icon',
                                leaf: '#6990E6',
                                background: 'linear-gradient(180deg, #BED6FF 0%, #6785CD 100%)',
                                buttonMessage: 'App设置',
                                button: {
                                    background: '#DAE1F6',
                                    color: '#1666D3'
                                },
                            }],
                            payment: [{
                                id: 'wechat',
                                title: '微信支付',
                                tip: '',
                                message: '',
                                icon: 'wechat-icon',
                                leaf: '#6ACAA4',
                                background: 'linear-gradient(180deg, #AAF0D7 0%, #5DC1A0 100%)',
                                button: {
                                    background: '#DEF0EA',
                                    color: '#0EA753'
                                },
                            }, {
                                id: 'alipay',
                                title: '支付宝支付',
                                tip: '',
                                message: '',
                                icon: 'alipay-icon',
                                leaf: '#6990E6',
                                background: 'linear-gradient(180deg, #BFD6FF 0%, #6786CE 100%)',
                                button: {
                                    background: '#DAE1F6',
                                    color: '#005AD7',
                                },
                            }, {
                                id: 'wallet',
                                title: '余额支付',
                                tip: '',
                                message: '',
                                icon: 'wallet-icon',
                                leaf: '#EC9371',
                                background: 'linear-gradient(180deg, #FADDC0 0%, #E6816E 100%)',
                                button: {
                                    background: '#F7E6E1',
                                    color: '#D75E37'
                                },
                            }, {
                                id: 'apple',
                                title: 'Apple Pay',
                                tip: '',
                                message: '',
                                icon: 'apple-icon',
                                leaf: '#6962F7',
                                background: 'linear-gradient(180deg, #C2C0FF 0%, #6563C9 100%) ',
                                button: {
                                    background: '#D8D8F1',
                                    color: '#1E14E0',
                                    cursor: 'auto'
                                },
                            }]
                        }
                    }
                },
                methods: {
                    tabClick(tab, event) {
                        this.activeName = tab.name;
                    },
                    operation(id, title) {
                        let that = this;
                        Fast.api.open("shopro/config/platform?type=" + id + "&tab=" + that.activeName + "&title=" + title, title);
                    },
                },
            })
        },
        platform: function () {
            Vue.directive('enterInteger', {
                inserted: function (el) {
                    const input = el.nodeName === 'INPUT' ? el : el.getElementsByTagName('input')[0]
                    const fn = (e) => {
                        input.value = input.value.replace(/(^[^1-9])|[^\d]/g, '')
                        const ev = document.createEvent('HTMLEvents')
                        ev.initEvent('input', true, true)
                        input.dispatchEvent(ev)
                    }
                    input.onkeyup = fn
                    input.onblur = fn
                }
            });
            function debounce(handle, delay) {
                let time = null;
                return function () {
                    let self = this,
                        arg = arguments;
                    clearTimeout(time);
                    time = setTimeout(function () {
                        handle.apply(self, arg);
                    }, delay)
                }
            }
            var configPlatform = new Vue({
                el: "#configPlatform",
                data() {
                    return {
                        platformData: {
                            shopro: {
                                name: '',
                                domain: '',
                                version: '',
                                logo: '',
                                logo_arr: [],
                                copyright: ['', ''],
                                user_protocol: '',
                                privacy_protocol: '',
                                about_us: '',
                            },
                            user: {
                                nickname: '',
                                avatar: '',
                                avatar_arr: [],
                                group_id: '',
                                money: '',
                                score: '',
                            },
                            share: {
                                title: '',
                                image: '',
                                goods_poster_bg: '',
                                user_poster_bg: '',
                                groupon_poster_bg: '',
                                image_arr: [],
                                goods_poster_bg_arr: [],
                                user_poster_bg_arr: [],
                                groupon_poster_bg_arr: [],
                            },
                            score: {
                                everyday: '',
                                until_day: '',
                                inc_value: '',
                                consume_get_score: '0',
                                consume_get_score_ratio: '0'
                            },
                            withdraw: {
                                methods: [],
                                wechat_alipay_auto: 0,
                                service_fee: '',
                                min: '',
                                max: '',
                                perday_amount: 0,
                                perday_num: 0,
                                recharge: {
                                    enable: 0,
                                    methods: [],
                                    moneys: []
                                }
                            },
                            order: {
                                order_auto_close: '',
                                order_auto_confirm: '',
                                order_auto_comment: '',
                                order_comment_content: '',
                                user_reply: '0',
                                goods: {
                                    stock_warning: 0
                                },
                                invoice: {
                                    enable: "1",
                                    price_type: "goods_price"
                                }
                            },
                            services: {
                                amap: {
                                    appkey: '',
                                    jscode: ''
                                },
                                express: {
                                    ebusiness_id: '',
                                    type: 'free',
                                    appkey: '',
                                    jd_code: '',
                                    Sender: {
                                        Name: '',
                                        Mobile: '',
                                        ProvinceName: '',
                                        CityName: '',
                                        ExpAreaName: '',
                                        Address: '',
                                    },
                                    PayType: 1,
                                    ExpType: 1,
                                    CustomerName: '',
                                    CustomerPwd: '',
                                    ShipperCode: '',
                                },
                                area_arr: []
                            },
                            chat: {
                                type: 'shopro',
                                basic: {
                                    last_customer_service: 1,
                                    allocate: 'busy',
                                    notice: '显示在用户端头部',
                                },
                                system: {
                                    is_ssl: 0,
                                    ssl_type: 'cert',
                                    ssl_cert: '/www/server/panel/vhost/cert/****/fullchain.pem',
                                    ssl_key: '/www/server/panel/vhost/cert/****/privkey.pem',
                                    gateway_port: 1819,
                                    gateway_num: 2,
                                    gateway_start_port: 2010,
                                    business_worker_port: 2238,
                                    business_worker_num: 4
                                }
                            },
                            store: {
                                protocol: '',
                                selfetch_protocol: '',
                            },
                            wxOfficialAccount: {
                                name: '',
                                wx_type: '',
                                avatar: '',
                                qrcode: '',
                                avatar_arr: [],
                                qrcode_arr: [],
                                app_id: '',
                                secret: '',
                                url: '',
                                token: '',
                                aes_key: '',
                                auto_login: '',
                                status: ''
                            },
                            wxMiniProgram: {
                                name: '',
                                avatar: '',
                                qrcode: '',
                                avatar_arr: [],
                                qrcode_arr: [],
                                app_id: '',
                                secret: '',
                                auto_login: '',
                            },
                            H5: {
                                app_id: '',
                                secret: '',
                            },
                            App: {
                                app_id: '',
                                secret: '',
                            },
                            wechat: {
                                platform: [],
                                mch_id: '',
                                key: '',
                                cert_client: '',
                                cert_key: '',
                                mode: 'normal',
                                sub_mch_id: '',
                                appid: '',
                                app_id: '',
                                miniapp_id: '',
                            },
                            alipay: {
                                platform: [],
                                app_id: '',
                                ali_public_key: '',
                                app_cert_public_key: '',
                                alipay_root_cert: '',
                                private_key: '',
                                mode: 'normal',
                                pid: '',
                            },
                            wallet: {
                                platform: [],
                            },
                        },
                        type: new URLSearchParams(location.search).get('type'),
                        tab: new URLSearchParams(location.search).get('tab'),
                        title: new URLSearchParams(location.search).get('title'),
                        groupList: [],
                        detailForm: {},
                        must_delete: ['logo_arr', 'avatar_arr', 'image_arr', 'goods_poster_bg_arr', 'user_poster_bg_arr', 'groupon_poster_bg_arr', 'qrcode_arr', 'area_arr'],
                        expressAddress: window.location.origin + '/addons/shopro/express/callback',
                        deliverCompany: [],
                        areaOptions: []
                    }
                },
                mounted() {
                    this.operationData();
                    if (this.type == 'services') {
                        this.getDeliverCompany();
                        this.getAreaOptions()
                    }
                },
                methods: {
                    operationData() {
                        this.detailForm = this.platformData[this.type]
                        if (Config.row) {
                            for (key in this.detailForm) {
                                if (Config.row[key]) {
                                    if (Config.row[key] instanceof Object) {
                                        for (inner in Config.row[key]) {
                                            if (Config.row[key][inner]) {
                                                this.detailForm[key][inner] = Config.row[key][inner]
                                            }
                                        }
                                    } else {
                                        this.detailForm[key] = Config.row[key]
                                    }
                                }
                            }
                        }
                        if (this.type == 'shopro') {
                            this.detailForm.logo_arr = []
                            this.detailForm.logo_arr.push(Fast.api.cdnurl(this.detailForm.logo))
                        } else if (this.type == 'user') {
                            this.groupList = Config.groupList
                            this.detailForm.avatar_arr = []
                            this.detailForm.avatar_arr.push(Fast.api.cdnurl(this.detailForm.avatar))
                        } else if (this.type == 'share') {
                            this.detailForm.image_arr = []
                            this.detailForm.image_arr.push(Fast.api.cdnurl(this.detailForm.image))
                            this.detailForm.goods_poster_bg_arr = []
                            this.detailForm.goods_poster_bg_arr.push(Fast.api.cdnurl(this.detailForm.goods_poster_bg))
                            this.detailForm.user_poster_bg_arr = []
                            this.detailForm.user_poster_bg_arr.push(Fast.api.cdnurl(this.detailForm.user_poster_bg))
                            this.detailForm.groupon_poster_bg_arr = []
                            this.detailForm.groupon_poster_bg_arr.push(Fast.api.cdnurl(this.detailForm.groupon_poster_bg))
                        } else if (this.type == 'withdraw') {
                            this.detailForm.service_fee = this.detailForm.service_fee * 100
                        } else if (this.type == 'services') {
                            this.detailForm.area_arr = []
                            if (this.detailForm.express && this.detailForm.express.Sender) {
                                this.detailForm.area_arr = [this.detailForm.express.Sender.ProvinceName, this.detailForm.express.Sender.CityName, this.detailForm.express.Sender.ExpAreaName]
                            }
                        } else if (this.type == 'chat') {
                            if (!this.detailForm.system.ssl_type) {
                                this.$set(this.detailForm.system, 'ssl_type', 'cert')
                            }
                        } else if (this.type == 'wxOfficialAccount') {
                            this.detailForm.avatar_arr = []
                            this.detailForm.avatar_arr.push(Fast.api.cdnurl(this.detailForm.avatar))
                            this.detailForm.qrcode_arr = []
                            this.detailForm.qrcode_arr.push(Fast.api.cdnurl(this.detailForm.qrcode))
                        } else if (this.type == 'wxMiniProgram') {
                            this.detailForm.avatar_arr = []
                            this.detailForm.avatar_arr.push(Fast.api.cdnurl(this.detailForm.avatar))
                            this.detailForm.qrcode_arr = []
                            this.detailForm.qrcode_arr.push(Fast.api.cdnurl(this.detailForm.qrcode))
                        }
                    },
                    richtextSelect(field) {
                        let that = this;
                        Fast.api.open("shopro/richtext/select?multiple=false", "选择协议", {
                            callback: function (data) {
                                that.detailForm[field] = data.data.id;
                            }
                        });
                        return false;
                    },
                    attachmentSelect(type, field) {
                        let that = this;
                        Fast.api.open("general/attachment/select?multiple=false", "选择", {
                            callback: function (data) {
                                switch (type) {
                                    case "image":
                                        that.detailForm[field] = data.url;
                                        that.detailForm[field + '_arr'] = data.url;
                                        break;
                                    // case "file":
                                    //     that.detailForm[field] = data.url;
                                    //     break;
                                    case "ssl":
                                        that.detailForm.system[field] = data.url;
                                        break;
                                }
                            }
                        });
                        return false;
                    },
                    delImg(type, field) {
                        let that = this;
                        switch (type) {
                            case "image":
                                that.detailForm[field] = '';
                                that.detailForm[field + '_arr'] = [];
                                break;
                            case "file":
                                that.detailForm[field] = '';
                                break;
                        }
                    },
                    submitFrom(type) {
                        let that = this;
                        if (type == 'yes') {
                            if (this.type == 'withdraw') {
                                let flag = that.detailForm.recharge.moneys.every(m => {
                                    return m.money
                                })
                                if (!flag) {
                                    this.$message({
                                        message: '请把数据填写完整',
                                        type: 'warning'
                                    });
                                    return false
                                }
                            }
                            let submitData = JSON.parse(JSON.stringify(that.detailForm))
                            if (that.type == 'withdraw') {
                                submitData.service_fee = (Number(submitData.service_fee) / 100).toFixed(2)
                            }
                            if (that.type == 'services') {
                                submitData.express.Sender.ProvinceName = submitData.area_arr[0];
                                submitData.express.Sender.CityName = submitData.area_arr[1];
                                submitData.express.Sender.ExpAreaName = submitData.area_arr[2];
                            }
                            that.must_delete.forEach(i => {
                                if (submitData[i]) {
                                    delete submitData[i]
                                }
                            });
                            Fast.api.ajax({
                                url: 'shopro/config/platform?type=' + that.type,
                                loading: true,
                                type: 'POST',
                                data: {
                                    data: JSON.stringify(submitData),
                                    group: that.tab,
                                    title: that.title
                                },
                            }, function (ret, res) {
                                Fast.api.close()
                            })
                        } else {
                            Fast.api.close()
                        }
                    },
                    getDeliverCompany(searchWhere = '') {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/express/select',
                            loading: false,
                            type: 'GET',
                            data: {
                                searchWhere: searchWhere,
                            }
                        }, function (ret, res) {
                            that.deliverCompany = res.data.rows;
                            return false
                        })
                    },
                    deliverDebounceFilter: debounce(function (searchWhere) {
                        this.getDeliverCompany(searchWhere)
                    }, 1000),
                    deliverFilter(searchWhere) {
                        this.deliverDebounceFilter(searchWhere);
                    },
                    getAreaOptions() {
                        var that = this;
                        Fast.api.ajax({
                            url: `shopro/area/select`,
                            loading: false,
                            type: 'GET',
                        }, function (ret, res) {
                            that.areaOptions = res.data;
                            return false;
                        })
                    },
                    changeWechatType() {
                        for (key in this.detailForm) {
                            if (key != 'mode' && key != 'platform') {
                                this.detailForm[key] = ''
                            }
                        }
                    },
                    ajaxUpload(id) {
                        let that = this;
                        var formData = new FormData();
                        formData.append("file", $('#' + id)[0].files[0]);
                        $.ajax({
                            type: "post",
                            url: "ajax/upload",
                            data: formData,
                            cache: false,
                            processData: false,
                            contentType: false,
                            success: function (data) {
                                if (data.code == 1) {
                                    that.detailForm[id] = data.data.url
                                } else {
                                    that.$notify({
                                        title: '警告',
                                        message: data.msg,
                                        type: 'warning'
                                    });
                                }
                            }
                        })
                    },
                    addTemplate() {
                        this.detailForm.recharge.moneys.push({
                            money: "",
                        });
                    },
                    deleteTemplate(index) {
                        this.detailForm.recharge.moneys.splice(index, 1);
                    }
                },
            })
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});