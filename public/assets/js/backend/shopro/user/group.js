const { cssNumber } = require("jquery");

define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'toastr'], function ($, undefined, Backend, Table, Form, Toastr) {

    var Controller = {
        index: function () {
            var groupIndex = new Vue({
                el: "#groupIndex",
                data() {
                    return {
                        data: [],


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
                            url: 'shopro/user/group/index',
                            loading: true,
                            type: 'GET',
                            data: {
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
                                Fast.api.open('shopro/user/group/add', '新建', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'edit':
                                Fast.api.open('shopro/user/group/edit?ids=' + id, '编辑', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'del':
                                that.$confirm('此操作将永久直接删除用户, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/user/group/del/ids/' + id,
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

                },

            })
        },
        recyclebin: function () {},
        add: function () {
            Controller.detailInit('add')
        },
        edit: function () {
            Controller.detailInit('edit')
        },
        detailInit: function (type) {

            var groupDetail = new Vue({
                el: "#groupDetail",
                data() {
                    return {
                        detailForm: {},
                        detailFormInit: {
                            name: '',
                            image: '',
                            rules_arr: [],
                            rules: '',
                            status: 'normal'
                        },
                        fromRules: {
                            name: [{
                                required: true,
                                message: '请输入组名',
                                trigger: 'blur'
                            }],
                            image: [{
                                required: true,
                                message: '请选择图片',
                                trigger: 'blur'
                            }],
                            status: [{
                                required: true,
                                message: '请选择状态',
                                trigger: 'blur'
                            }],
                        },
                        detail_id: null,
                        optType: type,

                        nodeList: [],
                        selectList: [],
                        defaultProps: {
                            children: 'children',
                            label: 'text'
                        },
                        isexpand: false,
                        expand_arr: [],
                        ischecked: false,
                        id_arr: [],
                        openOrNot:true
                    }
                },
                created() {
                    this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit))
                    let nodeList_arr = [];
                    Config.nodeList.forEach(i => {
                        if (i.parent == '#') {
                            nodeList_arr.push(i)
                        }
                    })
                    nodeList_arr.forEach(i => {
                        i.children = []
                        Config.nodeList.forEach(k => {
                            if (i.id == k.parent) {
                                i.children.push(k)
                            }
                        })
                    })
                    nodeList_arr.forEach(i => {
                        if (i.children.length > 0) {
                            i.children.forEach(j => {
                                j.children = []
                                Config.nodeList.forEach(k => {
                                    if (j.id == k.parent) {
                                        j.children.push(k)
                                    }
                                })
                            })
                        }
                    })

                    this.nodeList = nodeList_arr
                    if (this.optType == 'edit') {
                        for (key in this.detailForm) {
                            this.detailForm[key] = Config.row[key]
                        }
                        this.detail_id = Config.row.id
                        this.selectList = Config.nodeList
                        let rules_arr = []
                        this.selectList.forEach(i => {
                            if (i.state.selected) {
                                rules_arr.push(i.id)
                            }
                        })
                        this.detailForm.rules_arr = rules_arr;
                    }
                },
                methods: {
                    checkedAll(val) {
                        this.detailForm.rules_arr = []
                        if (val) {
                            let add_id = []
                            this.nodeList.forEach(i => {
                                add_id.push(i.id)
                                if (i.children.length > 0) {
                                    i.children.forEach(j => {
                                        add_id.push(j.id)
                                        Config.nodeList.forEach(k => {
                                            add_id.push(k.id)
                                        })
                                    })
                                }
                            })
                            this.detailForm.rules_arr = add_id
                        }else{
                            this.$refs.tree.setCheckedKeys([]);
                        }
                    },
                    expandAll(val) {
                        this.expand_arr = []
                        if (val) {
                            let add_id = []
                            this.nodeList.forEach(i => {
                                add_id.push(i.id)
                                if (i.children.length > 0) {
                                    i.children.forEach(j => {
                                        add_id.push(j.id)
                                        Config.nodeList.forEach(k => {
                                            add_id.push(k.id)
                                        })
                                    })
                                }
                            })
                            this.expand_arr = add_id
                        }else{
                            this.$nextTick(() => {
                                for(var i=0;i<this.$refs.tree.store._getAllNodes().length;i++){
                                       this.$refs.tree.store._getAllNodes()[i].expanded=false;
                                    }
                            });
                        }

                    },
                    addImg() {
                        let that = this;
                        Fast.api.open("general/attachment/select?multiple=false", "选择图片", {
                            callback: function (data) {
                                that.detailForm.image = data.url;
                            }
                        });
                        return false;
                    },
                    delImg() {
                        this.detailForm.image = '';
                    },
                    selcetedStatus(val, key) {
                        let arr_id = key.checkedKeys.concat(key.halfCheckedKeys)
                        this.id_arr = arr_id
                    },
                    submitFrom(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[issub].validate((valid) => {
                                if (valid) {
                                    let subData = JSON.parse(JSON.stringify(that.detailForm));
                                    subData.rules = this.id_arr.join(',');
                                    delete subData.rules_arr;
                                    if (this.optType != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/user/group/edit?ids=' + that.detail_id,
                                            loading: true,
                                            type: "POST",
                                            data: {
                                                data: JSON.stringify(subData)
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close()
                                        })
                                    } else {
                                        Fast.api.ajax({
                                            url: 'shopro/user/group/add',
                                            loading: true,
                                            type: "POST",
                                            data: {
                                                data: JSON.stringify(subData)
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close()
                                        })
                                    }
                                } else {
                                    return false;
                                }
                            });
                        } else {
                            this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit))
                        }
                    },
                },
            })
        },
        select: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/goods/goods/index?page_type=select',
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
                    selectArr = []
                }
                $.each(row, function (i, j) {
                    if (e.type.indexOf("uncheck") > -1) {
                        var index = idArr.indexOf(j.id);
                        var indexall = idArr.indexOf(j);
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
                sortName: 'id',
                showToggle: false,
                showExport: false,
                columns: [
                    [{
                            field: 'state',
                            checkbox: true,
                        },
                        {
                            field: 'title',
                            title: __('Title'),
                            align: 'left'
                        },
                        {
                            field: 'image',
                            title: __('Image'),
                            operate: false,
                            events: Table.api.events.image,
                            formatter: Table.api.formatter.image
                        },
                        {
                            field: 'status_text',
                            title: __('Status'),
                            // formatter: Table.api.formatter.status,
                        },
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            formatter: Table.api.formatter.datetime,
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            sortable: true
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
                if (Backend.api.query('type') == 'activity') {
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

            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            //绑定TAB事件
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                // var options = table.bootstrapTable(tableOptions);
                var typeStr = $(this).attr("href").replace('#', '');
                var options = table.bootstrapTable('getOptions');
                options.pageNumber = 1;
                options.queryParams = function (params) {
                    // params.filter = JSON.stringify({type: typeStr});
                    params.type = typeStr;
                    params.status = typeStr.replace('t-', '');
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

        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },

    };
    return Controller;
});