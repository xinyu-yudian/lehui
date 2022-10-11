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
            var fansIndex = new Vue({
                el: "#fansIndex",
                data() {
                    return {
                        searchKey: '',
                        fansList: [],
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,
                        limit: 10,
                    }
                },
                mounted() {
                    this.getfansList();
                },
                methods: {
                    getfansList() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/fans/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                offset: that.offset,
                                limit: that.limit,
                                searchWhere: that.searchKey,
                            }
                        }, function (ret, res) {
                            that.fansList = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    handleSizeChange(val) {
                        this.limit = val;
                        this.offset=0;
                        this.currentPage= 1;
                        this.getfansList();
                    },
                    handleCurrentChange(val) {
                        this.limit=10;
                        this.offset = this.limit*(val-1);
                        this.currentPage= 1;
                        this.getfansList();
                    },
                    viewBtn(openid) {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/fans/user?openid='+openid,
                            loading: true,
                            type: 'GET',
                        }, function (ret, res) {
                            Fast.api.open('shopro/user/user/profile?id=' + res.data,"查看详情")
                        })
                    },
                    getSync() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/fans/syncfans',
                            loading: true,
                            type: 'GET',
                        }, function (ret, res) {
                            if (res.code == 1) {
                                that.getfansList();
                            }
                        })
                    },
                    tableRowClassName({rowIndex}) {
                        if (rowIndex % 2 == 1) {
                            return 'bg-color';
                        }
                        return '';
                    },
                    tableCellClassName({columnIndex}) {
                        if (columnIndex == 1 || columnIndex == 2) {
                            return 'cell-left';
                        }
                        return '';
                    },
                    debounceFilter: debounce(function () {
                        this.getfansList()
                    }, 1000),
                },
                watch: {
                    searchKey(newVal, oldVal) {
                        if (newVal != oldVal) {
                            this.offset = 0;
                            this.limit=10
                            this.currentPage= 1;
                            this.debounceFilter();
                        }
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