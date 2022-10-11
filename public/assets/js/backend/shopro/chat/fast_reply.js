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
            var fastReplyIndex = new Vue({
                el: "#fastReplyIndex",
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
                            url: 'shopro/chat/fast_reply/index',
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
                                Fast.api.open('shopro/chat/fast_reply/add', '查看', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'edit':
                                Fast.api.open('shopro/chat/fast_reply/edit?ids=' + id, '编辑', {
                                    callback() {
                                        that.getData();
                                    }
                                })
                                break;
                            case 'del':
                                that.$confirm('此操作将删除回复, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/chat/fast_reply/del/ids/' + id,
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
                        if (columnIndex == 1 || columnIndex == 5) {
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
            var fastReplyDetail = new Vue({
                el: "#fastReplyDetail",
                data() {
                    return {
                        optType: type,
                        detailForm: {
                            name: "",
                            content: '',
                            status: "normal",
                            weigh: 0
                        },
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入名称',
                                trigger: 'blur'
                            }],
                            content: [{
                                required: true,
                                message: '请输入内容',
                                trigger: 'blur'
                            }],
                            status: [{
                                required: true,
                                message: '请选择状态',
                                trigger: 'blur'
                            }],
                            weigh: [{
                                required: true,
                                message: '请输入权重',
                                trigger: 'blur'
                            }],
                        },
                    }
                },
                created() {},
                mounted() {
                    this.$nextTick(() => {
                        Controller.api.bindevent();
                    })
                    if (this.optType == 'edit') {
                        $('#c-content').html(Config.row.content);
                        for (var key in this.detailForm) {
                            this.detailForm[key] = Config.row[key]
                        }
                    }
                },
                methods: {
                    submitForm(formName) {
                        let that = this;
                        that.detailForm.content = $('#c-content').val();
                        this.$refs[formName].validate((valid) => {
                            if (valid) {
                                if (that.optType == 'add') {
                                    Fast.api.ajax({
                                        url: 'shopro/chat/fast_reply/add',
                                        loading: true,
                                        type: 'POST',
                                        data: that.detailForm
                                    }, function (ret, res) {
                                        Fast.api.close();
                                    })
                                } else {
                                    Fast.api.ajax({
                                        url: 'shopro/chat/fast_reply/edit?ids=' + Config.row.id,
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