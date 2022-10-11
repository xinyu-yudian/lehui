const {
    isArray
} = require("jquery");

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        lists: function () {
            var decorateIndex = new Vue({
                el: "#decorate-index",
                data() {
                    return {
                        templateForm: {
                            name: '',
                            platform: [],
                            memo: ''
                        },
                        createDialog: false,
                        templateList: [],
                        submitId: null,
                        refreshAction: false,
                        rules: {
                            name: [
                                { required: true, message: '请输入名称', trigger: 'blur' },
                                { min: 2, max: 10, message: '长度在 2 到 10 个字符', trigger: 'blur' }
                            ],
                            memo: [
                                { required: true, message: '请输入备注', trigger: 'blur' },
                                { min: 2, max: 10, message: '长度在 3 到 10 个字符', trigger: 'blur' }
                            ],
                        }
                    }
                },
                mounted() {
                    this.getTemplate();
                },
                methods: {
                    getTemplate(type) {
                        let that = this;
                        if (type == 'refresh') {
                            that.refreshAction = true
                        }
                        Fast.api.ajax({
                            url: 'shopro/decorate/lists',
                            loading: true,
                            type: 'GET',
                            data: {
                                type: 'shop'
                            }
                        }, function (ret, res) {
                            that.templateList = res.data;
                            that.refreshAction = false
                            return false
                        })
                    },
                    async operation(type, id) {
                        let that = this;
                        switch (type) {
                            case 'create':
                                that.createDialog = true;
                                that.submitId = id; //创建新的
                                break;
                            case 'edit':
                                that.createDialog = true;
                                that.templateList.forEach(i => {
                                    if (i.id == id) {
                                        that.templateForm.name = i.name;
                                        that.templateForm.memo = i.name;
                                        if (i.platform != '') {
                                            that.templateForm.platform = i.platform.split(',')
                                        } else {
                                            that.templateForm.platform = []
                                        }
                                    }
                                })
                                that.submitId = id;
                                break;
                            case 'decorate':
                                Controller.editTemplateApi.decorate(id, 'shop', '页面管理')
                                break;
                            case 'copy':
                                let copyRes = await Controller.editTemplateApi.copy(id)
                                if (copyRes.code == 1) {
                                    that.getTemplate();
                                }
                                break;
                            case 'release':
                                Fast.api.ajax({
                                    url: 'shopro/decorate/publish/id/' + id,
                                    loading: true,
                                    data: {}
                                }, function (ret, res) {
                                    that.getTemplate()
                                }, function (ret, res) {
                                    let code = res.data
                                    that.$confirm(res.msg, '提示', {
                                        confirmButtonText: '确定',
                                        cancelButtonText: '取消',
                                        type: 'warning'
                                    }).then(() => {
                                        if (code === 0) {
                                            that.createDialog = true;
                                            that.templateList.forEach(i => {
                                                if (i.id == id) {
                                                    that.templateForm.name = i.name;
                                                    that.templateForm.memo = i.memo;
                                                    that.templateForm.platform = i.platform ? i.platform.split(',') : []
                                                }
                                            })
                                            that.submitId = id;
                                        } else {
                                            Fast.api.ajax({
                                                url: 'shopro/decorate/publish/id/' + id + "/force/1",
                                                loading: true,
                                                data: {}
                                            }, function (ret, res) {
                                                that.getTemplate()
                                            })
                                        }
                                    })
                                    return false;
                                })
                                break;
                            case 'delete':
                                let deleteRes = await Controller.editTemplateApi.delete(id)
                                if (deleteRes.code == 1) {
                                    that.getTemplate();
                                }
                                break;
                            case 'down':
                                Fast.api.ajax({
                                    url: 'shopro/decorate/down/id/' + id,
                                    loading: true,
                                    data: {}
                                }, function (ret, res) {
                                    that.getTemplate()
                                })
                                break;
                        }
                    },
                    // 修改信息
                    createClose(type) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs.ruleForm.validate(async (valid) => {
                                if (valid) {
                                    let res = ''
                                    if (that.submitId) {
                                        res = await Controller.editTemplateApi.edit(that.submitId, that.templateForm)
                                    } else {
                                        res = await Controller.editTemplateApi.add('shop', that.templateForm, 'hidden')
                                    }
                                    if (res.code == 1) {
                                        that.clearTemplateForm()
                                        that.getTemplate();
                                    }
                                } else {
                                    return false;
                                }
                            });

                        } else {
                            that.clearTemplateForm()
                        }
                    },
                    clearTemplateForm() {
                        this.createDialog = false
                        this.templateForm.platform = [];
                        this.templateForm.name = '';
                        this.templateForm.memo = ""
                    },
                    goRecycle() {
                        let that = this;
                        Fast.api.open("shopro/decorate/recyclebin", "查看回收站", {
                            callback() {
                                that.getTemplate();
                            }
                        })
                    }
                },
            })
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'shopro/decorate/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [{
                        checkbox: true
                    },
                    {
                        field: 'id',
                        title: __('Id')
                    },
                    {
                        field: 'name',
                        title: __('Name'),
                        align: 'left'
                    },
                    {
                        field: 'deletetime',
                        title: __('Deletetime'),
                        operate: 'RANGE',
                        addclass: 'datetimerange',
                        formatter: Table.api.formatter.datetime
                    },
                    {
                        field: 'operate',
                        width: '130px',
                        title: __('Operate'),
                        table: table,
                        events: Table.api.events.operate,
                        buttons: [{
                            name: 'Restore',
                            text: __('Restore'),
                            classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                            icon: 'fa fa-rotate-left',
                            url: 'shopro/decorate/restore',
                            refresh: true
                        },
                        {
                            name: 'Destroy',
                            text: __('Destroy'),
                            classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                            icon: 'fa fa-times',
                            url: 'shopro/decorate/destroy',
                            refresh: true
                        }
                        ],
                        formatter: Table.api.formatter.operate
                    }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        dodecorate: function () {
            var themcolor = ""
            var vueAdd = new Vue({
                el: "#decorateApp",
                data() {
                    return {
                        menuOption: {
                            group: {
                                name: 'draggableName',
                                pull: 'clone',
                                put: false,
                            },
                            ghostClass: "sortable-ghost",
                            fallbackClass: 'clone-item',
                            fallbackOnBody: true,
                        },
                        defaultOption: {
                            filter: '.undraggable',
                            animation: 100,
                            group: {
                                name: 'draggableName'
                            },
                            ghostClass: "sortable-ghost",
                            fallbackClass: 'clone-item',
                            dragClass: 'dragin-item',
                        },
                        saveHtml: null,

                        decorateContent: '',
                        group: "changepull",
                        toolsBox: [{
                            name: "图文",
                            show: "common",
                            data: [{
                                name: "轮播图",
                                type: "banner",
                                image: "/assets/addons/shopro/img/decorate/banner.png",
                                flag: false,
                                show: "common"
                            }, {
                                name: "菜单组",
                                type: "menu",
                                image: "/assets/addons/shopro/img/decorate/menu.png",
                                flag: false,
                                show: "common"
                            }, {
                                name: "广告魔方",
                                type: "adv",
                                image: "/assets/addons/shopro/img/decorate/adv.png",
                                flag: false,
                                show: "common"
                            }, {
                                name: "标题栏",
                                type: "title-block",
                                image: "/assets/addons/shopro/img/decorate/title-block.png",
                                flag: false,
                                show: "home"
                            }, {
                                name: "宫格导航",
                                type: "grid-list",
                                image: "/assets/addons/shopro/img/decorate/grid-list.png",
                                flag: false,
                                show: "user"
                            }, {
                                name: "列表导航",
                                type: "nav-list",
                                image: "/assets/addons/shopro/img/decorate/nav-list.png",
                                flag: false,
                                show: "user"
                            }]
                        }, {
                            name: "商品组",
                            show: "common",
                            data: [{
                                name: "商品分类",
                                type: "goods-group",
                                image: "/assets/addons/shopro/img/decorate/goods-group.png",
                                flag: false,
                                show: "home"
                            }, {
                                name: "分类选项卡",
                                type: "category-tabs",
                                image: "/assets/addons/shopro/img/decorate/category_tabs.png",
                                flag: false,
                                show: "home"
                            }, {
                                name: "自定义商品",
                                type: "goods-list",
                                image: "/assets/addons/shopro/img/decorate/goods-list.png",
                                flag: false,
                                show: "common"
                            }]
                        }, {
                            name: "活动营销",
                            show: "home",
                            data: [{
                                name: "优惠券",
                                type: "coupons",
                                image: "/assets/addons/shopro/img/decorate/coupon.png",
                                flag: false,
                                show: "home"
                            },
                            {
                                name: "拼团",
                                type: "groupon",
                                image: "/assets/addons/shopro/img/decorate/groupon.png",
                                show: "home"
                            },
                            {
                                name: "秒杀",
                                type: "seckill",
                                image: "/assets/addons/shopro/img/decorate/secKill.png",
                                flag: false,
                                show: "home"
                            }, {
                                name: "小程序直播",
                                type: "live",
                                image: "/assets/addons/shopro/img/decorate/live.png",
                                flag: false,
                                show: "home"
                            }
                            ]
                        }, {
                            name: "其他",
                            show: "common",
                            data: [{
                                name: "搜索框",
                                type: "search",
                                image: "/assets/addons/shopro/img/decorate/search.png",
                                flag: false,
                                show: "home"
                            }, {
                                name: "富文本",
                                type: "rich-text",
                                image: "/assets/addons/shopro/img/decorate/rich-text.png",
                                flag: false,
                                show: "common"
                            }, {
                                name: "订单卡片",
                                type: "order-card",
                                image: "/assets/addons/shopro/img/decorate/order-card.png",
                                flag: false,
                                show: "user"
                            }, {
                                name: "资产卡片",
                                type: "wallet-card",
                                image: "/assets/addons/shopro/img/decorate/wallet-card.png",
                                flag: false,
                                show: "user"
                            }]
                        }],
                        templateData: [],
                        centerSelect: null,
                        templateForm: {},
                        advStyleImage: [{
                            src: '/assets/addons/shopro/img/decorate/adv_01.png',
                            num: 1
                        },
                        {
                            src: '/assets/addons/shopro/img/decorate/adv_02.png',
                            num: 2
                        },
                        {
                            src: '/assets/addons/shopro/img/decorate/adv_03.png',
                            num: 3
                        },
                        {
                            src: '/assets/addons/shopro/img/decorate/adv_04.png',
                            num: 3
                        },
                        {
                            src: '/assets/addons/shopro/img/decorate/adv_05.png',
                            num: 3
                        },
                        {
                            src: '/assets/addons/shopro/img/decorate/adv_06.png',
                            num: 3
                        },
                        {
                            src: '/assets/addons/shopro/img/decorate/adv_07.png',
                            num: 5
                        }
                        ],
                        titleBlock: {
                            isSelected: false,
                            data: [{
                                src: 'https://file.shopro.top/imgs/title1.png',
                                selected: false,
                            },
                            {
                                src: 'https://file.shopro.top/imgs/title2.png',
                                selected: false,
                            },
                            {
                                src: 'https://file.shopro.top/imgs/title3.png',
                                selected: false,
                            },
                            {
                                src: 'https://file.shopro.top/imgs/title4.png',
                                selected: false,
                            },
                            {
                                src: 'https://file.shopro.top/imgs/title5.png',
                                selected: false,
                            }
                            ],
                            currentImage: ''
                        },
                        httpsDlocked: true,
                        iframeSrc: '',
                        qrcodeSrc: '',
                        wxacodeSrc: '',
                        iframeTitle: '',
                        iframeCopyright: [],
                        iframePlatform: '',
                        previewDialog: false,
                        isPageType: 'home',
                        advdrawer: false,
                        pageTypeList: [{
                            name: '首页',
                            type: 'home',
                            flag: false
                        },
                        {
                            name: '个人中心',
                            type: 'user',
                            flag: false
                        },
                        {
                            name: '底部导航',
                            type: 'tabbar',
                            flag: false
                        },
                        {
                            name: '弹窗提醒',
                            type: 'popup',
                            flag: false
                        }, {
                            name: '悬浮按钮',
                            type: 'float-button',
                            flag: false
                        }
                        ],
                        homeData: Config.templateData.home ? Config.templateData.home : [],
                        userData: Config.templateData.user,
                        tabbarData: Config.templateData.tabbar ? Config.templateData.tabbar : [{
                            type: 'tabbar',
                            name: '底部导航',
                            content: {
                                style: 1,
                                color: '#000',
                                activeColor: "#999",
                                bgcolor: '#fff',
                                list: [{
                                    name: "标题",
                                    image: "",
                                    activeImage: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1,
                                    selected: false
                                }, {
                                    name: "标题",
                                    image: "",
                                    activeImage: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1,
                                    selected: false
                                }, {
                                    name: "标题",
                                    image: "",
                                    activeImage: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1,
                                    selected: false
                                }, {
                                    name: "标题",
                                    image: "",
                                    activeImage: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1,
                                    selected: false
                                }]
                            }
                        }],
                        popupData: Config.templateData.popup ? Config.templateData.popup : [{
                            type: 'popup',
                            name: '弹窗提醒',
                            content: {
                                list: [{
                                    name: "",
                                    style: 1,
                                    image: '',
                                    btnimage: '',
                                    path: '',
                                    path_name: '',
                                    path_type: 1,
                                }]
                            }
                        }],
                        floatButtonData: Config.templateData['float-button'] ? Config.templateData['float-button'] : [{
                            type: 'float-button',
                            name: '悬浮按钮',
                            content: {
                                image: '',
                                list: []
                            }
                        }],
                        customData: Config.templateData.custom ? Config.templateData.custom : [],
                        customName: new URLSearchParams(location.search).get('name'),
                        fromtype: new URLSearchParams(location.search).get('type'),
                        decorate_id: new URLSearchParams(location.search).get('id'),
                        popupIndex: null,
                        isfloat: false,
                        shoproName: '',
                        saveLoading: false
                    }
                },
                mounted() {
                    this.shoproName = Config.shoproConfig.name

                    // 兼容老数据
                    this.homeData.forEach(h => {
                        if (h.type == 'goods-group' || h.type == 'goods-list' || h.type == 'category-tabs' || h.type == 'groupon' || h.type == 'seckill' || h.type == 'coupons') {
                            if (!h.content.style) {
                                this.$set(h.content, 'style', 1)
                            }
                            if (h.type == 'coupons') {
                                if (!h.content.bgcolor1) {
                                    this.$set(h.content, 'bgcolor1', '#EFC480')
                                    this.$set(h.content, 'bgcolor2', '#EFC480')
                                    this.$set(h.content, 'pricecolor', '#4F3A1E')
                                    this.$set(h.content, 'color', '#A8700D')
                                }
                            }
                        } else if (h.type == 'banner') {
                            if (!h.content.height) {
                                this.$set(h.content, 'height', 520)
                                this.$set(h.content, 'radius', 0)
                                this.$set(h.content, 'x', 0)
                                this.$set(h.content, 'y', 0)
                            }
                        }
                    })
                    //组合数据
                    var that = this;
                    let domain = window.location.origin;
                    if (this.fromtype == 'shop') {
                        this.initFrontendData(this.homeData)
                        this.initFrontendData(this.userData)
                        this.tabbarData[0].content.list.forEach(i => {
                            i.selected = false;
                        })
                        this.templateData = this.homeData;
                        this.pageTypeList.forEach(i => {
                            if (i.type == this.isPageType) {
                                i.flag = true;
                            }
                        });
                    } else {
                        this.initFrontendData(this.customData)
                        this.templateData = this.customData;
                        this.showForm(this.centerSelect);
                    }
                },
                methods: {
                    checkToolsShow(s) {
                        return this.fromtype == 'custom' || s == 'common' || s == this.isPageType
                    },
                    initFrontendData(data) {
                        let that = this
                        let domain = window.location.origin;
                        data.forEach(i => {
                            if (i.type == 'goods-group' || i.type == 'category-tabs') {
                                let id = ''
                                if (i.type == 'goods-group') {
                                    id = i.content.id
                                } else if (i.type == 'category-tabs') {
                                    id = i.content.ids.split(',')[0]
                                }
                                if (id) {
                                    Fast.api.ajax({
                                        url: domain + '/addons/shopro/goods/lists?category_id=' + id,
                                    }, function (ret, res) {
                                        that.$set(i.content, 'timeData', res.data.data);
                                        return false;
                                    }, function () {
                                        that.$set(i.content, 'timeData', []);
                                        return false;
                                    })
                                } else {
                                    that.$set(i.content, 'timeData', [])
                                }
                            } else if (i.type == 'groupon') {
                                Fast.api.ajax({
                                    url: domain + '/addons/shopro/goods/activity?activity_id=' + i.content.id,
                                }, function (ret, res) {
                                    that.$set(i.content, 'timeData', res.data.goods.data)
                                    return false;
                                }, function () {
                                    that.$set(i.content, 'timeData', [])
                                    return false;
                                })
                            } else if (i.type == 'seckill') {
                                Fast.api.ajax({
                                    url: domain + '/addons/shopro/goods/activity?activity_id=' + i.content.id,
                                }, function (ret, res) {
                                    that.$set(i.content, 'timeData', res.data.goods.data)
                                    return false;
                                }, function () {
                                    that.$set(i.content, 'timeData', [])
                                    return false;
                                })
                            } else if (i.type == 'goods-list') {
                                Fast.api.ajax({
                                    url: domain + '/addons/shopro/goods/lists?goods_ids=' + i.content.ids + "&per_page=999999999",
                                }, function (ret, res) {
                                    that.$set(i.content, 'timeData', res.data.data)
                                    return false;
                                }, function () {
                                    that.$set(i.content, 'timeData', [])
                                    return false;
                                })
                            } else if (i.type == 'coupons') {
                                Fast.api.ajax({
                                    url: domain + '/addons/shopro/coupons/lists?ids=' + i.content.ids,
                                }, function (ret, res) {
                                    that.$set(i.content, 'timeData', res.data)
                                    return false;
                                }, function () {
                                    that.$set(i.content, 'timeData', [])
                                    return false;
                                })
                            } else if (i.type == 'live') {
                                Fast.api.ajax({
                                    url: domain + '/addons/shopro/live?ids=' + i.content.ids,
                                }, function (ret, res) {
                                    that.$set(i.content, 'timeData', res.data)
                                    return false;
                                }, function () {
                                    that.$set(i.content, 'timeData', [])
                                    return false;
                                })
                            } else if (i.type == 'rich-text') {
                                if (i.content.id) {
                                    Fast.api.ajax({
                                        url: domain + '/addons/shopro/index/richtext?id=' + i.content.id,
                                    }, function (ret, res) {
                                        that.$set(i.content, 'timeData', res.data.content)
                                        return false;
                                    }, function () {
                                        that.$set(i.content, 'timeData', '')
                                        return false;
                                    })
                                } else {
                                    that.$set(i.content, 'timeData', '')
                                }
                            }
                        })
                    },
                    cloneComponent(type) {
                        var form;
                        switch (type) {
                            case 'search':
                                form = {
                                    name: "搜索",
                                    content: "",
                                    type: "search",
                                };
                                break;
                            case 'banner':
                                form = {
                                    name: "轮播图",
                                    type: "banner",
                                    content: {
                                        name: "",
                                        style: 1,
                                        height: 520,
                                        radius: 0,
                                        x: 0,
                                        y: 0,
                                        list: [{
                                            name: "",
                                            bgcolor: "",
                                            image: "",
                                            path: "",
                                            path_name: "",
                                            path_type: 1,
                                        }],
                                    }
                                };
                                break;
                            case 'menu':
                                form = {
                                    name: "菜单组",
                                    type: "menu",
                                    content: {
                                        name: "",
                                        style: 4,
                                        list: [{
                                            name: "标题",
                                            image: "/assets/addons/shopro/img/decorate/image-default3.png",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }, {
                                            name: "标题",
                                            image: "/assets/addons/shopro/img/decorate/image-default3.png",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }, {
                                            name: "标题",
                                            image: "/assets/addons/shopro/img/decorate/image-default3.png",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }, {
                                            name: "标题",
                                            image: "/assets/addons/shopro/img/decorate/image-default3.png",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }]
                                    }
                                };
                                break;
                            case 'live':
                                form = {
                                    name: "小程序直播",
                                    type: "live",
                                    content: {
                                        style: 1,
                                        ids: '',
                                        name: "",
                                        timeData: []
                                    }
                                };
                                break;
                            case 'adv':
                                form = {
                                    name: "广告魔方",
                                    type: "adv",
                                    content: {
                                        list: [{
                                            name: "",
                                            image: "",
                                            path: "",
                                            path_name: "",
                                            path_type: 1,
                                        }],
                                        name: "",
                                        style: 1
                                    }
                                };
                                break;
                            case 'goods-group':
                                form = {
                                    name: "商品分类",
                                    type: "goods-group",
                                    content: {
                                        id: '',
                                        name: "",
                                        style: 1,
                                        category_name: "",
                                        image: "",
                                        timeData: []
                                    }
                                };
                                break;
                            case 'goods-list':
                                form = {
                                    name: "自定义商品",
                                    type: "goods-list",
                                    content: {
                                        ids: '',
                                        image: "",
                                        name: "",
                                        style: 1,
                                        timeData: []

                                    }
                                };
                                break;
                            case 'coupons':
                                form = {
                                    name: "优惠券",
                                    type: "coupons",
                                    content: {
                                        ids: '',
                                        name: '',
                                        style: 1,
                                        bgcolor1: '#EFC480',
                                        bgcolor2: '#EFC480',
                                        pricecolor: '#4F3A1E',
                                        color: '#A8700D',
                                        timeData: []
                                    }
                                };
                                break;
                            case 'groupon':
                                form = {
                                    name: "拼团",
                                    type: "groupon",
                                    content: {
                                        id: '',
                                        name: "",
                                        style: 1,
                                        groupon_name: '',
                                        timeData: [],
                                        team_num: ''
                                    }
                                };
                                break;
                            case 'seckill':
                                form = {
                                    name: "秒杀",
                                    type: "seckill",
                                    content: {
                                        id: '',
                                        name: "",
                                        style: 1,
                                        seckill_name: '',
                                        timeData: []
                                    }
                                };
                                break;
                            case 'nav-list':
                                form = {
                                    name: "列表导航",
                                    type: "nav-list",
                                    content: {
                                        name: "",
                                        list: [{
                                            name: "标题",
                                            image: "/assets/addons/shopro/img/decorate/image-default3.png",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }]
                                    }
                                };
                                break;
                            case 'grid-list':
                                form = {
                                    name: "宫格列表",
                                    type: "grid-list",
                                    content: {
                                        name: "",
                                        list: [{
                                            name: "标题",
                                            image: "",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }, {
                                            name: "标题",
                                            image: "",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }, {
                                            name: "标题",
                                            image: "",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }, {
                                            name: "标题",
                                            image: "",
                                            path: "",
                                            path_name: "",
                                            path_type: 1
                                        }]
                                    }
                                };
                                break;
                            case 'rich-text':
                                form = {
                                    name: "富文本",
                                    type: "rich-text",
                                    content: {
                                        id: '',
                                        name: '',
                                        content: ''
                                    }
                                };
                                break;
                            case 'title-block':
                                form = {
                                    name: "标题栏",
                                    type: "title-block",
                                    content: {
                                        name: "",
                                        color: "#000000",
                                        image: ''
                                    }
                                };
                                break;
                            case 'order-card':
                                form = {
                                    name: "订单卡片",
                                    type: "order-card",
                                    content: {}
                                };
                                break;
                            case 'wallet-card':
                                form = {
                                    name: "资产卡片",
                                    type: "wallet-card",
                                    content: {}
                                };
                                break;
                            case 'category-tabs':
                                form = {
                                    name: "分类选项卡",
                                    type: "category-tabs",
                                    content: {
                                        ids: '',
                                        category_arr: [],
                                        style: 1,
                                        timeData: []
                                    }
                                };
                                break;
                        }
                        return form
                    },
                    menuStart(e) {
                        this.saveHtml = this.deepClone(e.clone.innerHTML);
                    },
                    menuMove(e) {
                        if (e.to.className.indexOf('center-draggable') != -1) {
                            var flag = false
                            this.templateData.forEach(i => {
                                if (i.type == 'category-tabs') {
                                    flag = true
                                }
                            })
                            //头部不可移动
                            if (this.fromtype == 'shop' && e.draggedContext.futureIndex == 0) {
                                return false;
                            }
                            //存在时，其余的元素不可放在最后
                            // if (this.fromtype == 'shop') {
                            if (e.dragged.className.split(' ')[1] == 'category-tabs') {
                                if (flag) {
                                    return false;
                                }
                            } else {
                                if (flag) {
                                    if (e.draggedContext.futureIndex == this.templateData.length) {
                                        return false;
                                    }
                                }
                            }
                            // }
                            e.dragged.innerHTML = `<div style="padding:0 50px;width:100%;height:50px;line-height:50px">新添加的元素</div>`;
                        } else {
                            return false;
                        }
                    },
                    menuEnd(e) {
                        e.item.innerHTML = this.saveHtml;
                        //clone数据格式有变化
                        let type = e.item.className.split(' ')[1];

                        if (this.fromtype == 'shop' && e.newIndex == 0) {
                            return false;
                        }

                        if (this.templateData.length > 0) {
                            if (e.newIndex == this.templateData.length && type != 'category-tabs') {
                                return false;
                            }
                            this.centerSelect = e.newIndex;
                        } else {
                            this.centerSelect = 0;
                        }
                        let clonehtml = this.cloneComponent(type)
                        if (type == 'category-tabs') {
                            this.centerSelect = this.templateData.length - 1;
                        }
                        this.$set(this.templateData, this.centerSelect, clonehtml)
                        this.showForm(this.centerSelect)
                    },
                    centerMove(e) {
                        if (this.fromtype == 'shop' && e.relatedContext.index == 0) {
                            return false;
                        }
                        if (e.draggedContext.futureIndex == this.templateData.length - 1) {
                            if (e.draggedContext.element.type == 'category-tabs') {
                                if (this.notifyFlag) {
                                    this.$notify({
                                        title: '警告',
                                        message: '分类选项卡不能移动',
                                        type: 'warning'
                                    });
                                }
                                this.notifyFlag = false
                                return false;
                            }
                            if (e.draggedContext.element.type != 'category-tabs') {
                                if (this.notifyFlag) {
                                    this.$notify({
                                        title: '警告',
                                        message: '分类选项卡必须放在最后',
                                        type: 'warning'
                                    });
                                }
                                this.notifyFlag = false
                                return false;
                            }
                        }
                        if (e.draggedContext.element.type == 'category-tabs') {
                            if (this.notifyFlag) {
                                this.$notify({
                                    title: '警告',
                                    message: '分类选项卡必须放在最后',
                                    type: 'warning'
                                });
                            }
                            this.notifyFlag = false
                            return false;
                        }
                    },
                    centerEnd(e) {
                        this.centerSelect = e.newIndex;
                        this.showForm(this.centerSelect);
                    },
                    deepClone(obj) {
                        return JSON.parse(JSON.stringify(obj))
                    },
                    compotentShowForm(index) {
                        this.centerSelect = index;
                        this.templateForm = this.templateData[index];
                    },
                    popupSelect(index) {
                        this.popupIndex = index;
                    },
                    selectTools(type) {
                        let form = this.cloneComponent(type);
                        let flag = false
                        this.templateData.forEach(i => {
                            if (i.type == 'category-tabs') {
                                flag = true
                            }
                        })
                        if (this.centerSelect == null) {
                            this.centerSelect = this.templateData.length;
                            if (type != 'category-tabs' && this.templateData.length > 1) {
                                this.centerSelect = this.templateData.length
                            }
                        } else {
                            this.centerSelect = this.centerSelect + 1;
                            if (type != 'category-tabs' && this.centerSelect == this.templateData.length) {
                                if (flag) {
                                    this.centerSelect = this.centerSelect - 1
                                }
                            }
                        }
                        if (type == 'category-tabs') {
                            if (!flag) {
                                this.centerSelect = this.templateData.length;
                                this.templateData.splice(this.centerSelect, 0, form);
                                this.showForm(this.centerSelect);
                            }
                        } else {
                            this.templateData.splice(this.centerSelect, 0, form);
                            this.showForm(this.centerSelect);
                        }
                    },
                    centerDel(idx) {
                        this.templateData.splice(idx, 1);
                        this.centerSelect = idx;
                        if (this.centerSelect == 0) {
                            if (this.templateData.length > 1) {
                                this.templateForm = this.templateData[this.centerSelect]
                            } else {
                                this.centerSelect = null;
                            }
                        } else {
                            this.centerSelect = this.centerSelect - 1;
                            this.templateForm = this.templateData[this.centerSelect]
                        }
                    },
                    //删除子元素
                    rightDel(index) {
                        this.popupIndex = null;
                        this.templateData[this.centerSelect].content.list.splice(index, 1)
                    },
                    showForm(index) {
                        this.centerSelect = index;
                        this.templateForm = this.templateData[index];
                    },
                    // 添加
                    addForm(type) {
                        let form = {};
                        switch (type) {
                            case 'banner':
                                form = {
                                    image: '',
                                    path: '',
                                    path_type: 1,
                                    name: '',
                                    bgcolor: '',
                                    path_name: ""
                                };
                                break;
                            case 'menu':
                                form = {
                                    image: '',
                                    path: '',
                                    name: '',
                                    path_name: '',
                                    path_type: 1
                                };
                                break;
                            case 'nav-list':
                                form = {
                                    name: "",
                                    image: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1
                                };
                                break;
                            case 'grid-list':
                                form = {
                                    name: "",
                                    image: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1
                                };
                                break;
                            case 'tabbar':
                                form = {
                                    name: "",
                                    image: "",
                                    activeImage: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1,
                                    selected: false
                                }
                                break;
                            case 'popup':
                                form = {
                                    image: "",
                                    path: "",
                                    path_name: "",
                                    path_type: 1,
                                    style: 1,
                                }
                                break;
                            case 'float-button':
                                this.isfloat = false;
                                form = {
                                    name: "",
                                    style: 1,
                                    image: '',
                                    btnimage: '',
                                    path: '',
                                    path_name: '',
                                    path_type: 1,
                                }
                                break;
                        }
                        this.templateData[this.centerSelect].content.list.push(form);
                    },
                    goPreview() {
                        let that = this;
                        that.httpsDlocked = true
                        let templateData = ''
                        if (that.fromtype == 'shop') {
                            that.tabbarData[0].content.isshow = true;
                            //删除多余的数据
                            //秒杀 拼团 优惠券 直播 tabbar 商品分类 自定义商品
                            let submitHome = JSON.parse(JSON.stringify(that.homeData));
                            let submitUser = JSON.parse(JSON.stringify(that.userData))
                            submitHome.forEach(i => {
                                delete i.content.timeData
                            })
                            submitUser.forEach(i => {
                                delete i.content.timeData
                            })
                            that.tabbarData[0].content.list.forEach(i => {
                                delete i.selected
                            })
                            templateData = {
                                home: submitHome,
                                popup: that.popupData,
                                tabbar: that.tabbarData,
                                user: submitUser,
                                'float-button': that.floatButtonData,
                            }

                        } else {
                            let submitCustom = JSON.parse(JSON.stringify(that.customData));
                            submitCustom.forEach(i => {
                                delete i.content.timeData
                            })
                            templateData = {
                                custom: submitCustom,
                            }
                        }
                        templateData = JSON.stringify(templateData)
                        Fast.api.ajax({
                            url: 'shopro/decorate/preview/id/' + that.decorate_id,
                            loading: true,
                            data: {
                                templateData: templateData,
                            }
                        }, function (ret, res) {
                            that.iframeTitle = res.msg;
                            that.iframeCopyright = Config.shoproConfig.copyright;
                            that.iframePlatform = res.data.platform;
                            that.previewDialog = true;
                            if (Config.shoproConfig.domain) {
                                if (Config.shoproConfig.domain.split('://')[0] == 'http' && window.location.protocol == 'https:') {
                                    that.httpsDlocked = false
                                }
                                that.iframeSrc = Config.shoproConfig.domain + '?' + that.fromtype + '_id=' + res.data.id + "&time=" + new Date().getTime();
                                if (that.iframePlatform && (that.iframePlatform.includes('wxOfficialAccount') || that.iframePlatform.includes('H5'))) {
                                    that.qrcodeSrc = 'http://qrcode.7wpp.com?url=' + encodeURIComponent(that.iframeSrc);
                                }
                            }
                            // 小程序码
                            if (that.iframePlatform && that.iframePlatform.includes('wxMiniProgram')) {
                                that.wxacodeSrc = window.location.origin + '/addons/shopro/wechat/wxacode?scene=' + encodeURIComponent(that.fromtype + '_id=' + res.data.id);
                            }

                        })
                    },
                    getHtml2canvas() {
                        let that = this;
                        that.centerSelect = null;
                        $('.decorate-center-container').removeClass("decorate-center-container-scrollbar")
                        return new Promise(resolve => {
                            document.getElementById("html2canvasWrap").scrollTop = '0px'
                            html2canvas(document.getElementById("html2canvasWrap"), {
                                backgroundColor: '#f00',
                                foreignObjectRendering: true,
                                allowTaint: false,
                                taintTest: true,
                                scale: 2,
                                useCORS: true,
                                scrollX: 0,
                                scrollY: 0,
                                dpi: 750,
                            }).then(canvas => {
                                $('.decorate-center-container').addClass("decorate-center-container-scrollbar")
                                let dataURI = canvas.toDataURL()

                                var binary = atob(dataURI.split(',')[1]);
                                var array = [];
                                for (var i = 0; i < binary.length; i++) {
                                    array.push(binary.charCodeAt(i));
                                }
                                var $Blob = new Blob([new Uint8Array(array)], { type: 'image/png' });

                                var formData = new FormData();
                                let fileOfBlob = new File([$Blob], new Date() + '.png')
                                formData.append("file", fileOfBlob);

                                $.ajax({
                                    type: "post",
                                    url: "/addons/shopro/index/upload",
                                    data: formData,
                                    cache: false,
                                    processData: false,
                                    contentType: false,
                                    success: function (data) {
                                        resolve(data.data.url);
                                    }
                                })
                            });
                        });
                    },
                    uploadTemplateImage(image) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/decorate/saveDecorateImage/id/' + that.decorate_id,
                            loading: false,
                            data: {
                                image: image
                            }
                        }, function (ret, res) {
                            return false;
                        })
                    },
                    async goPreserve() {
                        let that = this;
                        if (that.fromtype == 'shop') {
                            if (that.isPageType != 'home') {
                                that.$message({
                                    message: '为了更新您的店铺封面，请切换至首页进行保存',
                                    type: 'warning'
                                });
                                return false
                            }
                            that.saveLoading = true
                            let templateImage = await this.getHtml2canvas()
                            // 如果使用底部导航 可以判断tabber
                            // that.tabbarData[0].content.isshow = true;
                            // if (that.tabbarData[0].content.list.length > 6) {
                            //     const h = this.$createElement;
                            //     that.$message({
                            //         message: h('p', null, [
                            //             h('span', null, '底部导航最多5个 ')
                            //         ])
                            //     });
                            //     return false;
                            // } else if (that.tabbarData[0].content.list.length > 0) {
                            //     let flag = false;
                            //     if (that.tabbarData[0].content.style == 1) {
                            //         if (that.tabbarData[0].content.color == '' || that.tabbarData[0].content.activeColor == '') {
                            //             flag = true
                            //         }
                            //         that.tabbarData[0].content.list.forEach(i => {
                            //             if (i.activeImage == '' || i.image == '' || i.name == '' || i.path == '') {
                            //                 flag = true
                            //             }
                            //         })
                            //     } else if (that.tabbarData[0].content.style == 3) {
                            //         if (that.tabbarData[0].content.color == '' || that.tabbarData[0].content.activeColor == '') {
                            //             flag = true
                            //         }
                            //         that.tabbarData[0].content.list.forEach(i => {
                            //             if (i.name == '' || i.path == '') {
                            //                 flag = true
                            //             }
                            //         })
                            //     } else if (that.tabbarData[0].content.style == 2) {
                            //         that.tabbarData[0].content.list.forEach(i => {
                            //             if (i.activeImage == '' || i.image == '' || i.path == '') {
                            //                 flag = true
                            //             }
                            //         })
                            //     } else {
                            //         flag = true
                            //     }
                            //     if (flag) {
                            //         that.$message({
                            //             message: "请完善底部导航"
                            //         });
                            //         return false;
                            //     }
                            // }
                            let submitHome = JSON.parse(JSON.stringify(that.homeData));
                            let submitUser = JSON.parse(JSON.stringify(that.userData))
                            submitHome.forEach(i => {
                                delete i.content.timeData
                            })
                            submitUser.forEach(i => {
                                delete i.content.timeData
                            })
                            that.tabbarData[0].content.list.forEach(i => {
                                delete i.selected
                            })
                            let templateData = {
                                home: submitHome,
                                popup: that.popupData,
                                tabbar: that.tabbarData,
                                user: submitUser,
                                'float-button': that.floatButtonData,
                            }
                            templateData = JSON.stringify(templateData);
                            Fast.api.ajax({
                                url: 'shopro/decorate/dodecorate_save/id/' + that.decorate_id,
                                loading: false,
                                data: {
                                    templateData: templateData
                                }
                            }, function (ret, res) {
                                that.$nextTick(() => {
                                    that.uploadTemplateImage(templateImage)
                                })
                                that.saveLoading = false
                            }, function (ret, res) {
                                that.saveLoading = false
                            })
                        } else {
                            that.saveLoading = true
                            let submitCustom = JSON.parse(JSON.stringify(that.customData));
                            submitCustom.forEach(i => {
                                delete i.content.timeData
                            })
                            let templateData = {
                                custom: submitCustom,
                            }
                            templateData = JSON.stringify(templateData)
                            Fast.api.ajax({
                                url: 'shopro/decorate/dodecorate_save/id/' + that.decorate_id,
                                loading: false,
                                data: {
                                    templateData: templateData
                                }
                            }, function (ret, res) {
                                that.saveLoading = false
                            }, function (ret, res) {
                                that.saveLoading = false
                            })
                        }
                    },
                    previewClose() {
                        this.previewDialog = false;
                    },
                    selectTitleBlock(index) {
                        if (index != null) {
                            this.titleBlock.isSelected = true
                            this.templateData[this.centerSelect].content.image = this.titleBlock.data[index].src
                            this.titleBlock.currentImage = this.titleBlock.data[index].src
                        } else {
                            this.titleBlock.isSelected = false
                            this.templateData[this.centerSelect].content.image = ''
                        }
                    },
                    chooseAdvPic() {
                        this.advdrawer = true;
                    },
                    changeAdv(index, num) {
                        this.templateData[this.centerSelect].content.list = []
                        this.templateData[this.centerSelect].content.style = index + 1
                        for (let i = 0; i < num; i++) {
                            this.templateData[this.centerSelect].content.list.push({
                                image: "",
                                name: "",
                                path: "",
                                path_name: "",
                                path_type: 1,
                            })
                        }
                        this.templateForm = this.templateData[this.centerSelect]
                        this.advdrawer = false;
                    },
                    selectType(type, index) {
                        let that = this;
                        that.isPageType = type;
                        that.pageTypeList.forEach(i => {
                            i.flag = false
                        });
                        that.pageTypeList[index].flag = true;
                    },
                    selectDate(type) {
                        let that = this;
                        switch (type) {
                            case 'home':
                                that.homeData = that.templateData;
                                break;
                            case 'user':
                                that.userData = that.templateData;
                                break;
                            case 'tabbar':
                                that.tabbarData = that.templateData;
                                break;
                            case 'popup':
                                that.popupData = that.templateData;
                                break;
                            case 'float-button':
                                that.floatButtonData = that.templateData;
                                break;
                        }
                    },
                    selecttoDate(type) {
                        let that = this;
                        switch (type) {
                            case 'home':
                                that.templateData = that.homeData;
                                break;
                            case 'user':
                                that.templateData = that.userData;
                                break;
                            case 'tabbar':
                                that.templateData = that.tabbarData
                                break;
                            case 'popup':
                                that.templateData = that.popupData
                                break;
                            case 'float-button':
                                that.templateData = that.floatButtonData;
                                break;
                        }
                    },
                    isweblink(type, index) {
                        this.templateForm.content.list[index].path = '';
                        this.templateForm.content.list[index].path_name = '';
                    },
                    operation(type) {
                        let that = this;
                        let domain = window.location.origin;
                        switch (type) {
                            case 'goods-group': //商品分类 单选
                                Fast.api.open("shopro/category/select?multiple=false&from=group", "选择分类", {
                                    callback: function (data) {
                                        that.templateData[that.centerSelect].content.category_name = data.data.category_name;
                                        that.templateData[that.centerSelect].content.id = data.data.id;
                                        that.templateForm = that.templateData[that.centerSelect];
                                        //请求数据
                                        Fast.api.ajax({
                                            url: domain + '/addons/shopro/goods/lists?category_id=' + data.data.id,
                                        }, function (ret, res) {
                                            that.templateData[that.centerSelect].content.timeData = res.data.data;
                                            that.$forceUpdate()
                                            return false;
                                        })
                                    }
                                });
                                break;
                            case 'goods-list': //自定义商品 多选
                                let ids = that.templateData[that.centerSelect].content.ids
                                parent.Fast.api.open("shopro/goods/goods/select?multiple=true&type=activity&ids=" + ids, "选择商品", {
                                    callback: function (data) {
                                        let idsArr = []
                                        let newArr = data.data
                                        that.templateData[that.centerSelect].content.timeData = newArr;
                                        newArr.forEach(i => {
                                            idsArr.push(i.id)
                                        })
                                        that.templateData[that.centerSelect].content.timeData = newArr
                                        that.templateData[that.centerSelect].content.ids = idsArr.join(',')
                                        that.templateForm = that.templateData[that.centerSelect]
                                        that.$forceUpdate()
                                    }
                                });
                                break;
                            case 'coupons':
                                parent.Fast.api.open("shopro/coupons/select?multiple=true&type=decorate", "选择优惠券", {
                                    callback: function (data) {
                                        let idsArr = []
                                        let obj = {}
                                        let newArr = []
                                        if (isArray(data.data)) {
                                            newArr = data.data
                                        } else {
                                            newArr.push(data.data)
                                        }
                                        newArr.forEach(i => {
                                            idsArr.push(i.id)
                                        })
                                        that.templateData[that.centerSelect].content.timeData = newArr
                                        that.templateData[that.centerSelect].content.ids = idsArr.join(',')
                                        that.templateForm = that.templateData[that.centerSelect]
                                        that.$forceUpdate()
                                    }
                                });
                                break;
                            case 'groupon':
                                Fast.api.open("shopro/activity/activity/select?multiple=false&type=groupon", "选择拼团活动", {
                                    callback: function (data) {
                                        that.templateData[that.centerSelect].content.id = data.data.id
                                        that.templateData[that.centerSelect].content.groupon_name = data.data.title
                                        that.templateForm = that.templateData[that.centerSelect]
                                        Fast.api.ajax({
                                            url: domain + '/addons/shopro/goods/activity?activity_id=' + data.data.id,
                                        }, function (ret, res) {
                                            that.templateData[that.centerSelect].content.timeData = res.data.goods.data
                                            that.templateData[that.centerSelect].content.team_num = res.data.rules.team_num
                                            that.$forceUpdate()
                                            return false;
                                        })
                                    }
                                });
                                break;
                            case 'seckill':
                                parent.Fast.api.open("shopro/activity/activity/select?multiple=false&type=seckill", "选择秒杀活动", {
                                    callback: function (data) {
                                        that.templateData[that.centerSelect].content.id = data.data.id
                                        that.templateData[that.centerSelect].content.seckill_name = data.data.title
                                        that.templateForm = that.templateData[that.centerSelect];
                                        Fast.api.ajax({
                                            url: domain + '/addons/shopro/goods/activity?activity_id=' + data.data.id,
                                        }, function (ret, res) {
                                            that.templateData[that.centerSelect].content.timeData = res.data.goods.data
                                            that.$forceUpdate()
                                            return false;
                                        })
                                    }
                                });
                                break;
                            case 'live':
                                Fast.api.open("shopro/app/live/select?multiple=true&type=decorate", "选择小程序直播", {
                                    callback: function (data) {
                                        let idsArr = []
                                        let obj = {}
                                        let newArr = []
                                        if (isArray(data.data)) {
                                            newArr = data.data
                                        } else {
                                            newArr.push(data.data)
                                        }
                                        newArr.forEach(i => {
                                            idsArr.push(i.id)
                                        })
                                        that.templateData[that.centerSelect].content.timeData = newArr
                                        that.templateData[that.centerSelect].content.ids = idsArr.join(',')
                                        that.templateForm = that.templateData[that.centerSelect]
                                        that.$forceUpdate()
                                    }
                                });
                                break;
                            case 'category-tabs': //分类选项卡 多选
                                Fast.api.open("shopro/category/select?multiple=true&from=category-tabs", "选择分类", {
                                    callback: function (data) {
                                        that.templateData[that.centerSelect].content.ids = data.data.id;
                                        that.templateData[that.centerSelect].content.category_arr = data.data.category_arr;
                                        that.templateForm = that.templateData[that.centerSelect];
                                        // //请求数据
                                        Fast.api.ajax({
                                            url: domain + '/addons/shopro/goods/lists?category_id=' + data.data.id.split(',')[0],
                                        }, function (ret, res) {
                                            that.templateData[that.centerSelect].content.timeData = res.data.data;
                                            that.$forceUpdate()
                                            return false;
                                        })
                                    }
                                });
                                break;
                        }
                    },
                    tabbarSelected(index) {
                        this.templateData[0].content.list.forEach(i => {
                            i.selected = false;
                        })
                        this.$set(this.templateData[0].content.list[index], 'selected', true)
                        this.$forceUpdate()
                    },
                    choosePicture(type, index) {
                        var that = this;
                        parent.Fast.api.open("general/attachment/select?multiple=false", "选择图片", {
                            callback: function (data) {
                                switch (type) {
                                    case "banner":
                                        that.templateData[that.centerSelect].content.list[index].image = data.url;
                                        that.templateForm = that.templateData[that.centerSelect];
                                        //转换
                                        const rgbToHex = (r, g, b) => '#' + [r, g, b].map(x => {
                                            const hex = x.toString(16)
                                            return hex.length === 1 ? '0' + hex : hex
                                        }).join('')
                                        //实例
                                        const colorThief = new ColorThief();
                                        const imgss = new Image();
                                        if (Config.__CDN__) {
                                            imgss.src = window.location.origin + Config.moduleurl + '/shopro/upload/proxyImg?url=' + encodeURIComponent(Fast.api.cdnurl(that.templateData[that.centerSelect].content.list[index].image));
                                        } else {
                                            imgss.src = that.templateData[that.centerSelect].content.list[index].image
                                        }
                                        imgss.addEventListener('load', function () {
                                            that.templateData[that.centerSelect].content.list[index].bgcolor = rgbToHex(colorThief.getColor(imgss)[0], colorThief.getColor(imgss)[1], colorThief.getColor(imgss)[2])
                                        });
                                        break;
                                }
                            }
                        });
                        return false;
                    },
                    customList(index) {
                        this.templateData[this.centerSelect].content.timeData.splice(index, 1);
                        let idsArr = this.templateData[this.centerSelect].content.ids.split(',');
                        idsArr.splice(index, 1);
                        this.templateData[this.centerSelect].content.ids = idsArr.join(',');
                        this.templateForm.content.ids = idsArr.join(',');
                        this.$forceUpdate();
                    },
                    // 自定义列表排序
                    goodsListEnd() {
                        this.templateForm.content.ids = ''
                        let idsArr = []
                        this.templateForm.content.timeData.forEach(t => {
                            idsArr.push(t.id)
                        })
                        this.templateForm.content.ids = idsArr.join(',')
                    }
                },
                watch: {
                    templateData: {
                        handler: function (newVal, oldVal) {
                            newVal.length == 0 ? this.templateForm = {} : this.templateForm
                        },
                        deep: true
                    },
                    isPageType(newVal, oldVal) {
                        this.popupIndex = null;
                        if (oldVal) {
                            this.selectDate(oldVal)
                        }
                        this.selecttoDate(newVal);
                        if (newVal != 'home') {
                            this.centerSelect = 0;
                        } else {
                            if (this.homeData.length > 0) {
                                this.centerSelect = 0;
                            } else {
                                this.centerSelect = null;
                            }
                        }
                        this.showForm(this.centerSelect);
                    },
                }
            })
            //所有图片选择	
            $(document).on("click", ".choosePicture", function () {
                var that = this;
                var multiple = $(this).data("multiple") ? $(this).data("multiple") : false;
                parent.Fast.api.open("general/attachment/select?multiple=" + multiple, "选择图片", {
                    callback: function (data) {
                        let index = $(that).attr("data-index")
                        switch (index) {
                            case "image":
                                vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.image = data.url;
                                vueAdd.$data.templateForm = vueAdd.$data.templateData[vueAdd.$data.centerSelect];
                                break;
                            case "title-block":
                                vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.image = data.url;
                                vueAdd.$data.titleBlock.currentImage = data.url;
                                vueAdd.$data.templateForm = vueAdd.$data.templateData[vueAdd.$data.centerSelect];
                                break;
                            default:
                                if ($(that).attr("data-active") == 'active') {
                                    vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.list[index].activeImage = data.url;
                                } else if ($(that).attr("data-type") == 'btn') {
                                    vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.list[index].btnimage = data.url;
                                } else {
                                    vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.list[index].image = data.url;
                                }
                                vueAdd.$data.templateForm = vueAdd.$data.templateData[vueAdd.$data.centerSelect]
                        }
                    }
                });
                return false;
            })
            //选择链接Path	
            $(document).on("click", ".choosePath", function () {
                var that = this;
                var multiple = $(this).data("multiple") ? $(this).data("multiple") : false;
                parent.Fast.api.open("shopro/link/select?multiple=" + multiple, "选择链接", {
                    callback: function (data) {
                        var index = $(that).attr("data-index")
                        let page = $(that).attr("data-page")
                        if (page == 'page') {
                            if (data.data.pathId) {
                                vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.list[index].page = data.data.path.split(',')
                                vueAdd.$data.templateForm = vueAdd.$data.templateData[vueAdd.$data.centerSelect]
                            } else {
                                vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.list[index].page = data.data.path.split(',')
                                vueAdd.$data.templateForm = vueAdd.$data.templateData[vueAdd.$data.centerSelect]
                            }

                        } else {
                            vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.list[index].path_name = data.data.path_name
                            vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.list[index].path = data.data.path
                            vueAdd.$data.templateForm = vueAdd.$data.templateData[vueAdd.$data.centerSelect]
                        }

                    }
                });
                return false;
            })
            // 选择富文本chooseRichText
            $(document).on("click", ".chooseRichText", function () {
                var that = this;
                parent.Fast.api.open("shopro/richtext/select?multiple=" + false, "选择富文本", {
                    callback: function (data) {
                        vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.id = data.data.id
                        vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.name = data.data.title
                        vueAdd.$data.templateData[vueAdd.$data.centerSelect].content.timeData = data.data.content
                        vueAdd.$data.templateForm = vueAdd.$data.templateData[vueAdd.$data.centerSelect]
                    }
                });
                return false;
            })
        },
        designer: function () {
            var decorateDesigner = new Vue({
                el: "#decorate-designer",
                data() {
                    return {
                        decorateList: Config.designerData,
                        previewDialog: false,
                        previewData: {},
                        iframeSrc: "",
                        qrcodeSrc: "",
                    }
                },
                mounted() { },
                methods: {
                    operation(type, index, id) {
                        let that = this;
                        that.previewData = that.decorateList[index];
                        switch (type) {
                            case 'preview':
                                that.iframeSrc = window.location.protocol + "//designer.7wpp.com?shop_id=" + id;
                                that.qrcodeSrc = 'http://qrcode.7wpp.com?url=' + that.iframeSrc;
                                that.previewDialog = true;
                                break;
                            case 'use':
                                Fast.api.ajax({
                                    url: 'shopro/decorate/use_designer_template?id=' + id,
                                    loading: true,
                                    data: {}
                                }, function (ret, res) {

                                })
                                break;
                        }

                    },
                    previewClose() {
                        this.previewDialog = false;
                    },
                },
            })
        },
        custom: function () {
            var decorateList = new Vue({
                el: "#decorate-list",
                data() {
                    return {
                        decorateList: [],
                        customDialog: false,
                        customTem: {
                            name: '',
                            memo: '',
                            platform: [],
                        },
                        editId: null,
                        rules: {
                            name: [
                                { required: true, message: '请输入名称', trigger: 'blur' },
                                { min: 2, max: 10, message: '长度在 2 到 10 个字符', trigger: 'blur' }
                            ],
                            memo: [
                                { required: true, message: '请输入备注', trigger: 'blur' },
                                { min: 2, max: 10, message: '长度在 3 到 10 个字符', trigger: 'blur' }
                            ],
                        }
                    }
                },
                mounted() {
                    this.getdecorateList();
                },
                methods: {
                    getdecorateList() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/decorate/lists',
                            loading: true,
                            type: 'GET',
                            data: {
                                type: 'custom'
                            }
                        }, function (ret, res) {
                            that.decorateList = res.data;
                            that.decorateList.forEach(i => {
                                i.iseditname = false
                            })
                            return false
                        })
                    },
                    async operation(opttype, id, type, name) {
                        let that = this;
                        switch (opttype) {
                            case 'decorate':
                                Controller.editTemplateApi.decorate(id, type, name)
                                break;
                            case 'delete':
                                let deleteRes = await Controller.editTemplateApi.delete(id)
                                if (deleteRes.code == 1) {
                                    that.getdecorateList();
                                }
                                break;
                            case 'copy':
                                let copyRes = await Controller.editTemplateApi.copy(id)
                                if (copyRes.code == 1) {
                                    that.getdecorateList();
                                }
                                break;
                            case 'create':
                                this.customDialog = true;
                                this.editId = id;
                                break;
                            case 'edit':
                                this.customDialog = true;
                                this.editId = id;
                                that.decorateList.forEach(i => {
                                    if (i.id == that.editId) {
                                        that.customTem.name = i.name;
                                        that.customTem.memo = i.name;
                                        if (i.platform != '') {
                                            that.customTem.platform = i.platform.split(',')
                                        } else {
                                            that.customTem.platform = []
                                        }
                                    }
                                })
                                break;
                        }
                    },
                    customClose(type) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs.ruleForm.validate(async (valid) => {
                                if (valid) {
                                    let res = ''
                                    if (that.editId) {
                                        res = await Controller.editTemplateApi.edit(that.editId, that.customTem)
                                    } else {
                                        res = await Controller.editTemplateApi.add('custom', that.customTem)
                                    }
                                    if (res.code == 1) {
                                        that.clearTemplateForm()
                                        that.getdecorateList();
                                    }
                                } else {
                                    return false;
                                }
                            });
                        } else {
                            that.clearTemplateForm()
                        }
                    },
                    clearTemplateForm() {
                        this.customDialog = false;
                        for (var key in this.customTem) {
                            this.customTem[key] = ""
                            if (key == 'platform') {
                                this.customTem[key] = []
                            }
                        }
                    },
                },
            })
        },
        select: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/decorate/index',
                }
            });
            var idArr = [];
            var table = $("#table");
            table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function (e, row) {
                if (e.type == 'check' || e.type == 'uncheck') {
                    row = [row];
                } else {
                    idArr = [];
                }
                $.each(row, function (i, j) {
                    if (e.type.indexOf("uncheck") > -1) {
                        var index = idArr.indexOf(j.id);
                        if (index > -1) {
                            idArr.splice(index, 1);
                        }
                    } else {
                        idArr.indexOf(j.id) == -1 && idArr.push(j.id);
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                showToggle: false,
                showExport: false,
                columns: [
                    [{
                        checkbox: true
                    },
                    {
                        field: 'id',
                        title: __('Id')
                    },
                    {
                        field: 'name',
                        title: __('Name')
                    },
                    {
                        field: 'type',
                        title: __('Type'),
                        searchList: {
                            "shop": '商城模板',
                            "custom": '自定义模板',
                            "preview": '预览模板'
                        },
                        formatter: Table.api.formatter.normal
                    },
                    {
                        field: 'updatetime',
                        title: __('Updatetime'),
                        operate: 'RANGE',
                        addclass: 'datetimerange',
                        formatter: Table.api.formatter.datetime
                    },
                    {
                        field: 'operate',
                        title: __('Operate'),
                        events: {
                            'click .btn-chooseone': function (e, value, row, index) {
                                var multiple = Backend.api.query('multiple');
                                multiple = multiple == 'true' ? true : false;
                                row.ids = row.id.toString()
                                Fast.api.close({
                                    data: row,
                                    multiple: multiple
                                });
                            },
                        },
                        formatter: function () {
                            return '<a href="javascript:;" class="btn btn-danger btn-chooseone btn-xs"><i class="fa fa-check"></i> ' + __('Choose') + '</a>';
                        }
                    }
                    ]
                ]
            });

            // 选中多个
            $(document).on("click", ".btn-choose-multi", function () {
                // var couponsArr = new Array();
                // $.each(table.bootstrapTable("getAllSelections"), function (i, j) {
                //     couponsArr.push(j.id);
                // });
                var multiple = Backend.api.query('multiple');
                multiple = multiple == 'true' ? true : false;
                let row = {}
                row.ids = idArr.join(",")
                Fast.api.close({
                    data: row,
                    multiple: multiple
                });
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            require(['upload'], function (Upload) {
                Upload.api.plupload($("#toolbar .plupload"), function () {
                    $(".btn-refresh").trigger("click");
                });
            });

        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
        editTemplateApi: {
            add: function (type, templateForm, status) {
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: 'shopro/decorate/add',
                        loading: true,
                        data: {
                            name: templateForm.name,
                            platform: templateForm.platform.join(','),
                            memo: templateForm.memo,
                            status: status,
                            type: type
                        }
                    }, function (ret, res) {
                        resolve(res);
                    }, function (ret, res) {
                        resolve(res);
                    })
                })
            },
            edit: function (id, templateForm) {
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: `shopro/decorate/edit/id/${id}`,
                        loading: true,
                        data: {
                            name: templateForm.name,
                            platform: templateForm.platform.join(','),
                            memo: templateForm.memo
                        }
                    }, function (ret, res) {
                        resolve(res);
                    }, function (ret, res) {
                        resolve(res);
                    })
                })
            },
            delete: function (id) {
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: 'shopro/decorate/del/ids/' + id,
                        type: 'POST',
                        loading: true,
                    }, function (ret, res) {
                        resolve(res);
                    }, function (ret, res) {
                        resolve(res);
                    })
                })
            },
            copy: function (id) {
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: 'shopro/decorate/copy/id/' + id,
                        loading: true,
                        data: {}
                    }, function (ret, res) {
                        resolve(res);
                    }, function (ret, res) {
                        resolve(res);
                    })
                })
            },
            decorate: function (id, type, name) {
                Fast.api.addtabs('shopro/decorate/dodecorate?id=' + id + '&type=' + type + '&name=' + name, '店铺装修');
            }
        }
    };
    return Controller;
});