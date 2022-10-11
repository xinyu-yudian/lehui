function shoproSelectGoods(params,title) {
    return new Promise(function (resolve, reject) {
        let url="shopro/goods/goods/select?multiple=" + params.multiple + "&type=" + params.type + "&ids=" + params.ids
        Fast.api.open(url, title, {
            callback: function (data) {
                resolve(data);
            }
        });
    });
}
function shoproGoodsList(ids){
    return new Promise(function (resolve, reject) {
        let domain=window.location.origin;
        Fast.api.ajax({
            url: domain+'/addons/shopro/goods/lists?goods_ids=' + ids +"&per_page=999999999&type=all",
            loading: false,
        }, function (ret, res) {
            resolve(res.data);
            return false;
        })
    });
}
function shoproGoodsDetail(id){
    return new Promise(function (resolve, reject) {
        let domain=window.location.origin;
        Fast.api.ajax({
            url: domain+'/addons/shopro/goods/detail?id='+id,
            loading: false,
        }, function (ret, res) {
            resolve(res.data);
            return false;
        }, function (ret, res) {
            reject(res.data);
            return false;
        })
    });
}

