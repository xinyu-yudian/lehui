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
            var indexPage = new Vue({
                el: "#indexPage",
                data() {
                    return {
                        data: [],
                        multipleSelection: [],

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
                            url: 'shopro/coupons/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                searchWhere: that.searchKey,
                                offset: that.offset,
                                limit: that.limit,
                            },
                        }, function (ret, res) {
                            that.data = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    operation(type, id) {
                        let that = this;
                        switch (type) {
                            case 'create':
                                Fast.api.open('shopro/coupons/add', '新增', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'edit':
                                Fast.api.open("shopro/coupons/edit?ids=" + id, '编辑', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'del':
                                let ids;
                                if (id) {
                                    ids = id;
                                } else {
                                    let idArr = []
                                    if (that.multipleSelection.length > 0) {
                                        that.multipleSelection.forEach(i => {
                                            idArr.push(i.id)
                                        })
                                        ids = idArr.join(',')
                                    }
                                }
                                if (ids) {
                                    that.$confirm('此操作将删除优惠券, 是否继续?', '提示', {
                                        confirmButtonText: '确定',
                                        cancelButtonText: '取消',
                                        type: 'warning'
                                    }).then(() => {
                                        Fast.api.ajax({
                                            url: 'shopro/coupons/del/ids/' + ids,
                                            loading: true,
                                            type: 'POST'
                                        }, function (ret, res) {
                                            that.getData();
                                            return false;
                                        })
                                    }).catch(() => {
                                        that.$message({
                                            type: 'info',
                                            message: '已取消删除'
                                        });
                                    });
                                }
                                break;
                            case 'recyclebin':
                                Fast.api.open('shopro/coupons/recyclebin', '查看回收站', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                        }
                    },
                    handleSelectionChange(val) {
                        this.multipleSelection = val;
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
                        if (columnIndex == 1 || columnIndex == 2 || columnIndex == 9) {
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
                url: 'shopro/coupons/recyclebin' + location.search,
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
                            url: 'shopro/coupons/restore',
                            refresh: true
                        },
                        {
                            name: 'Destroy',
                            text: __('Destroy'),
                            classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                            icon: 'fa fa-times',
                            url: 'shopro/coupons/destroy',
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
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/coupons/select',
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
                        idArr.indexOf(j.id) == -1 && idArr.push(j.id);
                        selectArr.indexOf(j) == -1 && selectArr.push(j);
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                showToggle: false,
                showExport: false,
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
                        title: __('Name')
                    },
                    {
                        field: 'goods_ids',
                        title: __('Goods_ids')
                    },
                    {
                        field: 'amount',
                        title: __('Amount'),
                        operate: 'BETWEEN'
                    },
                    {
                        field: 'enough',
                        title: __('Enough'),
                        operate: 'BETWEEN'
                    },
                    {
                        field: 'stock',
                        title: __('Stock')
                    },
                    {
                        field: 'limit',
                        title: __('Limit')
                    },
                    {
                        field: 'gettime',
                        title: __('Gettime')
                    },
                    {
                        field: 'usetime',
                        title: __('Usetime')
                    },
                    {
                        field: 'description',
                        title: __('Description')
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
                        events: {
                            'click .btn-chooseone': function (e, value, row, index) {
                                var multiple = Backend.api.query('multiple');
                                multiple = multiple == 'true' ? true : false;
                                row.ids = row.id.toString()
                                Fast.api.close({
                                    data: row,
                                    multiple: multiple
                                });
                            },
                        },
                        formatter: function () {
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
                // let row = {}
                // row.ids = idArr.join(",")
                // Fast.api.close({
                //     data: row,
                //     multiple: multiple
                // });
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            require(['upload'], function (Upload) {
                Upload.api.plupload($("#toolbar .plupload"), function () {
                    $(".btn-refresh").trigger("click");
                });
            });

        },
        add: function () {
            Controller.detailInit('add');
        },
        edit: function () {
            Controller.detailInit('edit');
        },
        detailInit: function (type) {
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
            Vue.directive('positiveInteger', {
                inserted: function (el) {
                    el.addEventListener("keypress", function (e) {
                        e = e || window.event;
                        let charcode = typeof e.charCode == 'number' ? e.charCode : e.keyCode;
                        let re = /\d/;
                        if (!re.test(String.fromCharCode(charcode)) && charcode > 9 && !e.ctrlKey) {
                            if (e.preventDefault) {
                                e.preventDefault();
                            } else {
                                e.returnValue = false;
                            }
                        }
                    });
                }
            });
            var pageDetail = new Vue({
                el: "#pageDetail",
                computed: {
                    enoughAmount() {
                        return this.detailData.enough - this.detailData.amount
                    }
                },
                data() {
                    var checkEnough = (rule, value, callback) => {
                        if (value && this.enoughAmount > 0) {
                            callback();
                        } else {
                            callback(new Error('使用门槛必须大于减免金额'));
                        }
                    }
                    var checkAmount = (rule, value, callback) => {
                        if (value && this.enoughAmount > 0) {
                            callback();
                        } else {
                            callback(new Error('减免金额必须小于使用门槛'));
                        }
                    }
                    var checkTime = (rule, value, callback) => {
                        if(value){
                            if (value.length > 0 && value[0]) {
                                callback();
                            } else {
                                callback(new Error('领取结束时间不能大于使用结束时间'));
                            }
                        }
                    }
                    return {
                        optType: type,
                        detailData: {},
                        detailDataInit: {
                            amount: "",
                            description: "",
                            enough: "",
                            gettime: "",
                            goods_ids: "",
                            limit: 1,
                            name: "",
                            stock: 0,
                            usetime: "",
                            type: 'cash',
                            goods_ids: '',
                            goods_type: 'all'
                        },
                        detail_id: null,
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入优惠券名称',
                                trigger: 'blur'
                            }],
                            type: [{
                                required: true,
                                message: '请选择优惠券类型',
                                trigger: 'blur'
                            }],
                            gettime: [{
                                required: true,
                                message: '请选择有效时间',
                                trigger: 'change'
                            }, {
                                validator: checkTime,
                                trigger: 'change'
                            }],
                            usetime: [{
                                required: true,
                                message: '请选择用券时间',
                                trigger: 'change'
                            }, {
                                validator: checkTime,
                                trigger: 'change'
                            }],
                            enough: [{
                                required: true,
                                message: '请输入使用门槛',
                                trigger: 'change'
                            }, {
                                validator: checkEnough,
                                trigger: 'change'
                            }],
                            amount: [{
                                required: true,
                                message: '请输入减免金额',
                                trigger: 'change'
                            }, {
                                validator: checkAmount,
                                trigger: 'change'
                            }],
                            goods_ids: [{
                                required: true,
                                message: '请选择商品',
                                trigger: 'change'
                            }],
                        },
                        goods_arr: []
                    }
                },
                created() {
                    this.detailData = JSON.parse(JSON.stringify(this.detailDataInit))
                    if (this.optType == 'edit') {
                        this.detail_id = Config.row.id;
                        for (key in this.detailData) {
                            this.detailData[key] = Config.row[key]
                        }
                        this.detailData.gettime = this.detailData.gettime.split(' - ');
                        this.detailData.usetime = this.detailData.usetime.split(' - ');
                        if (this.detailData.goods_ids == 0) {
                            this.detailData.goods_type = 'all'
                        } else {
                            this.detailData.goods_type = 'part'
                        }
                        if (Config.row.goods) {
                            this.goods_arr = Config.row.goods;
                            this.goods_arr.forEach(i => {
                                i.selected = false;
                            })
                        }
                    }
                },
                methods: {
                    operation(type, id) {
                        let that = this;
                        switch (type) {
                            case 'selected':
                                let idsArr = [];
                                if (that.goods_arr.length > 0) {
                                    that.goods_arr.forEach(i => {
                                        idsArr.push(i.id)
                                    })
                                }
                                let ids = idsArr.join(',')
                                parent.Fast.api.open('shopro/goods/goods/select?multiple=true&type=activity&ids=' + ids, '选择商品', {
                                    callback(data) {
                                        that.goods_arr = data.data
                                        that.goods_arr.forEach(i => {
                                            i.selected = false;
                                        })
                                        that.$forceUpdate();
                                    }
                                })
                                break;
                            case 'selectedDel':
                                that.goods_arr.forEach(i => {
                                    i.selected = false;
                                })
                                if (that.goods_arr[id]) {
                                    that.$set(that.goods_arr[id], 'selected', true)
                                    that.$forceUpdate()
                                }
                                break;
                            case 'clear':
                                that.goods_arr = []
                                break;
                            case 'delete':
                                that.goods_arr.splice(id, 1)
                                break;
                            case 'edit':
                                Fast.api.open("shopro/coupons/edit/?ids=" + that.detail_id, '编辑', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                        }
                    },
                    changeTime(type) {
                        if (this.detailData.usetime && this.detailData.gettime) {
                            if (this.detailData.usetime[1] < this.detailData.gettime[1]) {
                                this.detailData[type] = [false]
                            }
                        }
                    },
                    submit(type, check) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[check].validate((valid) => {
                                if (valid) {
                                    let subData = JSON.parse(JSON.stringify(that.detailData));
                                    subData.gettime = subData.gettime.join(' - ');
                                    subData.usetime = subData.usetime.join(' - ');
                                    if (subData.goods_type == 'all') {
                                        subData.goods_ids = 0
                                    } else {
                                        if (that.goods_arr.length == 0) {
                                            return false;
                                        }
                                        let goodsArr = []
                                        that.goods_arr.forEach(i => {
                                            goodsArr.push(i.id)
                                        })
                                        subData.goods_ids = goodsArr.join(',')
                                    }
                                    delete subData.goods_type
                                    if (that.optType != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/coupons/edit?ids=' + that.detail_id,
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
                                    } else {
                                        Fast.api.ajax({
                                            url: 'shopro/coupons/add',
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
                            Fast.api.close()
                            that.storeForm = JSON.parse(JSON.stringify(that.storeFormInit[that.store_type]));
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