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
            var scoreShopIndex = new Vue({
                el: "#scoreShopIndex",
                data() {
                    return {
                        scoreShopData: [],
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
                        let dataAc = {
                            search: that.searchKey,
                            offset: that.offset,
                            limit: that.limit,
                        };
                        Fast.api.ajax({
                            url: 'shopro/app/score_shop/index',
                            loading: true,
                            type: 'GET',
                            data: dataAc
                        }, function (ret, res) {
                            that.scoreShopData = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    operation(type, id) {
                        let that = this;
                        switch (type) {
                            case 'create':
                                parent.Fast.api.open("shopro/goods/goods/select?multiple=false", "选择商品", {
                                    callback: function (data) {
                                        if(data.data.id){
                                            parent.Fast.api.open("shopro/app/score_shop/add?id=" + data.data.id, "设置积分规格", {
                                                callback: function (data) {
                                                    that.getData()
                                                }
                                            });
                                        }
                                    }
                                });
                                break;
                            case 'edit':
                                parent.Fast.api.open("shopro/app/score_shop/edit?id=" + id, "编辑积分规格", {
                                    callback: function (data) {
                                        that.getData()
                                    }
                                });
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
                                    that.$confirm('此操作将删除积分商品, 是否继续?', '提示', {
                                        confirmButtonText: '确定',
                                        cancelButtonText: '取消',
                                        type: 'warning'
                                    }).then(() => {
                                        Fast.api.ajax({
                                            url: 'shopro/app/score_shop/del/ids/' + ids,
                                            loading: true,
                                            type: 'POST',
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
                            case 'recycle':
                                Fast.api.open('shopro/app/score_shop/recyclebin', '查看回收站')
                                break;
                        }
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
                        if (columnIndex == 1 || columnIndex == 4) {
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
                url: 'shopro/app/score_shop/recyclebin' + location.search,
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
                            field: 'title',
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
                                    url: 'shopro/app/score_shop/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/app/score_shop/destroy',
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
        sku: function () {
            function urlParmas(par) {
                let value = ""
                window.location.search.replace("?", '').split("&").forEach(i => {
                    if (i.split('=')[0] == par) {
                        value = JSON.parse(decodeURI(i.split('=')[1]))
                    }
                })
                return value
            }
            var skuPrice = new Vue({
                el: "#skuPrice",
                data() {
                    return {
                        skuList: Config.skuList,
                        skuPrice: Config.skuPrice,
                        activitySkuPrice: Config.activitySkuPrice,
                        id: urlParmas('goods_id'),
                        optType: urlParmas('type'),
                        goodsDetail: {},

                        allEditPopover: {
                            price: false,
                            stock: false,
                            score: false,
                        },
                        allEditDatas: "",
                    }
                },
                mounted() {
                    this.getGoodsDetail();
                },
                methods: {
                    getGoodsDetail() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/goods/goods/detail/ids/' + that.id,
                            loading: true,
                        }, function (ret, res) {
                            that.goodsDetail = res.data.detail;
                            return false;
                        })
                    },
                    goJoin(i) {
                        let status = this.activitySkuPrice[i].status === 'up' ? 'down' : 'up';
                        this.$set(this.activitySkuPrice[i], 'status', status)
                    },
                    allEditData(type, opt) {
                        switch (opt) {
                            case 'define':
                                this.activitySkuPrice.forEach(i => {
                                    if (i.status == 'up') {
                                        i[type] = this.allEditDatas;
                                    }
                                })
                                this.allEditDatas = ''
                                this.allEditPopover[type] = false;
                                break;
                            case 'cancel':
                                this.allEditDatas = ''
                                this.allEditPopover[type] = false;
                                break;
                        }
                    },
                    submitForm() {
                        let that = this;
                        let isSubmit = true
                        isSubmit = !(this.activitySkuPrice.every(function (item, index, array) {
                            return item.status == 'down';
                        }))
                        this.activitySkuPrice.forEach(i => {
                            if (i.status == 'up' && !i.stock) {
                                isSubmit = false
                            }
                            if (i.status == 'up' && !i.price) {
                                isSubmit = false
                            }
                            if (i.status == 'up' && !i.score) {
                                isSubmit = false
                            }
                        })
                        if (!isSubmit) {
                            layer.msg('请把信息填写完整');
                            return false;
                        } else {
                            let arr = []
                            this.activitySkuPrice.forEach(i => {
                                if (i.status == "up") {
                                    arr.push(i)
                                }
                            })
                            Fast.api.ajax({
                                url: 'shopro/app/score_shop/add',
                                loading: true,
                                data: {
                                    goods_id: that.id,
                                    goodsList: JSON.stringify(arr)
                                }
                            }, function (ret, res) {
                                Fast.api.close();
                            })
                        }
                    }
                },
            })
        },
        edit: function () {
            Controller.detailInit('edit');
        },
        detailInit: function (type) {
            var deatailPage = new Vue({
                el: "#deatailPage",
                data() {
                    return {
                        skuList: Config.skuList,
                        skuPrice: Config.skuPrice,
                        activitySkuPrice: Config.activitySkuPrice ? Config.activitySkuPrice : [],
                        id: Config.goodsInfo.id,
                        optType: type,
                        goodsDetail: Config.goodsInfo,

                        allEditPopover: {
                            price: false,
                            stock: false,
                            score: false,
                        },
                        allEditDatas: "",
                    }
                },
                mounted() {
                },
                methods: {
                    goJoin(i) {
                        let status = this.activitySkuPrice[i].status === 'up' ? 'down' : 'up';
                        this.$set(this.activitySkuPrice[i], 'status', status)
                    },
                    allEditData(type, opt) {
                        switch (opt) {
                            case 'define':
                                this.activitySkuPrice.forEach(i => {
                                    if (i.status == 'up') {
                                        i[type] = this.allEditDatas;
                                    }
                                })
                                this.allEditDatas = ''
                                this.allEditPopover[type] = false;
                                break;
                            case 'cancel':
                                this.allEditDatas = ''
                                this.allEditPopover[type] = false;
                                break;
                        }
                    },
                    submitForm() {
                        let that = this;
                        //监测数据是否填写完整
                        let isSubmit = true
                        isSubmit = !(this.activitySkuPrice.every(function (item, index, array) {
                            return item.status == 'down';
                        }))
                        this.activitySkuPrice.forEach(i => {
                            if (i.status == 'up' && i.stock=='') {
                                isSubmit = false
                            }
                            if (i.status == 'up' && i.price<0) {
                                isSubmit = false
                            }
                            if (i.status == 'up' && !i.score) {
                                isSubmit = false
                            }
                        })
                        if (!isSubmit) {
                            this.$notify({
                                title: '警告',
                                message: '请把信息填写完整',
                                type: 'warning'
                            });
                            return false;
                        } else {
                            let arr = []
                            this.activitySkuPrice.forEach(i => {
                                if (i.status == "up") {
                                    arr.push(i)
                                }
                            })
                            if (that.optType == 'add') {
                                Fast.api.ajax({
                                    url: 'shopro/app/score_shop/add',
                                    loading: true,
                                    data: {
                                        id: that.id,
                                        goodsList: JSON.stringify(arr)
                                    }
                                }, function (ret, res) {
                                    Fast.api.close();
                                })
                            } else {
                                Fast.api.ajax({
                                    url: 'shopro/app/score_shop/edit',
                                    loading: true,
                                    data: {
                                        id: that.id,
                                        goodsList: JSON.stringify(arr)
                                    }
                                }, function (ret, res) {
                                    Fast.api.close();
                                })
                            }

                        }
                    }
                },
            })
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
        // select: function () {
        //     // 初始化表格参数配置
        //     Table.api.init({
        //         extend: {
        //             index_url: 'shopro/goods/goods/index',
        //         }
        //     });

        //     var table = $("#table");

        //     // 初始化表格
        //     table.bootstrapTable({
        //         url: $.fn.bootstrapTable.defaults.extend.index_url,
        //         sortName: 'id',
        //         showToggle: false,
        //         showExport: false,
        //         columns: [
        //             [{
        //                     field: 'state',
        //                     checkbox: true,
        //                 },
        //                 {
        //                     field: 'title',
        //                     title: __('Title'),
        //                     align: 'left'
        //                 },
        //                 {
        //                     field: 'image',
        //                     title: __('Image'),
        //                     operate: false,
        //                     events: Table.api.events.image,
        //                     formatter: Table.api.formatter.image
        //                 },
        //                 {
        //                     field: 'createtime',
        //                     title: __('Createtime'),
        //                     formatter: Table.api.formatter.datetime,
        //                     operate: 'RANGE',
        //                     addclass: 'datetimerange',
        //                     sortable: true
        //                 },
        //                 {
        //                     field: 'operate',
        //                     title: __('Operate'),
        //                     events: {
        //                         'click .btn-chooseone': function (e, value, row, index) {
        //                             var multiple = Backend.api.query('multiple');
        //                             multiple = multiple == 'true' ? true : false;
        //                             row.ids = row.id.toString()
        //                             Fast.api.close({
        //                                 data: row,
        //                                 multiple: multiple
        //                             });
        //                         },
        //                     },
        //                     formatter: function () {
        //                         return '<a href="javascript:;" class="btn btn-danger btn-chooseone btn-xs"><i class="fa fa-check"></i> ' + __('Choose') + '</a>';
        //                     }
        //                 }
        //             ]
        //         ]
        //     });

        //     // 选中多个
        //     $(document).on("click", ".btn-choose-multi", function () {
        //         var goodsIdArr = new Array();
        //         $.each(table.bootstrapTable("getAllSelections"), function (i, j) {
        //             goodsIdArr.push(j.id);
        //         });
        //         var multiple = Backend.api.query('multiple');
        //         multiple = multiple == 'true' ? true : false;
        //         let row = {}
        //         row.ids = couponsArr.join(",")
        //         Fast.api.close({
        //             data: row,
        //             multiple: multiple
        //         });
        //     });

        //     // 为表格绑定事件
        //     Table.api.bindevent(table);
        //     //绑定TAB事件
        //     $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        //         var typeStr = $(this).attr("href").replace('#', '');
        //         var options = table.bootstrapTable('getOptions');
        //         options.pageNumber = 1;
        //         options.queryParams = function (params) {
        //             params.type = typeStr;

        //             return params;
        //         };
        //         table.bootstrapTable('refresh', {});
        //         return false;

        //     });
        //     require(['upload'], function (Upload) {
        //         Upload.api.plupload($("#toolbar .plupload"), function () {
        //             $(".btn-refresh").trigger("click");
        //         });
        //     });

        // },
    };
    return Controller;
});