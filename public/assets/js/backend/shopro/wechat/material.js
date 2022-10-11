define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {

        index: function () {
            $(window).resize(function () {
                init();
            })

            function init() {
                var itemWidth = $(".news-item").outerWidth(true);
                var cols = parseInt($(window).width() / itemWidth);
                var heightArr = [];
                for (var i = 0; i < cols; i++) {
                    heightArr.push(0);
                }
                $('.news-item').each(function (index, item) {
                    var idex = 0; //初始索引为0
                    var minHeight = heightArr[0]; //初始设置最小高度是数组的第一个
                    for (var i = 0; i < heightArr.length; i++) {
                        if (heightArr[i] < minHeight) { //判断数组中的每一个是否比默认设置的最小高度小，小于直接赋值给最小高度
                            minHeight = heightArr[i]; //最小高度
                            idex = i; //当前索引 
                        }
                    }
                    //设置每个图片的样式
                    $(item).css({
                        left: itemWidth * idex,
                        top: minHeight
                    })
                    heightArr[idex] += $(item).outerHeight(true); //高度对应的索引值就是当前图片高度的值
                })
            }

            var materialIndex = new Vue({
                el: "#materialIndex",
                data() {
                    return {
                        listData: [],
                        currentPage: 1,
                        totalPage: 0,
                        offset: 0,

                        activeName: 'news',
                        scrollTop: 0

                    }
                },
                mounted() {
                    this.getlistData();
                    window.addEventListener('scroll', this.handleScroll, true)
                },
                methods: {
                    handleScroll() {
                        this.scrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop;
                        var contentH = $(document).height();
                        var contents = $('.custom-body').height();
                        if (contentH - contents - this.scrollTop == 0) {
                            this.offset += 20
                            this.getlistData()
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
                                        url: 'shopro/wechat/material/del/ids/' + id,
                                        loading: true,
                                        type: 'POST',
                                    }, function (ret, res) {
                                        that.getlistData()
                                    })
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消删除'
                                    });
                                });

                                break;
                            case 'create':
                                Fast.api.open(`shopro/wechat/material/add?type=${that.activeName}`, "新建", {
                                    callback(data) {
                                        that.getlistData();
                                    }
                                });
                                break;
                            case 'edit':
                                Fast.api.open(`shopro/wechat/material/edit?id=${id}&type=${that.activeName}`, "编辑", {
                                    callback(data) {
                                        that.getlistData();
                                    }
                                });
                                break;
                        }
                    },
                    getlistData() {
                        var that = this;
                        Fast.api.ajax({
                            url: 'shopro/wechat/material/index',
                            loading: true,
                            type: 'GET',
                            data: {
                                offset: that.offset,
                                type: that.activeName,
                            }
                        }, function (ret, res) {
                            that.listData = res.data.rows;
                            if (that.activeName == 'image') {
                                that.listData.forEach(e => {
                                    e.arr = [];
                                    e.arr.push(e.url)
                                })
                            }
                            that.totalPage = res.data.total;
                            that.$nextTick(function () {
                                init();
                            })
                            return false;
                        })
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * 20
                        this.getlistData()
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
                        if (columnIndex == 0 || columnIndex == 1) {
                            return 'cell-left';
                        }
                        return '';
                    },

                },
                destroyed() {
                    // 离开该页面需要移除这个监听的事件，不然会报错
                    window.removeEventListener('scroll', this.handleScroll)
                },
                watch: {
                    activeName() {
                        this.currentPage = 1;
                        this.totalPage = 0;
                        this.offset = 0;
                        this.listData = [];
                        this.getlistData();
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
            var materialDetail = new Vue({
                el: "#materialDetail",
                data() {
                    return {
                        optType: type,
                        sourceType: new URLSearchParams(location.search).get('type'),
                        detailForm: {
                            type: new URLSearchParams(location.search).get('type'),
                            name: '',
                            content: '',
                            image:'',
                            url:''
                        },
                        detailFormInit: {
                            type: new URLSearchParams(location.search).get('type'),
                            name: '',
                            content: '',
                            image:'',
                            url:'',
                            description:''
                        },
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入标题',
                                trigger: 'blur'
                            }],
                            content: [{
                                required: true,
                                message: '请输入内容',
                                trigger: 'blur'
                            }],
                            description: [{
                                required: true,
                                message: '请输入内容',
                                trigger: 'blur'
                            }],
                            image: [{
                                required: true,
                                message: '请选择图片',
                                trigger: 'blur'
                            }],
                            url: [{
                                required: true,
                                message: '请输入链接地址',
                                trigger: 'blur'
                            }],
                        },
                        storeOptions: [],
                        text_id: null,

                        hrefMsg: '',
                        hrefA: '',

                        visible: false,

                    }
                },
                mounted() {
                    if (this.optType == 'add') {
                        this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit));
                    } else {
                        this.text_id = Config.row.id;
                        this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit));
                        this.detailForm.name = Config.row.name
                        if(this.sourceType=='text'){
                            this.detailForm.content = JSON.parse(Config.row.content);
                        }else{
                            let content=JSON.parse(Config.row.content)
                            this.detailForm.image=content.image
                            this.detailForm.description=content.description
                            this.detailForm.url=content.url
                        }
                        
                    }
                },
                methods: {
                    addHref(type) {
                        if (type) {
                            if (this.hrefMsg && this.hrefA) {
                                this.visible = false
                                this.detailForm.content = this.detailForm.content + `<a href="${this.hrefA}">${this.hrefMsg}</a>`
                                this.hrefMsg = ''
                                this.hrefA = ''
                            } else {
                                this.$notify({
                                    title: '警告',
                                    message: '超链接请填写完整',
                                    type: 'warning'
                                });
                            }
                        } else {
                            this.visible = false
                            this.hrefMsg = ''
                            this.hrefA = ''
                        }
                    },
                    dispatchSub(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[issub].validate((valid) => {
                                if (valid) {
                                    let subData=JSON.parse(JSON.stringify(that.detailForm));
                                    if(that.sourceType=='link'){
                                        subData.content={}
                                        subData.content.url=subData.url;
                                        subData.content.image=subData.image;
                                        subData.content.description=subData.description;
                                    }
                                    delete subData.url;
                                    delete subData.image;
                                    delete subData.description;
                                    if (this.optType != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/wechat/material/edit?id=' + that.text_id,
                                            loading: true,
                                            type: "POST",
                                            data: {
                                                data: JSON.stringify(subData)
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close({
                                                data: true
                                            })
                                        })
                                    } else {
                                        Fast.api.ajax({
                                            url: 'shopro/wechat/material/add',
                                            loading: true,
                                            type: "POST",
                                            data: {
                                                data: JSON.stringify(subData)
                                            }
                                        }, function (ret, res) {
                                            Fast.api.close({
                                                data: true
                                            })
                                        })
                                    }
                                } else {
                                    return false;
                                }
                            });
                        } else {
                            Fast.api.close({
                                data: false
                            })
                        }
                    },
                    addImg() {
                        let that = this;
                        parent.Fast.api.open("general/attachment/select?multiple=false", "选择图片", {
                            callback: function (data) {
                                that.detailForm.image = data.url;
                            }
                        });
                        return false;
                    },
                    delImg() {
                        this.detailForm.image = '';
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