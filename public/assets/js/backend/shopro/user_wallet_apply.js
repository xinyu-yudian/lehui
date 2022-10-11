define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var userWalletApply = new Vue({
                el: "#userWalletApply",
                data() {
                    return {
                        walletApplyData: [],
                        tableCheckedAll: false,
                        isIndeterminate: false,
                        multipleTable: [],
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,
                        limit: 10,
                        // form搜索
                        filterRule: {
                            apply_type: [],
                            status: [],
                        },
                        searchForm: {
                            createtime: [moment().startOf('day').format('YYYY-MM-DD HH:mm:ss'), moment().endOf('day').format('YYYY-MM-DD HH:mm:ss')],
                            updatetime: [],
                            apply_type: 'all',
                            status: "all",
                            form_1_key: "user_id",
                            form_1_value: "",
                        },
                        searchFormInit: {
                            createtime: [moment().startOf('day').format('YYYY-MM-DD HH:mm:ss'), moment().endOf('day').format('YYYY-MM-DD HH:mm:ss')],
                            updatetime: [],
                            apply_type: 'all',
                            status: "all",
                            form_1_key: "user_id",
                            form_1_value: "",
                        },
                        searchOp: {
                            createtime: "range",
                            updatetime: "range",
                            apply_type: '=',
                            status: "=",
                            user_id: "=",
                            user_nickname: "like",
                            user_mobile: "like"
                        },
                        pickerOptions: {
                            shortcuts: [{
                                text: '今天',
                                onClick(picker) {
                                    const start = moment(new Date()).format('YYYY-MM-DD') + ' 00:00:00';
                                    const end = moment(new Date()).format('YYYY-MM-DD') + ' 23:59:59';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '昨天',
                                onClick(picker) {
                                    const start = moment(new Date()).add(-1, 'days').format('YYYY-MM-DD') + ' 00:00:00';
                                    const end = moment(new Date()).add(-1, 'days').format('YYYY-MM-DD') + ' 23:59:59';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '最近一周',
                                onClick(picker) {
                                    const end = moment(new Date()).format('YYYY-MM-DD') + ' 23:59:59';
                                    const start = moment(new Date()).add(-7, 'days').format('YYYY-MM-DD') + ' 00:00:00';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '最近一个月',
                                onClick(picker) {
                                    const end = moment(new Date()).format('YYYY-MM-DD') + ' 23:59:59';
                                    const start = moment(new Date()).add(-30, 'days').format('YYYY-MM-DD') + ' 00:00:00';
                                    picker.$emit('pick', [start, end]);
                                }
                            }, {
                                text: '上月',
                                onClick(picker) {
                                    const end = moment().month(moment().month() - 1).endOf('month').format('YYYY-MM-DD') + ' 23:59:59';
                                    const start = moment().month(moment().month() - 1).startOf('month').format('YYYY-MM-DD') + ' 00:00:00';
                                    picker.$emit('pick', [start, end]);
                                }
                            }]
                        },
                        // 同意
                        agreeDialogVisible: false,
                        agreeRow: {},
                        // 立即打款
                        immediatelyDialogVisible: false,
                        immediatelyRow: {},
                        // 拒绝
                        refuseDialogVisible: false,
                        refuseRow: {},
                        refuseForm: {
                            status: '',
                            status_msg: ''
                        },
                        // 日志
                        logDialogVisible: false,
                        logList: [],
                    }
                },
                mounted() {
                    this.getType()
                    this.getWalletApply()
                },
                methods: {
                    getType() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/user_wallet_apply/getType',
                            loading: true,
                            type: 'GET',
                        }, function (ret, res) {
                            that.filterRule.apply_type = res.data.apply_type;
                            that.filterRule.status = res.data.status;
                            return false;
                        })
                    },
                    getWalletApply(offset = 0, limit = 10) {
                        var that = this;
                        that.offset = offset;
                        that.limit = limit;
                        let filter = {}
                        let op = {}
                        for (key in that.searchForm) {
                            if (key == 'status' || key == 'apply_type') {
                                if (that.searchForm[key] !== '' && that.searchForm[key] != 'all') {
                                    filter[key] = that.searchForm[key];
                                }
                            } else if (key == 'form_1_value') {
                                if (that.searchForm[key] != '') {
                                    filter[that.searchForm.form_1_key] = that.searchForm[key];
                                }
                            } else if (key == 'createtime' || key == 'updatetime') {
                                if (that.searchForm[key]) {
                                    if (that.searchForm[key].length > 0) {
                                        filter[key] = that.searchForm[key].join(' - ');
                                    }
                                }
                            }
                        }
                        for (key in filter) {
                            op[key] = that.searchOp[key]
                        }
                        // return false;
                        Fast.api.ajax({
                            url: 'shopro/user_wallet_apply/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                filter: JSON.stringify(filter),
                                op: JSON.stringify(op),
                                offset: offset,
                                limit: limit,
                            }
                        }, function (ret, res) {
                            that.walletApplyData = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    handleSizeChange(val) {
                        this.currentPage = 1;
                        this.getWalletApply(0, val)
                    },
                    handleCurrentChange(val) {
                        this.currentPage = val;
                        this.getWalletApply((val - 1) * this.limit, this.limit)
                    },
                    filterEmpty() {
                        this.searchForm = JSON.parse(JSON.stringify(this.searchFormInit));
                        this.currentPage = 1;
                        this.getWalletApply(0, 0);
                    },
                    filterConfirm() {
                        this.currentPage = 1;
                        this.getWalletApply(0, 10)
                    },
                    changeApplyStatus(tab, event) {
                        this.currentPage = 1;
                        this.getWalletApply(0, 10)
                    },
                    changeCheckedAll(val) {
                        if (val) {
                            this.walletApplyData.forEach(row => {
                                this.$refs.multipleTable.toggleRowSelection(row, true);
                            });
                            this.isIndeterminate = false;
                        } else {
                            this.$refs.multipleTable.clearSelection();
                        }
                    },
                    tableSelect(val) {
                        this.multipleSelection = val;
                        this.tableCheckedAll = this.multipleSelection.length == 0 ? false : true
                        this.isIndeterminate = (this.multipleSelection.length == 0 || this.multipleSelection.length == this.walletApplyData.length) ? false : true;
                    },
                    checkSelectable(row) {
                        return this.searchForm.status == 0 || this.searchForm.status == 1 ? true : false
                    },
                    // 导出
                    exportApply() {
                        let that = this;
                        let filter = {}
                        let op = {}
                        for (key in that.searchForm) {
                            if (key == 'status' || key == 'apply_type') {
                                if (that.searchForm[key] !== '' && that.searchForm[key] != 'all') {
                                    filter[key] = that.searchForm[key];
                                }
                            } else if (key == 'form_1_value') {
                                if (that.searchForm[key] != '') {
                                    filter[that.searchForm.form_1_key] = that.searchForm[key];
                                }
                            } else if (key == 'createtime' || key == 'updatetime') {
                                if (that.searchForm[key]) {
                                    if (that.searchForm[key].length > 0) {
                                        filter[key] = that.searchForm[key].join(' - ');
                                    }
                                }
                            }
                        }
                        for (key in filter) {
                            op[key] = that.searchOp[key]
                        }
                        window.location.href = `${Config.moduleurl}/shopro/user_wallet_apply/export?filter=${JSON.stringify(filter)}&op=${JSON.stringify(op)}&offset=${that.offset}&limit=${that.limit}`;
                    },
                    // 同意
                    openAgreeDialog(row) {
                        this.agreeDialogVisible = true;
                        this.agreeRow = row;
                    },
                    agreePayment() {
                        this.reqApplyOper(this.agreeRow, 1, '')
                    },
                    closeAgreeDialog() {
                        this.agreeDialogVisible = false;
                    },
                    // 同意&打款
                    confirmPayment() {
                        this.reqApplyOper(this.agreeRow, 3, '')
                    },
                    // 立即打款
                    openImmediatelyDialog(row) {
                        this.immediatelyDialogVisible = true
                        this.immediatelyRow = row;
                    },
                    closeImmediatelyDialog() {
                        this.immediatelyDialogVisible = false
                    },
                    immediatelyPayment() {
                        this.reqApplyOper(this.immediatelyRow, 2, '')
                    },
                    // 拒绝
                    openRefuseDialog(row) {
                        this.refuseDialogVisible = true;
                        this.refuseRow = row;
                    },
                    refusePayment() {
                        this.reqApplyOper(this.refuseRow, -1, this.refuseForm.status_msg)
                    },
                    closeRefuseDialog() {
                        this.refuseDialogVisible = false;
                        this.refuseForm.status_msg = "";
                    },
                    reqApplyOper(row, status, status_msg) {
                        let that = this;
                        let ids = ''
                        if (Array.isArray(row)) {
                            let idsArr = []
                            row.forEach(m => {
                                idsArr.push(m.id)
                            })
                            ids = idsArr.join(',')
                        } else {
                            ids = row.id
                        }
                        let reaData = {
                            operate: status
                        }
                        if (status == -1) {
                            reaData = {
                                operate: status,
                                rejectInfo: status_msg
                            }
                        }
                        Fast.api.ajax({
                            url: `shopro/user_wallet_apply/handle/ids/${ids}`,
                            loading: true,
                            type: 'POST',
                            data: reaData //同意 1  立即打款 2   同意&打款3
                        }, function (ret, res) {
                            that.getWalletApply(that.offset, that.limit);
                            that.immediatelyDialogVisible = false
                            that.agreeDialogVisible = false
                            that.refuseDialogVisible = false;
                            that.refuseForm.status_msg = "";
                            return false;
                        })
                    },
                    openUser(id) {
                        Fast.api.open('shopro/user/user/profile?id=' + id, '查看')
                    },
                    openLogDialog(id) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/user_wallet_apply/log?id=${id}`,
                            loading: true,
                            type: 'GET',
                        }, function (ret, res) {
                            that.logList = res.data;
                            that.logDialogVisible = true;
                            return false;
                        })
                    },
                    closeLogDialog() {
                        this.logDialogVisible = false;
                    },
                    // 复制
                    copyMessage(key) {
                        let that = this;
                        let clipboard = new Clipboard('.custom-copy-message' + key)
                        let copynum = 0
                        clipboard.on('success', function () {
                            that.$message({
                                message: '复制成功',
                                type: 'success'
                            });
                            copynum++;
                            if (copynum >= 1) {
                                clipboard.destroy();
                                clipboard = new Clipboard('.custom-copy-message' + key);
                            };
                        });
                        clipboard.on('error', function () {
                            that.$message.error('复制失败');
                        });
                    },
                },
            })
        },
    };
    return Controller;
});