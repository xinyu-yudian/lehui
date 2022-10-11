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
            var companyIndex = new Vue({
                el: "#companyIndex",
                data() {
                    return {
                        indexData: [],
                        searchKey: '',

                        offset: 0,
                        limit: 10,
                        totalPage: 0,
                        currentPage: 1,
                        isAjax:false,
                    }
                },
                created() {
                    this.getData();
                },
                methods: {
                    getData() {
                        let that = this;
                        that.isAjax=true
                        Fast.api.ajax({
                            url: 'shopro/express/index',
                            loading: false,
                            type: 'GET',
                            data: {
                                searchWhere: that.searchKey,
                                offset: that.offset,
                                limit: that.limit,
                            }
                        }, function (ret, res) {
                            that.indexData = res.data.rows;
                            that.totalPage = res.data.total;
                            that.isAjax=false
                            return false;
                        }, function (ret, res) {
                            that.isAjax=false;
                        })
                    },
                    operation(type, id) {
                        let that = this;
                        switch (type) {
                            case 'create':
                                Fast.api.open("shopro/express/add", "新建", {
                                    callback(data) {
                                        if(data.data){
                                            that.getData();
                                        }
                                    }
                                });
                                break;
                            case 'edit':
                                parent.Fast.api.open("shopro/express/edit?ids=" + id, "编辑", {
                                    callback: function (data) {
                                        if(data.data){
                                            that.getData();
                                        }
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
                                    that.$confirm('此操作将删除快递公司, 是否继续?', '提示', {
                                        confirmButtonText: '确定',
                                        cancelButtonText: '取消',
                                        type: 'warning'
                                    }).then(() => {
                                        Fast.api.ajax({
                                            url: 'shopro/express/del/ids/' + ids,
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
                                Fast.api.open('shopro/express/recyclebin', '查看回收站')
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
        add: function () {
            Controller.detailInit('add');
        },
        edit: function () {
            Controller.detailInit('edit');
        },
        detailInit: function (type) {
            function urlParmas(par) {
                let value = ""
                window.location.search.replace("?", '').split("&").forEach(i => {
                    if (i.split('=')[0] == par) {
                        value = JSON.parse(decodeURI(i.split('=')[1]))
                    }
                })
                return value
            }
            var detailInit = new Vue({
                el: "#detailInit",
                data() {
                    return {
                        optType: type,
                        id: urlParmas('ids'),
                        detailForm: {},
                        detailFormInit: {
                            name: '',
                            code: '',
                            weigh: ''
                        },
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入快递公司',
                                trigger: 'blur'
                            }],
                            code: [{
                                required: true,
                                message: '请输入快递编号',
                                trigger: 'blur'
                            }],
                        }

                    }
                },
                created() {
                    this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit));
                    if (this.optType == 'edit') {
                        for(key in this.detailForm){
                            this.detailForm[key]=Config.row[key]
                        }
                    }
                },
                methods: {
                    getdetailForm() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/express/edit/ids/' + that.id,
                            loading: true,
                        }, function (ret, res) {
                            that.detailForm = res.data
                            return false;
                        })
                    },
                    submitForm(type, formName) {
                        let that = this;
                        if (type) {
                            this.$refs[formName].validate((valid) => {
                                if (valid) {
                                    let formData = JSON.stringify(that.detailForm);
                                    if (that.optType == 'edit') {
                                        Fast.api.ajax({
                                            url: 'shopro/express/edit/ids/' + that.id,
                                            loading: true,
                                            data: {
                                                data:formData
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close({data:true});
                                        })
                                    } else {
                                        Fast.api.ajax({
                                            url: 'shopro/express/add',
                                            loading: true,
                                            data: {
                                                data:formData
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close({data:true});
                                        })
                                    }
                                } else {
                                    return false;
                                }
                            });
                        } else {
                            Fast.api.close({data:false});
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