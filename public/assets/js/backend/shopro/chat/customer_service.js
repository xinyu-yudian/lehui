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
            var customerServiceIndex = new Vue({
                el: "#customerServiceIndex",
                data() {
                    return {
                        data: [],
                        search: '',

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
                            url: 'shopro/chat/customer_service/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                search: that.search,
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
                                Fast.api.open('shopro/chat/customer_service/add', '查看', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'edit':
                                Fast.api.open('shopro/chat/customer_service/edit?ids=' + id, '编辑', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'del':
                                that.$confirm('此操作将删除客服, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/chat/customer_service/del/ids/' + id,
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
                    isShoose() {
                        this.chooseType == 0 ? 1 : 0;
                        if (this.chooseType == 0) {
                            this.activityType = 'all';
                            this.priceFrist = "";
                            this.priceLast = "";
                        }
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
                    search(newVal, oldVal) {
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
            Controller.initEdit('add');
        },
        edit: function () {
            Controller.initEdit('edit');
        },
        initEdit: function (type) {
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
            var customerServiceDetail = new Vue({
                el: "#customerServiceDetail",
                data() {
                    return {
                        optType: type,
                        detailForm: {
                            admin_id: '',
                            avatar: '',
                            max_num: 1,
                            name: '',
                            status: "online",
                        },
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入客服昵称',
                                trigger: 'blur'
                            }],
                            avatar: [{
                                required: true,
                                message: '请输入客服头像',
                                trigger: 'blur'
                            }],
                            admin_id: [{
                                required: true,
                                message: '请选择管理员',
                                trigger: 'blur'
                            }],
                            max_num: [{
                                required: true,
                                message: '请输入接待上限',
                                trigger: 'blur'
                            }],
                            status: [{
                                required: true,
                                message: '请选择状态',
                                trigger: 'blur'
                            }],
                        },

                        adminList: [],

                        searchPage: '',

                        offset: 0,
                        limit: 6,
                        totalPage: 0,
                        currentPage: 1,

                    }
                },
                created() {
                    this.getAdminList();
                    if (this.optType == 'edit') {
                        for (var key in this.detailForm) {
                            this.detailForm[key] = Config.row[key]
                        }
                    }
                },
                methods: {
                    getAdminList() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/admin/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                offset: that.offset,
                                limit: that.limit,
                                search: that.searchPage,
                                type: 'customer_service',
                                type_id: this.optType == 'edit' ? Config.row.id : 0
                            }
                        }, function (ret, res) {
                            that.adminList = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    debounceFilter: debounce(function () {
                        this.getAdminList()
                    }, 1000),
                    dataFilter(val) {
                        this.searchPage = val;
                        this.limit = 6;
                        this.offset = 0;
                        this.currentPage = 1;
                        this.debounceFilter();
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * this.limit;
                        this.limit = 6;
                        this.currentPage = val;
                        this.getAdminList();
                    },
                    addAvatar() {
                        let that = this;
                        Fast.api.open("general/attachment/select?multiple=false", "选择头像", {
                            callback: function (data) {
                                that.detailForm.avatar = data.url;
                            }
                        });
                    },
                    submitForm(formName) {
                        let that = this;
                        this.$refs[formName].validate((valid) => {
                            if (valid) {
                                if (that.optType == 'add') {
                                    Fast.api.ajax({
                                        url: 'shopro/chat/customer_service/add',
                                        loading: true,
                                        type: 'POST',
                                        data: that.detailForm
                                    }, function (ret, res) {
                                        Fast.api.close();
                                    })
                                } else {
                                    Fast.api.ajax({
                                        url: 'shopro/chat/customer_service/edit?ids=' + Config.row.id,
                                        loading: true,
                                        type: 'POST',
                                        data: that.detailForm
                                    }, function (ret, res) {
                                        Fast.api.close();
                                    })
                                }
                            }
                        })
                    }
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