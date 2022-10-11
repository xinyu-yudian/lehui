define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () { },
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
                url: 'shopro/dispatch/store/recyclebin' + location.search,
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
                        field: 'name',
                        title: __('Title'),
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
                            url: 'shopro/dispatch/store/restore',
                            refresh: true
                        },
                        {
                            name: 'Destroy',
                            text: __('Destroy'),
                            classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                            icon: 'fa fa-times',
                            url: 'shopro/dispatch/store/destroy',
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
            Controller.detailInit('add');
        },
        edit: function () {
            Controller.detailInit('edit');
        },
        detailInit: function (type) {
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
            var dispatchDetail = new Vue({
                el: "#dispatchDetail",
                data() {
                    return {
                        optType: type,
                        dispatchForm: {},
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入模板名称',
                                trigger: 'blur'
                            }],
                            store_ids: [{
                                required: true,
                                message: '请选择配送门店',
                                trigger: 'blur'
                            }],
                        },
                        dispatchFormInit: {
                            name: '',
                            store_ids: []
                        },
                        storeOptions: [],
                        dispatch_id: null,

                        limit: 6,
                        offset: 0,
                        currentPage: 1,
                        totalPage: 0,

                        self_user_type: 'all'
                    }
                },
                mounted() {
                    if (this.optType == 'add') {
                        this.dispatchForm = JSON.parse(JSON.stringify(this.dispatchFormInit));
                    } else {
                        this.dispatch_id = Config.row.id;
                        this.dispatchForm = JSON.parse(JSON.stringify(this.dispatchFormInit));
                        this.dispatchForm.name = Config.row.name
                        this.dispatchForm.store_ids = Config.row.store.store_ids;
                        this.storeOptions = Config.row.store.store_ids_list
                        if (this.dispatchForm.store_ids.length>0) {
                            this.self_user_type = 'part'
                        }
                    }
                    this.getstoreOptions()
                },
                methods: {
                    getstoreOptions() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/store/store/select',
                            loading: true,
                            type: 'GET',
                            data: {
                                limit: that.limit,
                                offset: that.offset,
                                filter: '{"store":"1"}',
                                op: '{"store":"="}',
                                searchWhere: that.searchPage,
                            }
                        }, function (ret, res) {
                            that.storeOptions = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    dispatchSub(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[issub].validate((valid) => {
                                if (valid) {
                                    let subData = JSON.parse(JSON.stringify(that.dispatchForm));
                                    if (that.self_user_type == 'all') {
                                        subData.store_ids = ''
                                    } else {
                                        subData.store_ids = subData.store_ids.join(',')
                                    }
                                    if (this.optType != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/dispatch/store/edit?ids=' + that.dispatch_id,
                                            loading: true,
                                            data: {
                                                data: JSON.stringify(subData)
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close({
                                                data: true
                                            })
                                        })
                                    } else {
                                        Fast.api.ajax({
                                            url: 'shopro/dispatch/store/add',
                                            loading: true,
                                            type: "POST",
                                            data: {
                                                data: JSON.stringify(subData)
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close({
                                                data: true
                                            })
                                        })
                                    }
                                } else {
                                    return false;
                                }
                            });
                        } else {
                            Fast.api.close({
                                data: false
                            })
                        }
                    },
                    createStore() {
                        let that=this;
                        Fast.api.open('shopro/store/store/add', '增加门店', {
                            callback(data) {
                                that.getstoreOptions();
                            }
                        })
                    },
                    debounceFilter: debounce(function () {
                        this.getstoreOptions()
                    }, 1000),
                    dataFilter(val) {
                        this.searchPage = val;
                        this.limit = 6;
                        this.offset = 0;
                        this.currentPage = 1;

                        this.debounceFilter()
                    },
                    //分页
                    pageSizeChange(val) {
                        this.offset = 0;
                        this.limit = val;
                        this.currentPage = 1;
                        this.getstoreOptions();
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * 6;
                        this.limit = 6;
                        this.currentPage = 1;
                        this.getstoreOptions();
                    },
                },
            })
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});