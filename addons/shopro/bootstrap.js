if (Config.modulename == 'admin' && Config.controllername == 'index' && Config.actionname == 'index') {
    require.config({
        paths: {
            'vue': "../addons/shopro/libs/vue",
            'moment': "../addons/shopro/libs/moment",
            'text': "../addons/shopro/libs/require-text",
            'chat': '../addons/shopro/libs/chat',
            'ELEMENT': '../addons/shopro/libs/element/element',
        },
        shim: {
            'ELEMENT': {
                deps: ['css!../addons/shopro/libs/element/element.css']
            },
        },
    });
    require(['vue', 'jquery', 'chat', 'text!../addons/shopro/chat.html', 'ELEMENT', 'moment'], function (Vue, $, Chat, ChatTemp, ELEMENT, Moment) {

        Vue.use(ELEMENT);

        var wsUri;
        Fast.api.ajax({
            url: 'shopro/chat/index/init',
            loading: false,
            type: 'GET'
        }, function (ret, res) {
            if (res.data.config.type == 'shopro') {

                let wg = 'ws';
                if (res.data.config.system.is_ssl == 1) {
                    wg = 'wss';
                }
                wsUri = wg + '://' + window.location.hostname + ':' + res.data.config.system.gateway_port;
                // 反向代理
                if (res.data.config.system.is_ssl == 1 && res.data.config.system.ssl_type == 'reverse_proxy') {
                    wsUri = wg + '://' + window.location.hostname + '/websocket/';
                }
                $("body").append(`<div id="chatTemplateContainer" style="display:none"></div>
                    <div id="chatService"><Chat :passvalue="obj"></Chat></div>`);

                $("#chatTemplateContainer").append(ChatTemp);

                new Vue({
                    el: "#chatService",
                    data() {
                        return {
                            obj: {
                                commonWordsList: res.data.fast_reply,
                                token: res.data.token,
                                wsUri: wsUri,
                                expire_time: res.data.expire_time,
                                customer_service_id: res.data.customer_service.id,
                                adminData: res.data,
                                emoji_list: res.data.emoji
                            }
                        }
                    }
                });

            }
            return false;
        }, function (ret, res) {
            if (res.msg == '') {
                return false;
            }
        })
    });
}