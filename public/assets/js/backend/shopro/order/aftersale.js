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
            var aftersaleIndex = new Vue({
                el: "#aftersaleIndex",
                data() {
                    return {
                        statusType: 'all',
                        searchKey: '',

                        pageData: [],
                        //分页
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,
                        limit: 10,

                    }
                },
                mounted() {
                    this.getData();
                },
                methods: {
                    arraySpanMethod({ row, column, rowIndex, columnIndex }) {
                        if (rowIndex >= 0) {
                            if (columnIndex === 0) {
                                return [, 0];
                            } else if (columnIndex === 1) {
                                return [1, 4];
                            }
                        }
                    },
                    getData() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/order/aftersale/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                sort: 'id',
                                order: 'desc',
                                search: that.searchKey,
                                offset: that.offset,
                                limit: that.limit,
                                status: that.statusType,
                            }
                        }, function (ret, res) {
                            that.pageData = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    //分页(更换页面显示条数)
                    pageSizeChange(val) {
                        this.offset = 0
                        this.limit = val
                        this.getData()
                    },
                    //当前是第几页
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * this.limit
                        this.getData()
                    },
                    viewDetail(id) {
                        Fast.api.open('shopro/order/aftersale/detail?id=' + id, "售后详情");

                    },
                    colorFilters(type) {
                        let color = ""
                        switch (type) {
                            case -2:
                                color = '#999'
                                break;
                            case -1:
                                color = '#FF5959'
                                break;
                            case 0:
                                color = '#444'
                                break;
                            case 1:
                                color = '#F8A92B'
                                break;
                            case 2:
                                color = '#7438D5'
                                break;
                        }
                        return color;
                    },
                    debounceFilter: debounce(function () {
                        this.getData()
                    }, 1000),
                },
                watch: {
                    searchKey(newval, val) {
                        if (newval != val) {
                            this.limit = 10;
                            this.offset = 0;
                            this.debounceFilter()
                        }
                    },
                    statusType(newval, val) {
                        if (newval != val) {
                            this.limit = 10;
                            this.offset = 0;
                            this.getData()
                        }
                    },
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
            var vue = new Vue({
                el: "#aftersaleDetail",
                data() {
                    return {
                        detailId: window.location.search.replace("?", '').split("&")[0].split('=')[1],
                        detailData: {},
                        detailLogs: [],
                        orderData: [],
                        stepActive: 1,
                        refundDialog: false,
                        refundType: 'agree',
                        refundMoney: '',
                        refuseReason: '',
                        replyMsg: '',
                        replyImages: [],
                        dialogName: '',
                        dialoglist: {
                            "agree-refund": '同意退款',
                            "reject-refund": '拒绝申请',
                            "reply-msg": '回复买家'
                        }
                    }
                },
                mounted() {
                    this.getDetail();
                },
                methods: {
                    getDetail() {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/order/aftersale/detail/id/${that.detailId}`,
                            loading: true,
                            type: "GET",
                        }, function (ret, res) {
                            that.detailData = res.data.detail;
                            that.detailLogs = res.data.logs;
                            that.orderData = res.data.order
                            if (that.detailData.aftersale_status == -1 || that.detailData.aftersale_status == -2 || that.detailData.aftersale_status == 2) {
                                that.stepActive = 3;
                            } else {
                                that.stepActive = that.detailData.aftersale_status + 1;
                            }
                            return false;
                        })
                    },
                    operationfunc(type, optType) {
                        let that = this
                        switch (optType) {
                            case 'dialog':
                                that.refundDialog = true;
                                that.refundType = type;
                                that.dialogName = that.dialoglist[that.refundType];
                                break;
                            case 'finish-aftersale':
                                Fast.api.ajax({
                                    url: `shopro/order/aftersale/finish/id/${that.detailId}`,
                                    loading: true,
                                }, function (ret, res) {
                                    that.getDetail();
                                })
                                break;
                            default:
                                if (type == 'yes') {
                                    if (that.refundType == 'agree-refund') {
                                        Fast.api.ajax({
                                            url: `shopro/order/aftersale/refund/id/${that.detailId}`,
                                            loading: true,
                                            data: {
                                                refund_money: that.refundMoney
                                            }
                                        }, function (ret, res) {
                                            that.refundDialog = false;
                                            that.refundMoney = "";
                                            that.getDetail();
                                        })
                                    } else if (that.refundType == 'reject-refund') {
                                        Fast.api.ajax({
                                            url: `shopro/order/aftersale/refuse/id/${that.detailId}`,
                                            loading: true,
                                            data: {
                                                refuse_msg: that.refuseReason
                                            }
                                        }, function (ret, res) {
                                            that.refundDialog = false;
                                            that.refuseReason = "";
                                            that.getDetail();
                                        })
                                    } else if (that.refundType == 'reply-msg') {
                                        Fast.api.ajax({
                                            url: `shopro/order/aftersale/addLog/id/${that.detailId}`,
                                            loading: true,
                                            data: {
                                                content: that.replyMsg,
                                                images: that.replyImages.join(',')
                                            }
                                        }, function (ret, res) {
                                            that.refundDialog = false;
                                            that.replyMsg = "";
                                            that.replyImages = []
                                            that.getDetail();
                                        })
                                    }
                                } else {
                                    that.refundDialog = false;
                                    that.refundMoney = "";
                                    that.refuseReason = "";
                                    that.replyMsg = '';
                                    that.replyImages = [];
                                }
                                break;
                        }
                    },
                    finishService(orderId, itemId) {
                        var that = this
                        Fast.api.ajax({
                            url: `shopro/order/order/aftersaleFinish/id/${orderId}/item_id/${itemId}`,
                            loading: true,
                            data: {}
                        }, function (ret, res) {
                            that.orderGoodsList = []
                            res.data.forEach(item => {
                                that.orderGoodsList.push(item)
                            })
                            that.aftersale_statusFlag = false
                            that.orderGoodsList.forEach(i => {
                                if (i.aftersale_status == 1) {
                                    that.aftersale_statusFlag = true
                                }
                            })
                        })
                    },
                    addImg() {
                        let that = this;
                        parent.Fast.api.open("general/attachment/select?multiple=true", "选择图片", {
                            callback: function (data) {
                                let arrUrl = data.url.split(',');
                                that.replyImages = that.replyImages.concat(arrUrl);
                                that.replyImages = that.replyImages.slice(0, 5)
                            }
                        });
                        return false;
                    },
                    delImg(index) {
                        this.replyImages.splice(index, 1);
                    },

                },
                watch: {

                }
            })
            $('.more-ellipsis').each(function (i, obj) {
                var lineHeight = parseInt($(this).css("line-height"));
                var height = parseInt($(this).height());
                if ((height / lineHeight) > 2) {
                    $(this).addClass("more-ellipsis-after")
                    $(this).css("height", "36px");
                } else {
                    $(this).removeClass("more-ellipsis-after");
                }
            });
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
        }
    };
    return Controller;
});