requirejs.config({
	paths: {
		g2: "/assets/addons/shopro/libs/antv"
	}
})
define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'g2'], function ($, undefined, Backend, Table, Form, G2) {

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
			var dashboardIndex = new Vue({
				el: "#dashboardIndex",
				data() {
					return {
						orderList: [],
						chartsData: [],
						dataList: [{
								id: 'payed',
								title: '支付订单',
								num: 0,
								checked: true,
								type:'payOrder',
								color: 'rgba(157, 96, 255, 1)',
								back: 'rgba(157, 96, 255, 0.1)',
								unit: '单'
							}, {
								id: 'paymoney',
								title: '支付金额',
								num: 0,
								checked: true,
								type:'payAmount',
								color: 'rgba(0,198,198,1)',
								back: 'rgba(0,198,198,0.1)',
								unit: '元'
							}, {
								id: 'nosend',
								title: '待备货订单',
								num: 0,
								checked: false,
								type:'noSent',
								color: 'rgba(246,95,34,1)',
								back: 'rgba(246,95,34,0.1)',
								unit: '笔'
							}, {
								id: 'noget',
								title: '待配送/待核销',
								num: 0,
								checked: false,
								type:'offSent',
								color: 'rgba(101,180,1,1)',
								back: 'rgba(101,180,1,0.1)',
								unit: '笔'
							},
							{
								id: 'orderpersonnum',
								title: '下单人数',
								num: 0,
								checked: false,
								type:'down',
								color: 'rgba(231,19,184,1)',
								back: 'rgba(231,19,184,0.1)',
								unit: '人'
							}, {
								id: 'allordernum',
								title: '总订单量(含未付款)',
								num: 0,
								checked: false,
								type:'order',
								color: 'rgba(50,108,222,1)',
								back: 'rgba(50,108,222,0.1)',
								unit: '笔'
							}, {
								id: 'aftersale',
								title: '维权订单',
								num: 0,
								checked: false,
								type: 'aftersale',
								color: 'rgba(231,172,37,1)',
								back: 'rgba(231,172,37,0.1)',
								unit: '笔'
							}, {
								id: 'refund',
								title: '退款订单',
								num: 0,
								checked: false,
								type: 'refund',
								color: 'rgba(237,41,104,1)',
								back: 'rgba(237,41,104,0.1)',
								unit: '笔'
							}
						],
						selectInputs: [],
						dropdownList: [{
							date: 'yesterday',
							name: '昨日'
						}, {
							date: 'today',
							name: '今日'
						}, {
							date: 'week',
							name: '近一周'
						}, {
							date: 'month',
							name: '近一月'
						}, {
							date: 'year',
							name: '近一年'
						}],
						dropdownName: '今日',
						dropdownDate: 'today',
						searchTime: [new Date(), new Date()],
						//支付单数/总单数
						tranPeople: 0,
						tranPeoplescale: 0,
						//支付比例					
						allOrderPayNum: 0,
						transcale: 0,
						allAjax: false,
						storeSelected: '0',
						searchWhree:'',
						storeSelectList: []
					}
				},
				mounted() {
					//请求数据
					this.getstoreOptions();
					this.changeTime();
				},
				methods: {
					getstoreOptions() {
						let that = this;
                        Fast.api.ajax({
                            url: 'shopro/store/store/all',
                            loading: false,
                            type: 'GET',
                            data: {
                                search: that.searchWhree,
                            }
                        }, function (ret, res) {
                            that.storeSelectList = res.data;
                            that.storeSelectList.unshift({
                                id:'0',
                                name:'全部门店'
                            })
                            return false;
                        })
                    },
                    sdebounceFilter: debounce(function () {
                        this.getstoreOptions()
					}, 1000),
					dataFilter(val) {
						this.searchWhree = val;
						this.sdebounceFilter()
                    },
					//折线
					charts() {
						$("#antvContainer").empty()
						const chart = new G2.Chart({
							container: 'antvContainer',
							autoFit: true,
							height: 332,
							animate: true,
						});
						chart.data(this.chartsData);
						var dataBoxNum = 0
						this.selectInputs.forEach(e => {
							if (e.checked) {
								dataBoxNum++
							}
						})
						if (dataBoxNum == 1) {
							let title
							this.selectInputs.forEach(i => {
								if (i.checked) {
									title = i.title
								}
							})
							chart.scale({
								date: {
									alias: ' ',
								},
								y2: {
									alias: title,
									min: 0,
									sync: true,
									nice: true,
								},
							});
							chart
								.line()
								.position('date*y2')
								.color(this.selectInputs[0].color).size(10).shape('smooth');
						} else if (dataBoxNum == 2) {
							chart.scale({
								date: {
									alias: ' ',
								},
								y2: {
									alias: this.selectInputs[1].title,
									sync: true,
									nice: true,
									min: 0
								},
								y1: {
									alias: this.selectInputs[0].title,
									sync: true,
									nice: true,
									min: 0
								},
							});
							chart
								.line().size(10)
								.position('date*y1')
								.color(this.selectInputs[0].color).shape('smooth');
							chart
								.line().size(10)
								.position('date*y2')
								.color(this.selectInputs[1].color).shape('smooth');

						} else {
							return false
						}

						chart.axis('y1', {
							grid: null,
							title: null, //左右不显示坐标提示{}
						});
						chart.axis('y2', {
							title: null,
						});

						chart.tooltip({
							showCrosshairs: true, //展示辅助线
							shared: true,
						});
						chart.render();
						this.allAjax = false;
					},
					//选择显示数据
					selectLine(idx) {
						this.dataList[idx].checked = !this.dataList[idx].checked
						if (this.dataList[idx].checked == true) {
							this.selectInputs.push(this.dataList[idx]);
							if (this.selectInputs.length > 2) {
								this.selectInputs[0].checked = false;
								this.selectInputs.shift();
							}
						} else {
							this.selectInputs.forEach((item, index) => {
								if (this.dataList[idx].id == item.id)
									this.selectInputs.splice(index, 1);
							})
						}
						this.countOrderData()
					},
					//选择时间段
					changeTime(index = 1) {
						this.dropdownDate = this.dropdownList[index].date;
						this.dropdownName = this.dropdownList[index].name;
						this.searchTime = this.getTimeSlot();
						this.getDataInfo();
					},
					//选择请求数据
					getDataInfo() {
						let that = this
						that.allAjax = true
						let timeSlot = this.searchTime.join(' - ')
						
						let urlData={
							datetimerange: timeSlot
						}
						if(that.storeSelected!='all'){
							urlData={
								datetimerange: timeSlot,
								store_id:that.storeSelected
							}
						}
						Fast.api.ajax({
							url: 'shopro/store/dashboard/index',
							loading: false,
							data: urlData,
							success: function (res, ret) {
								that.dataList.forEach(d=>{
									d.num=res.data[d.type+'Num']
									d.item=res.data[d.type+'Arr']
								})

								//判断是否选中
								that.selectInputs = []
								that.dataList.forEach((item, index) => {
									if (item.checked == true) that.selectInputs.push(item);
								})
								// 请求数据
								that.countOrderData();
							}
						})
					},
					countOrderData() {
						let that = this;
						let time = (
							new Date(that.searchTime[1]).getTime() - new Date(that.searchTime[0]).getTime()
						) / 1000 + 1;
						let kld = '';
						let interval = 0;
						if (time <= 60 * 60) {
							interval = parseInt(time / 60);

							kld = 'minutes';
						} else if (time <= 60 * 60 * 24) {
							interval = parseInt(time / (60 * 60));

							kld = 'hours';
						} else if (time <= 60 * 60 * 24 * 30 * 1.5) {
							interval = parseInt(time / (60 * 60 * 24));

							kld = 'days';

						} else if (time < 60 * 60 * 24 * 30 * 24) {
							interval = parseInt(time / (60 * 60 * 24 * 30));

							kld = 'months';

						} else if (time >= 60 * 60 * 24 * 30 * 24) {
							interval = parseInt(time / (60 * 60 * 24 * 30 * 12));

							kld = 'years';

						}
						this.drawX(interval, kld);

					},
					drawX(interval, kld) {
						let that = this
						let x = [];
						let selectInputLeng = 0
						this.selectInputs.forEach(e => {
							if (e.checked) {
								selectInputLeng++
							}
						})
						for (let i = 0; i <= interval; i++) {
							if (kld == 'minutes' || kld == 'hours') {
								x.push({
									date: moment(that.searchTime[0]).add(i, kld).format("DD HH:mm"),
									timeStamp: moment(that.searchTime[0]).add(i, kld).valueOf(),
									y2: 0,
									y1: 0
								});
							} else if (kld == 'days') {
								x.push({
									date: moment(that.searchTime[0]).add(i, kld).format("YYYY-MM-DD"),
									timeStamp: moment(that.searchTime[0]).add(i, kld).valueOf(),
									y2: 0,
									y1: 0
								});
							} else if (kld == 'months') {
								x.push({
									date: moment(that.searchTime[0]).add(i, kld).format("YYYY-MM"),
									timeStamp: moment(that.searchTime[0]).add(i, kld).valueOf(),
									y2: 0,
									y1: 0
								});
							} else {
								x.push({
									date: moment(that.searchTime[0]).add(i, kld).format("YYYY"),
									timeStamp: moment(that.searchTime[0]).add(i, kld).valueOf(),
									y2: 0,
									y1: 0
								});
							}
						}
						if (selectInputLeng == 1) {
							for (var y = 0; y < x.length; y++) {
								let y2 = 0
								let selectItem = []
								this.selectInputs.forEach(element => {
									if (element.checked == true) {
										selectItem = element.item
									}
								})
								selectItem.forEach(se => {
									if (y != x.length - 1) {
										if (se.createtime > x[y].timeStamp && se.createtime <= x[y + 1].timeStamp) {
											y2 += Number(se.counter)
										}
									} else {
										if (se.createtime > x[y].timeStamp) {
											y2 += Number(se.counter)
										}
									}

								})
								x[y].y2 = y2
							}
						}
						if (selectInputLeng == 2) {
							for (var y = 0; y < x.length; y++) {
								let y1 = 0
								let y2 = 0
								this.selectInputs.forEach((si,sindex)=>{
									si.item.forEach(se => {
										if (y != x.length - 1) {
											if (se.createtime > x[y].timeStamp && se.createtime <= x[y + 1].timeStamp) {
												if(sindex==0){
													y1 += Number(se.counter)
												}else{
													y2+= Number(se.counter)
												}
											}
										} else {
											if (se.createtime > x[y].timeStamp) {
												if(sindex==0){
													y1 += Number(se.counter)
												}else{
													y2+= Number(se.counter)
												}
											}
										}
									})
								})
								x[y].y1 = y1
								x[y].y2 = y2
							}
						}
						that.chartsData = x
						that.allAjax = false
						that.charts()
					},
					//获取时间
					getTimeSlot() {
						let beginTime = '';
						let endTime = moment().format('YYYY-MM-DD');
						switch (this.dropdownDate) {
							case 'yesterday':
								endTime = moment().subtract(1, 'days').format('YYYY-MM-DD')
								beginTime = endTime
								break;
							case 'today':
								beginTime = endTime;
								break;
							case 'week':
								beginTime = moment().subtract(1, 'weeks').format('YYYY-MM-DD')
								break;
							case 'month':
								beginTime = moment().subtract(1, 'months').format('YYYY-MM-DD')
								break;
							case 'year':
								beginTime = moment().subtract(1, 'years').format('YYYY-MM-DD')
								break;
						}
						let timeSlot = [beginTime + ' 00:00:00', endTime + ' 23:59:59'];
						return timeSlot;
					},
					goDetail(status) {
						let that = this;
						let times = encodeURI(that.searchTime.join(" - "))
						parent.Fast.api.open(`shopro/order/order/index?status=${status}&datetimerange=${times}&store_id=${that.storeSelected}`, "查看详情", {
							callback: function (data) {}
						});
						return false;
					},
					scaleFunc(a, b) {
						return (a <= 0 ? 0 : a / b).toFixed(2) - 0
					}
				},
			})
		},
	};
	return Controller;
});