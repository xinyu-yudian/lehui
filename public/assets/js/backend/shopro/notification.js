define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'shopro/notification/config/index' + location.search,
                    add_url: 'shopro/notification/config/add',
                    edit_url: 'shopro/notification/config/edit',
                    del_url: 'shopro/notification/config/del',
                    multi_url: 'shopro/notification/config/multi',
                    table: 'shopro_notification_config',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
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
                            field: 'platform',
                            title: __('Platform')
                        },
                        {
                            field: 'name',
                            title: __('Name')
                        },
                        {
                            field: 'event',
                            title: __('Event')
                        },
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {
                                "0": __('Status 0'),
                                "1": __('Status 1')
                            },
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'updatetime',
                            title: __('Updatetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        config: function () {
            var notification_index = new Vue({
                el: "#notification-index",
                data() {
                    return {
                        tipCloseFlag: true,
                        notificationDialog: false,
                        notificationData: [],
                        notificationForm: {},
                        editIndex:null,
                        editPlatform:null
                    }
                },
                mounted() {
                    this.getnotificationData();
                },
                methods: {
                    getnotificationData(){
                        let that=this;
                        Fast.api.ajax({
                            url: 'shopro/notification/config',
                            type:'GET',
                            loading: true,
                            data: {}
                        }, function (ret, res) {
                            that.notificationData=res.data;
                            return false;
                        })
                    },
                    tipClose() {
                        this.tipCloseFlag = false;
                    },
                    changeStatua(platform, event, name, status) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/notification/set_status',
                            loading: true,
                            type: "POST",
                            data: {
                                platform: platform,
                                event: event,
                                name: name,
                                status: status
                            }
                        }, function (ret, res) {
                            that.getnotificationData();
                        })
                    },
                    fieldDel(index){
                        this.notificationForm.fields.splice(index,1)
                    },
                    notificationClose(type) {
                        let that = this;
                        if(this.editPlatform!='email'){
                            that.notificationForm.template_id=that.notificationForm.template_id.replace(/\s*/g,"");
                        }
                        if (type == 'yes') {
                            let content=JSON.stringify(that.notificationForm)
                            if(this.editPlatform=='email'){
                                content=$('#c-content').val()
                            }
                            Fast.api.ajax({
                                url: 'shopro/notification/set_template',
                                loading: true,
                                data: {
                                    platform: that.editMsg.platform,
                                    event: that.editMsg.event,
                                    name: that.editMsg.name,
                                    content: content
                                }
                            }, function (ret, res) {
                                that.getnotificationData();
                                that.notificationDialog = false;
                                that.notificationForm = {};
                                that.editMsg={};
                            })
                        } else {
                            that.notificationForm = {};
                            that.editMsg={};
                            that.notificationDialog = false;
                        }
                    },
                    edit(index, type) {
                        this.editMsg=this.notificationData[index][type];
                        this.notificationForm = JSON.parse(JSON.stringify(this.notificationData[index][type].content_arr));
                        this.editPlatform=type;
                        if(this.editPlatform=='email'){
                            this.$nextTick(callback=>{
                                Controller.api.bindevent();
                                $('#c-content').html(this.notificationData[index][type].content)
                            })
                        }
                        this.notificationDialog = true;
                    },
                    addfield() {
                        this.notificationForm.fields.push({
                            'name': '',
                            'template_field': '',
                            'value': '',
                        });
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
                url: 'shopro/notification/recyclebin' + location.search,
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
                                    url: 'shopro/notification/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/notification/destroy',
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