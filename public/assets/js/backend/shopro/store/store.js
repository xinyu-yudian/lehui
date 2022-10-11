define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'toastr'], function ($, undefined, Backend, Table, Form, Toastr) {

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
            var storeIndex = new Vue({
                el: "#storeIndex",
                data() {
                    return {
                        storeData: [],
                        searchKey: '',
                        currentPage: 1,
                        limit: 10,
                        offset: 0,
                        totalPage: 0,
                        allAjax: true,
                        tableAjax: false,
                    }
                },
                mounted() {
                    this.getData();
                    if (!Config.hasAmap) {
                        Toastr.error('请在商城配置->第三方服务中配置高德地图')
                    }
                },
                methods: {
                    getData() {
                        let that = this;
                        if (!that.allAjax) {
                            that.tableAjax = true;
                        }
                        Fast.api.ajax({
                            url: 'shopro/store/store/index',
                            loading: false,
                            type: 'GET',
                            data: {
                                search: that.searchKey,
                                limit: that.limit,
                                offset: that.offset
                            }
                        }, function (ret, res) {
                            that.storeData = res.data.rows;
                            that.totalPage = res.data.total;
                            that.allAjax = false;
                            that.tableAjax = false;
                            return false;
                        }, function (ret, res) {
                            that.allAjax = false;
                            that.tableAjax = false;
                        })
                    },
                    operation(opttype, id, status) {
                        let that = this;
                        switch (opttype) {
                            case 'delete':
                                that.$confirm('此操作将删除门店, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: 'shopro/store/store/del/ids/' + id,
                                        loading: false,
                                        type: 'POST',
                                    }, function (ret, res) {
                                        that.getData()
                                    })
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消删除'
                                    });
                                });

                                break;
                            case 'status':
                                let str = status == 1 ? 0 : 1;
                                that.$confirm('此操作将修改门店状态, 是否继续?', '提示', {
                                    confirmButtonText: '确定',
                                    cancelButtonText: '取消',
                                    type: 'warning'
                                }).then(() => {
                                    Fast.api.ajax({
                                        url: `shopro/store/store/setStatus/ids/${id}/status/${str}`,
                                        loading: false,
                                    }, function (ret, res) {
                                        that.getData();
                                    })
                                }).catch(() => {
                                    that.$message({
                                        type: 'info',
                                        message: '已取消'
                                    });
                                });
                                break;
                            case 'create':
                                Fast.api.open("shopro/store/store/add", "创建门店", {
                                    callback(data) {
                                        if (data.data) {
                                            that.getData();
                                        }
                                    }
                                });
                                break;
                            case 'edit':
                                Fast.api.open("shopro/store/store/edit?ids=" + id, "编辑门店", {
                                    callback(data) {
                                        if (data.data) {
                                            that.getData();
                                        }
                                    }
                                });
                                break;
                            case 'recycle':
                                Fast.api.open("shopro/store/store/recyclebin", "回收站", function () {
                                    that.getData();
                                });
                                break;
                        }
                    },
                    pageSizeChange(val) {
                        this.offset = 0;
                        this.currentPage = 1
                        this.limit = val;
                        this.getData();
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * this.limit;
                        this.currentPage = val;
                        this.getData();
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
                        if (columnIndex == 1 || columnIndex == 2 || columnIndex == 7) {
                            return 'cell-left';
                        }
                        return '';
                    },
                    debounceFilter: debounce(function () {
                        this.getData()
                    }, 1000),
                },
                watch: {
                    searchKey(newVal, oldVal) {
                        if (newVal != oldVal) {
                            this.debounceFilter();
                        }
                    }
                }
            })
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'shopro/store/store/recyclebin' + location.search,
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
                        title: __('Name'),
                        align: 'left'
                    },
                    {
                        field: 'deletetime',
                        title: __('Deletetime'),
                        operate: 'RANGE',
                        addclass: 'datetimerange',
                        formatter: Table.api.formatter.datetime
                    },
                    {
                        field: 'operate',
                        width: '130px',
                        title: __('Operate'),
                        table: table,
                        events: Table.api.events.operate,
                        buttons: [{
                            name: 'Restore',
                            text: __('Restore'),
                            classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                            icon: 'fa fa-rotate-left',
                            url: 'shopro/store/store/restore',
                            refresh: true
                        },
                        {
                            name: 'Destroy',
                            text: __('Destroy'),
                            classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                            icon: 'fa fa-times',
                            url: 'shopro/store/store/destroy',
                            refresh: true
                        }
                        ],
                        formatter: Table.api.formatter.operate
                    }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.detailInit('add');
        },
        edit: function () {
            Controller.detailInit('edit');
        },
        detailInit: function (type) {
            Vue.directive('enterNumber', {
                inserted: function (el) {
                    let changeValue = (el, type) => {
                        const e = document.createEvent('HTMLEvents')
                        e.initEvent(type, true, true)
                        el.dispatchEvent(e)
                    }
                    el.addEventListener("keyup", function (e) {
                        let input = e.target;
                        let reg = new RegExp('^((?:(?:[1-9]{1}\\d*)|(?:[0]{1}))(?:\\.(?:\\d){0,2})?)(?:\\d*)?$');
                        let matchRes = input.value.match(reg);
                        if (matchRes === null) {
                            input.value = "";
                        } else {
                            if (matchRes[1] !== matchRes[0]) {
                                input.value = matchRes[1];
                            }
                        }
                        changeValue(input, 'input')
                    });
                }
            });
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
            var storeDetail = new Vue({
                el: "#storeDetail",
                data() {
                    return {
                        opttype: type,
                        storeForm: {},
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入门店名称',
                                trigger: 'blur'
                            }],
                            images: [{
                                required: true,
                                message: '请选择门店图片',
                                trigger: 'change'
                            }],
                            realname: [{
                                required: true,
                                message: '请选择联系人',
                                trigger: 'blur'
                            }],
                            phone: [{
                                required: true,
                                message: '请输入联系电话',
                                trigger: 'blur'
                            }],
                            user_ids: [{
                                required: true,
                                message: '请选择管理员',
                                trigger: 'blur'
                            }],
                            express_type: [{
                                required: true,
                                message: '请选择配送方式',
                                trigger: 'change'
                            }],
                            openhours: [{
                                required: true,
                                message: '请选择营业时间',
                                trigger: 'change'
                            }],
                            openweeks: [{
                                required: true,
                                message: '请选择星期',
                                trigger: 'change'
                            }],
                            store_address: [{
                                required: true,
                                message: '请选择省市区',
                                trigger: 'change'
                            }],
                            address: [{
                                required: true,
                                message: '请输入详细地址',
                                trigger: 'change'
                            }],
                        },
                        storeFormInit: {
                            name: '',
                            realname: '',
                            phone: '',
                            store_address: [],
                            store_address_id: [],
                            address: '',
                            user_ids: [],

                            express_type: ['store'],

                            service_type: 'radius',

                            service_radius: 1000,

                            area_text: '',
                            service_province_ids: '',
                            service_city_ids: '',
                            service_area_ids: '',
                            service_area: '',

                            openhours: '09:00 - 21:00',
                            openweeks: '1,2,3,4,5,6,7',
                            status: '1',
                            latitude: '',
                            longitude: '',
                            images: ''
                        },
                        timeData: {
                            images_arr: [],
                            openhours_arr: ['09:00', '21:00'],
                            openweeks_arr: [1, 2, 3, 4, 5, 6, 7],
                        },
                        expressData: [{
                            id: 'store',
                            name: "商家配送"
                        }, {
                            id: 'selfetch',
                            name: "到店/自提"
                        }],
                        aboutUserOptions: [],
                        areaOptions: [],
                        store_id: Config.row ? Config.row.id : null,
                        map: null,
                        diymap: null,
                        polygonOld: null,
                        polyEditor: null,
                        service_area: '',
                        locationsOld: null,
                        circle: null,
                        circleEditor: null,
                        marker: null,
                        lnglat: [116.433322, 39.900256],
                        radiusDelArr: ['service_area_ids', 'service_city_ids', 'service_province_ids'],
                        areaDelArr: ['service_radius'],
                        mustDelArr: ['store_address', 'store_address_id', 'express_type', 'area_text'],
                        store_address_arr: ['province_name', 'city_name', 'area_name'],
                        store_address_id_arr: ['province_id', 'city_id', 'area_id'],

                        limit: 6,
                        offset: 0,
                        currentPage: 1,
                        totalPage: 0,
                        searchPage: '',
                    }
                },
                mounted() {
                    if (!Config.hasAmap) {
                        Toastr.error('请在商城配置->第三方服务中配置高德地图')
                    }
                    if (this.opttype == 'add') {
                        this.storeForm = JSON.parse(JSON.stringify(this.storeFormInit));
                    } else {
                        //赋值
                        this.storeForm = JSON.parse(JSON.stringify(this.storeFormInit));

                        //经纬度
                        this.evaluationLnglat(Config.row.longitude, Config.row.latitude);

                        //避免管理员不显示
                        this.aboutUserOptions = Config.row.user_ids_list;

                        //数组赋值
                        for (key in this.storeForm) {
                            if (key == 'store_address') {
                                this.storeForm[key].push(Config.row.province_name)
                                this.storeForm[key].push(Config.row.city_name)
                                this.storeForm[key].push(Config.row.area_name)
                            } else if (key == 'express_type') {
                                this.storeForm[key] = []
                                if (Config.row.store == 1) {
                                    this.storeForm[key].push('store')
                                }
                                if (Config.row.selfetch == 1) {
                                    this.storeForm[key].push('selfetch')
                                }
                            } else {
                                this.storeForm[key] = Config.row[key]
                            }
                        }
                        for (key in this.timeData) {
                            if (key == 'openhours_arr') {
                                this.timeData[key] = Config.row[key.replace('_arr', '')].split(' - ')
                            } else {
                                this.timeData[key] = Config.row[key.replace('_arr', '')].split(',')
                            }
                        }
                    }
                    this.getAreaOptions();
                    this.getInitMap();
                    this.getUser();
                    let _self = this;
                    setTimeout(function(){
                        _self.getInitMapDiy();
                    },500);

                },
                computed: {
                    isAddImage: function () {
                        return this.timeData.images_arr.length;
                    },
                    isAddress: function () {
                        if (this.storeForm.store_address) {
                            return this.storeForm.store_address.length;
                        }
                    },
                },
                methods: {
                    evaluationLnglat(longitude, latitude) {
                        this.lnglat = [];
                        this.lnglat.push(longitude);
                        this.lnglat.push(latitude)
                    },
                    getUser() {
                        var that = this;
                        Fast.api.ajax({
                            url: `shopro/user/user/select`,
                            loading: false,
                            type: 'GET',
                            data: {
                                limit: that.limit,
                                offset: that.offset,
                                search: that.searchPage
                            }
                        }, function (ret, res) {
                            that.aboutUserOptions = res.data.rows;
                            that.totalPage = res.data.total
                            return false;
                        })
                    },
                    getInitMapDiy() {
                        let that = this;
                        if(that.storeForm.service_type != 'diyarea'){
                            return;
                        }
                        let center = Array();
                        center.push(that.lnglat[0]);
                        center.push(that.lnglat[1]);
                        that.diymap = new AMap.Map("mapAreaDiy", {
                            zoom:14,//级别
                            center: center,
                            // center: [113.363393,22.146874],//中心点坐标
                            viewMode:'3D'//使用3D视图
                        });
                        that.polyEditor = new AMap.PolygonEditor(that.diymap);
                        // that.locationsOld = [[116.475334, 39.997534], [116.476627, 39.998315], [116.478603, 39.99879], [116.478529, 40.000296], [116.475082, 40.000151], [116.473421, 39.998717]];
                        if(that.storeForm.service_area && that.storeForm.service_area.length > 5){
                            that.locationsOld = JSON.parse(that.storeForm.service_area);
                        } else {
                            that.locationsOld = [];
                        }
                        that.polygonOld = new AMap.Polygon({
                            path: that.locationsOld
                        })
                        that.diymap.add([that.polygonOld]);
                        that.diymap.setFitView();
                        that.polyEditor.addAdsorbPolygons([that.polygonOld]);
                        that.polyEditor.on('end', function(event) {
                            // event.target 即为编辑后的多边形对象
                            console.log('触发事件： end')
                            console.log(event.target.getPath())
                            let paths = event.target.getPath();
                            let locations = Array();
                            for(let i = 0; i<paths.length; i++){
                                let one = Array();
                                one.push(paths[i].lng);
                                one.push(paths[i].lat);
                                locations.push(one);
                            }
                            console.log('locations');
                            console.log(locations);
                            console.log(JSON.stringify(locations));
                            that.service_area = JSON.stringify(locations);
                        })
                        that.polyEditor.on('add', function (data) {
                            console.log('polyEditor add');
                            console.log(data.target.getPath());
                            let paths = data.target.getPath();
                            let locations = Array();
                            for(let i = 0; i<paths.length; i++){
                                let one = Array();
                                one.push(paths[i].lng);
                                one.push(paths[i].lat);
                                locations.push(one);
                            }
                            console.log('locations');
                            console.log(locations);
                            console.log(JSON.stringify(locations));
                            that.service_area = JSON.stringify(locations);
                            var polygon = data.target;
                            that.polyEditor.addAdsorbPolygons(polygon);
                            polygon.on('dblclick', () => {
                                that.polyEditor.setTarget(polygon);
                                that.polyEditor.open();
                            })
                            that.polygonOld = polygon;
                        })
                        that.polygonOld.on('dblclick', () => {
                            that.polyEditor.setTarget(that.polygonOld);
                            that.polyEditor.open();
                        })
                    },
                    // 新建多边形
                    createPolygon() {
                        this.diymap.remove(this.polygonOld);
                        this.polyEditor.close();
                        this.polyEditor.setTarget();
                        this.polyEditor.open();
                    },
                    // 停止编辑
                    stopCoverage() {
                        this.polyEditor.close();
                        console.log('结束编辑');
                    },
                    getInitMap() {
                        let that = this;
                        that.map = new AMap.Map("mapArea", {
                            zoom: 14
                        });
                        that.addCircle();
                        that.addMarker();
                        if (that.storeForm.express_type.indexOf('store') != -1) {
                            if (that.storeForm.service_type == 'radius') {
                                that.marker.hide()
                                that.circle.setCenter(that.lnglat)
                                that.map.setFitView([that.circle])
                            } else {
                                that.circle.hide()
                                that.circleEditor.close()
                                that.marker.show()
                                that.marker.setPosition(that.lnglat)
                                that.map.setFitView([that.marker])
                            }
                        } else {
                            that.circle.hide()
                            that.circleEditor.close()
                            that.marker.show()
                            that.marker.setPosition(that.lnglat)
                            that.map.setFitView([that.marker])
                        }
                    },
                    addCircle() {
                        let that = this;
                        that.circle = null
                        that.circle = new AMap.Circle({
                            center: that.lnglat,
                            radius: that.storeForm.service_radius, //半径
                            borderWeight: 1,
                            strokeColor: "#FF33FF",
                            strokeOpacity: 1,
                            strokeWeight: 6,
                            strokeOpacity: 0.2,
                            fillOpacity: 0.4,
                            strokeStyle: 'solid',
                            strokeDasharray: [10, 10],
                            fillColor: '#7438D5',
                            zIndex: 50,
                        })

                        that.circle.setMap(that.map)
                        // 缩放地图到合适的视野级别
                        that.map.setFitView([that.circle])

                        that.circleEditor = new AMap.CircleEditor(that.map, that.circle)
                        that.circleEditor.open()
                        that.circleEditor.on('adjust', function (event) {
                            that.storeForm.service_radius = that.circle.getRadius();
                        })
                        that.circleEditor.on('move', function (event) {
                            that.evaluationLnglat(that.circle.getCenter().lng, that.circle.getCenter().lat);
                        })
                    },
                    addMarker() {
                        let that = this;
                        var startIcon = new AMap.Icon({
                            image: '/assets/addons/shopro/img/dispatch/position-icon.png',
                            imageSize: new AMap.Size(27, 33),
                        });
                        that.marker = new AMap.Marker({
                            icon: startIcon,
                            position: that.lnglat,
                        });
                        that.marker.setMap(that.map);
                        that.map.on('click', function (event) {
                            that.evaluationLnglat(event.lnglat.lng, event.lnglat.lat);
                            if (that.storeForm.service_type == 'radius') {
                                that.circle.setCenter(that.lnglat)
                                that.map.setFitView([that.circle])
                            } else {
                                that.marker.setPosition(that.lnglat)
                                that.map.setFitView([that.marker])
                            }
                        })
                    },
                    analysisAddress() {
                        let that = this;
                        let address = that.storeForm.store_address.join(',') + that.storeForm.address
                        var geocoder = new AMap.Geocoder();
                        geocoder.getLocation(address, function (status, result) {
                            if (status === 'complete' && result.geocodes.length) {
                                var lnglat = result.geocodes[0].location
                                that.lnglat = []
                                that.lnglat.push(lnglat.lng)
                                that.lnglat.push(lnglat.lat)
                            } else {
                                Toastr.error('根据地址查询位置失败');
                            }
                            if (that.storeForm.express_type.indexOf('store') != -1) {
                                if (that.storeForm.service_type == 'radius') {
                                    that.marker.hide()
                                    that.circle.setCenter(that.lnglat)
                                    that.map.setFitView([that.circle])
                                } else {
                                    that.circle.hide()
                                    that.circleEditor.close()
                                    that.marker.show()
                                    that.marker.setPosition(that.lnglat)
                                    that.map.setFitView([that.marker])
                                }
                            } else {
                                that.circle.hide()
                                that.circleEditor.close()
                                that.marker.show()
                                that.marker.setPosition(that.lnglat)
                                that.map.setFitView([that.marker])
                            }
                        });
                    },
                    areaOptionsChange(val) {
                        this.storeForm.address = ""
                        this.analysisAddress(val.join(','))
                    },
                    radioChange(val) {
                        if (val == 'area') {
                            this.circle.hide()
                            this.circleEditor.close()

                            this.marker.show()
                            this.marker.setPosition(this.lnglat)
                            this.map.setFitView([this.marker])
                        } else if(val == 'radius') {
                            this.marker.hide()

                            this.circle.show()
                            this.circleEditor.open()
                            this.circle.setCenter(this.lnglat)
                            this.map.setFitView([this.circle])

                        } else if(val == 'diyarea'){
                            this.getInitMapDiy();
                        }
                    },
                    checkChange(val) {
                        this.storeForm.express_type = val
                        if (val.indexOf('store') == -1) {
                            this.circle.hide()
                            this.circleEditor.close()

                            this.marker.show()
                            this.marker.setPosition(this.lnglat)
                            this.map.setFitView([this.marker])
                        }
                    },
                    changeTime(val) {
                        this.storeForm.openhours = val.join(' - ')
                    },
                    changeWeek(val) {
                        this.storeForm.openweeks = val.join(',')
                    },
                    editArea(index) {
                        let that = this;
                        let parmas = {
                            name: that.storeForm.area_text,
                            province_ids: that.storeForm.service_province_ids,
                            city_ids: that.storeForm.service_city_ids,
                            area_ids: that.storeForm.service_area_ids,
                        }
                        Fast.api.open('shopro/area/select?parmas=' + encodeURI(JSON.stringify(parmas)), '区域选择', {
                            callback(data) {
                                that.storeForm.area_text = data.data.name.join(',');
                                that.storeForm.service_province_ids = data.data.province.join(',')
                                that.storeForm.service_city_ids = data.data.city.join(',')
                                that.storeForm.service_area_ids = data.data.area.join(',')
                            }
                        })
                    },
                    storeSub(type, issub) {
                        let that = this;
                        if (type == 'yes') {
                            this.$refs[issub].validate((valid) => {
                                if (valid) {
                                    let subData = JSON.parse(JSON.stringify(that.storeForm));
                                    //门店地址
                                    that.store_address_arr.forEach((i, index) => {
                                        subData[i] = subData.store_address[index]
                                    })
                                    that.store_address_id_arr.forEach((i, index) => {
                                        subData[i] = that.getStoreAreaId(subData.store_address)[index]
                                    })
                                    //配送
                                    if (subData.express_type.indexOf('store') != -1) {
                                        subData.store = 1
                                        if (subData.service_type == 'radius') {
                                            that.radiusDelArr.forEach(i => {
                                                delete subData[i]
                                            })
                                        } else {
                                            that.areaDelArr.forEach(i => {
                                                delete subData[i]
                                            })
                                        }
                                    } else {
                                        subData.store = 0
                                        that.radiusDelArr.forEach(i => {
                                            delete subData[i]
                                        })
                                        that.areaDelArr.forEach(i => {
                                            delete subData[i]
                                        })
                                    }
                                    if (subData.express_type.indexOf('selfetch') != -1) {
                                        subData.selfetch = 1
                                    } else {
                                        subData.selfetch = 0
                                    }
                                    subData.user_ids = subData.user_ids.join(',')

                                    //经纬度
                                    subData.latitude = that.lnglat[1]
                                    subData.longitude = that.lnglat[0]

                                    // 服务区域
                                    subData.service_area = that.service_area

                                    //最后处理
                                    that.mustDelArr.forEach(i => {
                                        delete subData[i]
                                    })
                                    if (that.opttype != 'add') {
                                        Fast.api.ajax({
                                            url: 'shopro/store/store/edit?ids=' + that.store_id,
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
                                            url: 'shopro/store/store/add',
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
                            Fast.api.close({ data: false })
                        }
                    },
                    getAreaOptions() {
                        var that = this;
                        Fast.api.ajax({
                            url: `shopro/area/select`,
                            loading: false,
                            type: 'GET',
                        }, function (ret, res) {
                            that.areaOptions = res.data;
                            return false;
                        })
                    },
                    getStoreAreaId(arrname) {
                        let arr = []
                        this.areaOptions.forEach(i => {
                            if (i.name == arrname[0]) {
                                arr.push(i.id)
                            }
                            i.children.forEach(j => {
                                if (j.name == arrname[1]) {
                                    arr.push(j.id)
                                }
                                j.children.forEach(k => {
                                    if (k.name == arrname[2]) {
                                        arr.push(k.id)
                                    }
                                })
                            })
                        })
                        return arr
                    },
                    changeUser() {
                        this.dataFilter('')
                    },
                    debounceFilter: debounce(function () {
                        this.getUser()
                    }, 1000),
                    dataFilter(val) {
                        this.searchPage = val;
                        this.limit = 6;
                        this.offset = 0;
                        this.currentPage = 1;
                        this.debounceFilter();
                    },
                    //分页
                    pageSizeChange(val) {
                        this.offset = 0;
                        this.limit = val;
                        this.currentPage = 1;
                        this.getUser();
                    },
                    pageCurrentChange(val) {
                        this.offset = (val - 1) * 6;
                        this.limit = 6;
                        this.currentPage = 1;
                        this.getUser();
                    },
                    addImg(index, multiple) {
                        let that = this;
                        parent.Fast.api.open("general/attachment/select?multiple=" + multiple, "选择图片", {
                            callback: function (data) {
                                if (index != null) {
                                    that.$set(that.timeData.images_arr, index, data.url)
                                    that.storeForm.images = that.timeData.images_arr.join(',');
                                } else {
                                    that.storeForm.images = that.storeForm.images ? that.storeForm.images + ',' + data.url : data.url;
                                    let arrs = that.storeForm.images.split(',');
                                    if (arrs.length > 9) {
                                        that.timeData.images_arr = arrs.slice(-9)
                                    } else {
                                        that.timeData.images_arr = arrs
                                    }
                                    that.storeForm.images = that.timeData.images_arr.join(',');
                                }
                            }
                        });
                        return false;
                    },
                    delImg(index) {
                        this.timeData.images_arr.splice(index, 1);
                        this.storeForm.images = this.timeData.images_arr.join(',');
                    },
                    detailAddressFilter: debounce(function () {
                        let that = this;
                        var autoOptions = {
                            input: "tipinput",
                            city: that.storeForm.store_address[1], //城市，默认全国
                        };
                        var auto = new AMap.Autocomplete(autoOptions);
                        var placeSearch = new AMap.PlaceSearch({
                            map: that.map,
                            city: that.storeForm.store_address[1], //城市，默认全国
                        }); //构造地点查询类
                        AMap.event.addListener(auto, "select", select); //注册监听，当选中某条记录时会触发
                        function select(e) {
                            that.storeForm.address = e.poi.name;
                            that.lnglat = []
                            that.lnglat.push(e.poi.location.lng)
                            that.lnglat.push(e.poi.location.lat)
                            if (that.storeForm.service_type == 'radius') {
                                that.circle.setCenter(that.lnglat)
                                that.map.setFitView([that.circle])
                            } else {
                                that.marker.setPosition(that.lnglat)
                                that.map.setFitView([that.marker])
                            }
                        }
                        let str = that.storeForm.store_address.join('')
                        let address = str + that.storeForm.address;
                        if (address) {
                            this.analysisAddress()
                        }
                    }, 1000),
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
