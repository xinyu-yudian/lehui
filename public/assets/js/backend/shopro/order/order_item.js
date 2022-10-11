define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shopro/order/order_item/index' + location.search,
                    add_url: 'shopro/order/order_item/add',
                    edit_url: 'shopro/order/order_item/edit',
                    del_url: 'shopro/order/order_item/del',
                    multi_url: 'shopro/order/order_item/multi',
                    table: 'shopro_order_item',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'user_id', title: __('User_id') },
                        { field: 'order_id', title: __('Order_id') },
                        { field: 'goods_id', title: __('Goods_id') },
                        { field: 'goods_sku_price_id', title: __('Goods_sku_price_id') },
                        { field: 'activity_id', title: __('Activity_id') },
                        { field: 'activity_type', title: __('Activity_type') },
                        { field: 'item_goods_sku_price_id', title: __('Item_goods_sku_price_id') },
                        { field: 'goods_sku_text', title: __('Goods_sku_text') },
                        { field: 'goods_title', title: __('Goods_title') },
                        { field: 'goods_image', title: __('Goods_image'), events: Table.api.events.image, formatter: Table.api.formatter.image },
                        { field: 'goods_original_price', title: __('Goods_original_price'), operate: 'BETWEEN' },
                        { field: 'discount_fee', title: __('Discount_fee'), operate: 'BETWEEN' },
                        { field: 'goods_price', title: __('Goods_price'), operate: 'BETWEEN' },
                        { field: 'goods_num', title: __('Goods_num') },
                        {
                            field: 'dispatch_status', title: __('Dispatch_status'), searchList: { "0": __('Dispatch_status 0'), "1": __('Dispatch_status 1'), "2": __('Dispatch_status 2') },
                            formatter: function (value, row) {
                                return `
                            <p class="popover-options">
                                <a href="#" type="button" class="btn btn-warning" title="" data-html="true" data-placement="top" 
                                data-container="body" data-toggle="popover" data-content="<h4><div class='popover-btn'><i class='fa fa-edit info btn btn-success sendAll'><span class='display-none'>${row.id}</span>统一发货</i></div>
                                <div class='popover-btn'><i class='fa fa-edit sendSingle info btn btn-success'><span class='display-none'>${row.id}</span>单品发货</i></div></h4>">
                                    ${value}
                                </a>
                            </p>
                            `
                            },
                        },
                        { field: 'dispatch_fee', title: __('Dispatch_fee'), operate: 'BETWEEN' },
                        { field: 'dispatch_type', title: __('Dispatch_type') },
                        { field: 'dispatch_id', title: __('Dispatch_id') },
                        { field: 'aftersale_status', title: __('Aftersale_status'), searchList: { "0": __('Aftersale_status 0'), "1": __('Aftersale_status 1') }, formatter: Table.api.formatter.status },
                        { field: 'comment_status', title: __('Comment_status'), searchList: { "0": __('Comment_status 0'), "1": __('Comment_status 1') }, formatter: Table.api.formatter.status },
                        { field: 'refund_status', title: __('Refund_status'),
                        formatter: function (value, row) {
                            return `
                        <p class="popover-options">
                            <a href="#" type="button" class="btn btn-warning" title="" data-html="true" data-placement="top" 
                            data-container="body" data-toggle="popover" data-content="
                            <h4><div class='popover-btn'><i class='fa fa-check express_ajax info btn btn-success'><span class='display-none'>${row.id}</span>确认</i></div>
                            <div class='popover-btn'><i class='fa fa-close info btn btn-danger express_ajax'><span class='display-none'>${row.id}</span>拒绝</i></div></h4>">
                                ${value}
                            </a>
                        </p>
                        `
                        }},

                        // searchList: { "-1": __('Refund_status -1'), "0": __('Refund_status 0'), "1": __('Refund_status 1'), "2": __('Refund_status 2') }, formatter: Table.api.formatter.status },
                        {
                            field: 'express_name', title: __('Express_name'),
                            
                        },
                        {
                            field: 'express_no', title: __('Express_no'),
                            formatter: function (value, row) {
                                return `<div>${value == null ? '' : value}<i class="fa fa-edit testExpress_no info"></i></div>`
                            }
                        },
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime },
                        { field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime },
                        { field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate }
                    ]
                ]
            });
            //申请退货
            $(document).on("click", ".express_ajax", function () {
                var that = this;
                var id=$(this).children("span")[0].innerHTML
                Layer.confirm(
                    __('确认退款?',),
                    {icon: 3, title: __('Warning'), offset: 0, shadeClose: true},
                    function (index) {
                        Backend.api.ajax({
                            url: "acc/bdd/delexp",
                            data: $(that).closest("form").serialize()
                        });
                        table.bootstrapTable('refresh');
                        Layer.close(index);
                    }
                );
            });
            $(".popover-options").blur(function(){
                $('.popover').css("display","none");
              });
               $('.popover-options').on('shown.bs.popover', function () {
                alert("当显示时警告消息");
            })
            // $(document).on("click",function(){
            //     $('.popover').css("display","none");
            // })
            //统一发货
            $(document).on("click", ".sendAll", function () {
                var id = $(this).children("span")[0].innerHTML
                var that = this;
                layer.open({
                    type: 1,
                    area: ['300px', '300px'],
                    shadeClose: false, //点击遮罩关闭
                    content: `<div style="display: flex;
                    flex-direction: column;padding:20px 0">
                    <div class="form-group">
            <label class="control-label col-xs-12 col-sm-4">快递公司:</label>
            <div class="col-xs-4 col-sm-8">
                <input class="form-control ggg" type="text" placeholder="请输入快递公司">
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-xs-12 col-sm-4">快递单号:</label>
            <div class="col-xs-4 col-sm-8">
                <input class="form-control fff" type="text" placeholder="请输入快递单号">
            </div>
        </div>
        <button class="sendAllBtn btn btn-success">确定</button>
                    </div>`
                });
                $(".sendAllBtn").click(function () {
                    var ggg = $(".ggg").val()
                    var fff = $(".fff").val()
                    Backend.api.ajax({
                        url: "general/config/emailtest?id=" + value,
                        data: $(that).closest("form").serialize()

                    });
                    table.bootstrapTable('refresh');
                    Layer.close(index);
                })
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
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
                url: 'shopro/order/order_item/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
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
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'shopro/order/order_item/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'shopro/order/order_item/destroy',
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