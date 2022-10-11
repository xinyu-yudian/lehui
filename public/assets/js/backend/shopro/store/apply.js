const { Callbacks } = require("jquery");

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var applyIndex = new Vue({
                el: "#applyIndex",
                data() {
                    return {
                        isAjax: true,
                        isAjaxtable: false,
                        activeTabsName: 'all',
                        tabsList: [{
                                name: 'all',
                                label: '全部'
                            },{
                                name: '0',
                                label: '待审核'
                            },{
                                name: '1',
                                label: '已同意'
                            },{
                                name: '-1',
                                label: '已拒绝'
                            }],
                        searchForm: {
                            status: "all",
                            name: "",
                            realname: "",
                            phone: "",
                        },
                        searchFormInit: {
                            status: "all",
                            name: "",
                            realname: "",
                            phone: "",
                        },
                        searchOp: {
                            status: "=",
                            name: "like",
                            realname: "like",
                            phone: "like",
                        },
                        listData: [],
                        offset: 0,
                        limit: 10,
                        totalPage: 0,
                        currentPage: 1,
                        rejectReason:""
                    }
                },
                created() {
                    this.getListData();
                },
                methods: {
                    getListData() {
                        let that = this;
                        if (!that.isAjax) {
                            that.isAjaxtable = true;
                        }
                        let filter = {}
                        let op = {}
                        for (key in that.searchForm) {
                            if (that.searchForm[key] != '' && that.searchForm[key] != 'all') {
                                filter[key] = that.searchForm[key];
                            }
                        }
                        for (key in filter) {
                            op[key] = that.searchOp[key]
                        }
                        Fast.api.ajax({
                            url: 'shopro/store/apply/index',
                            loading: false,
                            type: 'GET',
                            data: {
                                offset: that.offset,
                                limit: that.limit,
                                filter: JSON.stringify(filter),
                                op: JSON.stringify(op)
                            },
                        }, function (ret, res) {
                            that.listData = res.data.rows;
                            that.totalPage = res.data.total;
                            that.isAjax = false;
                            that.isAjaxtable = false;
                            return false;
                        }, function (ret, res) {
                            that.isAjax = false;
                            that.isAjaxtable = false;
                            return false;
                        })
                    },
                    screenEmpty() {
                        this.searchForm = JSON.parse(JSON.stringify(this.searchFormInit))
                    },
                    operation(type, id, index) {
                        let that = this;
                        switch (type) {
                            case 'cancel':
                                that.$refs['refNamePopover'+index].doClose();
                                that.rejectReason='';
                                break;
                            case 'reject':
                                Fast.api.ajax({
                                    url: `shopro/store/apply/applyOper/id/${id}`,
                                    loading: false,
                                    type: 'POST',
                                    data: {
                                        status:'-1',
                                        status_msg:that.rejectReason
                                    }
                                }, function (ret, res) {
                                    that.$refs['refNamePopover'+index].doClose();
                                    that.rejectReason='';
                                    that.getListData();
                                }, function (ret, res) {
                                    that.$refs['refNamePopover'+index].doClose();
                                    that.rejectReason='';
                                })
                                break;
                            case 'agree':
                                Fast.api.ajax({
                                    url: `shopro/store/apply/applyOper/id/${id}`,
                                    loading: false,
                                    type: 'POST',
                                    data: {
                                        status:'1',
                                    }
                                }, function (ret, res) {
                                    that.getListData();
                                    Fast.api.open(`shopro/store/store/edit?ids=${res.data.store.id}`, "门店详情");
                                    return false;
                                })
                                break;
                            case 'view':
                                Fast.api.open(`shopro/store/apply/detail?ids=${id}`, "查看详情",{
                                    callback(){
                                        that.getListData();
                                    }
                                });
                                break;
                        }
                    },
                    handleSizeChange(val) {
                        this.offset = 0
                        this.limit = val;
                        this.currentPage = 1;
                        this.getListData()
                    },
                    handleCurrentChange(val) {
                        this.currentPage = val;
                        this.offset = (val - 1) * this.limit;
                        this.getListData()
                    },
                    tabshandleClick(value) {
                        this.offset = 0;
                        this.limit = 10;
                        this.totalPage = 0;
                        this.currentPage = 1;
                        this.getListData();
                    },
                    tableCellClassName({
                        columnIndex
                    }) {
                        if (columnIndex == 0 || columnIndex == 6 || columnIndex == 8) {
                            return 'cell-left';
                        }
                        return '';
                    },
                },
            })
        },
        detail: function () {
            var applyDetail = new Vue({
                el: "#applyDetail",
                data() {
                    return {
                        detailForm:{},
                    }
                },
                created() {
                    this.detailForm=Config.row;
                },
                methods: {
                    operation(type, id, index) {
                        let that = this;
                        switch (type) {
                            case 'reject':
                                if(!that.detailForm.status_msg){
                                    that.$message({
                                        message: '请填写拒绝理由',
                                        type: 'warning'
                                      });
                                    return false;
                                }
                                Fast.api.ajax({
                                    url: `shopro/store/apply/applyOper/id/${that.detailForm.id}`,
                                    loading: false,
                                    type: 'POST',
                                    data: {
                                        status:'-1',
                                        status_msg:that.detailForm.status_msg
                                    }
                                }, function (ret, res) {
                                    Fast.api.close()
                                })
                                break;
                            case 'agree':
                                Fast.api.ajax({
                                    url: `shopro/store/apply/applyOper/id/${that.detailForm.id}`,
                                    loading: false,
                                    type: 'POST',
                                    data: {
                                        status:'1',
                                    }
                                }, function (ret, res) {
                                    window.location.reload();
                                    parent.Fast.api.open(`shopro/store/store/edit?ids=${res.data.store.id}`, "门店详情",{
                                        callback(){
                                            Fast.api.close()
                                        }
                                    });
                                })
                                break;
                        }
                    },
                },
            })
        },
    };
    return Controller;
});