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
                url: 'shopro/dispatch/express/recyclebin' + location.search,
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
                                    url: 'shopro/dispatch/express/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/dispatch/express/destroy',
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
            // 大于0的数、最多两位小数
            Vue.directive('enterNumber', {
                inserted: function (el) {
                    let changeValue = (el, type) => {
                        const e = document.createEvent('HTMLEvents')
                        e.initEvent(type, true, true)
                        el.dispatchEvent(e)
                    }
                    el.addEventListener("keyup", function (e) {
                        let input = e.target;
                        let reg = new RegExp('^((?:(?:[1-9]{1}\\d*)|(?:[0]{1}))(?:\\.(?:\\d){0,2})?)(?:\\d*)?$');
                        let matchRes = input.value.match(reg);
                        if (matchRes === null) {
                            input.value = "";
                        } else {
                            if (matchRes[1] !== matchRes[0]) {
                                input.value = matchRes[1];
                            }
                        }
                        changeValue(input, 'input')
                    });
                }
            });
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
                            type: [{
                                required: true,
                                message: '请选择计价方式',
                                trigger: 'change'
                            }],
                        },
                        dispatchFormInit: {
                            name: '',
                            type: 'number',
                            express: [{
                                first_num: 0,
                                first_price: 0.00,
                                additional_num: 0,
                                additional_price: 0.00,
                                area_text: '',
                                province_ids: '',
                                city_ids: '',
                                area_ids: '',
                                weigh: '',
                            }],
                        },
                        dispatch_id: null,
                        deleteArr: ['createtime', 'deletetime', 'name', 'type', 'type_text', 'updatetime'],

                    }
                },
                mounted() {
                    if (this.optType == 'add') {
                        this.dispatchForm = JSON.parse(JSON.stringify(this.dispatchFormInit));
                    } else {
                        this.dispatch_id = Config.row.id;
                        this.dispatchForm = JSON.parse(JSON.stringify(this.dispatchFormInit));
                        for (key in this.dispatchForm) {
                            if (key == 'type') {
                                this.dispatchForm[key] = Config.row.express[0].type
                            } else {
                                this.dispatchForm[key] = Config.row[key]
                            }
                        }
                        this.dispatchForm.express.forEach(i => {
                            this.deleteArr.forEach(j => {
                                delete i[j]
                            })
                        })
                    }
                },
                methods: {
                    dispatchSub(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[issub].validate((valid) => {
                                if (valid) {
                                    if (that.dispatchForm.express.length == 0) {
                                        Toastr.error('请选择配送规则');
                                        return false;
                                    }
                                    let isArea = true;
                                    that.dispatchForm.express.forEach((i, index) => {
                                        if (i.province_ids == '' && i.city_ids == '' && i.area_ids == '') {
                                            isArea = false
                                        }
                                    })
                                    if (!isArea) {
                                        Toastr.error('请选择地址');
                                        return false;
                                    }
                                    let subData = JSON.parse(JSON.stringify(that.dispatchForm));
                                    let leng = subData.express.length;
                                    subData.express.forEach((i, index) => {
                                        i.weigh = leng - index;
                                        delete i.area_text
                                    })
                                    if (this.optType != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/dispatch/express/edit?ids=' + that.dispatch_id,
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
                                            url: 'shopro/dispatch/express/add',
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
                    editArea(index) {
                        let that = this;
                        let parmas = {
                            name: that.dispatchForm.express[index].area_text,
                            province_ids: that.dispatchForm.express[index].province_ids,
                            city_ids: that.dispatchForm.express[index].city_ids,
                            area_ids: that.dispatchForm.express[index].area_ids,
                        }
                        Fast.api.open('shopro/area/select?parmas=' + encodeURI(JSON.stringify(parmas)), '区域选择', {
                            callback(data) {
                                that.dispatchForm.express[index].area_text = data.data.name.join(',');
                                that.dispatchForm.express[index].province_ids = data.data.province.join(',')
                                that.dispatchForm.express[index].city_ids = data.data.city.join(',')
                                that.dispatchForm.express[index].area_ids = data.data.area.join(',')
                            }
                        })
                    },
                    delArea(index) {
                        this.dispatchForm.express.splice(index, 1);
                    },
                    addExpress() {
                        this.dispatchForm.express.push({
                            first_num: 0,
                            first_price: 0.00,
                            additional_num: 0,
                            additional_price: 0.00,
                            area_text: '',
                            province_ids: '',
                            city_ids: '',
                            area_ids: '',
                            weigh: '',
                        });
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