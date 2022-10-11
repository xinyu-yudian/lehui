define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/app/live/index' + location.search,
                    add_url: 'shopro/app/live/add',
                    edit_url: 'shopro/app/live/edit',
                    detail_url: 'shopro/app/live/detail',
                    del_url: 'shopro/app/live/del',
                    multi_url: 'shopro/app/live/multi',
                    table: 'shopro_live',
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
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name')},
                        {field: 'room_id', title: __('Room_id')},
                        {field: 'live_status', title: __('Live_status'), searchList: {"101":__('Live_status 101'),"102":__('Live_status 102'),"103":__('Live_status 103'),"104":__('Live_status 104'),"105":__('Live_status 105'),"106":__('Live_status 106'),"107":__('Live_status 107')}, formatter: Table.api.formatter.status},
                        {field: 'starttime', title: __('Starttime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'endtime', title: __('Endtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'anchor_name', title: __('Anchor_name')},
                        {field: 'share_img', title: __('Share_img'),events: Table.api.events.image,formatter: Table.api.formatter.image},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {
                            field: 'buttons',
                            width: "120px",
                            title: '操作',
                            table: table,
                            operate: false,
                            buttons: [
                                {
                                    name: 'status',
                                    title: '直播详情',
                                    text: '查看详情',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    extend: 'data-area=\'["100%", "100%"]\'',
                                    url: function (row) {
                                        return 'shopro/app/live/detail?ids=' + row.id
                                    },

                                    success: function (data, ret) {
                                        Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },
                            ],
                            formatter: Table.api.formatter.buttons
                        },
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $("#refreshStatus").click(function(){
                Fast.api.ajax({
                    url: 'shopro/app/live/syncLive',
                    loading: true,
                    data: {}
                }, function (ret, res) {
                    setTimeout(function() {
                        $(".btn-refresh").click()
                    }, 1000);

                })   
            })
        },
        detail: function () {           
            Controller.api.bindevent();
        },
        select: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/app/live/index',
                }
            });

            var idArr = [];
            var selectArr = [];
            var table = $("#table");

            table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function (e, row) {
                if (e.type == 'check' || e.type == 'uncheck') {
                    row = [row];
                } else {
                    idArr = [];
                    selectArr = [];
                }
                $.each(row, function (i, j) {
                    if (e.type.indexOf("uncheck") > -1) {
                        var index = idArr.indexOf(j.id);
                        if (index > -1) {
                            idArr.splice(index, 1);
                        }
                        if (indexall > -1) {
                            selectArr.splice(index, 1);
                        }
                    } else {
                        idArr.indexOf(j.url) == -1 && idArr.push(j.id);
                        selectArr.indexOf(j) == -1 && selectArr.push(j);
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                sortName: 'id',
                showToggle: false,
                showExport: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name')},
                        {field: 'room_id', title: __('Room_id')},
                        {field: 'live_status', title: __('Live_status'), searchList: {"101":__('Live_status 101'),"102":__('Live_status 102'),"103":__('Live_status 103'),"104":__('Live_status 104'),"105":__('Live_status 105'),"106":__('Live_status 106'),"107":__('Live_status 107')}, formatter: Table.api.formatter.status},
                        {field: 'starttime', title: __('Starttime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'endtime', title: __('Endtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'anchor_name', title: __('Anchor_name')},
                        {field: 'share_img', title: __('Share_img'),events: Table.api.events.image,formatter: Table.api.formatter.image},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate', title: __('Operate'), events: {
                                'click .btn-chooseone': function (e, value, row, index) {
                                    var multiple = Backend.api.query('multiple');
                                    multiple = multiple == 'true' ? true : false;
                                    row.ids=row.id.toString()
                                    Fast.api.close({data: row, multiple: multiple});
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
                if (Backend.api.query('type') == 'decorate') {
                    var multiple = Backend.api.query('multiple');
                    multiple = multiple == 'true' ? true : false;
                    Fast.api.close({
                        data: selectArr,
                        multiple: multiple
                    });
                } else {
                    let row = {}
                    var multiple = Backend.api.query('multiple');
                    multiple = multiple == 'true' ? true : false;
                    row.ids = idArr.join(",")
                    Fast.api.close({
                        data: row,
                        multiple: multiple
                    });
                }
                // var multiple = Backend.api.query('multiple');
                // multiple = multiple == 'true' ? true : false;
                // let row={}
                // row.ids=idArr.join(",")
                // Fast.api.close({data: row, multiple: multiple});
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            //绑定TAB事件
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                var typeStr = $(this).attr("href").replace('#', '');
                var options = table.bootstrapTable('getOptions');
                options.pageNumber = 1;
                options.queryParams = function (params) {
                    params.type = typeStr;

                    return params;
                };
                table.bootstrapTable('refresh', {});
                return false;

            });
            require(['upload'], function (Upload) {
                Upload.api.plupload($("#toolbar .plupload"), function () {
                    $(".btn-refresh").trigger("click");
                });
            });

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
                url: 'shopro/app/live/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), align: 'left'},
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
                                    url: 'shopro/app/live/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/app/live/destroy',
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