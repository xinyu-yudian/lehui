define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'toastr'], function ($, undefined, Backend, Table, Form, Toastr) {

    var Controller = {
        index: function () {},
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
                url: 'shopro/dispatch/autosend/recyclebin' + location.search,
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
                                    url: 'shopro/dispatch/autosend/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/dispatch/autosend/destroy',
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
            var dispatchDetail = new Vue({
                el: "#dispatchDetail",
                data() {
                    return {
                        optType: type,
                        dispatchForm: {},
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入自提点名称',
                                trigger: 'blur'
                            }],
                            type: [{
                                required: true,
                                message: '请选择发货类型',
                                trigger: 'blur'
                            }],
                            timecontent: [{
                                required: true,
                                message: '请输入发货内容',
                                trigger: 'blur'
                            }],
                        },
                        dispatchFormInit: {
                            name: '',
                            type: 'text',
                            timecontent: '',
                        },
                        dispatch_id: null,
                    }
                },
                mounted() {
                    
                    if (this.optType == 'add') {
                        this.dispatchForm = JSON.parse(JSON.stringify(this.dispatchFormInit));
                    } else {
                        this.dispatch_id = Config.row.id;
                        this.dispatchForm = JSON.parse(JSON.stringify(this.dispatchFormInit));
                        this.dispatchForm.type=Config.row.autosend.type;
                        this.dispatchForm.name=Config.row.name;
                        if(this.dispatchForm.type=='params'){
                            let arr=[]
                            Config.row.autosend.content.forEach((i,index)=>{
                                arr.push({
                                    title:Object.keys(i)[0],
                                    content:Object.values(i)[0],
                                })
                            })
                            this.dispatchForm.timecontent=arr;
                        }else{
                            this.dispatchForm.timecontent=Config.row.autosend.content
                        }
                    }
                },
                methods: {
                    radioChange(val){
                        if(val=='text'){
                            this.dispatchForm.timecontent='';
                        }else if(val=='params'){
                            this.dispatchForm.timecontent=[];
                        }
                    },
                    addParams() {
                        this.dispatchForm.timecontent.push({
                            title: '',
                            content: ''
                        })
                    },
                    delParams(index) {
                        this.dispatchForm.timecontent.splice(index, 1)
                    },
                    dispatchSub(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[issub].validate((valid) => {
                                if (valid) {
                                    let subData = JSON.parse(JSON.stringify(that.dispatchForm));
                                    if(subData.type=='params'){
                                        let arrfield=[];
                                        let arrcontent=[];
                                        let paramsflg=true;
                                        subData.timecontent.forEach(i=>{
                                            for(key in i){
                                                if(!i[key]){
                                                    paramsflg=false
                                                   
                                                }
                                            }
                                            arrfield.push(i.title);
                                            arrcontent.push(i.content);
                                        })
                                        if(!paramsflg){
                                            Toastr.error('发货详情未填写完整');
                                            return false;
                                        }
                                        subData.content=[];
                                        arrfield.forEach((i,index)=>{
                                            let obj={}
                                            obj[i]=arrcontent[index]
                                            subData.content.push(obj)
                                        })
                                    } else{
                                        subData.content=subData.timecontent
                                    }
                                    delete subData.timecontent
                                    if (this.optType != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/dispatch/autosend/edit?ids=' + that.dispatch_id,
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
                                            url: 'shopro/dispatch/autosend/add',
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