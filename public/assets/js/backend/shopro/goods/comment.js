define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var commentView = new Vue({
                el: "#commentView",
                data() {
                    return {
                        data: [],
                        offset: 0,
                        limit: 10,
                        totalPage: 20,
                        currentPage: 1,

                        selectedData: [],

                        searchForm: {
                            goods_title: "",
                            comment_status: "all"
                        },
                        searchFormInit: {
                            goods_title: "",
                            comment_status: "all"
                        },
                        searchOp: {
                            goods_title: "like",
                            comment_status: "=",
                        },

                        allAjax: false,
                        tableAjax: false
                    }
                },
                created() {
                    this.getData();
                },
                methods: {
                    getData() {
                        let that = this;
                        that.allAjax = true;
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
                            url: 'shopro/goods/comment/index',
                            loading: false,
                            type: 'GET',
                            data: {
                                limit: that.limit,
                                offset: that.offset,
                                filter: JSON.stringify(filter),
                                op: JSON.stringify(op)
                            }
                        }, function (ret, res) {
                            that.data = res.data.rows;
                            that.totalPage = res.data.total;
                            that.allAjax = false;
                            return false;
                        }, function (ret, res) {
                            that.allAjax = false;
                        })
                    },

                    handleSizeChange(val) {
                        this.limit = val;
                        this.getData()
                    },
                    handleCurrentChange(val) {
                        this.currentPage = val;
                        this.offset = (val - 1) * this.limit;
                        this.getData()
                    },
                    handleSelectionChange(row) {
                        this.selectedData = row;
                    },
                    recyclebin() {
                        let that = this;
                        Fast.api.open('shopro/goods/comment/recyclebin', '查看回收站')
                    },
                    editRow(row) {
                        let that = this;
                        Fast.api.open("shopro/goods/comment/edit?ids=" + row.id, '编辑')
                    },
                    deleteRow(id) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/goods/comment/del/ids/${id}`,
                            loading: true,
                        }, function (ret, res) {
                            that.getData();
                            return false;
                        })
                    },
                    batchDelete() {
                        let ids = []
                        this.selectedData.forEach(s => {
                            ids.push(s.id)
                        })
                        this.deleteRow(ids.join(","))
                    },
                    screenEmpty() {
                        this.searchForm = JSON.parse(JSON.stringify(this.searchFormInit))
                        this.screenFilter()
                    },
                    screenFilter() {
                        this.offset = 0;
                        this.getData()
                    },
                    setStatus(type) {
                        let that = this;
                        let ids = []
                        that.selectedData.forEach(s => {
                            ids.push(s.id)
                        })
                        Fast.api.ajax({
                            url: `shopro/goods/comment/setStatus/ids/${ids.join(",")}/status/${type}`,
                            loading: true,
                        }, function (ret, res) {
                            that.getData();
                            return false;
                        })
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
                url: 'shopro/goods/comment/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
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
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'shopro/goods/comment/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/goods/comment/destroy',
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