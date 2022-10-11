define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var linkIndex = new Vue({
                el: "#linkIndex",
                data() {
                    return {
                        linkData: [],
                        isData: false,
                        activeName: null,
                        activeIndex: null,
                    }
                },
                mounted() {
                    this.activeName = null
                    this.getData();
                },
                methods: {
                    getData() {
                        let that = this;
                        that.isData = false;
                        Fast.api.ajax({
                            url: 'shopro/link/index',
                            loading: true,
                            type: 'GET',
                        }, function (ret, res) {
                            that.linkData = res.data;
                            if (that.activeName == null && that.activeIndex == null) {
                                that.activeName = that.linkData[0].group ? that.linkData[0].group : '其它';
                                that.activeIndex = 0;
                            }

                            return false;
                        })
                    },
                    tabClick(tab, event) {
                        this.activeName = tab.name;
                        this.activeIndex = Number(tab.index);

                    },
                    operation(opttype, id, idx, type) {
                        let that = this;
                        switch (opttype) {
                            case 'delete':
                                that.$confirm('此操作将永久删除链接, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/link/del/ids/' + id,
                                        loading: true,
                                        type: 'POST',
                                    }, function (ret, res) {
                                        that.activeName=null;
                                        that.activeIndex=null;
                                        that.getData();
                                    })
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消删除'
                                    });
                                });
                                break;
                            case 'add':
                                Fast.api.open("shopro/link/add", "创建链接", {
                                    callback(data) {
                                        if (data.data) {
                                            that.getData();
                                        }
                                    }
                                });
                                break;
                            case 'edit':
                                Fast.api.open("shopro/link/edit?id=" + id, "编辑链接", {
                                    callback(data) {
                                        if (data.data) {
                                            that.getData();
                                        }
                                    }
                                });
                                break;
                            case 'recyclebin':
                                Fast.api.open("shopro/link/recyclebin", "回收站");
                                break;
                        }
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
                        if (columnIndex == 2 || columnIndex == 6) {
                            return 'cell-left';
                        }
                        return '';
                    },
                },
            })

        },
        select: function () {
            var linkSelect = new Vue({
                el: "#linkSelect",
                data() {
                    return {
                        linkData: [],
                        searchWhere: '',
                        activeIndex: 0,
                        selectedid: null,
                        rowData: {},
                        multiple: new URLSearchParams(location.search).get('multiple'),
                        dialogVisible: false,
                        isAll:false

                    }
                },
                mounted() {
                    this.getData();
                    this.$nextTick(() => {
                        $('.scroll-item').each(function (i, element) {
                            var h = $(element).height();
                        });

                    });
                    window.addEventListener('scroll', this.handleScroll, true)
                },
                methods: {
                    checkedAll(val) {
                        this.isAll = val;
                        this.rowData=[];
                        this.linkData.forEach(i => {
                            if(i.children && i.children.length>0){
                                i.children.forEach(j=>{
                                    j.selected=val
                                })
                            }
                        })
                        if(val){
                            this.linkData.forEach(i => {
                                if(i.children && i.children.length>0){
                                    i.children.forEach(j=>{
                                        this.rowData.push(j)
                                    })
                                }
                            })
                        }
                        this.$forceUpdate();
                    },
                    getData() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/link/select',
                            type: "GET",
                        }, function (ret, res) {
                            that.linkData = res.data;
                            return false;
                        })
                    },
                    operation(type, rows, index, idx) {
                        let multiple = false;
                        let that = this;
                        switch (type) {
                            case 'cancel':
                                Fast.api.close({
                                    data: {},
                                    multiple: multiple
                                });
                                break;
                            case 'define':
                                let row = this.rowData;
                                if (that.multiple == 'true') {
                                    if (row.length > 0) {
                                        var multiplePath = []
                                        row.forEach(r => {
                                            r.path_name = r.name;
                                            multiplePath.push(r.path)
                                        })
                                        row = {
                                            path: multiplePath.join(',')
                                        }
                                        Fast.api.close({
                                            data: row,
                                            multiple: multiple
                                        });
                                    }
                                } else {
                                    Fast.api.close({
                                        data: row,
                                        multiple: multiple
                                    });
                                }
                                break;
                            case 'select':
                                if (that.multiple == 'true') {
                                    this.linkData[index].children[idx].selected = !this.linkData[index].children[idx].selected;
                                    let rowsArr = []
                                    this.linkData.forEach(e => {
                                        if (e.children && e.children.length > 0) {
                                            e.children.forEach(i => {
                                                if (i.selected) {
                                                    rowsArr.push(i)
                                                }
                                            })
                                        }
                                    });
                                    this.rowData = rowsArr;
                                } else {
                                    this.linkData.forEach(e => {
                                        if (e.children && e.children.length > 0) {
                                            e.children.forEach(i => {
                                                i.selected = false;
                                            })
                                        }
                                    });
                                    this.linkData[index].children[idx].selected = !this.linkData[index].children[idx].selected;
                                    this.rowData = rows;
                                    let row = JSON.parse(JSON.stringify(this.rowData));
                                    switch (row.path) {
                                        case '/pages/app/coupon/detail':
                                            Fast.api.open("shopro/coupons/select", __('Choose'), {
                                                callback: function (data) {
                                                    row.path_name = row.name + '-' + data.data.name
                                                    row.path += '?id=' + data.data.id.toString();
                                                    that.rowData = row;
                                                }
                                            });
                                            break;
                                        case '/pages/goods/list':
                                            Fast.api.open("shopro/category/select?from=group", __('Choose'), {
                                                callback: function (data) {
                                                    console.log(data,'data')
                                                    row.path_name = row.name + '-' + data.data.category_name
                                                    row.path += '?id=' + data.data.id.toString()
                                                    that.rowData = row;
                                                }
                                            });
                                            break;
                                        case '/pages/goods/detail':
                                            parent.Fast.api.open("shopro/goods/goods/select?multiple=" + false, __('Choose'), {
                                                callback: function (data) {
                                                    row.path_name = row.name + '-' + data.data.title
                                                    row.path += '?id=' + data.data.id.toString()
                                                    that.rowData = row;
                                                }
                                            });
                                            break;
                                        case '/pages/public/richtext':
                                            Fast.api.open("shopro/richtext/select", __('Choose'), {
                                                callback: function (data) {
                                                    row.path_name = row.name + '-' + data.data.title
                                                    row.path += '?id=' + data.data.id.toString()
                                                    that.rowData = row;
                                                }
                                            });
                                            break;
                                        case '/pages/public/poster/index':
                                            that.dialogVisible = true;
                                            that.rowData = row;
                                            break;
                                        case '/pages/index/view':
                                            Fast.api.open("shopro/decorate/select?type=custom", __('Choose'), {
                                                callback: function (data) {
                                                    row.path_name = row.name + '-' + data.data.name;
                                                    row.path += '?id=' + data.data.id.toString()
                                                    that.rowData = row;
                                                }
                                            });
                                            break;
                                        case '/pages/index/category':
                                            Fast.api.open("shopro/category/select?from=link", __('Choose'), {
                                                callback: function (data) {
                                                    row.path_name = row.name + '-' + data.data.category_name
                                                    row.path += '?id=' + data.data.id.toString()
                                                    that.rowData = row;
                                                }
                                            });
                                            break;
                                        default:
                                            row.path_name = row.name
                                            that.rowData = row;
                                    }
                                }

                                this.$forceUpdate();
                                break;
                        }
                    },
                    selected(index) {
                        location.href = "#" + index
                        this.activeIndex = index;
                    },
                    posterUser() {
                        let that = this;
                        let row = that.rowData;
                        that.dialogVisible = false;
                        row.path_name = '个人海报'
                        row.path = row.path + '?posterType=user'
                        that.rowData = row;
                    },
                    posterGood() {
                        let that = this;
                        let row = that.rowData;
                        that.dialogVisible = false;
                        parent.Fast.api.open("shopro/goods/goods/select?multiple=" + false, __('Choose'), {
                            callback: function (data) {
                                row.path_name = '商品海报'
                                row.path += '?posterType=goods&id=' + data.data.id.toString()
                                that.rowData = row;
                            }
                        });
                    },
                    handleScroll() {
                        let arr = [];
                        let heightArr = [];
                        $('.scroll-item').each(function (i, element) {
                            var v = $(element).offset().top;
                            var h = $(element).outerHeight(true);
                            arr.push(v);
                            heightArr.push(h)
                        });
                        let handel = [];
                        let indexs = [];
                        arr.forEach((i, index) => {
                            if (i > 0) {
                                handel.push(i);
                                indexs.push(index);
                            }
                        })
                        if (handel[0] < heightArr[indexs[0]] / 2) {
                            this.activeIndex = indexs[0];
                        } else {
                            this.activeIndex = indexs[0] - 1;
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
                url: 'shopro/link/recyclebin' + location.search,
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
                                    url: 'shopro/link/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/link/destroy',
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
            var linkDetail = new Vue({
                el: "#linkDetail",
                data() {
                    return {
                        optType: type,
                        detailForm: {},
                        detailFormInit: {
                            group: '',
                            name: '',
                            path: ''
                        },
                        rulesForm: {
                            group: [{
                                required: true,
                                message: '请输入所属分组',
                                trigger: 'blur'
                            }],
                            name: [{
                                required: true,
                                message: '请输入名称',
                                trigger: 'blur'
                            }],
                            path: [{
                                required: true,
                                message: '请输入路径',
                                trigger: 'blur'
                            }],
                        },
                    }
                },
                mounted() {
                    this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit))
                    if (this.optType == 'edit') {
                        for (key in this.detailForm) {
                            this.detailForm[key] = Config.row[key]
                        }
                    }
                },
                methods: {
                    addpath() {
                        this.detailForm.children.push({
                            name: '',
                            path: ''
                        })
                    },
                    submitForm(check) {
                        let that = this;
                        this.$refs[check].validate((valid) => {
                            if (valid) {
                                if (that.optType != 'add') {
                                    Fast.api.ajax({
                                        url: 'shopro/link/edit?id=' + Config.row.id,
                                        loading: true,
                                        type: "POST",
                                        data: {
                                            data: JSON.stringify(this.detailForm)
                                        }
                                    }, function (ret, res) {
                                        Fast.api.close({
                                            data: true,
                                            // type: 'edit'
                                        })
                                    })
                                } else {
                                    Fast.api.ajax({
                                        url: 'shopro/link/add',
                                        loading: true,
                                        type: "POST",
                                        data: {
                                            data: JSON.stringify(this.detailForm)
                                        }
                                    }, function (ret, res) {
                                        Fast.api.close({
                                            data: true,
                                        })
                                    })
                                }
                            } else {
                                return false;
                            }
                        });
                    }
                }
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