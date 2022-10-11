const {
    contains,
    cssNumber
} = require("jquery");

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var listsIndex = new Vue({
                el: "#listsIndex",
                data() {
                    return {
                        listData: [],
                        currentMenu: [],
                        currentPage: 1,
                        limit: 10,
                        offset: 0,
                        totalPage: 0,

                    }
                },
                mounted() {
                    this.getList();
                },
                methods: {
                    getList() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/menu/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                limit: that.limit,
                                offset: that.offset,
                            }
                        }, function (ret, res) {
                            that.listData = res.data.rows;
                            that.totalPage = res.data.total;
                            that.currentMenu = res.data.currentMenu;
                            return false;
                        })
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
                                        url: 'shopro/wechat/menu/del/ids/' + id,
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
                            case 'status':
                                that.$confirm('确认发布当前菜单？公众号底部菜单有延迟生效时间，您可稍等或重新关注查看', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: `shopro/wechat/menu/publish?id=${id}`,
                                        loading: true,
                                    }, function (ret, res) {
                                        that.getList();
                                    })
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消'
                                    });
                                });
                                break;
                            case 'create':
                                Fast.api.open("shopro/wechat/menu/add", "新建", {
                                    callback(data) {
                                        that.getList();
                                    }
                                });
                                break;
                            case 'copy':
                                Fast.api.ajax({
                                    url: `shopro/wechat/menu/copy?id=${id}`,
                                    loading: true,
                                }, function (ret, res) {
                                    that.getList();
                                })
                                break;
                            case 'edit':
                                Fast.api.open(`shopro/wechat/menu/edit?id=${id}`, "编辑", {
                                    callback(data) {
                                        that.getList();
                                    }
                                });
                                break;
                        }
                    },
                    //分页
                    pageSizeChange(val) {
                        this.offset = 0;
                        this.limit = val;
                        this.getList();
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * 10;
                        this.getList();
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
                        if (columnIndex == 1 || columnIndex == 2) {
                            return 'cell-left';
                        }
                        return '';
                    },
                },
            })
        },
        add: function () {
            Controller.menu('add');
        },
        edit: function () {
            Controller.menu('edit');
        },
        menu: function (type) {
            var wechatMenu = new Vue({
                el: "#wechatMenu",
                data() {
                    return {
                        menuData: [],
                        rightData: {},
                        selectedIndex1: null,
                        selectedIndex2: null,
                        selectLevel: null,
                        rightShow: false,
                        menuTitle: '',
                        edit_id: null,
                        optType: type,
                        viewUrl: Config.shopro.domain,
                        wxMiniProgramapp_id: Config.wxMiniProgram.app_id,

                        options: [],
                        selectLimit: 6,
                        selectOffset: 0,
                        selectCurrentPage: 1,
                        selectTotalPage: 0,

                        detailForm: {
                            type: '',
                            content_id: {},
                            content: {},
                            content_title: ''
                        }
                    }
                },
                mounted() {
                    if (this.optType == 'edit') {
                        this.getmenuData();
                    }
                },
                methods: {
                    typeChange() {
                        this.getoptions()
                        this.rightData.media_id = "";
                    },
                    selectChange(val) {
                        if (this.rightData.media_type == 'news') {
                            let num = 0
                            this.options.forEach(i => {
                                if (i.media_id == val) {
                                    num++
                                }
                            })
                            if (num > 1) {
                                this.rightData.media_id = ''
                                this.$notify({
                                    title: '警告',
                                    message: '不支持选择多条图文消息',
                                    type: 'warning'
                                });
                                return false;
                            }
                        }
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
                                type: that.rightData.media_type
                            }
                        }, function (ret, res) {
                            that.options = [];
                            if (that.rightData.media_type == 'news') {
                                res.data.rows.forEach(i => {
                                    i.content.news_item.forEach(e => {
                                        that.options.push({
                                            media_id: i.media_id,
                                            title: e.title,
                                            thumb_url: e.thumb_url
                                        })
                                    })
                                })
                            } else if (that.rightData.media_type == 'image') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: i.url
                                    })
                                })
                            } else if (that.rightData.media_type == 'video') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: i.cover_url
                                    })
                                })
                            } else if (that.rightData.media_type == 'voice') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.media_id,
                                        title: i.name,
                                        thumb_url: ''
                                    })
                                })
                            } else if (that.rightData.media_type == 'text') {
                                res.data.rows.forEach(i => {
                                    that.options.push({
                                        media_id: i.id,
                                        title: i.name,
                                        thumb_url: JSON.parse(i.content)
                                    })
                                })
                            } else if (that.rightData.media_type == 'link') {
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
                    createTemplate() {
                        let that = this;
                        Fast.api.open(`shopro/wechat/material/add?type=${that.rightData.media_type}`, '创建', {
                            callback(data) {
                                that.getoptions();
                            }
                        })
                    },
                    getmenuData() {
                        var that = this;
                        if (Config.row.content) {
                            that.menuData = JSON.parse(Config.row.content);
                            that.menuData.forEach(i => {
                                i.selected = false;
                                i.show = false;
                                if (!i.appid) {
                                    i.appid = '';
                                    i.pagepath = '';
                                }
                                this.$set(i, 'media_type', i.key ? i.key.split('|')[0] : '')
                                this.$set(i, 'media_id', i.key ? i.key.split('|')[1] : '')
                                if (i.sub_button) {
                                    i.sub_button.forEach(j => {
                                        j.selected = false;
                                        if (!j.appid) {
                                            j.appid = '';
                                            j.pagepath = '';
                                        }
                                        this.$set(j, 'media_type', j.key ? j.key.split('|')[0] : '')
                                        this.$set(j, 'media_id', j.key ? j.key.split('|')[1] : '')
                                    })
                                } else {
                                    i.sub_button = []
                                }
                            })
                        } else {
                            that.menuData = []
                        }
                        that.edit_id = Config.row.id;
                        that.menuTitle = Config.row.name;
                    },
                    changeRadio(e) {
                        if (e == 'click') {
                            this.rightData.url = "";
                            this.rightData.appid = "";
                            this.rightData.pagepath = "";
                        } else {
                            this.rightData.key = ''
                        }

                        this.$forceUpdate();
                    },
                    menuSelect(index1, index2) {
                        this.selectedIndex1 = index1;
                        this.selectedIndex2 = index2;
                        this.rightShow = true;
                        this.menuData.forEach(i => {
                            i.selected = false;
                            i.show = false;
                            if (i.sub_button) {
                                i.sub_button.forEach(j => {
                                    j.selected = false;
                                })
                            }
                        });
                        this.menuData[index1].show = true;
                        //选择1
                        if (index2 == null) {
                            this.selectLevel = 1;
                            this.menuData[index1].selected = true;
                            this.menuData[index1].show = true;
                            this.rightData = this.menuData[index1];
                        } else {
                            this.selectLevel = 2;
                            this.menuData[index1].sub_button[index2].selected = true;
                            this.rightData = this.menuData[index1].sub_button[index2];
                        }
                        if (this.rightData.media_type) {
                            this.getoptions();
                        }
                    },
                    addMenu(index, level) {
                        //右侧显示
                        this.rightShow = true;
                        this.selectLevel = level;
                        if (index != null) {
                            this.selectedIndex1 = index;
                            this.menuData.forEach(i => {
                                i.selected = false;
                                if (i.sub_button) {
                                    i.sub_button.forEach(j => {
                                        j.selected = false;
                                    })
                                }
                            });
                            this.menuData[index].sub_button.push({
                                name: '',
                                type: 'view',
                                selected: true,
                                url: '',
                                appid: '',
                                pagepath: '',
                                media_type: '',
                                media_id: '',
                            })
                            this.rightData = this.menuData[index].sub_button[this.menuData[index].sub_button.length - 1];
                            this.selectedIndex2 = this.menuData[index].sub_button.length - 1;
                        } else {
                            this.menuData.forEach(i => {
                                i.selected = false;
                                i.show = false;
                            });
                            this.menuData.push({
                                name: '',
                                selected: true,
                                show: true,
                                type: 'view',
                                url: '',
                                appid: '',
                                pagepath: '',
                                sub_button: [],
                                media_type: '',
                                media_id: '',
                            })
                            this.selectedIndex1 = this.menuData.length - 1;
                            this.rightData = this.menuData[this.menuData.length - 1];
                        }
                    },
                    delMenu() {
                        if (this.selectedIndex2 != null) {
                            this.menuData[this.selectedIndex1].sub_button.splice(this.selectedIndex2, 1);
                            if (this.menuData[this.selectedIndex1].sub_button.length > 0) {
                                if (this.selectedIndex2 == 0) {
                                    this.menuData[this.selectedIndex1].sub_button[0].selected = true;
                                    this.rightData = this.menuData[this.selectedIndex1].sub_button[0];
                                } else {
                                    this.menuData[this.selectedIndex1].sub_button[this.selectedIndex2 - 1].selected = true;
                                    this.rightData = this.menuData[this.selectedIndex1].sub_button[this.selectedIndex2 - 1];
                                    this.selectedIndex2--
                                }
                            } else {
                                this.rightData = {};
                                this.rightShow = false;
                            }
                        } else {
                            this.menuData.splice(this.selectedIndex1, 1);
                            if (this.menuData.length > 0) {
                                if (this.selectedIndex1 == 0) {
                                    this.menuData[0].selected = true;
                                    this.menuData[0].show = true;
                                    this.rightData = this.menuData[0];
                                } else {
                                    this.menuData[this.selectedIndex1 - 1].selected = true;
                                    this.menuData[this.selectedIndex1 - 1].show = true;
                                    this.rightData = this.menuData[this.selectedIndex1 - 1];
                                    this.selectedIndex1--
                                }

                            } else {
                                this.rightData = {};
                                this.rightShow = false;
                            }
                        }
                    },
                    choosePath() {
                        let that = this;
                        let multiple = $(this).data("multiple") ? $(this).data("multiple") : false;
                        parent.Fast.api.open("shopro/link/select?multiple=" + multiple, "选择路径", {
                            callback: function (data) {
                                let link_path = ''
                                if (data.data.path != '/pages/index/index') {
                                    link_path = data.data.path.substring(1)
                                }
                                if (that.selectedIndex2 != null) {
                                    if (that.menuData[that.selectedIndex1].sub_button[that.selectedIndex2].type == 'view') {
                                        that.menuData[that.selectedIndex1].sub_button[that.selectedIndex2].url = that.viewUrl + link_path;
                                    } else {
                                        that.menuData[that.selectedIndex1].sub_button[that.selectedIndex2].pagepath = '/pages/index/index?page=' + encodeURIComponent(link_path);
                                        that.menuData[that.selectedIndex1].sub_button[that.selectedIndex2].url = that.viewUrl + link_path;
                                        that.menuData[that.selectedIndex1].sub_button[that.selectedIndex2].appid = that.wxMiniProgramapp_id;
                                        that.rightData.appid = that.wxMiniProgramapp_id;
                                    }
                                    that.rightData.url = that.menuData[that.selectedIndex1].sub_button[that.selectedIndex2].url;
                                } else {
                                    if (that.menuData[that.selectedIndex1].type == 'view') {
                                        that.menuData[that.selectedIndex1].url = that.viewUrl + link_path;
                                    } else {
                                        that.menuData[that.selectedIndex1].url = that.viewUrl + link_path;
                                        that.menuData[that.selectedIndex1].pagepath = link_path ? '/pages/index/index?page=' + encodeURIComponent(link_path) : '/pages/index/index';
                                        that.menuData[that.selectedIndex1].appid = that.wxMiniProgramapp_id;
                                        that.rightData.appid = that.wxMiniProgramapp_id;
                                    }
                                    that.rightData.url = that.menuData[that.selectedIndex1].url;
                                    that.rightData.pagepath = that.menuData[that.selectedIndex1].pagepath;
                                }
                            }
                        });
                    },
                    menuHide() {
                        this.selectedIndex1 = null;
                        this.selectedIndex2 = null;
                        this.menuData.forEach(i => {
                            i.selected = false;
                            i.show = false;
                            if (i.sub_button.length > 0) {
                                i.sub_button.forEach(j => {
                                    j.selected = false;
                                })
                            }
                        });
                        this.rightShow = false;
                    },
                    menuShow() {
                        this.rightShow = true;
                    },
                    subData(type) {
                        let that = this;
                        if (that.menuTitle == '') {
                            that.$notify({
                                title: '警告',
                                message: '请输入标题',
                                type: 'warning'
                            });
                            return false;
                        }
                        if (that.menuData.length == 0) {
                            that.$notify({
                                title: '警告',
                                message: '请输入菜单内容',
                                type: 'warning'
                            });
                            return false;
                        }
                        let savemenuData = JSON.parse(JSON.stringify(that.menuData))
                        savemenuData.forEach(i => {
                            delete i.show;
                            delete i.selected;
                            if (i.sub_button.length > 0) {
                                delete i.url;
                                delete i.appid;
                                delete i.pagepath;
                                delete i.type;
                                delete i.key;
                                i.sub_button.forEach(j => {
                                    delete j.selected;
                                    if (j.type == 'view') {
                                        delete j.appid;
                                        delete j.pagepath;
                                    } else if (j.type == 'click') {
                                        delete j.appid;
                                        delete j.pagepath;
                                        delete j.url;
                                        let type = j.media_type;
                                        let id = j.media_id
                                        this.$set(j, 'key', type + '|' + id)
                                    }
                                    delete j.media_type;
                                    delete j.media_id;
                                })
                            } else {
                                delete i.sub_button;
                                if (i.type) {
                                    if (i.type == 'view') {
                                        delete i.appid;
                                        delete i.pagepath;
                                    } else if (i.type == 'click') {
                                        delete i.appid;
                                        delete i.pagepath;
                                        delete i.url;
                                        let type = i.media_type
                                        let id = i.media_id
                                        this.$set(i, 'key', type + '|' + id)
                                    }
                                }
                            }
                            delete i.media_type;
                            delete i.media_id;
                        })
                        var urlsub = ''
                        if (that.optType == 'edit') {
                            if (type == 'publish') {
                                urlsub = 'shopro/wechat/menu/edit?id=' + that.edit_id + '&act=' + type
                            } else {
                                urlsub = 'shopro/wechat/menu/edit?id=' + that.edit_id
                            }
                        } else {
                            if (type == 'publish') {
                                urlsub = 'shopro/wechat/menu/add?act=' + type
                            } else {
                                urlsub = 'shopro/wechat/menu/add'
                            }
                        }
                        // return false;
                        Fast.api.ajax({
                            url: urlsub,
                            loading: true,
                            type: 'POST',
                            data: {
                                data: JSON.stringify({
                                    name: that.menuTitle,
                                    type: 'menu',
                                    content: savemenuData
                                })
                            }
                        }, function (ret, res) {
                            Fast.api.close()
                        })
                    },
                },
                watch: {
                    rightData: {
                        handler: function (newVal) {
                            if (this.selectLevel == 1) {
                                let name = newVal.name;
                                let num = 0;
                                for (var i = 0; i < name.length; i++) {
                                    if (name[i].charCodeAt() >= 20 && name[i].charCodeAt() <= 127) {
                                        num++
                                    } else {
                                        num += 2
                                    }
                                    if (num > 8) {
                                        newVal.name = newVal.name.substr(0, i)
                                    }
                                }
                            } else if (this.selectLevel == 2) {
                                let name = newVal.name;
                                let num = 0;
                                for (var i = 0; i < name.length; i++) {
                                    if (name[i].charCodeAt() >= 20 && name[i].charCodeAt() <= 127) {
                                        num++
                                    } else {
                                        num += 2
                                    }
                                    if (num > 16) {
                                        newVal.name = newVal.name.substr(0, i)
                                    }
                                }
                            }
                        },
                        deep: true
                    }
                }
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