<?php
/**
 * 订单单个查询
 * @date 2019年6月13日
 * @copyright 重庆迅虎网络有限公司
 */
require_once 'api.php';
$param=$_GET;
if($param['type']=='wechat'){
	$appid              = 'YOUR_APPID';
	$appsecret          = 'YOUR_APPSECRET';
}else{
	$appid              = 'YOUR_APPID';
	$appsecret          = 'YOUR_APPSECRET';
}
//out_trade_order，open_order_id 二选一
$request=array(
    'appid'     => $appid, //必须的，APPID

    'out_trade_order'=> $param['order_id'], //网站订单号(out_trade_order，open_order_id 二选一)
    //'open_order_id'=> $open_order_id, //虎皮椒内部订单号，在下单时会返回，或支付后会异步回调(out_trade_order，open_order_id 二选一)

    'time'      => time(),//必须的，当前时间戳，根据此字段判断订单请求是否已超时，防止第三方攻击服务器
    'nonce_str' => str_shuffle(time())//必须的，随机字符串，作用：1.避免服务器缓存，2.防止安全密钥被猜测出来
);

$request['hash'] =  XH_Payment_Api::generate_xh_hash($request,$appsecret);

$url              = 'https://api.xunhupay.com/payment/query.html';

try {
    $response     = XH_Payment_Api::http_post($url, http_build_query($request));
    /**
     * 支付回调数据
     * @var array(
     *      status,//OD：已支付  WP:未支付  CD 已取消
     *  )
     */
    $result       = $response?json_decode($response,true):null;
    if(!$result){
        throw new Exception('Internal server error:'.$response,500);
    }
    if($result['data']['status']=='OD'){
    	echo 'complete';
    	exit;
    }else{
    	echo 'paidding';
    	exit;
    }
} catch (Exception $e) {
    echo "errcode:{$e->getCode()},errmsg:{$e->getMessage()}";
    //TODO:处理支付调用异常的情况
}
?>
