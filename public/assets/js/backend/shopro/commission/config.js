define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Vue.directive('enterNumber', {
                inserted: function (el) {
                    let changeValue = (el, type) => {
                        const e = document.createEvent('HTMLEvents')
                        e.initEvent(type, true, true)
                        el.dispatchEvent(e)
                    }
                    el.addEventListener("keyup", function (e) {
                        let input = e.target;
                        let reg = new RegExp('^((?:(?:[1-9]{1}\\d*)|(?:[0]{1}))(?:\\.(?:\\d){0,2})?)(?:\\d*)?$');
                        let matchRes = input.value.match(reg);
                        if (matchRes === null) {
                            input.value = "";
                        } else {
                            if (matchRes[1] !== matchRes[0]) {
                                input.value = matchRes[1];
                            }
                        }
                        changeValue(input, 'input')
                    });
                }
            });
            var indexPage = new Vue({
                el: "#indexPage",
                data() {
                    return {
                        initData: {},
                        configData: {
                            commission_level: '1',
                            self_buy: '0',
                            invite_lock: 'share',
                            agent_check: '0',
                            upgrade_check: '0',
                            upgrade_jump: '0',
                            upgrade_display: '0',
                            become_agent: {
                                type: 'apply',
                                value: ''
                            },
                            agent_form: {
                                background_image: '',
                                content: [],
                            },
                            apply_protocol: '0',

                            commission_price_type: 'goods_price',
                            commission_event: 'payed',
                            refund_commission_reward: '0',
                            refund_commission_order: '0'
                        },
                        needApplyProtocol: "0",
                        needAgentForm: "0",

                        become_register_options: [{
                            value: 'input',
                            label: '文本内容'
                        }, {
                            value: 'number',
                            label: '纯数字'
                        }, {
                            value: 'image',
                            label: '上传图片'
                        }],
                        defaultOption: {
                            sort: true,
                            animation: 100,
                            ghostClass: "sortable-ghost",
                            handle: '#draggableHandle',
                            forceFallback: true,
                            fallbackClass: 'clone-item',
                            fallbackOnBody: true,

                            dragClass: 'dragin-item',
                        },
                        isAjax: true,
                        goodsDetail: null,
                        tipshow: true,
                        configis_upgrade:false,
                    }
                },
                created() {
                    this.init();
                    this.configis_upgrade=Config.is_upgrade
                },
                computed: {},
                methods: {
                    operation(type){
                        switch(type){
                            case 'close':
                                this.configis_upgrade=false
                                break;
                            case 'refresh':
                                window.location.reload();
                                break;
                            case 'upgrade':
                                window.open("https://www.fastadmin.net/store/shopro.html")
                                break;
                        }
                    },
                    tipClose() {
                        this.tipshow = false;
                    },
                    init() {
                        this.getConfigData();
                    },
                    getConfigData() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/commission/config/index',
                            type: 'GET',
                            loading: false,
                        }, function (ret, res) {
                            that.initData = res.data;
                            //转换数据格式
                            if(Array.isArray(that.initData)){
                            }else{
                                that.configData = that.initConfigData(that.initData);
                            }
                            that.isAjax = false;
                            return false;
                        })
                    },
                    initConfigData(data) {
                        let that = this
                        //拷贝一份可更改的configData，保留原始数据 
                        let configData = JSON.parse(JSON.stringify(data));
                        configData.become_agent = JSON.parse(configData.become_agent);
                        if (configData.become_agent.type == 'goods') {
                            shoproGoodsList(configData.become_agent.value).then(data => {
                                this.goodsDetail = data.data
                            }).catch(error => {
                                this.goodsDetail = error
                            });
                        }
                        if (configData.agent_form != '0') {
                            that.needAgentForm = '1';
                            configData.agent_form = JSON.parse(configData.agent_form);
                            if (configData.agent_form.content.length == 0) {
                                configData.agent_form.content.push({
                                    name: '',
                                    type: '',
                                })
                            }
                        }
                        if (configData.apply_protocol != '0') {
                            that.needApplyProtocol = '1';
                            configData.apply_protocol = JSON.parse(configData.apply_protocol);
                        }

                        return configData;
                    },
                    changeApplyProtocol(value) {
                        if (value == '0') {
                            this.configData.apply_protocol = '0';
                        } else {
                            this.configData.apply_protocol = {
                                name: '',
                                richtext_id: '',
                            }
                        }
                    },
                    changeBecomeAgentType(value) {
                        if (value == 'apply') {
                            this.needAgentForm = '1';
                            if (this.configData.agent_form == 0) {
                                this.configData.agent_form = {
                                    background_image: '',
                                    content: [{
                                        name: '',
                                        type: '',
                                    }],
                                }
                            }
                        }
                        this.configData.become_agent.value = "";
                    },
                    changeAgentForm(value) {
                        if (value == 0) {
                            this.configData.agent_form = '0'
                        } else {
                            this.configData.agent_form = {
                                background_image: '',
                                content: [{
                                    name: '',
                                    type: '',
                                }],
                            }
                        }
                    },
                    bgimageAdd() {
                        let that = this;
                        Fast.api.open("general/attachment/select?multiple=false", "选择", {
                            callback: function (data) {
                                that.configData.agent_form.background_image = data.url;
                            }
                        });
                        return false;
                    },
                    addGoods() {
                        let that = this;
                        let params = {
                            multiple: true,
                            type: '',
                            ids: that.configData.become_agent.value ? that.configData.become_agent.value : ''
                        }
                        shoproSelectGoods(params, "选择商品").then(data => {
                            if(data.data.length>0){
                                let idsArr = [];
                                data.data.forEach(goods => {
                                    idsArr.push(goods.id)
                                })
                                that.configData.become_agent.value = idsArr.join(',');
                                that.goodsDetail = data.data;
                            }
                        }).catch(error => {
                        });
                        return false;
                    },
                    deleteGoods(index) {
                        this.goodsDetail.splice(index, 1)
                        let idsArr = this.configData.become_agent.value.split(',')
                        idsArr.splice(index, 1)
                        this.configData.become_agent.value = idsArr.join(',')
                    },
                    chooseRichText() {
                        var that = this;
                        Fast.api.open("shopro/richtext/select?multiple=false", "选择申请协议", {
                            callback: function (data) {
                                that.configData.apply_protocol.richtext_id = data.data.id;
                                that.configData.apply_protocol.name = data.data.title;
                                that.$forceUpdate();
                            }
                        });
                        return false;
                    },
                    becomeRegisterAdd() {
                        this.configData.agent_form.content.push({
                            name: '',
                            type: '',
                        })
                        this.$forceUpdate();
                    },
                    becomeRegisterDelete(index) {
                        this.configData.agent_form.content.splice(index, 1)
                    },
                    formRestore() {
                        this.configData = this.initConfigData(this.initData);
                    },
                    formSubmit() {
                        let that = this;
                        this.$refs['configData'].validate((valid) => {
                            if (valid) {
                                let configForm = JSON.parse(JSON.stringify(that.configData))
                                //完善资料内容验证
                                if (that.needAgentForm != '0') {
                                    let agentFormValidate = true;
                                    if (configForm.agent_form.content.length > 0) {
                                        configForm.agent_form.content.forEach(i => {
                                            if (i.name == '' || i.type == '') {
                                                agentFormValidate = false;
                                            }
                                        })
                                        if (!agentFormValidate) {
                                            this.$notify({
                                                title: '警告',
                                                message: '表单内容必须填写完整',
                                                type: 'warning'
                                            });
                                            return false;
                                        }
                                        configForm.agent_form = JSON.stringify(configForm.agent_form)
                                    } else {
                                        this.$notify({
                                            title: '警告',
                                            message: '至少添加一条表单',
                                            type: 'warning'
                                        });
                                        return false;
                                    }
                                    // 验证申请协议
                                    if (that.needApplyProtocol == '1') {
                                        if (configForm.apply_protocol.richtext_id && configForm.apply_protocol.name) {
                                            configForm.apply_protocol = JSON.stringify(configForm.apply_protocol)
                                        } else {
                                            this.$notify({
                                                title: '警告',
                                                message: '协议内容必须填写完整',
                                                type: 'warning'
                                            });
                                            return false;
                                        }
                                    } else {
                                        configForm.apply_protocol = "0";
                                    }
                                } else {
                                    configForm.apply_protocol = "0";
                                }

                                //验证分销商条件
                                if (configForm.become_agent.type != 'apply' && configForm.become_agent.value == '') {
                                    this.$notify({
                                        title: '警告',
                                        message: '成为分销商条件必须填写完整',
                                        type: 'warning'
                                    });
                                    return false;
                                } else {
                                    configForm.become_agent = JSON.stringify(configForm.become_agent)
                                }
                                that.isAjax = true;
                                // return false;
                                Fast.api.ajax({
                                    url: 'shopro/commission/config/save',
                                    loading: false,
                                    data: configForm
                                }, function (ret, res) {
                                    that.isAjax = false;
                                    that.getConfigData();
                                    // return false;
                                }, function (ret, res) {
                                    that.isAjax = false;
                                })
                            } else {
                                return false;
                            }
                        });
                    },
                },
            })
        },
    };
    return Controller;
});