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
            var feedbackIndex = new Vue({
                el: "#feedbackIndex",
                data() {
                    return {
                        data: [],
                        searchKey: '',
                        offset: 0,
                        limit: 10,
                        totalPage: 0,
                        currentPage: 1,

                    }
                },
                created() {
                    this.getData();
                },
                methods: {
                    getData() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/feedback/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                searchWhere: that.searchKey,
                                offset: that.offset,
                                limit: that.limit,
                            },
                        }, function (ret, res) {
                            that.data = res.data.rows;
                            that.data.forEach(i => {
                                i.images_arr = i.images.split(',').map(i => {
                                    return Fast.api.cdnurl(i)
                                })
                                if(i.user.avatar){
                                    i.user.avatar_arr=i.user.avatar.split(',').map(i=>{
                                        return Fast.api.cdnurl(i)
                                    })
                                }
                            })
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    operation(type, id) {
                        let that = this;
                        switch (type) {
                            case 'edit':
                                Fast.api.open('shopro/feedback/edit?id=' + id, '查看', {
                                    callback(data) {
                                        if (data.data) {
                                            that.getData();
                                        }
                                    }
                                })
                                break;
                            case 'recyclebin':
                                Fast.api.open('shopro/feedback/recyclebin', '查看回收站')
                                break;
                            case 'del':
                                that.$confirm('此操作将删除反馈, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/feedback/del/ids/' + id,
                                        loading: true,
                                        type: 'POST',
                                    }, function (ret, res) {
                                        that.getData();

                                    })
                                    return false;
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消删除'
                                    });
                                });
                                break;
                        }
                    },
                    goUserDetail(id){
                        let that=this;
                        Fast.api.open('shopro/user/user/profile?id=' + id, '查看')
                    },
                    handleSizeChange(val) {
                        this.offset = 0
                        this.limit = val;
                        this.currentPage = 1;
                        this.getData()
                    },
                    handleCurrentChange(val) {
                        this.currentPage = val;
                        this.offset = (val - 1) * this.limit;
                        this.getData()
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
                        if (columnIndex == 1 || columnIndex == 2 || columnIndex == 3 || columnIndex == 10) {
                            return 'cell-left';
                        }
                        return '';
                    },
                    debounceFilter: debounce(function () {
                        this.getData()
                    }, 1000),
                },
                watch: {
                    searchKey(newVal, oldVal) {
                        if (newVal != oldVal) {
                            this.offset = 0;
                            this.limit = 10;
                            this.currentPage = 1;
                            this.debounceFilter();
                        }
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
                url: 'shopro/feedback/recyclebin' + location.search,
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
                                    url: 'shopro/feedback/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/feedback/destroy',
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
        edit: function () {
            var feedbackDetail = new Vue({
                el: "#feedbackDetail",
                data() {
                    return {
                        detailForm: Config.row,
                    }
                },
                created() {
                    if(this.detailForm.images){
                        this.detailForm.images_arr=this.detailForm.images.split(',').map(i => {
                            return Fast.api.cdnurl(i)
                        })
                    }
                },
                methods: {
                    goUserDetail(id){
                        let that=this;
                        Fast.api.open('shopro/user/user/profile?id=' + id, '查看')
                    },
                    submitFrom(type) {
                        let that = this;
                        if (type == 'yes') {
                            Fast.api.ajax({
                                url: 'shopro/feedback/edit?id=' + that.detailForm.id,
                                loading: true,
                                type: 'POST',
                                data: {
                                    data: JSON.stringify({
                                        status: that.detailForm.status,
                                        remark: that.detailForm.remark,
                                    })
                                },
                            }, function (ret, res) {
                                Fast.api.close({
                                    data: true
                                })
                            })
                        } else {
                            Fast.api.close({
                                data: false
                            })
                        }
                    },
                },
            })
        },
        add: function () {
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