const moment = require("moment");

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var grouponIndex = new Vue({
                el: "#groupon-index",
                data() {
                    return {
                        grouponType: '',
                        grouponName: '',
                        grouponOptions: [],
                        statusType: 'all',
                        grouponData: [],
                        searchKey: '',
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,
                        limit: 10,
                        dialogTeamDetail: false,
                        grouponGoodsData: [],
                        grouponTeamList: [],
                        is_define_grouponTeamListLeng: '',
                        is_dialog_opt: false,
                        temp: null
                    }
                },
                mounted() {
                    this.getgrouponOptions()
                },
                methods: {
                    selectChange(val) {
                        this.grouponType = val;
                        this.grouponOptions.forEach(i => {
                            if (i.id == val) {
                                this.grouponName = i.label
                            }
                        })
                        this.offset = 0;
                        this.currentPage = 1;
                        this.getgrouponData()
                    },
                    getgrouponOptions() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/activity/activity/all',
                            loading: true,
                            type: 'GET',
                            data: {
                                type: 'groupon'
                            }
                        }, function (ret, res) {
                            if (res.data.length) {
                                res.data.forEach(i => {
                                    that.grouponOptions.push({
                                        value: i.id,
                                        label: i.title
                                    })
                                })
                                that.grouponType = that.grouponOptions[0].value;
                                that.grouponName = that.grouponOptions[0].label;
                                that.getgrouponData();
                            }
                            return false;
                        })
                    },
                    getgrouponData() {
                        var that = this;
                        window.clearInterval(that.temp)
                        that.is_dialog_opt = false
                        Fast.api.ajax({
                            url: 'shopro/activity/groupon/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                sort: 'id',
                                order: 'desc',
                                offset: that.offset,
                                limit: that.limit,
                                activity_id: that.grouponType,
                                status: that.statusType,
                                search: that.searchKey,
                            }
                        }, function (ret, res) {
                            let arrMsg = []
                            res.data.rows.forEach(i => {
                                arrMsg.push({
                                    id: i.id,
                                    goods_title: i.goods.title,
                                    goods_image: i.goods.image,
                                    createtime: i.createtime,
                                    user_nickname: i.groupon_log[0].user_nickname,
                                    arr: i.groupon_log,
                                    expiretime: i.expiretime,
                                    // expiretime: 1590810077,
                                    countDown: '',
                                    status: i.status,
                                    status_text: i.status_text,
                                    num: i.num,
                                    current_num: i.current_num
                                })
                            })
                            that.totalPage = res.data.total
                            that.timer(arrMsg)
                            return false;
                        })
                    },
                    timer(arr) {
                        let that = this;
                        that.temp = setInterval(() => {
                            arr.forEach((item, index) => {
                                that.$set(arr[index], 'countDown', that.countDownFun(item.expiretime * 1000));
                            });
                            that.grouponData = arr
                        }, 1000);
                    },
                    countDownFun(time) {
                        time--;
                        let nowTime = new Date().getTime();
                        if (nowTime <= time) {
                            let timediff = Math.round((time - nowTime) / 1000);
                            let day = parseInt(timediff / 3600 / 24) > 9 ? parseInt(timediff / 3600 / 24) : '0' + parseInt(timediff / 3600 / 24);
                            let hour = parseInt((timediff / 3600) % 24) > 9 ? parseInt((timediff / 3600) % 24) : '0' + parseInt((timediff / 3600) % 24);
                            let minute = parseInt((timediff / 60) % 60) > 9 ? parseInt((timediff / 60) % 60) : '0' + parseInt((timediff / 60) % 60);
                            let second = timediff % 60 > 9 ? timediff % 60 : '0' + timediff % 60;
                            return day + "天" + hour + "时" + minute + "分" + second + "秒";
                        } else {
                            return "-";
                        }
                    },
                    goDetail(id, row) {
                        this.dialogTeamDetail = true
                        this.grouponGoodsData = []
                        this.grouponGoodsData.push(row)
                        this.grouponTeamList = JSON.parse(JSON.stringify(this.grouponGoodsData[0].arr));
                    },
                    handleSizeChange(val) {
                        this.offset = 0;
                        this.currentPage = 1;
                        this.limit = val
                        this.getgrouponData()
                    },
                    handleCurrentChange(val) {
                        this.currentPage = val;
                        this.offset = (val - 1) * this.limit
                        this.getgrouponData()
                    },
                    handleTeamDetailClose() {
                        this.dialogTeamDetail = false
                        if (this.is_dialog_opt) {
                            this.getgrouponData()
                        }
                    },
                    refreshTeamer(index) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/user_fake/random_user',
                            loading: true,
                            type: 'GET',
                            data: {}
                        }, function (ret, res) {
                            if (index == null) {
                                that.grouponTeamList.push({
                                    createtime: Date.parse(new Date()) / 1000,
                                    user_nickname: res.data.nickname,
                                    is_leader: false,
                                    user_avatar: res.data.avatar,
                                    is_fictitious: true,
                                    id: res.data.id,
                                    is_define: true
                                })

                            } else {
                                let arr = {
                                    createtime: Date.parse(new Date()) / 1000,
                                    user_nickname: res.data.nickname,
                                    is_leader: false,
                                    user_avatar: res.data.avatar,
                                    is_fictitious: true,
                                    id: res.data.id,
                                    is_define: true
                                }
                                for (key in arr) {
                                    that.grouponTeamList[index][key] = arr[key]
                                }
                            }
                            let leng = 0
                            that.grouponTeamList.forEach(i => {
                                if (!i.is_define) {
                                    leng++
                                }
                            })
                            that.is_define_grouponTeamListLeng = leng
                        })
                    },
                    defineTeamer(index, row) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/activity/groupon/addFictitious/id/${that.grouponGoodsData[0].id}`,
                            loading: true,
                            type: 'POST',
                            data: {
                                avatar: row.user_avatar,
                                nickname: row.user_nickname
                            }
                        }, function (ret, res) {
                            that.is_dialog_opt = true
                            that.grouponGoodsData = []
                            res.data.goods_title = res.data.goods.title
                            res.data.goods_image = res.data.goods.image
                            that.grouponGoodsData.push(res.data)
                            that.grouponTeamList = JSON.parse(JSON.stringify(that.grouponGoodsData[0].groupon_log));
                        })
                    },
                    cancelTeamer(index) {
                        this.grouponTeamList.splice(index, 1)
                    },
                    dismissTeam() {
                        let that = this;
                        that.dialogTeamDetail = false
                        Fast.api.ajax({
                            url: `shopro/activity/groupon/invalidGroupon/id/${that.grouponGoodsData[0].id}`,
                            loading: true,
                            type: 'POST',
                            data: {}
                        }, function (ret, res) {
                            that.getgrouponData()
                        })
                    },
                    callSearch(e) {
                        var evt = window.event || e;
                        if (evt.keyCode == 13) {
                            this.offset = 0;
                            this.currentPage = 1;
                            this.getgrouponData();
                        }
                    }
                },
                beforeDestroy() {
                    window.clearInterval(this.temp)
                },
                watch: {
                    statusType(newVal, oldVal) {
                        if (newVal, oldVal) {
                            this.currentPage = 1;
                            this.offset = 0;
                            this.limit = 10;
                            this.getgrouponData()
                        }
                    }
                },
            })
        },
        detail: function () {
            var vue = new Vue({
                el: "#groupon-detail",
                data() {
                    return {
                        grouponGoodsData: [],
                        grouponTeamList: [],
                        is_define_grouponTeamListLeng: '',
                        // is_dialog_opt:false,
                        temp: null
                    }
                },
                mounted() {
                    let id = Config.id
                    let that = this;
                    Fast.api.ajax({
                        url: `shopro/activity/groupon/detail/id/${id}`,
                        loading: true,
                        type: 'GET',
                        data: {}
                    }, function (ret, res) {
                        let arrMsg = []
                        let datas = []
                        datas.push(res.data)
                        datas.forEach(i => {
                            arrMsg.push({
                                id: i.id,
                                goods_title: i.goods?i.goods.title:"",
                                goods_image: i.goods?i.goods.image:"",
                                createtime: i.createtime,
                                user_nickname: i.groupon_log[0].user_nickname,
                                arr: i.groupon_log,
                                expiretime: i.expiretime,
                                status: i.status,
                                status_text: i.status_text,
                                num: i.num,
                                current_num: i.current_num
                            })
                        })
                        that.grouponGoodsData = arrMsg;
                        that.grouponTeamList = JSON.parse(JSON.stringify(that.grouponGoodsData[0].arr));
                        let leng = 0
                        that.grouponTeamList.forEach(i => {
                            if (!i.is_define) {
                                leng++
                            }
                        })
                        that.is_define_grouponTeamListLeng = leng
                        return false;
                    })
                },
                methods: {
                    refreshTeamer(index) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/user_fake/random_user',
                            loading: true,
                            type: 'GET',
                            data: {}
                        }, function (ret, res) {
                            if (index == null) {
                                that.grouponTeamList.push({
                                    createtime: Date.parse(new Date()) / 1000,
                                    user_nickname: res.data.nickname,
                                    is_leader: false,
                                    user_avatar: res.data.avatar,
                                    is_fictitious: true,
                                    id: res.data.id,
                                    is_define: true
                                })

                            } else {
                                let arr = {
                                    createtime: Date.parse(new Date()) / 1000,
                                    user_nickname: res.data.nickname,
                                    is_leader: false,
                                    user_avatar: res.data.avatar,
                                    is_fictitious: true,
                                    id: res.data.id,
                                    is_define: true
                                }
                                for (key in arr) {
                                    that.grouponTeamList[index][key] = arr[key]
                                }
                            }
                            let leng = 0
                            that.grouponTeamList.forEach(i => {
                                if (!i.is_define) {
                                    leng++
                                }
                            })
                            that.is_define_grouponTeamListLeng = leng
                        })
                    },
                    defineTeamer(index, row) {
                        let that = this;
                        Fast.api.ajax({
                            url: `shopro/activity/groupon/addFictitious/id/${that.grouponGoodsData[0].id}`,
                            loading: true,
                            type: 'POST',
                            data: {
                                avatar: row.user_avatar,
                                nickname: row.user_nickname
                            }
                        }, function (ret, res) {
                            // that.is_dialog_opt=true
                            that.grouponGoodsData = []
                            res.data.goods_title = res.data.goods.title
                            res.data.goods_image = res.data.goods.image
                            that.grouponGoodsData.push(res.data)
                            that.grouponTeamList = JSON.parse(JSON.stringify(that.grouponGoodsData[0].groupon_log));
                        })
                    },
                    cancelTeamer(index) {
                        this.grouponTeamList.splice(index, 1)
                    },
                    dismissTeam() {
                        let that = this;
                        that.dialogTeamDetail = false
                        Fast.api.ajax({
                            url: `shopro/activity/groupon/invalidGroupon/id/${that.grouponGoodsData[0].id}`,
                            loading: true,
                            type: 'POST',
                            data: {}
                        }, function (ret, res) {
                            Fast.api.close({
                                data: 123
                            })
                        })
                    },
                }
            })
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});