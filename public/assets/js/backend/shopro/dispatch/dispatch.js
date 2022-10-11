define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var dispatchIndex = new Vue({
                el: "#dispatchIndex",
                data() {
                    return {
                        dispatchData: [],
                        isData: false,
                        activeName: "express",

                        limit: 10,
                        offset: 0,
                        currentPage: 1,
                        totalPage: 0,
                    }
                },
                mounted() {
                    this.getData();
                },
                methods: {
                    getData() {
                        let that = this;
                        that.isData = false;
                        Fast.api.ajax({
                            url: 'shopro/dispatch/' + that.activeName + '/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                limit: that.limit,
                                offset: that.offset,
                            }
                        }, function (ret, res) {
                            that.dispatchData = res.data.rows;
                            if (that.dispatchData.length == 0) {
                                that.isData = true
                            }
                            that.totalPage = res.data.total
                            return false;
                        })
                    },
                    tabClick(tab, event) {
                        this.dispatchData=[];
                        this.isData = false;
                        this.activeName = tab.name;
                        this.limit = 10;
                        this.offset = 0;
                        this.currentPage = 1;
                        this.getData();
                    },
                    operation(opttype, id, idx, type) {
                        let that = this;
                        switch (opttype) {
                            case 'delete':
                                that.$confirm('此操作将删除模板, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/dispatch/' + type + '/del/ids/' + id,
                                        loading: true,
                                        type: 'POST',
                                    }, function (ret, res) {
                                        that.getData();
                                    })
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消删除'
                                    });
                                });
                                break;
                            case 'create':
                                Fast.api.open("shopro/dispatch/" + type + "/add", "创建模板", {
                                    callback(data) {
                                        if (data.data) {
                                            that.getData();
                                        }
                                    }
                                });
                                break;
                            case 'edit':
                                Fast.api.open("shopro/dispatch/" + type + "/edit?ids=" + id, "编辑模板", {
                                    callback(data) {
                                        if (data.data) {
                                            that.getData();
                                        }
                                    }
                                });
                                break;
                            case 'recycle':
                                Fast.api.open('shopro/dispatch/' + type + '/recyclebin', '查看回收站')
                                break;
                        }
                    },
                    pageSizeChange(val) {
                        this.limit = val;
                        this.offset = 0;
                        this.getData();
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * 10,
                            this.limit = 10
                        this.getData();
                    },
                    tableRowClassName({
                        rowIndex
                    }) {
                        if (rowIndex % 2 == 1) {
                            return 'bg-color';
                        }
                        return '';
                    },
                    tableCellClassName({
                        columnIndex
                    }) {
                        if (columnIndex == 0) {
                            return 'cell-left';
                        } else if (columnIndex == 4) {
                            return 'border-right';
                        }
                        return '';
                    },
                    tableCellClassName2({
                        columnIndex
                    }) {
                        if (columnIndex == 1 || columnIndex == 2) {
                            return 'cell-left';
                        } else if (columnIndex == 4) {
                            return 'cell-left border-right';
                        }
                        return '';
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
                url: 'shopro/dispatch/dispatch/recyclebin' + location.search,
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
                                    url: 'shopro/dispatch/dispatch/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/dispatch/dispatch/destroy',
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