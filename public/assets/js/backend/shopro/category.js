const { contains } = require("jquery");

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            var categoryIndex = new Vue({
                el: "#categoryIndex",
                data() {
                    return {
                        tabsData: [],
                        activeName: null,
                        activeId: null,
                        activeIndex: null,
                        level: null,
                        data: [],
                        timeData: [],
                        defaultProps: {
                            children: 'children',
                            label: 'id'
                        },
                        processId: null,
                        dragId: null,
                        isAjax: true,
                        ajaxSubmitData: []
                    }
                },
                mounted() {
                    this.getData(null);
                },
                methods: {
                    getData(id) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/category/index',
                            loading: false,
                            type: "GET",
                        }, function (ret, res) {
                            that.tabsData = []
                            that.data = []
                            that.timeData = []
                            res.data.forEach(i => {
                                that.tabsData.push(i);
                                that.data.push(i.children);
                                that.timeData.push(i.children);
                            });
                            that.data = JSON.parse(JSON.stringify(that.data))
                            that.timeData = JSON.parse(JSON.stringify(that.timeData))
                            if (that.tabsData.length > 0) {
                                if (id == null) {
                                    that.activeName = that.tabsData[0].name;
                                    that.activeId = that.tabsData[0].id;
                                    that.activeIndex = 0;
                                    if (that.tabsData[0].type == 0 || that.tabsData[0].type == 1) {
                                        that.level = 1
                                    } else {
                                        that.level = that.tabsData[0].type - 1;
                                    }
                                } else {
                                    that.activeId = id;
                                    that.tabsData.forEach((i, index) => {
                                        if (i.id == id) {
                                            that.activeName = i.name;
                                            that.activeIndex = index;
                                            if (i.type == 0 || i.type == 1) {
                                                that.level = 1
                                            } else {
                                                that.level = i.type - 1;
                                            }
                                        }
                                    })
                                }
                                that.handleData();
                                for (var i = 0; i < that.tabsData.length; i++) {
                                    that.ajaxSubmitData.push([])
                                }

                            }
                            that.isAjax=false;
                            return false;
                        })
                    },
                    operation(type, id) {
                        let that = this;
                        switch (type) {
                            case 'addCategory':
                                Fast.api.open("shopro/category/add", "新增分类", {
                                    callback: function (data) {
                                        that.getData(data.id)
                                    }
                                });
                                return false;
                                break;
                            case 'editCategory':
                                Fast.api.open("shopro/category/edit?ids=" + id, "编辑分类", {
                                    callback: function (data) {
                                            if (data.type == 'edit') {
                                                that.getData(data.id)
                                            } else {
                                                that.getData(null)
                                            }
                                    }
                                });
                                return false;
                                break;
                            case 'add':
                                if(that.activeIndex!=null){
                                    let addArr = []
                                    if (that.data[that.activeIndex].length > 0) {
                                        that.data[that.activeIndex].forEach(i => {
                                            if ((i.id + '').indexOf('add') != -1) {
                                                addArr.push(i.id.replace('add', ''))
                                            } else {
                                                addArr.push(i.id)
                                            }
                                        })
                                    }
                                    if (addArr.length > 0) {
                                        addArr.sort(function (a, b) {
                                            return b - a
                                        })
                                        that.data[that.activeIndex].push({
                                            id: 'add' + (Number(addArr[0]) + 1),
                                            name: '',
                                            status: 'normal',
                                            image: '',
                                            weigh: 0,
                                            children: []
                                        })
                                    } else {
                                        that.data[that.activeIndex].push({
                                            id: 'add1',
                                            name: '',
                                            status: 'normal',
                                            image: '',
                                            weigh: 0,
                                            children: []
                                        })
                                    }
                                }else{
                                    that.$notify({
                                        title: '警告',
                                        message: '请先添加或选择主分类',
                                        type: 'warning'
                                    });
                                }
                                break;
                            case 'reset':
                                that.$set(that.data,that.activeIndex,JSON.parse(JSON.stringify(that.timeData[that.activeIndex])))
                                that.ajaxSubmitData[that.activeIndex] = [];
                                that.$forceUpdate()
                                break;
                            case 'filter':
                                if (id == 2) {
                                    return 'level-2'
                                } else if (id == 3) {
                                    return 'level-3'
                                }
                                break;
                            case 'update':
                                let isName = true;
                                that.data[that.activeIndex].forEach(i => {
                                    if (i.name == '') {
                                        isName = false;
                                    }
                                    if (i.children && i.children.length > 0) {
                                        i.children.forEach((j, jndex) => {
                                            if (j.name == '') {
                                                isName = false;
                                            }

                                            if (j.children && j.children.length > 0) {
                                                j.children.forEach(k => {
                                                    if (k.name == '') {
                                                        isName = false;
                                                    }
                                                })
                                            }
                                        })
                                    }
                                })
                                if (!isName) {
                                    that.$notify({
                                        title: '警告',
                                        message: '分类名称未填写不可保存并更新',
                                        type: 'warning'
                                    });
                                    return false;
                                }

                                let subData = JSON.parse(JSON.stringify(that.data[that.activeIndex]))
                                if (that.ajaxSubmitData.length > 0 && that.ajaxSubmitData[that.activeIndex].length > 0) {
                                    that.ajaxSubmitData[that.activeIndex].forEach((i, index) => {
                                        if (i.deleted == 1 && typeof(i.id)!='string') {
                                            subData.push(i)
                                        } else {
                                            if (i.children && i.children.length > 0) {
                                                i.children.forEach((j, jndex) => {
                                                    if (j.deleted == 1 && typeof(j.id)!='string') {
                                                        subData[index].children.push(j)
                                                    } else {
                                                        if (j.children && j.children.length > 0) {
                                                            j.children.forEach(k => {
                                                                if (k.deleted == 1 && typeof(k.id)!='string') {
                                                                    subData[index].children[jndex].children.push(k)
                                                                }
                                                            })
                                                        }
                                                    }
                                                })
                                            }
                                        }
                                    })
                                }

                                if (subData.length > 0) {
                                    subData.forEach(i => {
                                        if ((i.id + '').indexOf('add') != -1) {
                                            i.id = '';
                                        }
                                        if (i.children && i.children.length > 0) {
                                            i.children.forEach(j => {
                                                if ((j.id + '').indexOf('add') != -1) {
                                                    j.id = '';
                                                }
                                                if (j.children && j.children.length > 0) {
                                                    j.children.forEach(k => {
                                                        if ((k.id + '').indexOf('add') != -1) {
                                                            k.id = ''
                                                        }
                                                    })
                                                }
                                            })
                                        }
                                    })
                                } else {
                                    this.$notify({
                                        title: '警告',
                                        message: '没有需要提交的数据',
                                        type: 'warning'
                                    });
                                    return false;
                                }
                                // return false;
                                that.isAjax = false;
                                Fast.api.ajax({
                                    url: 'shopro/category/update?ids=' + that.tabsData[that.activeIndex].id,
                                    loading: false,
                                    type: 'POST',
                                    data: {
                                        data: JSON.stringify(subData)
                                    }
                                }, function (ret, res) {
                                    that.isAjax = true;
                                    that.getData(that.activeId);
                                    that.ajaxSubmitData[that.activeIndex]=[]
                                })
                                break;
                        }
                    },
                    addImage(form) {
                        let that = this;
                        Fast.api.open("general/attachment/select?multiple=false", "选择图片", {
                            callback: function (data) {
                                form.image = data.url;
                            }
                        });
                        return false;
                    },
                    isdrag(draggingNode, dropNode, type) {
                        if (draggingNode.level == dropNode.level && type != 'inner') {
                            return true
                        } else {
                            return false
                        }
                    },
                    handleData() {
                        let that = this;
                        if (that.level == 1) {
                            that.data[that.activeIndex].forEach(i => {
                                if (i.children) {
                                    delete i.children
                                }
                            })
                            that.timeData[that.activeIndex].forEach(i => {
                                if (i.children) {
                                    delete i.children
                                }
                            })
                        } else if (that.level == 2) {
                            that.data[that.activeIndex].forEach(i => {
                                if (i.children && i.children.length > 0) {
                                    i.children.forEach(j => {
                                        delete j.children
                                    })
                                }
                            })
                            that.timeData[that.activeIndex].forEach(i => {
                                if (i.children && i.children.length > 0) {
                                    i.children.forEach(j => {
                                        delete j.children
                                    })
                                }
                            })
                        } else if (that.level == 3) {
                            that.data[that.activeIndex].forEach(i => {
                                if (i.children && i.children.length > 0) {
                                    i.children.forEach(j => {
                                        if (j.children && j.children.length > 0) {
                                            j.children.forEach(k => {
                                                delete k.children
                                            })
                                        }
                                    })
                                }
                            })
                            that.timeData[that.activeIndex].forEach(i => {
                                if (i.children && i.children.length > 0) {
                                    i.children.forEach(j => {
                                        if (j.children && j.children.length > 0) {
                                            j.children.forEach(k => {
                                                delete k.children
                                            })
                                        }
                                    })
                                }
                            })
                        }
                    },
                    handleClick(tab) {
                        let that = this;
                        let index = Number(tab.index)
                        that.activeIndex = index;
                        that.activeId = that.tabsData[index].id
                        that.activeName = that.tabsData[index].name;

                        if (that.tabsData[index].type == 0 || that.tabsData[index].type == 1) {
                            that.level = 1
                        } else {
                            that.level = that.tabsData[index].type - 1;
                        }

                        that.handleData();

                    },
                    addTemplate(data) {
                        // this.processId=null;
                        // this.dragId=null
                        let newChild = {
                            id: 'add1',
                            name: '',
                            status: 'normal',
                            image: '',
                            weigh: 0,
                            children: []
                        };
                        if (!data.children) {
                            this.$set(data, 'children', []);
                        } else {
                            let addArr = []
                            if (data.children.length > 0) {
                                data.children.forEach(i => {
                                    if ((i.id + '').indexOf('add') != -1) {
                                        addArr.push(i.id.replace('add', ''))
                                    } else {
                                        addArr.push(i.id)
                                    }
                                })
                                addArr.sort(function (a, b) {
                                    return b - a
                                })
                                newChild.id = 'add' + (Number(addArr[0]) + 1)
                            }
                        }
                        data.children.push(newChild);
                    },
                    hiddenShow(node, data) {
                        if (data.status == 'normal') {
                            data.status = 'hidden'
                        } else {
                            data.status = 'normal'
                        }
                    },
                    remove(node, data) {
                        if (data.children && data.children.length > 0) {
                            this.$notify({
                                title: '警告',
                                message: '请先删除子分类数据',
                                type: 'warning'
                            });
                            return false;
                        } else {
                            const parent = node.parent;
                            const children = parent.data.children || parent.data;
                            const index = children.findIndex(d => d.id === data.id);

                            //赋值
                            children[index].deleted = 1;
                            if (this.ajaxSubmitData[this.activeIndex].length == 0) {
                                this.ajaxSubmitData[this.activeIndex] = JSON.parse(JSON.stringify(this.data[this.activeIndex]))
                            } else {
                                this.ajaxSubmitData[this.activeIndex].forEach(i => {
                                    if (i.id == data.id) {
                                        i.deleted = 1;
                                        isrealy = true;
                                    } else {
                                        if (i.children && i.children.length > 0) {
                                            i.children.forEach((j, jndex) => {
                                                if (j.id == data.id) {
                                                    j.deleted = 1;
                                                    isrealy = true;
                                                } else {
                                                    if (j.children && j.children.length > 0) {
                                                        j.children.forEach(k => {
                                                            if (k.id == data.id) {
                                                                k.deleted = 1;
                                                                isrealy = true;
                                                            }
                                                        })
                                                    }
                                                }
                                            })
                                        }
                                    }
                                })

                            }

                            children.splice(index, 1);

                            this.$forceUpdate();
                        }

                    },
                    isexpanded(data) {
                        data.expanded = !data.expanded
                    },
                    handleDragEnter(draggingNode, dropNode, ev) {
                        const parent = dropNode.parent;
                        const children = parent.data.children || parent.data;
                        const index = children.findIndex(d => d.id === dropNode.data.id);
                        this.processId = dropNode.label;
                        this.dragId = draggingNode.label
                    },
                    handleDragEnd(draggingNode, dropNode, dropType, ev) {
                        this.processId = null;
                    },
                },
            })
        },
        select: function () {
            var categorySelect = new Vue({
                el: "#categorySelect",
                data() {
                    return {
                        selectedData: [],
                        selectedids: null,
                        form: new URLSearchParams(location.search).get('from'),
                        currentPage: 1,
                        totalPage: 0,
                        limit: 10,
                        offset: 0,
                        defaultProps: {
                            children: 'children',
                            label: 'name',
                            multiple: false,
                            checkStrictly: true,
                            value: 'id',
                        },
                        defaultProps2: {
                            children: 'children',
                            label: 'name',
                            multiple: true,
                            checkStrictly: true,
                            value: 'id',
                        },
                        selectedArr: [],
                        selectedIndex: null,
                        checkList:[],
                        allSelectIds:{}
                    }
                },
                mounted() {
                    this.getData()
                },
                methods: {
                    selcetedStatus(val, key) {
                        this.selectedArr = [];
                        if (key.checkedKeys.length > 0) {
                            this.selectedArr.push(val.id)
                        }
                        this.$refs.tree.setCheckedKeys(this.selectedArr);
                    },
                    getData() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'shopro/category/select',
                            type: "GET",
                        }, function (ret, res) {
                            that.selectedData = res.data;
                            that.selectedData.forEach(i=>{
                                if(i.children && i.children.length>0){
                                    i.children.forEach(j=>{
                                        if(j.children && j.children.length>0){
                                            j.children.forEach(k=>{
                                                if(k.children && k.children.length>0){
                                                    k.children.forEach(g=>{
                                                        if(g.children && g.children.length>0){
                                                            
                                                        }else{
                                                            delete g.children
                                                        }
                                                    })
                                                }else{
                                                    delete k.children
                                                }
                                            })
                                        }else{
                                            delete j.children
                                        }
                                    })
                                }else{
                                    delete i.children
                                }
                            })
                            return false;
                        })
                    },
                    select(id, index) {
                        this.selectedIndex = index;
                        this.selectedids = id;
                        if (this.form == 'group') {
                            this.selectedArr=[]
                            this.selectedArr.push(id)
                        }
                    },
                    filterStyle(type) {
                        switch (type) {
                            case '1':
                                return '一'
                                break;
                            case '2':
                                return '二'
                                break;
                            case '3':
                                return '三'
                                break;
                            case '4':
                                return '四'
                                break;
                        }

                    },
                    close() {
                        let that = this;
                        let name = ''
                        if (this.form == 'link') {
                            this.selectedData.forEach(i => {
                                if (i.id == that.selectedids) {
                                    name = i.name
                                }
                            })
                            Fast.api.close({
                                data: {
                                    id: that.selectedids,
                                    category_name: name
                                }
                            })
                        } else if (this.form == 'group'){
                            that.selectedData.forEach(i => {
                                if (i.id == that.selectedArr[0]) {
                                    name = i.name
                                } else {
                                    if (i.children && i.children.length > 0) {
                                        i.children.forEach(j => {
                                            if (j.id == that.selectedArr[0]) {
                                                name = j.name
                                            } else {
                                                if (j.children && j.children.length > 0) {
                                                    j.children.forEach(k => {
                                                        if (k.id == that.selectedArr[0]) {
                                                            name = k.name
                                                        }else{
                                                            if (k.children && k.children.length > 0) {
                                                                k.children.forEach(g => {
                                                                    if (g.id == that.selectedArr[0]) {
                                                                        name = g.name
                                                                    }else{
                                                                        
                                                                    }
                                                                })
                                                            }
                                                        }
                                                    })
                                                }
                                            }
                                        })
                                    }
                                }
                            })
                            Fast.api.close({
                                data: {
                                    id: that.selectedArr[0],
                                    category_name: name
                                }
                            })
                        } else if (this.form == 'category-tabs'){
                            let category_arr=[]
                            that.selectedArr.forEach(n=>{
                                that.selectedData.forEach(i => {
                                    if (i.id==n) {
                                        category_arr.push(i)
                                    } else {
                                        if (i.children && i.children.length > 0) {
                                            i.children.forEach(j => {
                                                if (j.id == n) {
                                                    category_arr.push(j)
                                                } else {
                                                    if (j.children && j.children.length > 0) {
                                                        j.children.forEach(k => {
                                                            if (k.id == n) {
                                                                category_arr.push(k)
                                                            }
                                                        })
                                                    }
                                                }
                                            })
                                        }
                                    }
                                })
                            })
                            Fast.api.close({
                                data: {
                                    id: that.selectedArr.join(','),
                                    category_arr: category_arr
                                }
                            })
                        }

                    },
                    cascaderChange(val) {
                        this.selectedArr = [];
                        this.selectedids=null;
                        this.selectedArr.push(val[val.length - 1])
                    },
                    categoryTabsCascader(index,val) {
                        this.selectedArr=[]
                        this.allSelectIds[index]=val
                        for(key in this.allSelectIds){
                            this.allSelectIds[key].forEach(a=>{
                                this.selectedArr.push(a[a.length - 1])
                            })
                        }
                    }
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
            var categoryDetail = new Vue({
                el: "#categoryDetail",
                data() {
                    return {
                        optType: type,
                        detailForm: {},
                        detailFormInit: {
                            type: '',
                            name: '',
                            weigh: 0,
                            status: 'normal'
                        },
                        rulesForm: {
                            type: [{
                                required: true,
                                message: '请选择分类样式',
                                trigger: 'blur'
                            }],
                            name: [{
                                required: true,
                                message: '请输入分类名称',
                                trigger: 'blur'
                            }],
                            weigh: [{
                                required: true,
                                message: '请输入分类权重',
                                trigger: 'blur'
                            }],
                            // status: [{
                            //     required: true,
                            //     message: '请选择状态',
                            //     trigger: 'blur'
                            // }],
                        },
                    }
                },
                mounted() {
                    this.detailForm = JSON.parse(JSON.stringify(this.detailFormInit))
                    if (this.optType == 'edit') {
                        for (key in this.detailForm) {
                            this.detailForm[key] = Config.row[key]
                        }
                    }
                },
                methods: {
                    selectType(type) {
                        this.detailForm.type = type
                    },
                    deletecategory() {
                        let that = this;
                        that.$confirm('此操作将删除该分类的所有相关数据, 是否继续?', '提示', {
                            confirmButtonText: '确定',
                            cancelButtonText: '取消',
                            type: 'warning'
                        }).then(() => {
                            Fast.api.ajax({
                                url: 'shopro/category/del/ids/' + Config.row.id,
                                loading: true,
                                type: 'POST'
                            }, function (ret, res) {
                                Fast.api.close({
                                    data: true,
                                    id: Config.row.id,
                                    type: 'delete'
                                })
                            })
                        }).catch(() => {
                            that.$message({
                                type: 'info',
                                message: '已取消删除'
                            });
                        });
                    },
                    submitForm(check) {
                        let that = this;
                        this.$refs[check].validate((valid) => {
                            if (valid) {
                                if (that.optType != 'add') {
                                    Fast.api.ajax({
                                        url: 'shopro/category/edit?ids=' + Config.row.id,
                                        loading: true,
                                        type: "POST",
                                        data: {
                                            data: JSON.stringify(this.detailForm)
                                        }
                                    }, function (ret, res) {
                                        Fast.api.close({
                                            data: true,
                                            id: Config.row.id,
                                            type: 'edit'
                                        })
                                    })
                                } else {
                                    Fast.api.ajax({
                                        url: 'shopro/category/add',
                                        loading: true,
                                        type: "POST",
                                        data: {
                                            data: JSON.stringify(this.detailForm)
                                        }
                                    }, function (ret, res) {
                                        Fast.api.close({
                                            data: true,
                                            id: res.data
                                        })
                                    })
                                }
                            } else {
                                return false;
                            }
                        });
                    }
                }
            })
        },
        api: {
            formatter: {
                subnode: function (value, row, index) {
                    return '<a href="javascript:;" data-toggle="tooltip" title="' + __('Toggle sub menu') + '" data-id="' + row.id + '" data-pid="' + row.pid + '" data-haschild="' + row.haschild + '" class="btn btn-xs ' +
                        (row.haschild == 1 ? 'btn-success' : 'btn-default disabled') + ' btn-node-sub"><i class="fa fa-sitemap"></i></a>';
                }
            },
            bindevent: function () {
                $(document).on("change", "#c-type", function () {
                    $("#c-pid option[data-type='all']").prop("selected", true);
                    $("#c-pid option").removeClass("hide");
                    $("#c-pid option[data-type!='" + $(this).val() + "'][data-type!='all']").addClass("hide");
                    $("#c-pid").data("selectpicker") && $("#c-pid").selectpicker("refresh");
                });
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});