define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var userWalletApply = new Vue({
                el: "#userWalletApply",
                data() {
                    return {
                        fstatus: "0",
                        tableData: [],
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,
                        limit: 10,
                        tableCheckedAll: false,
                        isIndeterminate: false,
                        multipleSelection: [],
                    }
                },
                mounted() {
                    this.getData()
                },
                methods: {
                    changeStatus() {
                        this.getData(0, 10)
                    },
                    getData(offset, limit) {
                        var that = this;
                        offset ? offset : that.offset;
                        limit ? limit : that.limit;
                        Fast.api.ajax({
                            url: 'shopro/order/invoice/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                filter: JSON.stringify({ status: that.fstatus }),
                                op: JSON.stringify({ status: "=" }),
                                offset: offset,
                                limit: limit,
                            }
                        }, function (ret, res) {
                            that.tableData = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    handleSizeChange(val) {
                        this.currentPage = 1;
                        this.getData(0, val)
                    },
                    handleCurrentChange(val) {
                        this.currentPage = val;
                        this.getData((val - 1) * this.limit, this.limit)
                    },
                    changeCheckedAll(val) {
                        if (val) {
                            this.tableData.forEach(row => {
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
                        this.isIndeterminate = (this.multipleSelection.length == 0 || this.multipleSelection.length == this.tableData.length) ? false : true;
                    },
                    confirmRow(id) {
                        this.reqConfirm(id);
                    },
                    batchComfirm() {
                        let idsArr = [];
                        this.multipleSelection.forEach(v => {
                            idsArr.push(v.id)
                        })
                        this.reqConfirm(idsArr.join(","));
                    },
                    reqConfirm(ids) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/order/invoice/confirm',
                            loading: true,
                            type: 'POST',
                            data: {
                                ids: ids
                            }
                        }, function (ret, res) {
                            that.getData()
                        })
                    },
                    openOrderDetail(id) {
                        Fast.api.open('shopro/order/order/detail?id=' + id, '订单详情')
                    }
                },
            })
        }
    };
    return Controller;
});