define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/area/index' + location.search,
                    add_url: 'shopro/area/add',
                    edit_url: 'shopro/area/edit',
                    del_url: 'shopro/area/del',
                    multi_url: 'shopro/area/multi',
                    table: 'shopro_area',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [{
                            checkbox: true
                        },
                        {
                            field: 'id',
                            title: __('Id')
                        },
                        {
                            field: 'name',
                            title: __('Name')
                        },
                        {
                            field: 'pid',
                            title: __('Pid')
                        },
                        {
                            field: 'level',
                            title: __('Level')
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        select: function () {
            function urlParmas(par) {
                let value = ""
                window.location.search.replace("?", '').split("&").forEach(i => {
                    if (i.split('=')[0] == par) {
                        value = JSON.parse(decodeURI(i.split('=')[1]))
                    }
                })
                return value
            }
            var areaSelect = new Vue({
                el: "#areaSelect",
                data() {
                    return {
                        pcaData: Config.areaData,
                        data: Config.areaData,
                        selectedId: [],
                        selectedProvince: urlParmas('parmas').province_ids ? urlParmas('parmas').province_ids.split(',') : [],
                        selectedCity: urlParmas('parmas').city_ids ? urlParmas('parmas').city_ids.split(',') : [],
                        selectedArea: urlParmas('parmas').area_ids ? urlParmas('parmas').area_ids.split(',') : [],
                        selectedName: urlParmas('parmas').name ? urlParmas('parmas').name.split(',') : [],
                        defaultProps: {
                            children: 'children',
                            label: 'label'
                        },
                        filterText: '',
                        checked: false,
                    };
                },
                mounted() {
                    this.selectedId = this.selectedId.concat([], this.selectedProvince, this.selectedCity, this.selectedArea)
                },
                methods: {
                    selceted(data) {
                        let realy = this.getSimpleCheckedNodes(this.$refs.tree.store);
                        let arrId = [],
                            arrName = [],
                            arr1 = [],
                            arr2 = [],
                            arr3 = []
                        realy.forEach(i => {
                            if (i.level == 1) {
                                arr1.push(i.id)
                            }
                            if (i.level == 2) {
                                arr2.push(i.id)
                            }
                            if (i.level == 3) {
                                arr3.push(i.id)
                            }
                            arrId.push(i.id);
                            arrName.push(i.label)
                        })
                        this.selectedId = arrId;
                        this.selectedProvince = arr1;
                        this.selectedCity = arr2;
                        this.selectedArea = arr3;
                        this.selectedName = arrName;

                    },
                    getSimpleCheckedNodes(store) {
                        const checkedNodes = [];
                        const traverse = function (node) {
                            const childNodes = node.root ? node.root.childNodes : node.childNodes;

                            childNodes.forEach(child => {
                                if (child.checked) {
                                    checkedNodes.push(child.data);
                                }
                                if (child.indeterminate) {
                                    traverse(child);
                                }
                            });
                        };
                        traverse(store)
                        return checkedNodes;
                    },
                    filterNode(value, data) {
                        if (!value) return true;
                        return data.label.indexOf(value) !== -1;
                    },
                    define() {
                        let data = {
                            province: this.selectedProvince,
                            city: this.selectedCity,
                            area: this.selectedArea,
                            name: this.selectedName
                        }
                        Fast.api.close({
                            data: data
                        })
                    },

                },
                watch: {
                    filterText(val) {
                        this.$refs.tree.filter(val);
                    },
                    checked(val) {
                        if (val) {
                            this.selectedId = [];
                            this.selectedName = [];
                            this.selectedProvince = [];
                            this.selectedCity = [];
                            this.selectedArea = [];
                            this.pcaData.forEach(i => {
                                this.selectedId.push(i.id);
                                this.selectedName.push(i.label);
                                this.selectedProvince.push(i.id)
                            });
                            this.$refs.tree.setCheckedNodes(this.selectedId)
                        } else {
                            this.selectedId = [];
                            this.selectedName = [];
                            this.selectedProvince = [];
                            this.selectedCity = [];
                            this.selectedArea = [];
                            this.$refs.tree.setCheckedNodes(this.selectedId)
                        }
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