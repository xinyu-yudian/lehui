define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 检测是否安装 redis
            this.checkRedis();
            var activityIndex = new Vue({
                el: "#activityIndex",
                data() {
                    return {
                        tipCloseFlag: true,
                        activityOptions: [{
                            value: 'all',
                            label: '全部'
                        }, {
                            value: 'seckill',
                            label: '秒杀活动'
                        }, {
                            value: 'groupon',
                            label: '拼团活动'
                        }],
                        statusOptins: [{
                            value: 'all',
                            label: '全部'
                        }, {
                            value: 'nostart',
                            label: '未开始'
                        }, {
                            value: 'ing',
                            label: '进行中'
                        }, {
                            value: 'ended',
                            label: '已结束'
                        }],
                        searchForm: {
                            title: '',
                            type: "all",
                            status: "all",
                            activitytime: [],
                        },
                        searchFormInit: {
                            title: '',
                            type: "all",
                            status: "all",
                            activitytime: [],
                        },
                        searchOp: {
                            title: 'like',
                            type: "=",
                            status: "=",
                            activitytime: 'range',
                        },
                        activityData: [],
                        offset: 0,
                        limit: 10,
                        currentPage: 1,
                        totalPage: 0,

                    }
                },
                mounted() {
                    this.getType()
                    this.getActivityData()
                },
                methods: {
                    getType() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/activity/activity/getType',
                            loading: false,
                            type: 'GET',
                        }, function (ret, res) {
                            that.activityOptions = res.data.activity_type
                            that.statusOptins = res.data.activity_status
                            return false;
                        })
                    },
                    getActivityData(offset, limit) {
                        var that = this;
                        that.offset = (offset || offset == 0) ? offset : that.offset
                        that.limit = limit ? limit : that.limit
                        let filter = {}
                        let op = {}
                        for (key in that.searchForm) {
                            if (key == 'activitytime') {
                                if (that.searchForm[key]) {
                                    if (that.searchForm[key].length > 0) {
                                        filter[key] = that.searchForm[key].join(' - ');
                                    }
                                }
                            } else if (key == 'type' || key == 'status') {
                                if (that.searchForm[key] != '' && that.searchForm[key] != 'all') {
                                    filter[key] = that.searchForm[key];
                                }
                            } else if (key == 'title') {
                                if (that.searchForm[key] != '') {
                                    filter[key] = that.searchForm[key];
                                }
                            }
                        }
                        for (key in filter) {
                            op[key] = that.searchOp[key]
                        }
                        Fast.api.ajax({
                            url: 'shopro/activity/activity/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                offset: that.offset,
                                limit: that.limit,
                                filter: JSON.stringify(filter),
                                op: JSON.stringify(op)
                            }
                        }, function (ret, res) {
                            that.activityData = res.data.rows;
                            that.totalPage = res.data.total
                            return false;
                        })
                    },
                    screenEmpty() {
                        this.searchForm = JSON.parse(JSON.stringify(this.searchFormInit))
                        this.getActivityData(0, 10)
                    },
                    handleSizeChange(val) {
                        this.getActivityData(0, val)
                    },
                    handleCurrentChange(val) {
                        this.offset = (val - 1) * this.limit
                        this.getActivityData(this.offset, this.limit)
                    },
                    activityAdd() {
                        let that = this;
                        Fast.api.open(`shopro/activity/activity/add`, '新建活动', {
                            callback() {
                                that.getActivityData()
                            }
                        })
                    },
                    activityEdit(row) {
                        let that = this;
                        Fast.api.open(`shopro/activity/activity/edit?ids=${row.id}`, '编辑活动', {
                            callback() {
                                that.getActivityData()
                            }
                        })
                    },
                    activityView(row) {
                        Fast.api.open(`shopro/activity/activity/edit?ids=${row.id}&type=view`, '查看活动')
                    },
                    activityDelete(row) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/activity/activity/del/ids/${row.id}`,
                            loading: true,
                            type: 'POST',
                            data: {}
                        }, function (ret, res) {
                            that.getActivityData()
                        })
                    },
                    activityRecyclebin() {
                        Fast.api.open("shopro/activity/activity/recyclebin", "查看历史活动");
                    },
                    activityTipsClose() {
                        this.tipCloseFlag = false
                    },
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
                url: 'shopro/activity/activity/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                commonSearch: false,
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'title', title: __('Title'), align: 'left' },
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.detailInit('add')
        },
        sku: function () {
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
            Vue.directive('positiveInteger', {
                inserted: function (el) {
                    el.addEventListener("keypress", function (e) {
                        e = e || window.event;
                        let charcode = typeof e.charCode == 'number' ? e.charCode : e.keyCode;
                        let re = /\d/;
                        if (!re.test(String.fromCharCode(charcode)) && charcode > 9 && !e.ctrlKey) {
                            if (e.preventDefault) {
                                e.preventDefault();
                            } else {
                                e.returnValue = false;
                            }
                        }
                    });
                }
            });
            var skuPrice = new Vue({
                el: "#skuPrice",
                data() {
                    return {
                        skuList: Config.skuList,
                        skuPrice: Config.skuPrice,
                        actSkuPrice: Config.actSkuPrice,
                        type: '',
                        activity_status: '',
                    }
                },
                mounted() {
                    let actSkuPrice = decodeURI(new URLSearchParams(location.search).get('actSkuPrice'))
                    this.activity_status = new URLSearchParams(location.search).get('activity_status')
                    this.type = new URLSearchParams(location.search).get('type')
                    if (actSkuPrice) {
                        JSON.parse(actSkuPrice).forEach(i => {
                            this.actSkuPrice.forEach(e => {
                                if (i.sku_price_id == e.sku_price_id) {
                                    e.price = i.price
                                    e.status = i.status
                                    e.stock = i.stock
                                }
                            })
                        })
                    }
                },
                methods: {
                    changeStatus(i) {
                        let status = this.actSkuPrice[i].status === 'up' ? 'down' : 'up';
                        this.$set(this.actSkuPrice[i], 'status', status)
                    },
                    submitForm() {
                        this.$confirm('确认提交吗', '提示', {
                            confirmButtonText: '确定',
                            cancelButtonText: '取消',
                            type: 'warning'
                        }).then(() => {
                            let isSubmit = true
                            isSubmit = !(this.actSkuPrice.every(function (item, index, array) {
                                return item.status == 'down';
                            }))
                            this.actSkuPrice.forEach(i => {
                                if (i.status == 'up' && !i.stock) {
                                    isSubmit = false
                                }
                                if (i.status == 'up' && !i.price) {
                                    isSubmit = false
                                }
                            })
                            if (isSubmit) {
                                Fast.api.close(JSON.stringify(this.actSkuPrice));
                            } else {
                                this.$message({
                                    message: '请把信息填写完整',
                                    type: 'warning'
                                });
                            }
                        }).catch(() => {
                            this.$message({
                                type: 'info',
                                message: '已取消'
                            });
                        });
                    },
                    activityStock(i,field){
                        if(Number(this.skuPrice[i][field])<Number(this.actSkuPrice[i][field])){
                            this.actSkuPrice[i][field]=this.skuPrice[i][field]
                        }
                    }
                },
            })
        },
        edit: function () {
            Controller.detailInit('edit')
        },
        detailInit: function (type) {
            Vue.directive('positiveInteger', {
                inserted: function (el) {
                    el.addEventListener("keypress", function (e) {
                        e = e || window.event;
                        let charcode = typeof e.charCode == 'number' ? e.charCode : e.keyCode;
                        let re = /\d/;
                        if (!re.test(String.fromCharCode(charcode)) && charcode > 9 && !e.ctrlKey) {
                            if (e.preventDefault) {
                                e.preventDefault();
                            } else {
                                e.returnValue = false;
                            }
                        }
                    });
                }
            });
            var activityDetail = new Vue({
                el: "#activityDetail",
                data() {
                    var checkDiscounts = (rule, value, callback) => {
                        if (value && value.length == 0 && (this.activityForm.type == 'full_reduce' || this.activityForm.type == 'full_discount')) {
                            callback(new Error('请编辑优惠条件'));
                        } else {
                            callback();
                        }
                    }
                    var checkTeamNum = (rule, value, callback) => {
                        if(value!=0){
                            callback();
                        }else{
                            callback(new Error('成团人数必须大于0'));
                        }
                    }
                    return {
                        optType: new URLSearchParams(location.search).get('type') ? new URLSearchParams(location.search).get('type') : type,
                        pickerOptions: {
                            disabledDate: time => {
                                if (this.activityForm.dateTime[0]) {
                                    return time.getTime() < Date.now() - 8.64e7 || time.getTime() > this.activityForm.dateTime[0];
                                }
                                return time.getTime() < Date.now() - 8.64e7;
                            }
                        },
                        activityForm: {
                            title: '',
                            type: 'seckill',
                            starttime: '',
                            endtime: '',
                            richtext_id: '',
                            richtext_title: '',
                            goods_list: [],
                            goods_ids: '',
                            dateTime: [],
                            rules: {},
                        },
                        activityFormInit: {
                            title: '',
                            type: 'seckill',
                            starttime: '',
                            endtime: '',
                            richtext_id: '',
                            richtext_title: '',
                            goods_list: [],
                            goods_ids: '',
                            dateTime: [],
                            rules: {},
                        },
                        rules: {
                            title: [{ required: true, message: '请输入活动名称', trigger: 'blur' }],
                            type: [{ required: true, message: '请选择活动类型', trigger: 'blur' }],
                            dateTime: [{ required: true, message: '请选择活动时间', trigger: 'blur' }],
                            temp_team_num: [{ required: true, message: '请输入成团人数', trigger: 'change' },
                            { validator: checkTeamNum, trigger: 'change' }],
                            temp_discounts: [{ required: true, validator: checkDiscounts, trigger: 'blur' }],
                            temp_full_num: [{ required: true, message: '请输入消费金额', trigger: 'blur' }],
                            goods_list: [{ required: true, message: '请输入选择商品', trigger: 'blur' }],
                        },
                        isDisabled: false,
                        someIsDisabled: true,
                        selectedGoodsType: 'some',
                        selectedFlag: true,
                        activityTypeList: [{
                            label: '秒杀活动',
                            icon: 'seckill',
                            type: 'seckill',
                            rules: {
                                activity_auto_close: "",
                                limit_buy: "",
                                order_auto_close: "",
                            }
                        }, {
                            label: '拼团活动',
                            icon: 'groupon',
                            type: 'groupon',
                            rules: {
                                activity_auto_close: "",
                                fictitious_num: "",
                                is_alone: "1",
                                is_fictitious: "0",
                                limit_buy: "",
                                order_auto_close: "",
                                team_card: "1",
                                team_num: "2",
                                valid_time: ""
                            }
                        }, {
                            label: '满额立减',
                            icon: 'full_reduce',
                            type: 'full_reduce',
                            rules: {
                                type: 'money',
                                discounts: []
                            }
                        }, {
                            label: '满额折扣',
                            icon: 'full_discount',
                            type: 'full_discount',
                            rules: {
                                type: 'money',
                                discounts: []
                            }
                        }, {
                            label: '满额包邮',
                            icon: 'free_shipping',
                            type: 'free_shipping',
                            rules: {
                                type: 'money',
                                province_except: '',
                                city_except: '',
                                area_except: '',
                                area_text: '',
                                full_num: ''
                            }
                        }],
                    }
                },
                mounted() {
                    this.initData()
                },
                methods: {
                    initData() {
                        if (this.optType == 'view' || this.optType == 'edit') {
                            this.initForm(Config.activity.type)
                            for (key in this.activityForm) {
                                if (Config.activity[key]) {
                                    if (key == 'rules') {
                                        this.activityForm[key] = Config.activity.rule_arr
                                    } else {
                                        this.activityForm[key] = Config.activity[key]
                                    }
                                }
                            };
                            this.tempRule()
                            // 处理活动时间
                            this.activityForm.dateTime = [];
                            this.activityForm.dateTime.push(moment(this.activityForm.starttime * 1000).format("YYYY-MM-DD HH:mm:ss"));
                            this.activityForm.dateTime.push(moment(this.activityForm.endtime * 1000).format("YYYY-MM-DD HH:mm:ss"));
                            // 处理所有商品
                            if (this.activityForm.goods_ids == '') {
                                this.selectedGoodsType = 'all'
                            }
                            if (this.optType == 'view') {
                                this.isDisabled = true
                            } else if (this.optType == 'edit') {
                                if (Config.activity.status == 'end') {
                                    this.isDisabled = true
                                } else if (Config.activity.status == 'ing') {
                                    this.isDisabled = true
                                    this.someIsDisabled = false
                                }
                            }
                        } else {
                            this.initForm()
                        }
                    },
                    initForm(type) {
                        // 选中之后不可点击
                        if (type && this.activityForm.type == type) {
                            return false
                        }
                        this.activityForm = JSON.parse(JSON.stringify(this.activityFormInit))

                        if (type) {
                            this.activityForm.type = type
                        }
                        this.activityTypeList.forEach(a => {
                            if (a.type == this.activityForm.type) {
                                this.activityForm.rules = a.rules
                            }
                        })
                        this.tempRule()
                        this.selectedGoodsType = 'some';
                        if (this.$refs['activityForm']) {
                            this.$refs['activityForm'].clearValidate();//重置form检测
                        }
                    },
                    tempRule() {
                        for (key in this.activityForm.rules) {
                            this.$set(this.activityForm, 'temp_' + key, this.activityForm.rules[key])
                        }
                    },
                    richtextSelect() {
                        let that = this;
                        Fast.api.open("shopro/richtext/select", "选择活动说明", {
                            callback: function (data) {
                                that.activityForm.richtext_id = data.data.id;
                                that.activityForm.richtext_title = data.data.title
                            }
                        });
                    },
                    goodsSelect() {
                        let that = this;
                        let selectedGoodsList = that.activityForm.goods_list ? that.activityForm.goods_list : [];
                        let idsArr = []
                        selectedGoodsList.forEach(i => {
                            idsArr.push(i.id)
                        })
                        parent.Fast.api.open("shopro/goods/goods/select?multiple=true&type=activity&ids=" + idsArr.join(','), "选择商品", {
                            callback: function (data) {
                                let resData = []
                                let goodsList = []
                                if (Array.isArray(data.data)) {
                                    resData = data.data
                                } else {
                                    resData.push(data.data)
                                }
                                resData.forEach(e => {
                                    if (idsArr.includes(e.id)) {
                                        selectedGoodsList.forEach(i => {
                                            if (e.id == i.id) {
                                                goodsList.push(i)
                                            }
                                        })
                                    } else {
                                        goodsList.push(JSON.parse(JSON.stringify({
                                            actSkuPrice: "",
                                            dispatch_type_text: e.dispatch_type_text,
                                            id: e.id,
                                            image: e.image,
                                            opt: 0,
                                            status_text: e.status_text,
                                            title: e.title,
                                            type_text: e.type_text,
                                        })))
                                    }
                                })
                                that.activityForm.goods_list = goodsList;
                            }
                        });
                    },
                    goodsDelete(index) {
                        this.activityForm.goods_list.splice(index, 1)
                    },
                    activitySku(id, index, actSkuPrice) {
                        let that = this;
                        let activity_id = Config.activity ? Config.activity.id : ''
                        let activity_status = Config.activity ? Config.activity.status : ''
                        let activity_type = Config.activity ? Config.activity.type : that.activityForm.type
                        // activity_status actSkuPrice自用
                        parent.Fast.api.open(`shopro/activity/activity/sku?activity_id=${activity_id}&id=${id}&type=${that.optType}&activitytime=${that.activityForm.dateTime.join(' - ')}&activity_type=${activity_type}&activity_status=${activity_status}&actSkuPrice=${actSkuPrice}`, "设置活动商品", {
                            callback: function (data) {
                                that.$set(that.activityForm.goods_list[index], "opt", 1)
                                that.$set(that.activityForm.goods_list[index], "actSkuPrice", data)
                            }
                        });
                    },
                    discountsAdd() {
                        this.activityForm.temp_discounts.push({ full: '', discount: '' })
                    },
                    discountsDelete(index) {
                        this.activityForm.temp_discounts.splice(index, 1)
                    },
                    areaSelect() {
                        let that = this;
                        let parmas = {
                            name: that.activityForm.temp_area_text,
                            province_ids: that.activityForm.temp_province_except,
                            city_ids: that.activityForm.temp_city_except,
                            area_ids: that.activityForm.temp_area_except,
                        }
                        Fast.api.open('shopro/area/select?parmas=' + encodeURI(JSON.stringify(parmas)), '区域选择', {
                            callback(data) {
                                that.activityForm.temp_area_text = data.data.name.join(',');
                                that.activityForm.temp_province_except = data.data.province.join(',')
                                that.activityForm.temp_city_except = data.data.city.join(',')
                                that.activityForm.temp_area_except = data.data.area.join(',')
                            }
                        })
                    },
                    changeGoodsType(val) {
                        this.activityForm.goods_list = []
                        if (val == 'all') {
                            this.activityForm.goods_list = [{}]
                        }
                        this.activityForm.goods_ids = ''
                    },
                    changeFreeShippingType() {
                        this.activityForm.temp_full_num = ''
                    },
                    submitForm(activityForm) {
                        let that = this;
                        let submitForm = JSON.parse(JSON.stringify(that.activityForm))
                        that.$refs[activityForm].validate((valid) => {
                            if (valid) {
                                if (this.activityForm.type == 'full_reduce' || this.activityForm.type == 'full_discount') {
                                    let isSub = true
                                    this.activityForm.temp_discounts.forEach(d => {
                                        if (Number(d.full) <= 0 || Number(d.discount) <= 0) {
                                            isSub = false
                                        }
                                        if(this.activityForm.type == 'full_reduce' && submitForm.temp_type=='money'){
                                            if(Number(d.full) < Number(d.discount)){
                                                isSub = false
                                            }
                                        }
                                        if(this.activityForm.type == 'full_discount'){
                                            if(0> Number(d.discount) || Number(d.discount)>10){
                                                isSub = false
                                            }
                                        }
                                    })
                                    if (!isSub) {
                                        this.$message({
                                            message: '优惠条件填写有误、优惠金额不可大于消费金额或折扣不可小于0大于10',
                                            type: 'warning'
                                        });
                                        return false
                                    }
                                }
                                // 处理时间
                                submitForm.starttime = submitForm.dateTime[0];
                                submitForm.endtime = submitForm.dateTime[1];
                                delete submitForm.dateTime
                                // 处理goods_ids
                                let goods_ids = []
                                submitForm.goods_list.forEach(i => {
                                    if (i.id) {
                                        goods_ids.push(i.id)
                                    }
                                })
                                if (goods_ids.length > 0) {
                                    submitForm.goods_ids = goods_ids.join(',')
                                } else {
                                    submitForm.goods_ids = ''
                                    submitForm.goods_list = []
                                }
                                // 处理rules
                                for (key in submitForm.rules) {
                                    submitForm.rules[key] = submitForm['temp_' + key]
                                    delete submitForm['temp_' + key]
                                }
                                let reqUrl = that.optType == 'add' ? 'shopro/activity/activity/add' : `shopro/activity/activity/edit/ids/${Config.activity.id}`
                                // return false
                                Fast.api.ajax({
                                    url: reqUrl,
                                    loading: true,
                                    type: 'POST',
                                    data: JSON.parse(JSON.stringify(submitForm))
                                }, function (ret, res) {
                                    if (res.code == 1) {
                                        Fast.api.close()
                                    }
                                })
                            } else {
                                this.selectedFlag = false
                                return false;
                            }
                        });
                    },
                },
            })
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
        select: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/activity/activity/index?page_type=select&type=' + Backend.api.query('type'),
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'title', title: __('Title') },
                        { field: 'goods_ids', title: __('Goods_ids') },
                        { field: 'type', title: __('Type') },
                        // { field: 'description', title: __('Description') },
                        { field: 'starttime', title: __('Starttime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime },
                        { field: 'endtime', title: __('Endtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime },
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime },
                        { field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime },
                        {
                            field: 'operate', title: __('Operate'), events: {
                                'click .btn-chooseone': function (e, value, row, index) {
                                    var multiple = Backend.api.query('multiple');
                                    multiple = multiple == 'true' ? true : false;
                                    Fast.api.close({ data: row, multiple: multiple });
                                },
                            }, formatter: function () {
                                return '<a href="javascript:;" class="btn btn-danger btn-chooseone btn-xs"><i class="fa fa-check"></i> ' + __('Choose') + '</a>';
                            }
                        }
                    ]
                ]
            });

            // 选中多个
            $(document).on("click", ".btn-choose-multi", function () {
                var couponsArr = new Array();
                $.each(table.bootstrapTable("getAllSelections"), function (i, j) {
                    couponsArr.push(j.id);
                });
                var multiple = Backend.api.query('multiple');
                multiple = multiple == 'true' ? true : false;
                let row = {}
                row.ids = couponsArr.join(",")
                Fast.api.close({ data: row, multiple: multiple });
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            require(['upload'], function (Upload) {
                Upload.api.plupload($("#toolbar .plupload"), function () {
                    $(".btn-refresh").trigger("click");
                });
            });

        },
        checkRedis: function () {
            if (!Config.hasRedis) {
                Layer.confirm('检测到系统未配置 Redis，添加的活动将不能得到有效控制，确定要继续吗？', {
                    btn: ['确认', '取消']
                }, function () {
                    Layer.closeAll();
                    return true;
                }, function () {
                    Layer.closeAll();
                    if (window.parent) {
                        window.parent.Layer.closeAll();
                    }
                    return false;
                });
            }
        }
    };
    return Controller;
});