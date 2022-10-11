define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var replyIndex = new Vue({
                el: "#replyIndex",
                data() {
                    return {
                        listData: [],
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,
                        limit: 10,

                        activeName: 'auto_reply',

                        rules: {
                            type: [{
                                required: true,
                                message: '请选择类型',
                                trigger: 'blur'
                            }],
                            content_title: [{
                                required: true,
                                message: '请选择回复内容',
                                trigger: 'blur'
                            }],

                        },
                        detailForm: {
                            type: 'news',
                            content_title: '',
                            content_id: {},
                            content: {}
                        },
                        detailFormInit: {
                            type: 'news',
                            content_title: '',
                            content_id: {},
                            content: {}
                        },
                        detailFormOld: {
                            type: '',
                            content_title: '',
                            content_id: {},
                            content: {}
                        },

                        options: [],
                        selectLimit: 6,
                        selectOffset: 0,
                        selectCurrentPage: 1,
                        selectTotalPage: 0,
                    }
                },
                mounted() {
                    this.getList();
                },
                methods: {
                    getFormData() {
                        let that = this;
                        Fast.api.ajax({
                            url: "shopro/wechat/auto_reply/index?type=" + that.activeName,
                            loading: true,
                            type: 'GET',
                        }, function (ret, res) {
                            if (res.data.rows.length > 0) {
                                let content = JSON.parse(res.data.rows[0].content)
                                that.detailForm.type = content.type
                                that.detailForm.content_id = content
                                that.detailForm.content_title = content.media_id
                                //保存数据
                                that.detailFormOld.type = content.type
                                that.detailFormOld.content_id = content
                                that.detailFormOld.content_title = content.media_id;

                                if (that.detailForm.type == 'image') {
                                    that.detailForm.content_id.thumb_url = content.url
                                    that.detailFormOld.content_id.thumb_url = content.url
                                } else if (that.detailForm.type == 'video') {
                                    that.detailForm.content_id.thumb_url = content.cover_url
                                    that.detailFormOld.content_id.thumb_url = content.cover_url
                                } else if (that.detailForm.type == 'voice') {

                                } else if (that.detailForm.type == 'text') {
                                    that.detailForm.content_title = content.id
                                    that.detailFormOld.content_title = content.id
                                } else if (that.detailForm.type == 'link') {
                                    that.detailForm.content_title = content.id
                                    that.detailForm.content_id.thumb_url = content.image

                                    that.detailFormOld.content_title = content.id
                                    that.detailFormOld.content_id.thumb_url = content.image
                                }
                            } else {
                                that.detailForm = JSON.parse(JSON.stringify(that.detailFormInit))
                            }
                            that.getoptions()
                            return false;
                        })
                    },
                    typeChange() {
                        this.getoptions()
                        this.detailForm.content_title = "";
                        this.detailForm.content_id = {}
                    },
                    selectChange(val) {
                        this.options.forEach(i => {
                            if (val == i.media_id) {
                                this.detailForm.content_id = i
                            }
                        })
                        if (this.detailForm.type == 'news') {
                            let num = 0
                            this.options.forEach(i => {
                                if (i.media_id == val) {
                                    num++
                                }
                            })
                            if (num > 1) {
                                this.detailForm.content_title = ''
                                this.$notify({
                                    title: '警告',
                                    message: '不支持选择多条图文消息',
                                    type: 'warning'
                                });
                                return false;
                            }
                        }
                    },
                    tabClick() {
                        let that = this;
                        switch (that.activeName) {
                            case 'subscribe':
                                that.getFormData();
                                break;
                            case 'auto_reply':
                                that.getList();
                                break;
                            case 'default_reply':
                                that.getFormData();
                                break;
                        }
                    },
                    operation(opttype, id) {
                        let that = this;
                        switch (opttype) {
                            case 'delete':
                                that.$confirm('此操作将删除菜单, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/wechat/auto_reply/del/ids/' + id,
                                        loading: true,
                                        type: 'POST',
                                    }, function (ret, res) {
                                        that.getList()
                                    })
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消删除'
                                    });
                                });

                                break;
                            case 'create':
                                Fast.api.open("shopro/wechat/auto_reply/add", "新建", {
                                    callback(data) {
                                        that.getList();
                                    }
                                });
                                break;
                            case 'edit':
                                Fast.api.open("shopro/wechat/auto_reply/edit?id=" + id, "编辑", {
                                    callback(data) {
                                        that.getList();
                                    }
                                });
                                break;
                            case 'filter':
                                let types = ''
                                switch (id) {
                                    case 'news':
                                        types = '图文消息'
                                        break;
                                    case 'image':
                                        types = '图片'
                                        break;
                                    case 'video':
                                        types = '视频'
                                        break;
                                    case 'voice':
                                        types = '音频'
                                        break;
                                    case 'text':
                                        types = '文本'
                                        break;
                                    case 'link':
                                        types = '链接'
                                        break;
                                }
                                return types
                                break;
                        }
                    },
                    //er的数据
                    getList() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/auto_reply/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                offset: that.offset,
                                limit: that.limit,
                                type: 'auto_reply'
                            }
                        }, function (ret, res) {
                            that.listData = res.data.rows;
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    //er的分页
                    pageSizeChange(val) {
                        this.limit = val
                        this.getList()
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * this.limit
                        this.getList()
                    },
                    //
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
                        if (columnIndex == 0 || columnIndex == 2) {
                            return 'cell-left';
                        }
                        return '';
                    },
                    getoptions() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/material/select',
                            loading: true,
                            type: 'GET',
                            data: {
                                limit: that.selectLimit,
                                offset: that.selectOffset,
                                type: that.detailForm.type
                            }
                        }, function (ret, res) {
                            that.options = [];
                            if (that.detailForm.type == 'news') {
                                res.data.rows.forEach(i => {
                                    i.content.news_item.forEach(e => {
                                        that.options.push({
                                            media_id: i.media_id,
                                            title: e.title,
                                            thumb_url: e.thumb_url
                                        })
                                    })
                                })
                            } else if (that.detailForm.type == 'image') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: i.url
                                    })
                                })
                            } else if (that.detailForm.type == 'video') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: i.cover_url
                                    })
                                })
                            } else if (that.detailForm.type == 'voice') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: ''
                                    })
                                })
                            } else if (that.detailForm.type == 'text') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.id,
                                        title: i.name,
                                        thumb_url: JSON.parse(i.content)
                                    })
                                })
                            } else if (that.detailForm.type == 'link') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.id,
                                        title: i.name,
                                        thumb_url: JSON.parse(i.content).url,
                                        image: JSON.parse(i.content).image,
                                        description: JSON.parse(i.content).description,
                                    })
                                })
                            }
                            that.selectTotalPage = res.data.total;
                            return false;
                        })
                    },
                    createTemplate() {
                        let that = this;
                        Fast.api.open(`shopro/wechat/material/add?type=${that.detailForm.type}`, '创建', {
                            callback(data) {
                                that.getoptions();
                            }
                        })
                    },
                    dispatchSub(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[issub].validate((valid) => {
                                if (valid) {
                                    let subData = JSON.parse(JSON.stringify(that.detailForm));
                                    subData.content.type = subData.type;
                                    let content_arr = subData.content_id
                                    if (subData.content.type == 'news') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                        subData.content.thumb_url = content_arr.thumb_url;
                                    } else if (subData.content.type == 'image') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                        subData.content.url = content_arr.thumb_url;
                                    } else if (subData.content.type == 'video') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                        subData.content.cover_url = content_arr.thumb_url;
                                    } else if (subData.content.type == 'voice') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                    } else if (subData.content.type == 'text') {
                                        subData.content.id = content_arr.media_id;
                                        subData.content.title = content_arr.title;
                                        subData.content.content = content_arr.thumb_url;
                                    } else if (subData.content.type == 'link') {
                                        subData.content.id = content_arr.media_id;
                                        subData.content.title = content_arr.title;
                                        subData.content.description = content_arr.description;
                                        subData.content.image = content_arr.image;
                                        subData.content.url = content_arr.thumb_url;
                                    }
                                    subData.type = that.activeName;
                                    delete subData.content_title
                                    delete subData.content_id
                                    Fast.api.ajax({
                                        url: 'shopro/wechat/auto_reply/edit?id=' + that.activeName,
                                        loading: true,
                                        data: {
                                            data: JSON.stringify(subData)
                                        }
                                    }, function (ret, res) {
                                        that.getFormData();
                                    })
                                } else {
                                    return false;
                                }
                            });
                        } else {
                            this.detailForm = JSON.parse(JSON.stringify(this.detailFormOld))
                            this.getoptions()
                        }
                    },
                    selectSizeChange(val) {
                        this.selectOffset = 0;
                        this.selectLimit = val;
                        this.selectCurrentPage = 1;
                        this.getoptions();
                    },
                    selectCurrentChange(val) {
                        this.selectOffset = (val - 1) * 6;
                        this.selectLimit = 6;
                        this.selectCurrentPage = 1;
                        this.getoptions();
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
            var replyDetail = new Vue({
                el: "#replyDetail",
                data() {
                    return {
                        optType: type,
                        detailForm: {},
                        ruleForm: {
                            name: [{
                                required: true,
                                message: '请输入标题',
                                trigger: 'blur'
                            }],
                            rules: [{
                                required: true,
                                message: '请输入关键字,空格确认',
                                trigger: 'blur'
                            }],
                            type: [{
                                required: true,
                                message: '请选择类型',
                                trigger: 'blur'
                            }],
                            content_title: [{
                                required: true,
                                message: '请选择回复内容',
                                trigger: 'blur'
                            }],
                        },
                        detailFormInit: {
                            name: '',
                            rules: [],
                            type: 'news',
                            content_title: '',
                            content_id: {},
                            content: {}
                        },

                        keys: '',

                        options: [],
                        detail_id: null,

                        limit: 6,
                        offset: 0,
                        currentPage: 1,
                        totalPage: 0,

                    }
                },
                mounted() {
                    this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit));
                    if (this.optType == 'edit') {
                        let contant = JSON.parse(Config.row.content)
                        this.detail_id = Config.row.id;
                        this.detailForm.name = Config.row.name;
                        this.detailForm.type = contant.type
                        this.detailForm.rules = Config.row.rules.split(',');
                        this.detailForm.content_title = contant.media_id;
                        if (this.detailForm.type == 'text' || this.detailForm.type == 'link') {
                            this.detailForm.content_title = contant.id;
                        }
                        this.detailForm.content_id = contant
                        this.getoptions()
                    }
                    this.getoptions()
                },
                methods: {
                    searchFile(val) {
                        if (val.trim()) {
                            if (this.detailForm.rules.indexOf(val.trim()) == -1) {
                                this.detailForm.rules.push(val.trim())
                                this.keys = ""
                            } else {
                                this.$notify({
                                    title: '警告',
                                    message: '已存在不可再次添加',
                                    type: 'warning'
                                });
                            }
                        } else {
                            this.$notify({
                                title: '警告',
                                message: '请输入关键字',
                                type: 'warning'
                            });
                        }
                    },
                    delKey(index) {
                        this.detailForm.rules.splice(index, 1)
                    },
                    selectChange(val) {
                        this.options.forEach(i => {
                            if (val == i.media_id) {
                                this.detailForm.content_id = i
                            }
                        })
                        if (this.detailForm.type == 'news') {
                            let num = 0
                            this.options.forEach(i => {
                                if (i.media_id == val) {
                                    num++
                                }
                            })
                            if (num > 1) {
                                this.detailForm.content_title = ''
                                this.$notify({
                                    title: '警告',
                                    message: '不支持选择多条图文消息',
                                    type: 'warning'
                                });
                                return false;
                            }
                        }
                    },
                    typeChange() {
                        this.getoptions()
                        this.detailForm.content_title = '';
                        this.detailForm.content_id = {}
                    },
                    getoptions() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/material/select',
                            loading: true,
                            type: 'GET',
                            data: {
                                limit: that.limit,
                                offset: that.offset,
                                type: that.detailForm.type
                            }
                        }, function (ret, res) {
                            that.options = [];
                            if (that.detailForm.type == 'news') {
                                res.data.rows.forEach(i => {
                                    i.content.news_item.forEach(e => {
                                        that.options.push({
                                            media_id: i.media_id,
                                            title: e.title,
                                            thumb_url: e.thumb_url
                                        })
                                    })
                                })
                            } else if (that.detailForm.type == 'image') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: i.url
                                    })
                                })
                            } else if (that.detailForm.type == 'video') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: i.cover_url
                                    })
                                })
                            } else if (that.detailForm.type == 'voice') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: ''
                                    })
                                })
                            } else if (that.detailForm.type == 'text') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.id,
                                        title: i.name,
                                        thumb_url: JSON.parse(i.content)
                                    })
                                })
                            } else if (that.detailForm.type == 'link') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.id,
                                        title: i.name,
                                        thumb_url: JSON.parse(i.content).url,
                                        image: JSON.parse(i.content).image,
                                        description: JSON.parse(i.content).description,
                                    })
                                })
                            }
                            that.totalPage = res.data.total;
                            return false;
                        })
                    },
                    dispatchSub(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            that.$refs[issub].validate((valid) => {
                                if (valid) {
                                    let subData = JSON.parse(JSON.stringify(that.detailForm));
                                    subData.rules = subData.rules.join(',');
                                    subData.content.type = subData.type;

                                    let content_arr = subData.content_id
                                    if (subData.content.type == 'news') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                        subData.content.thumb_url = content_arr.thumb_url;
                                    } else if (subData.content.type == 'image') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                        subData.content.url = content_arr.thumb_url
                                    } else if (subData.content.type == 'video') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                        subData.content.cover_url = content_arr.thumb_url
                                    } else if (subData.content.type == 'voice') {
                                        subData.content.title = content_arr.title;
                                        subData.content.media_id = content_arr.media_id;
                                    } else if (subData.content.type == 'text') {
                                        subData.content.id = content_arr.media_id;
                                        subData.content.title = content_arr.title;
                                        subData.content.content = content_arr.thumb_url
                                    } else if (subData.content.type == 'link') {
                                        subData.content.id = content_arr.media_id;
                                        subData.content.title = content_arr.title;
                                        subData.content.description = content_arr.description
                                        subData.content.image = content_arr.image
                                        subData.content.url = content_arr.thumb_url
                                    }

                                    subData.type = 'auto_reply';
                                    delete subData.content_title
                                    delete subData.content_id
                                    if (that.optType != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/wechat/auto_reply/edit?id=' + that.detail_id,
                                            loading: true,
                                            data: {
                                                data: JSON.stringify(subData)
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close()
                                        })
                                    } else {
                                        Fast.api.ajax({
                                            url: 'shopro/wechat/auto_reply/add',
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
                            })
                        } else {
                            Fast.api.close()
                        }
                    },
                    createTemplate() {
                        let that = this;
                        Fast.api.open(`shopro/wechat/material/add?type=${that.detailForm.type}`, '增加文本', {
                            callback(data) {
                                that.getoptions();
                            }
                        })
                    },
                    //分页
                    pageSizeChange(val) {
                        this.offset = 0;
                        this.limit = val;
                        this.currentPage = 1;
                        this.getoptions();
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * 6;
                        this.limit = 6;
                        this.currentPage = 1;
                        this.getoptions();
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