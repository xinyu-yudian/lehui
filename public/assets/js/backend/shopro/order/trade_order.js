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
                        typeList: {},
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
                            form_2_key: "user_id",
                            form_2_value: "",
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
                            form_2_key: "user_id",
                            form_2_value: "",
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
                            user_id: "=",
                            nickname: "like",
                            user_phone: "like",
                            consignee: "like",
                            phone: "like",

                        },


                        focusi: false,

                    }
                },
                mounted() {
                    this.getData()
                    this.getType()
                },
                methods: {
                    getType() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/order/order/getType',
                        }, function (ret, res) {
                            that.typeList = res.data
                            return false;
                        })
                    },
                    //请求
                    getData(offset, limit) {
                        var that = this;
                        that.offset = offset >= 0 ? offset : that.offset;
                        that.limit = limit || that.limit;
                        that.currentPage = that.offset / that.limit + 1;
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
                        Fast.api.ajax({
                            url: 'shopro/order/trade_order/index',
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
                        this.getData()
                    },
                    handleCurrentChange(val) {
                        this.offset = (val - 1) * this.limit;
                        this.currentPage = 1;
                        this.getData()
                    },
                    //筛选
                    changeSwitch() {
                        this.screenType = !this.screenType;
                    },
                    screenEmpty() {
                        this.searchForm = JSON.parse(JSON.stringify(this.searchFormInit))
                        this.getData(0, 10)
                    },
                    goOrderRefresh() {
                        this.focusi = true;
                        this.getData()
                    },
                },
            })
        },
    };
    return Controller;
});