<?php
/**
 * 退款demo
 * @date 2019年6月13日
 * @copyright 重庆迅虎网络有限公司
 */
require_once 'api.php';
$appid              = '';
$appsecret          = '';
//trade_order_id，open_order_id 二选一
$request=array(
    'appid'     => $appid, //必须的，APPID
    // 'trade_order_id'=> '', //网站订单号(trade_order_id，open_order_id 二选一)
    'open_order_id'=> '', //虎皮椒内部订单号，在下单时会返回，或支付后会异步回调(trade_order_id，open_order_id 二选一)
    'reason'=>'退款测试',
    'time'      => time(),//必须的，当前时间戳，根据此字段判断订单请求是否已超时，防止第三方攻击服务器
    'nonce_str' => str_shuffle(time())//必须的，随机字符串，作用：1.避免服务器缓存，2.防止安全密钥被猜测出来
);

$request['hash'] =  XH_Payment_Api::generate_xh_hash($request,$appsecret);

$url              = 'https://api.diypc.com.cn/payment/refund.html';

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
    if($result['errcode'] != 0){
        echo $result['errmsg'];die;
    }
    $hash =  XH_Payment_Api::generate_xh_hash($result,$appsecret);
    if($result['hash'] != $hash){
        echo '返回签名错误';die;
    }
    if($result['refund_status'] == 'CD'){
        echo '退款成功';die;
    }
    if($result['refund_status'] == 'RD'){
        echo '退款处理中';die;
    }
} catch (Exception $e) {
    echo "errcode:{$e->getCode()},errmsg:{$e->getMessage()}";
    //TODO:处理支付调用异常的情况
}
?>
