define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'toastr'], function ($, undefined, Backend, Table, Form, Toastr) {

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
            var stockIndex = new Vue({
                el: "#stockIndex",
                data() {
                    return {
                        allAjax: true,
                        tableAjax: false,
                        stockList: [],
                        warning_total: 0,
                        over_total: 0,
                        stockDialogVisible: false,
                        stockId: null,
                        stockForm: {
                            stockNum: '',
                        },
                        stockRules: {
                            stockNum: [
                                { required: true, message: '请输入补货数量', trigger: 'blur' },
                            ],
                        },
                        offset: 0,
                        limit: 10,
                        totalPage: 0,
                        currentPage: 1,
                        stock_warning_list: [{
                            value: "all",
                            label: "全部"
                        }, {
                            value: "warning",
                            label: "预警中"
                        }, {
                            value: "over",
                            label: "已售罄"
                        }],
                        searchForm: {
                            goods_title: "",
                            stock_type: "all"
                        },
                        searchFormInit: {
                            goods_title: "",
                            stock_type: "all"
                        },
                        searchOp: {
                            goods_title: "like",
                            stock_type: "=",
                        },
                    }
                },
                created() {
                    this.getListData();
                },
                methods: {
                    getListData() {
                        let that = this;
                        if (!that.allAjax) {
                            that.tableAjax = true;
                        }
                        let filter = {}
                        let op = {}
                        for (key in that.searchForm) {
                            if (that.searchForm[key] != '') {
                                filter[key] = that.searchForm[key];
                            }
                        }
                        for (key in filter) {
                            op[key] = that.searchOp[key]
                        }
                        Fast.api.ajax({
                            url: 'shopro/goods/stock_warning/index',
                            loading: false,
                            type: 'GET',
                            data: {
                                limit: that.limit,
                                offset: that.offset,
                                filter: JSON.stringify(filter),
                                op: JSON.stringify(op)
                            }
                        }, function (ret, res) {
                            that.stockList = res.data.rows
                            that.totalPage = res.data.total;
                            that.warning_total = res.data.warning_total
                            that.over_total = res.data.over_total
                            that.allAjax = false;
                            that.tableAjax = false;
                            return false;
                        }, function (ret, res) {
                            that.allAjax = false;
                            that.tableAjax = false;
                        })
                    },
                    screenEmpty() {
                        this.searchForm = JSON.parse(JSON.stringify(this.searchFormInit))
                        this.offset = 0
                        this.limit = 10
                        this.currentPage = 1
                        this.getListData()
                    },
                    operation(type, id) {
                        switch (type) {
                            case 'recycle':
                                Fast.api.open('shopro/goods/stock_warning/recyclebin', '查看回收站')
                                break;
                            case 'addStock':
                                this.stockDialogVisible = true
                                this.stockId = id
                                break;
                            case 'openGoods':
                                Fast.api.open('shopro/goods/goods/edit/ids/' + id + "?id=" + id + "&type=edit", '商品', {
                                    callback() {
                                        that.getListData();
                                    }
                                })
                                break;

                        }
                    },
                    handleSizeChange(val) {
                        this.offset = 0
                        this.limit = val;
                        this.currentPage = 1;
                        this.getListData()
                    },
                    handleCurrentChange(val) {
                        this.currentPage = val;
                        this.offset = (val - 1) * this.limit;
                        this.getListData()
                    },
                    closeStock(type) {
                        let that = this;
                        if (type == 1) {
                            this.$refs.stock.validate((valid) => {
                                if (valid) {
                                    Fast.api.ajax({
                                        url: `shopro/goods/stock_warning/addStock/ids/${that.stockId}`,
                                        loading: true,
                                        type: 'POST',
                                        data: {
                                            stock: that.stockForm.stockNum
                                        },
                                    }, function (ret, res) {
                                        that.getListData()
                                        that.stockDialogVisible = false
                                    })
                                } else {
                                    return false;
                                }
                            });
                        } else {
                            that.stockDialogVisible = false
                        }
                        that.stockForm.stockNum = ''
                        that.stockId = null
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
                url: 'shopro/goods/stock_warning/recyclebin' + location.search,
                pk: 'id',
                sortName: 'deletetime',
                columns: [
                    [{
                        checkbox: true
                    },
                    {
                        field: 'id',
                        title: __('Id')
                    },
                    {
                        field: 'title',
                        title: __('Title'),
                        align: 'left',
                        formatter: function(value, row){
                            if(row.goods){
                                return `<div class="displsy-flex">
                                            <img class="goods-image" src="${Fast.api.cdnurl(row.goods.image)}">
                                            <div class="ellipsis-item goods-title">
                                                ${row.goods.title}
                                            </div>
                                        </div>`
                            }else{
                                return `${row.goods_id}`
                            }
                        }
                    },
                    {
                        field: 'goods_sku_text',
                        title: '商品规格',
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
                            name: 'Destroy',
                            text: __('Destroy'),
                            classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                            icon: 'fa fa-times',
                            url: 'shopro/goods/stock_warning/destroy',
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
    };
    return Controller;
});