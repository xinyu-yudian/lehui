define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'plupload'], function ($, undefined, Backend, Table, Form, Plupload) {

    var Controller = {
        index: function () {
            var uploadIndex = new Vue({
                el: "#uploadIndex",
                data() {
                    return {}
                },
                methods: {
                    selectPic(type) {
                        Fast.api.open('shopro/upload/select?type=' + type, '选择', {
                            callback(data) {
                            }
                        })
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
                url: 'shopro/upload/recyclebin' + location.search,
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
                                    url: 'shopro/upload/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/upload/destroy',
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
        select: function () {
            var uploadSelect = new Vue({
                el: "#uploadSelect",
                data() {
                    return {
                        searchKey: '',
                        type: window.location.search.replace('?', '').split('&')[0].split('=')[1] ? window.location.search.replace('?', '').split('&')[0].split('=')[1] : 'pic',
                        datalist: [{
                                url: "/uploads/20200720/dc6d42eaaa0ca252c6d610b5fdea48d9.png",
                            },{
                                url: "/uploads/20200720/dc6d42eaaa0ca252c6d610b5fdea48d9.png",
                            },{
                                url: "/uploads/20200720/dc6d42eaaa0ca252c6d610b5fdea48d9.png",
                            },{
                                url: "/uploads/20200720/dc6d42eaaa0ca252c6d610b5fdea48d9.png",
                            },{
                                url: "/uploads/20200720/dc6d42eaaa0ca252c6d610b5fdea48d9.png",
                            },{
                                url: "/uploads/20200720/dc6d42eaaa0ca252c6d610b5fdea48d9.png",
                            }],

                        currentPage: 1,
                        totalPage: 700
                    }
                },
                created() {
                    this.getdatalist()
                },
                methods: {
                    getdatalist() {
                        // Fast.api.ajax({
                        //     url: '',
                        //     data: {

                        //     }
                        // }, function (ret, res) {

                        // })
                        this.datalist.forEach(e => {
                            e.selected = false;
                        });
                    },
                    selectfun(index) {
                        this.datalist[index].selected = !this.datalist[index].selected;
                        this.$forceUpdate()
                    },
                    submitfun(url) {
                        let arrsrc = []
                        this.datalist.forEach(e => {
                            if (e.selected) {
                                arrsrc.push(Fast.api.cdnurl(e.url))
                            }
                        });
                        let strlist = arrsrc.join(',')
                        Fast.api.close({
                            selecturl: strlist
                        })
                    },
                    handleSizeChange(val) {
                    },
                    handleCurrentChange(val) {
                    }
                },
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
        }
    };
    return Controller;
});