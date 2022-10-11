define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
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
            var orderIndex = new Vue({
                el: "#orderIndex",
                data() {
                    return {
                        screenType: false,
                        orderScreenList: {},
                        orderList: [],
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,
                        limit: 10,
                        // form搜索
                        searchForm: {
                            status: "all",
                            createtime: [moment().startOf('day').format('YYYY-MM-DD HH:mm:ss'), moment().endOf('day').format('YYYY-MM-DD HH:mm:ss')],
                            form_1_key: "order_sn",
                            form_1_value: "",
                            platform: "",
                            dispatch_type: "",
                            type: "",
                            pay_type: "",
                            activity_type: "",
                            goods_type: "",
                            goods_title: "",
                            form_2_key: "user_id",
                            form_2_value: "",
                            store_id: 'all'
                        },
                        searchFormInit: {
                            status: "all",
                            createtime: [moment().startOf('day').format('YYYY-MM-DD HH:mm:ss'), moment().endOf('day').format('YYYY-MM-DD HH:mm:ss')],
                            form_1_key: "order_sn",
                            form_1_value: "",
                            platform: "",
                            dispatch_type: "",
                            type: "",
                            pay_type: "",
                            activity_type: "",
                            goods_type: "",
                            goods_title: "",
                            form_2_key: "user_id",
                            form_2_value: "",
                            store_id: 'all'
                        },
                        searchOp: {
                            status: "=",
                            createtime: "range",
                            order_sn: "like",
                            id: "=",
                            aftersale_sn: "=",
                            transaction_id: "=",
                            platform: "=",
                            dispatch_type: "=",
                            type: "=",
                            pay_type: "=",
                            activity_type: "like",
                            goods_type: "=",
                            goods_title: "like",
                            user_id: "=",
                            nickname: "like",
                            user_phone: "like",
                            consignee: "like",
                            phone: "like",

                            store_id: '='
                        },
                        // 发货
                        deliverRow: [],
                        deliverRowTable: [],
                        deliverDialog: false,
                        deliverForm: {
                            item_ids: '',
                            express_name: '',
                            express_code: '',
                            express_no: ''
                        },
                        deliverFormInit: {
                            item_ids: '',
                            express_name: '',
                            express_code: '',
                            express_no: ''
                        },
                        deliverCompany: [],//快递公司分页
                        deliverSearch: '',
                        deliverOffset: 0,
                        deliverLimit: 6,
                        deliverCurrentPage: 1,
                        deliverTotalPage: 0,
                        deliverType: 'input',
                        deliverDisabled: false,

                        totalStatus: '',
                        optRecordDialog: false,
                        optList: [],
                        focusi: false,

                        choice_dialog: false,
                        choice_list: [],
                        choice_order_id: null,
                        choice_id: null,

                        selectStoreList: [],
                        searchWhree: '',
                        store_id_switch: false,
                        pickerOptions: {
                            shortcuts: [{
                                text: '今天',
                                onClick(picker) {
                                    const start = moment(new Date()).format('YYYY-MM-DD') + ' 00:00:00';
                                    const end = moment(new Date()).format('YYYY-MM-DD') + ' 23:59:59';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '昨天',
                                onClick(picker) {
                                    const start = moment(new Date()).add(-1, 'days').format('YYYY-MM-DD') + ' 00:00:00';
                                    const end = moment(new Date()).add(-1, 'days').format('YYYY-MM-DD') + ' 23:59:59';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '最近一周',
                                onClick(picker) {
                                    const end = moment(new Date()).format('YYYY-MM-DD') + ' 23:59:59';
                                    const start = moment(new Date()).add(-7, 'days').format('YYYY-MM-DD') + ' 00:00:00';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '最近一个月',
                                onClick(picker) {
                                    const end = moment(new Date()).format('YYYY-MM-DD') + ' 23:59:59';
                                    const start = moment(new Date()).add(-30, 'days').format('YYYY-MM-DD') + ' 00:00:00';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '上月',
                                onClick(picker) {
                                    const end = moment().month(moment().month() - 1).endOf('month').format('YYYY-MM-DD') + ' 23:59:59';
                                    const start = moment().month(moment().month() - 1).startOf('month').format('YYYY-MM-DD') + ' 00:00:00';
                                    picker.$emit('pick', [start, end]);
                                }
                            }]
                        },
                        // 批量发货
                        multipleSelection: [],
                        batchSendDialogVisible: false,
                        batchSendData: [],
                        batchSendNosend: [],
                        batchSendSuccess: [],
                        batchSendError: [],
                        loopIndex: 0,
                        loopStart: true,
                        batchSendActive: 'all',
                        batchSendType: '',
                        batchSendTypeVisible: false,
                        batchSendButtonType: null,
                        deliverByUploadTemplateForm: {
                            uploadFile: '',
                            express_code: '',
                            express_name: ''
                        }
                    }
                },
                mounted() {
                    if (new URLSearchParams(location.search).get('status')) {
                        this.searchForm.status = new URLSearchParams(location.search).get('status')
                    }
                    if (new URLSearchParams(location.search).get('datetimerange')) {
                        this.searchForm.createtime = new URLSearchParams(location.search).get('datetimerange').split(' - ')
                    }
                    if (new URLSearchParams(location.search).get('store_id')) {
                        this.searchForm.store_id = new URLSearchParams(location.search).get('store_id')
                        this.store_id_switch = true
                    }
                    this.reqOrderList()
                    this.reqOrderScreenList()
                    this.getStoreOptions();
                },
                methods: {
                    // 选择门店
                    changeStoreId(val) {
                        if (val) {
                            this.searchForm.store_id = '0';
                        } else {
                            this.searchForm.store_id = '';
                        }
                    },
                    getStoreOptions() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/store/store/all',
                            loading: false,
                            type: 'GET',
                            data: {
                                search: that.searchWhree,
                            }
                        }, function (ret, res) {
                            that.selectStoreList = res.data;
                            let obj1 = {
                                id: '0',
                                name: '全部门店订单'
                            }
                            that.selectStoreList.unshift(obj1)
                            if (new URLSearchParams(location.search).get('store_id')) {
                                that.screenType = true;
                                that.searchForm.store_id = new URLSearchParams(location.search).get('store_id')
                            }
                            return false;
                        })
                    },
                    storeDebounceFilter: debounce(function () {
                        this.getStoreOptions()
                    }, 1000),
                    dataFilter(val) {
                        this.searchWhree = val;
                        this.storeDebounceFilter()
                    },
                    reqOrderScreenList() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/order/order/getType',
                            loading: true,
                            type: 'GET',
                            data: {}
                        }, function (ret, res) {
                            that.orderScreenList = res.data
                            return false;
                        })
                    },
                    //请求
                    reqOrderList(offset, limit) {
                        var that = this;
                        if (offset == 0 && limit == 10) {
                            that.offset = offset;
                            that.limit = limit;
                        }
                        let filter = {}
                        let op = {}
                        for (key in that.searchForm) {
                            if (key != 'status' && key != 'createtime' && key != 'form_1_key' && key != 'form_1_value' && key != 'form_2_key' && key != 'form_2_value') {
                                if (that.searchForm[key] != '' && that.searchForm[key] != 'all') {
                                    filter[key] = that.searchForm[key];
                                }
                            } else if (key == 'form_1_value') {
                                if (that.searchForm[key] != '') {
                                    filter[that.searchForm.form_1_key] = that.searchForm[key];
                                }
                            } else if (key == 'form_2_value') {
                                if (that.searchForm[key] != '') {
                                    filter[that.searchForm.form_2_key] = that.searchForm[key];
                                }
                            } else if (key == 'createtime') {
                                if (that.searchForm[key]) {
                                    if (that.searchForm[key].length > 0) {
                                        filter[key] = that.searchForm[key].join(' - ');
                                    }
                                }
                            } else if (key == 'status') {
                                if (that.searchForm[key] != '' && that.searchForm[key] != 'all') {
                                    filter[key] = that.searchForm[key];
                                }
                            }
                        }
                        for (key in filter) {
                            op[key] = that.searchOp[key]
                        }
                        // return false;
                        Fast.api.ajax({
                            url: 'shopro/order/order/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                filter: JSON.stringify(filter),
                                op: JSON.stringify(op),
                                offset: that.offset,
                                limit: that.limit,
                            }
                        }, function (ret, res) {
                            that.orderList = res.data.rows;
                            that.totalPage = res.data.total;
                            that.focusi = false;
                            return false;
                        })
                    },
                    handleSizeChange(val) {
                        this.offset = 0
                        this.limit = val
                        this.currentPage = 1;
                        this.reqOrderList()
                    },
                    handleCurrentChange(val) {
                        this.offset = (val - 1) * this.limit;
                        this.currentPage = 1;
                        this.reqOrderList()
                    },
                    //筛选
                    changeSwitch() {
                        this.screenType = !this.screenType;
                    },
                    screenEmpty() {
                        this.searchForm = JSON.parse(JSON.stringify(this.searchFormInit))
                    },
                    goOrderRefresh() {
                        this.focusi = true;
                        this.reqOrderList()
                    },
                    //导出 导出发货单
                    goExport(type = 'export') {
                        var that = this;
                        let filter = {}
                        let op = {}
                        for (key in that.searchForm) {
                            if (key != 'status' && key != 'createtime' && key != 'form_1_key' && key != 'form_1_value' && key != 'form_2_key' && key != 'form_2_value') {
                                if (that.searchForm[key] != '' && that.searchForm[key] != 'all') {
                                    filter[key] = that.searchForm[key];
                                }
                            } else if (key == 'form_1_value') {
                                if (that.searchForm[key] != '') {
                                    filter[that.searchForm.form_1_key] = that.searchForm[key];
                                }
                            } else if (key == 'form_2_value') {
                                if (that.searchForm[key] != '') {
                                    filter[that.searchForm.form_2_key] = that.searchForm[key];
                                }
                            } else if (key == 'createtime') {
                                if (that.searchForm[key]) {
                                    if (that.searchForm[key].length > 0) {
                                        filter[key] = that.searchForm[key].join(' - ');
                                    }
                                }
                            } else if (key == 'status' || key == 'store_id') {
                                if (that.searchForm[key] != '' && that.searchForm[key] != 'all') {
                                    filter[key] = that.searchForm[key];
                                }
                            }
                        }
                        for (key in filter) {
                            op[key] = that.searchOp[key]
                        }
                        window.location.href = Config.moduleurl + "/shopro/order/order/" + type + "?filter=" + JSON.stringify(filter) + "&op=" + JSON.stringify(op);
                    },
                    viewAftersale(aftersale_id) {
                        let that = this;
                        Fast.api.open("shopro/order/aftersale/detail?id=" + aftersale_id, "售后详情", {
                            callback() {
                                that.reqOrderList()
                            }
                        });
                    },

                    printOrder(sn){
                        if(confirm("请再次确认订单信息是否无误!\n\n确认打印小票吗？"))
                        {     
                            let that = this;
                            Fast.api.ajax({
                                url: `shopro/order/order/printOrder/sn/${sn}`,
                                loading: true,
                                data: {}
                            }, function (ret, res) {
                                
                                if(res.code == 1){
                                    that.$message({
                                        message: "打印成功",
                                        type: 'info'
                                    });
                                } else {
                                    that.$message({
                                        message: res.msg,
                                        type: 'warning'
                                    });
                                }
                                return false;
                            })

                        }else{
                              return false;
                        }
                    },

                   
                    goDetail(id) {
                        let that = this;
                        Fast.api.open('shopro/order/order/detail?id=' + id, "查看详情", {
                            callback() {
                                that.reqOrderList()
                            }
                        });
                    },
                    goGroupon(type, id) {
                        let that = this;
                        if (id == 0) {
                            return false;
                        }
                        if (type == "groupon") {
                            parent.Fast.api.open(`shopro/activity/groupon/detail/id/${id}`, "查看详情", {
                                callback(data) {
                                    that.reqOrderList()
                                }
                            });
                        }
                    },
                    goOrderUser(id) {
                        let that = this;
                        Fast.api.open('shopro/user/user/profile?id=' + id, '查看下单用户', {
                            callback() {
                                that.reqOrderList();
                            }
                        })
                    },
                    optRecord(id) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/order/actions/id/${id}`,
                            loading: true,
                            data: {}
                        }, function (ret, res) {
                            that.optList = res.data;
                            that.optRecordDialog = true
                            return false;
                        })
                    },
                    // 快递公司
                    async getDeliverCompany() {
                        let res = await Controller.orderApi.deliverCompany(this.deliverSearch, this.deliverLimit, this.deliverOffset)
                        this.deliverCompany = res.data.rows;
                        this.deliverTotalPage = res.data.total;
                    },
                    deliverCurrentChange(val) {
                        this.deliverOffset = (val - 1) * 6;
                        this.deliverLimit = 6;
                        this.deliverCurrentPage = 1;
                        this.getDeliverCompany();
                    },
                    deliverDebounceFilter: debounce(function () {
                        this.getDeliverCompany()
                    }, 1000),
                    deliverFilter(val) {
                        this.deliverSearch = val;
                        this.deliverOffset = 0;
                        this.deliverLimit = 6;
                        this.deliverCurrentPage = 1;
                        this.deliverDebounceFilter();
                    },
                    // 发货
                    changeExpressName(e) {
                        this.deliverForm.express_name = this.$refs[e][0].dataset.name
                    },
                    openDeliverDialog(row, index) {
                        this.deliverRow = row
                        this.deliverRowTable = []
                        this.deliverRowTable.push(row.item[index])
                        this.deliverDialog = true
                        this.getDeliverCompany()
                    },
                    deliverSelectionChange(val) {
                        let item_ids = []
                        val.forEach(i => {
                            item_ids.push(i.id)
                        })
                        this.deliverForm.item_ids = item_ids.join(',')
                    },
                    async reqDeliver() {
                        var that = this;
                        let res = null
                        if (that.deliverType == 'input') {
                            res = await Controller.orderApi.deliverByInput(that.deliverRow.id, that.deliverForm.item_ids, null, that.deliverForm)
                        } else {
                            res = await Controller.orderApi.deliverByApi(that.deliverRow.id, that.deliverForm.item_ids, null)
                        }
                        that.reqOrderList();
                        that.deliverDialog = false
                        that.deliverForm = JSON.parse(JSON.stringify(that.deliverFormInit));
                    },
                    closeDeliverDialog() {
                        this.deliverDialog = false;
                        this.deliverForm = JSON.parse(JSON.stringify(this.deliverFormInit));
                    },
                    //备货
                    openStockDialog(row, index) {
                        this.choice_order_id = row.id;
                        this.choice_list = []
                        this.choice_list.push(row.item[index]);
                        this.choice_id = row.item[index].id
                        this.choice_dialog = true;
                    },
                    async reqSendStore() {
                        await Controller.orderApi.sendStore(this.choice_order_id, this.choice_id)
                        this.reqOrderList();
                        this.choice_dialog = false;
                    },
                    closeStockDialog() {
                        this.choice_dialog = false;
                    },
                    deliverByUploadTemplate() {

                    },
                    // 批量发货
                    handleSelectionChange(val) {
                        this.multipleSelection = val
                    },
                    openBatchSendTypeDialog() {
                        this.batchSendTypeVisible = true
                        this.deliverSearch = '';
                        this.deliverOffset = 0;
                        this.deliverLimit = 6;
                        this.deliverCurrentPage = 1;
                        this.deliverTotalPage = 0;
                        this.getDeliverCompany();
                    },
                    batchSendInit() {
                        this.batchSendTypeVisible = false
                        this.batchSendSuccess = [];
                        this.batchSendError = [];
                        this.batchSendData = [];
                        this.batchSendData = JSON.parse(JSON.stringify(this.multipleSelection));
                        this.batchSendData.forEach(b => {
                            b.batchsend_code = -1
                        })
                        this.batchSendNosend = this.batchSendData;
                        if (this.batchSendData.length > 0) {
                            this.batchSendDialogVisible = true
                            this.loopIndex = 0
                        } else {
                            this.$message({
                                message: '请选择批量发货商品',
                                type: 'warning'
                            });
                        }
                    },
                    reqBatchSend(type) {
                        let that = this;
                        let order_id = this.batchSendData[this.loopIndex].id
                        // 重新发货、过滤掉已成功和未发货的
                        if (type == 'again') {
                            if (this.batchSendData[this.loopIndex].batchsend_code == -1 || this.batchSendData[this.loopIndex].batchsend_code == 1) {
                                that.loopIndex++;
                                if (that.loopIndex < that.batchSendData.length && that.loopStart) {
                                    that.reqBatchSend(type)
                                }
                                return false
                            }
                        }
                        Fast.api.ajax({
                            url: 'shopro/order/order/deliverByApi',
                            loading: false,
                            type: 'POST',
                            data: {
                                id: order_id,
                                item_id: 0,
                                send_type: 'api'
                            }
                        }, function (ret, res) {
                            that.$set(that.batchSendData[that.loopIndex], 'batchsend_status', res.msg)
                            that.$set(that.batchSendData[that.loopIndex], 'batchsend_code', res.code)
                            if (res.data.order) {
                                that.$set(that.batchSendData[that.loopIndex], 'batchsend_time', res.data.order.ext_arr.send_time)
                            }
                            if (res.code == 0) {
                                that.batchSendError.push(that.batchSendData[that.loopIndex])
                            } else if (res.code === 1) {
                                that.batchSendSuccess.push(that.batchSendData[that.loopIndex])
                            }
                            that.loopIndex++;
                            // 开始发货 处理未发货
                            if (type == 'once') {
                                let noIndex = that.loopIndex
                                that.batchSendNosend = that.batchSendData.slice(noIndex)
                            }
                            if (that.loopIndex < that.batchSendData.length && that.loopStart) {
                                that.reqBatchSend(type)
                            } else {
                                if (that.loopIndex >= that.batchSendData.length) {
                                    that.reqOrderList()
                                }
                            }
                            return false
                        }, function (ret, res) {
                            that.$set(that.batchSendData[that.loopIndex], 'batchsend_status', res.msg)
                            that.$set(that.batchSendData[that.loopIndex], 'batchsend_code', res.code)
                            if (res.data.order) {
                                that.$set(that.batchSendData[that.loopIndex], 'batchsend_time', res.data.order.ext_arr.send_time)
                            }
                            if (res.code == 0) {
                                that.batchSendError.push(that.batchSendData[that.loopIndex])
                            } else if (res.code === 1) {
                                that.batchSendSuccess.push(that.batchSendData[that.loopIndex])
                            }
                            that.loopIndex++;
                            // 开始发货 处理未发货
                            if (type == 'once') {
                                let noIndex = that.loopIndex
                                that.batchSendNosend = that.batchSendData.slice(noIndex)
                            }
                            if (that.loopIndex < that.batchSendData.length && that.loopStart) {
                                that.reqBatchSend(type)
                            } else {
                                if (that.loopIndex >= that.batchSendData.length) {
                                    that.batchSendButtonType = 'start'
                                    that.reqOrderList()
                                }
                            }
                            return false
                        })
                    },
                    openBatchSend(type) {
                        if (type == 'start') {
                            this.loopStart = true
                            this.reqBatchSend('once')
                            this.batchSendButtonType = 'suspend'
                        } else if (type == 'continue') {
                            this.loopStart = true
                            this.loopIndex = this.batchSendData.length - this.batchSendNosend.length
                            this.reqBatchSend('once')
                            this.batchSendButtonType = 'suspend'
                        } else if (type == 'suspend') {
                            this.batchSendButtonType = 'continue'
                            this.loopStart = false
                        }
                    },
                    closeBatchSend() {
                        this.batchSendDialogVisible = false
                        this.loopStart = false
                        this.reqOrderList()
                        this.batchSendData = [];
                        this.batchSendSuccess = [];
                        this.batchSendError = [];
                        this.batchSendNosend = [];
                        this.batchSendButtonType = null
                    },
                    againBatchSend() {
                        this.loopStart = true
                        this.batchSendError = [];
                        this.loopIndex = 0
                        this.reqBatchSend('again')
                    },
                    // 单独重新发货
                    aloneAgainBatchSend(row) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/order/order/deliverByApi',
                            loading: false,
                            type: 'POST',
                            data: {
                                id: row.id,
                                item_id: 0,
                                send_type: 'api'
                            }
                        }, function (ret, res) {
                            that.$set(row, 'batchsend_status', res.msg)
                            that.$set(row, 'batchsend_code', res.code)
                            if (res.data.order) {
                                that.$set(row, 'batchsend_time', res.data.order.ext_arr.send_time)
                            }
                            if (res.code === 1) {
                                that.batchSendData.forEach(d => {
                                    if (d.id == row.id) {
                                        d = row
                                    }
                                })
                                that.batchSendSuccess.push(row)
                                let errorIndex = null
                                that.batchSendError.forEach((e, eindex) => {
                                    if (e.id == row.id) {
                                        errorIndex = eindex
                                    }
                                })
                                that.batchSendError.splice(errorIndex, 1)
                            }
                            return false
                        }, function (ret, res) {
                            that.$set(row, 'batchsend_status', res.msg)
                            that.$set(row, 'batchsend_code', res.code)
                            if (res.data.order) {
                                that.$set(row, 'batchsend_time', res.data.order.ext_arr.send_time)
                            }
                            that.batchSendError.forEach((e, eindex) => {
                                if (e.id == row.id) {
                                    e = row
                                }
                            })
                        })
                    },
                    // 模板发货
                    uploadTemplate() {
                        let that = this;
                        Fast.api.open("general/attachment/select?multiple=false", "选择", {
                            callback: function (data) {
                                that.deliverByUploadTemplateForm.uploadFile = data.url
                            }
                        });
                    },
                    deliverByUploadTemplate() {
                        let that = this;
                        if (this.deliverByUploadTemplateForm.uploadFile == '') {
                            this.$message({
                                message: '请先导入模板',
                                type: 'warning'
                            });
                            return false
                        }
                        if (this.deliverByUploadTemplateForm.express_name == '') {
                            this.$message({
                                message: '请先选择物流公司',
                                type: 'warning'
                            });
                            return false
                        }
                        Fast.api.ajax({
                            url: 'shopro/order/order/deliverByUploadTemplate',
                            loading: false,
                            type: 'POST',
                            data: {
                                file: that.deliverByUploadTemplateForm.uploadFile,
                                express_code: that.deliverByUploadTemplateForm.express_code,
                                express_name: that.deliverByUploadTemplateForm.express_name
                            }
                        }, function (ret, res) {
                            that.batchSendTypeVisible = false
                            that.deliverByUploadTemplateForm.express_code = ''
                            that.deliverByUploadTemplateForm.express_name = ''
                            that.deliverByUploadTemplateForm.uploadFile = ''
                            that.reqOrderList()
                        })
                    },
                    deliverByUploadTemplateName(e) {
                        this.deliverByUploadTemplateForm.express_name = this.$refs[e + 'template'][0].dataset.name
                    },
                    closeBatchSendType() {
                        this.deliverByUploadTemplateForm.express_code = ''
                        this.deliverByUploadTemplateForm.express_name = ''
                        this.deliverByUploadTemplateForm.uploadFile = ''
                        this.batchSendTypeVisible = false
                        this.deliverSearch = '';
                        this.deliverOffset = 0;
                        this.deliverLimit = 6;
                        this.deliverCurrentPage = 1;
                        this.deliverTotalPage = 0;
                    },
                    openConfigServices() {
                        Fast.api.open("shopro/config/platform?type=services&tab=basic&title=第三方服务", '第三方服务');
                    }
                },
                watch: {
                    deliverForm: {
                        handler: function (newVal) {
                            if (this.deliverType == 'input') {
                                if (newVal.item_ids && newVal.express_name && newVal.express_no) {
                                    this.deliverDisabled = true
                                } else {
                                    this.deliverDisabled = false
                                }
                            } else {
                                this.deliverDisabled = newVal.item_ids ? true : false
                            }
                        },
                        deep: true
                    },
                    deliverType() {
                        if (this.deliverType == 'input') {
                            if (this.deliverForm.item_ids && this.deliverForm.express_name && this.deliverForm.express_no) {
                                this.deliverDisabled = true
                            } else {
                                this.deliverDisabled = false
                            }
                        } else {
                            this.deliverDisabled = this.deliverForm.item_ids ? true : false
                        }
                    }
                }
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
                url: 'shopro/order/order/recyclebin' + location.search,
                pk: 'type',
                sortName: 'type',
                columns: [
                    [{
                        checkbox: true
                    },
                    {
                        field: 'type',
                        title: __('type')
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
                        wtypeth: '130px',
                        title: __('Operate'),
                        table: table,
                        events: Table.api.events.operate,
                        buttons: [{
                            name: 'Restore',
                            text: __('Restore'),
                            classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                            icon: 'fa fa-rotate-left',
                            url: 'shopro/order/order/restore',
                            refresh: true
                        },
                        {
                            name: 'Destroy',
                            text: __('Destroy'),
                            classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                            icon: 'fa fa-times',
                            url: 'shopro/order/order/destroy',
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
        detail: function () {
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
            var vue = new Vue({
                el: "#orderDetail",
                data() {
                    return {
                        orderId: new URLSearchParams(location.search).get('id'),
                        orderDetail: [],
                        orderDetailCopy: [],
                        goodsList: [], //所有商品
                        onlyExpressGoodsList: [], //物流快递
                        noRefundGoodsList: [], //去除退款的
                        noSendGoodsList: [], //未发货
                        stepActive: 1,
                        activeName: 1,
                        deliveryActive: 0,
                        deliverForm: {
                            item_ids: '',
                            express_name: '',
                            express_code: '',
                            express_no: ''
                        },
                        deliverFormInit: {
                            item_ids: '',
                            express_name: '',
                            express_code: '',
                            express_no: ''
                        },
                        expressCompany: [],
                        express_id: null, //包裹id
                        isEditAddress: false,
                        disabledBtn: false,
                        express_dialog: false,
                        // 退款
                        refund_money: '',
                        refund_dialog: false,
                        refund_orderId: '',
                        refund_itemId: '',
                        addMemoFlag: false,
                        receivingAddress: [],
                        receivingAddressName: [],
                        areaList: [],

                        packageList: [],

                        //快递公司分页
                        selectSearchKey: '',
                        selectOffset: 0,
                        selectLimit: 6,
                        selectCurrentPage: 1,
                        selectTotalPage: 0,
                        // 备货
                        sendStoreDialog: false,
                        sendStoreList: [],
                        sendStoreIds: '',
                        // 核销
                        verifiesDialogVisible: false,
                        verifiesList: [],
                        lidongtony: {
                            name: "中通快递",
                            code: "ZTO"
                        },

                        orderSendType: 'input',
                        invoice: [],

                        change_msg: "",
                        total_fee: "",
                        changeMsgDialog: false
                    }
                },
                mounted() {
                    this.getDetail();
                },
                methods: {
                    getDetail() {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/order/detail?id=${that.orderId}`,
                            loading: true,
                            data: {}
                        }, function (ret, res) {
                            let filteractivity_discount_infos = []
                            if (res.data.order.ext_arr && res.data.order.ext_arr.activity_discount_infos) {
                                res.data.order.ext_arr.activity_discount_infos.forEach(a => {
                                    if (a.activity_type != 'free_shipping') {
                                        filteractivity_discount_infos.push(a)
                                    }
                                })
                            }
                            res.data.order.ext_arr.activity_discount_infos = filteractivity_discount_infos;
                            that.orderDetail = res.data.order;
                            that.orderDetailCopy = JSON.parse(JSON.stringify(res.data.order));
                            that.goodsList = res.data.item;
                            //快递发货商品
                            let onlyExpress = []
                            res.data.item.forEach(i => {
                                if (i.dispatch_type == 'express') {
                                    onlyExpress.push(i)
                                }
                            })
                            that.onlyExpressGoodsList = onlyExpress;
                            //快递发货没有退货商品
                            let noRefund = []
                            onlyExpress.forEach(i => {
                                if (i.refund_status < 2) {
                                    noRefund.push(i);
                                }
                            })
                            that.noRefundGoodsList = noRefund;

                            that.packageList = res.data.express;
                            that.changeSteps();
                            that.getNoSendGoodsList();
                            that.receivingAddress.push(that.orderDetail.province_id);
                            that.receivingAddress.push(that.orderDetail.city_id);
                            that.receivingAddress.push(that.orderDetail.area_id);
                            that.receivingAddressName.push(that.orderDetail.province_name);
                            that.receivingAddressName.push(that.orderDetail.city_name);
                            that.receivingAddressName.push(that.orderDetail.area_name);

                            that.getInvoice()
                            return false;
                        })
                    },
                    getNoSendGoodsList() {
                        let noSend = []
                        this.noRefundGoodsList.forEach(i => {
                            if (i.dispatch_status == 0) {
                                noSend.push(i)
                            }
                        })
                        this.noSendGoodsList = noSend;
                    },
                    changeSteps() {
                        let that = this;
                        let status_code = that.orderDetail.status_code
                        if (that.orderDetail.status == 0) {
                            that.stepActive = 1
                        } else if (that.orderDetail.status == 1) {
                            that.stepActive = 2;
                            switch (status_code) {
                                case 'nosend':
                                    that.stepActive = 2
                                    break;
                                case 'noget':
                                    that.stepActive = 3
                                    break;
                                case 'nocomment':
                                    that.stepActive = 4
                                    break;
                                case 'commented':
                                    that.stepActive = 4
                                    break;
                            }
                        } else if (that.orderDetail.status == 2) {
                            that.stepActive = 4;
                        }
                    },
                    copyMsg() {
                        let that = this;
                        let clipboard = new Clipboard('.copy-msg')
                        let copynum = 0
                        clipboard.on('success', function () {
                            that.$message({
                                message: '复制成功',
                                type: 'success'
                            });
                            copynum++;
                            if (copynum >= 1) {
                                clipboard.destroy();
                                clipboard = new Clipboard('.copy-msg');
                            };
                        });
                        clipboard.on('error', function () {
                            that.$message.error('复制失败');
                        });
                    },
                    getAreaList() {
                        var that = this;
                        Fast.api.ajax({
                            url: `shopro/area/select`,
                            loading: false,
                            type: 'GET',
                        }, function (ret, res) {
                            that.areaList = res.data;
                            that.isEditAddress = true;
                            return false;
                        })
                    },
                    reqEditConsignee() {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/order/editConsignee/id/${that.orderDetail.id}`,
                            loading: true,
                            data: {
                                consignee: that.orderDetail.consignee,
                                phone: that.orderDetail.phone,
                                province_id: that.receivingAddress[0],
                                province_name: that.receivingAddressName[0],
                                city_id: that.receivingAddress[1],
                                city_name: that.receivingAddressName[1],
                                area_id: that.receivingAddress[2],
                                area_name: that.receivingAddressName[2],
                                address: that.orderDetail.address,
                            }
                        }, function (ret, res) {
                            that.orderDetail = res.data
                            that.isEditAddress = false;
                        })
                    },
                    cancelEditConsignee() {
                        this.isEditAddress = false;
                        this.orderDetail = JSON.parse(JSON.stringify(this.orderDetailCopy))
                    },
                    changeAddress(value) {
                        let that = this;
                        that.receivingAddress = value
                        let arr = []
                        that.areaList.forEach((i, index) => {
                            if (i.id == that.receivingAddress[0]) {
                                arr.push(i.label)
                                if (i.children.length > 0) {
                                    i.children.forEach((j, inde) => {
                                        if (j.id == that.receivingAddress[1]) {
                                            arr.push(j.label)
                                            if (j.children.length > 0) {
                                                j.children.forEach(k => {
                                                    if (k.id == that.receivingAddress[2]) {
                                                        arr.push(k.label)
                                                    }
                                                })
                                            }
                                        }
                                    })
                                }
                            }
                        })
                        that.receivingAddressName = arr
                    },
                    goComment(order_item_id) {
                        parent.Fast.api.open("shopro/goods/comment/index?order_item_id=" + order_item_id, "查看评价");
                    },
                    goAftersale(aftersale_id) {
                        Fast.api.open("shopro/order/aftersale/detail?id=" + aftersale_id, "售后详情");
                    },
                    goUserDetail(user_id) {
                        Fast.api.open('shopro/user/user/profile?id=' + user_id, "用户详情");
                    },
                    addMemo(type) {
                        if (type == 'cancel') {
                            this.addMemoFlag = false;
                            this.orderDetail.memo = this.orderDetailCopy.memo;
                        } else {
                            this.addMemoFlag = true;
                        }
                    },
                    reqEditMemo(id) {
                        var that = this;
                        Fast.api.ajax({
                            url: `shopro/order/order/editMemo/id/${id}`,
                            loading: true,
                            data: {
                                memo: that.orderDetail.memo,
                            }
                        }, function (ret, res) {
                            that.orderDetail = res.data;
                            that.addMemoFlag = false
                        })
                    },
                    getExpress(id, type) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/order/getExpress/express_id/${id}`,
                            loading: false,
                            type: 'GET',
                            data: {
                                type: type
                            }
                        }, function (ret, res) {
                            that.getDetail();
                        })
                    },
                    // 快递公司
                    async getDeliverCompanyDetail() {
                        let res = await Controller.orderApi.deliverCompany(this.selectSearchKey, this.selectLimit, this.selectOffset)
                        this.expressCompany = res.data.rows;
                        this.selectTotalPage = res.data.total;
                    },
                    selectDebounceFilter: debounce(function () {
                        this.getDeliverCompanyDetail()
                    }, 1000),
                    selectFilter(val) {
                        this.selectSearchKey = val;
                        this.selectLimit = 6;
                        this.selectOffset = 0;
                        this.selectCurrentPage = 1;
                        this.selectDebounceFilter();
                    },
                    selectCurrentChange(val) {
                        this.selectOffset = (val - 1) * 6;
                        this.selectLimit = 6;
                        this.selectCurrentPage = 1;
                        this.getDeliverCompanyDetail();
                    },
                    // 发货
                    changeExpressName(e) {
                        this.deliverForm.express_name = this.$refs[e][0].dataset.name
                    },
                    openDeliverDialog(deliverId = null) {
                        this.express_dialog = true;
                        this.getDeliverCompanyDetail();
                        if (deliverId) { //修改物流
                            this.express_id = deliverId;
                            let sended = [];
                            this.noRefundGoodsList.forEach(i => {
                                this.packageList[this.activeName - 1].item.forEach(j => {
                                    if (i.id == j.id) {
                                        sended.push(i)
                                    }
                                })
                            })
                            this.deliverForm.express_name = this.packageList[this.activeName - 1].express_name;
                            this.deliverForm.express_no = this.packageList[this.activeName - 1].express_no;
                            this.deliverForm.express_code = this.packageList[this.activeName - 1].express_code;
                            this.$nextTick(() => {
                                this.toggleSelection(sended)
                            })
                        }
                    },
                    toggleSelection(rows) {
                        if (rows) {
                            rows.forEach(row => {
                                this.$refs.multipleTable.toggleRowSelection(row);
                            });
                        } else {
                            this.$refs.multipleTable.clearSelection();
                        }
                    },
                    deliverSelectionChange(val) {
                        let item_ids = []
                        val.forEach(i => {
                            item_ids.push(i.id)
                        })
                        this.deliverForm.item_ids = item_ids.join(',')
                    },
                    async reqDeliver() {
                        var that = this;
                        let res = null;
                        if (that.orderSendType == 'input') {
                            res = await Controller.orderApi.deliverByInput(that.orderDetail.id, that.deliverForm.item_ids, that.express_id, that.deliverForm)
                        } else {
                            res = await Controller.orderApi.deliverByApi(that.orderDetail.id, that.deliverForm.item_ids, that.express_id)
                        }
                        that.getDetail();
                        that.express_dialog = false;
                        that.deliverForm = that.deliverFormInit;
                        if (that.packageList[that.activeName - 1]) {
                            that.activeName = that.packageList.length;
                        }
                        that.express_id = null;
                    },
                    closeDeliverDialog() {
                        this.express_dialog = false;
                        this.deliverForm = this.deliverFormInit;
                        this.express_id = null;
                    },
                    // 备货
                    openSendStoreDialog() {
                        this.sendStoreDialog = true
                        this.sendStoreList = [];
                        this.goodsList.forEach(goods => {
                            if (goods.refund_status == 0 && goods.dispatch_status == 0 && (goods.dispatch_type == "selfetch" || goods.dispatch_type == "store")) {
                                this.sendStoreList.push(goods)
                            }
                        })
                    },
                    sendStoreSelectionChange(val) {
                        let sendStoreIds = [];
                        val.forEach(element => {
                            sendStoreIds.push(element.id)
                        });
                        this.sendStoreIds = sendStoreIds.join(',')
                    },
                    async reqSendStore() {
                        await Controller.orderApi.sendStore(this.orderId, this.sendStoreIds);
                        this.getDetail();
                        this.sendStoreDialog = false;
                    },
                    closeSendStoreDialog() {
                        this.sendStoreDialog = false;
                    },
                    // 退款
                    openRefundDialog(order_id, id) {
                        this.refund_dialog = true
                        this.refund_orderId = order_id
                        this.refund_itemId = id
                    },
                    reqRefund() {
                        let that = this;
                        let reqUrl = `shopro/order/order/refund/id/${that.refund_orderId}` + (that.refund_itemId !== null ? `/item_id/${that.refund_itemId}` : '');
                        Fast.api.ajax({
                            url: reqUrl,
                            loading: true,
                            data: {
                                refund_money: that.refund_money
                            }
                        }, function (ret, res) {
                            that.getDetail();
                            that.refund_dialog = false;
                            that.refund_money = "";
                        })
                    },
                    closeRefundDialog() {
                        this.refund_dialog = false;
                        this.refund_money = "";
                    },
                    // 核销
                    openVerifiesDialog(order_id) {
                        this.verifiesDialogVisible = true;
                        this.verifiesList = order_id.verifies
                    },
                    closeVerifiesDialog() {
                        this.verifiesDialogVisible = false;
                    },
                    subscribe(id, type) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/order/getExpress/express_id/${id}`,
                            loading: false,
                            type: 'GET',
                            data: {
                                type: type
                            }
                        }, function (ret, res) {
                            that.getDetail();
                        })
                    },
                    getInvoice() {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/invoice/index`,
                            loading: false,
                            type: 'POST',
                            data: {
                                filter: JSON.stringify({ order_id: that.orderDetail.id }),
                                op: JSON.stringify({ order_id: "=" }),
                            }
                        }, function (ret, res) {
                            that.invoice = res.data.rows;
                            return false
                        }, function (ret, res) {
                            return false
                        })
                    },
                    confirmInvoice() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/order/invoice/confirm',
                            loading: true,
                            type: 'POST',
                            data: {
                                ids: that.invoice[0].id
                            }
                        }, function (ret, res) {
                            that.getDetail()
                        })
                    },
                    openChangeMsgDialog() {
                        this.changeMsgDialog = true;
                    },
                    changeFee() {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/order/changeFee/id/${that.orderDetail.id}`,
                            loading: true,
                            type: 'POST',
                            data: {
                                total_fee: that.total_fee,
                                change_msg: that.change_msg
                            }
                        }, function (ret, res) {
                            that.getDetail()
                            that.closeChangeMsgDialog()
                        })
                    },
                    closeChangeMsgDialog() {
                        this.changeMsgDialog = false;
                        this.total_fee = "";
                        this.change_msg = ""
                    }
                },
                watch: {
                    deliverForm: {
                        handler: function (newVal) {
                            if (this.orderSendType == 'input') {
                                if (newVal.item_ids && newVal.express_name && newVal.express_no) {
                                    this.disabledBtn = true
                                } else {
                                    this.disabledBtn = false
                                }
                            } else {
                                this.disabledBtn = newVal.item_ids ? true : false
                            }
                        },
                        deep: true
                    },
                    orderSendType() {
                        if (this.orderSendType == 'input') {
                            if (this.deliverForm.item_ids && this.deliverForm.express_name && this.deliverForm.express_no) {
                                this.disabledBtn = true
                            } else {
                                this.disabledBtn = false
                            }
                        } else {
                            this.disabledBtn = this.deliverForm.item_ids ? true : false
                        }
                    }
                }
            })
            Controller.api.bindevent();
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
        },
        orderApi: {
            deliverByInput(orderId, itemId, deliverId, deliverForm) {
                let reqUrl = `shopro/order/order/deliverByInput/id/${orderId}/item_ids/${itemId}` + (deliverId !== null ? `/express_id/${deliverId}` : '');
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: reqUrl,
                        loading: true,
                        data: {
                            express_name: deliverForm.express_name,
                            express_code: deliverForm.express_code,
                            express_no: deliverForm.express_no
                        }
                    }, function (ret, res) {
                        resolve(res);
                        return false
                    }, function (ret, res) {
                        resolve(res);
                    })
                });
            },
            deliverByApi(orderId, itemId, deliverId) {
                let reqUrl = `shopro/order/order/deliverByApi/id/${orderId}/item_ids/${itemId}` + (deliverId !== null ? `/express_id/${deliverId}` : '');
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: reqUrl,
                        loading: true,
                        data: {
                            send_type: 'api'
                        }
                    }, function (ret, res) {
                        resolve(res);
                        return false
                    }, function (ret, res) {
                        resolve(res);
                    })
                });
            },
            deliverCompany(searchWhere, limit, offset) {
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: 'shopro/express/select',
                        loading: false,
                        type: 'GET',
                        data: {
                            searchWhere: searchWhere,
                            limit: limit,
                            offset: offset
                        }
                    }, function (ret, res) {
                        resolve(res);
                        return false
                    }, function (ret, res) {
                        resolve(res);
                    })
                });
            },
            sendStore(orderId, stockId) {
                return new Promise(resolve => {
                    Fast.api.ajax({
                        url: `shopro/order/order/sendStore/id/${orderId}/item_id/${stockId}`,
                        loading: true,
                    }, function (ret, res) {
                        resolve(res);
                    })
                });
            }
        },
    };
    return Controller;
});