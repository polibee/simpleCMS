<?php
require_once 'api.php';
$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
$recent_url=dirname($http_type.$_SERVER['SERVER_NAME'].$_SERVER["REQUEST_URI"]);
$param=$_POST;
if($param){
    $title = '微信';
	if($param['type']=='wechat'){
		$appid              = '201906130470';
		$appsecret          = 'd6031f0c7debcc61d486cad77468ae46';
		$title = '微信';
	}else{
		$appid              = '201906130470';
		$appsecret          = 'd6031f0c7debcc61d486cad77468ae46';
		$title = '支付宝';
	}
	$data=array(
	    'version'   => '1.1',//固定值，api 版本，目前暂时是1.1
	    'appid'     => $appid, //必须的，APPID
	    'plugins'	=> $param['type'],
	    'trade_order_id'=> time(), //必须的，网站订单ID，唯一的，匹配[a-zA-Z\d\-_]+
	    'total_fee' => $param['price'],//人民币，单位精确到分(测试账户只支持0.1元内付款)
	    'title'     => '支付测试', //必须的，订单标题，长度32或以内
	    'time'      => time(),//必须的，当前时间戳，根据此字段判断订单请求是否已超时，防止第三方攻击服务器
	    'notify_url'=> $recent_url.'/notify.php', //必须的，支付成功异步回调接口
	    'return_url'=> $recent_url.'/return.html', //支付成功返回地址
	    'nonce_str' => str_shuffle(time())//必须的，随机字符串，作用：1.避免服务器缓存，2.防止安全密钥被猜测出来
	);
	if($param['type']=='wechat' && XH_Payment_Api::isWebApp()){
		$data['type'] = "WAP";
		$data['wap_url'] = $recent_url;
		$data['wap_name'] = $recent_url;
	}
	$hashkey =$appsecret;
	$data['hash']     = XH_Payment_Api::generate_xh_hash($data,$hashkey);
	/**
	 * 个人支付宝/微信官方支付，支付网关：https://api.xunhupay.com
	 * 微信支付宝代收款，需提现，支付网关：https://pay.wordpressopen.com
	 */
	$url              = 'https://api.xunhupay.com/payment/do.html';

	try {
	    $response     = XH_Payment_Api::http_post($url, json_encode($data));
	    /**
	     * 支付回调数据
	     * @var array(
	     *      order_id,//支付系统订单ID
	     *      url//支付跳转地址
	     *  )
	     */
	    $result       = $response?json_decode($response,true):null;
	    if(empty($result)){
	    	exit($response);
	    }
	    if ($result['errcode'] != 0) {
	    	exit($result['errmsg']);
	    }
	    if (XH_Payment_Api::isWebApp()) {
	    	$pay_url =$result['url'];
		    header("Location: $pay_url");
		    exit;
	    }
		$qrcode=$result['url_qrcode'];
	} catch (Exception $e) {
	    echo "errcode:{$e->getCode()},errmsg:{$e->getMessage()}";
	    //TODO:处理支付调用异常的情况
	}
}
?>
<!DOCTYPE html>
	<html>
	<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="keywords" content="">
    <meta name="description" content="">
    <title><?php echo $title;?>支付收银台</title>
    <style>
         *{margin:0;padding:0;}
          body{background: #f2f2f4;}
         .clearfix:after { content: "."; display: block; height: 0; clear: both; visibility: hidden; }
        .clearfix { display: inline-block; }
        * html .clearfix { height: 1%; }
        .clearfix { display: block; }
          .xh-title{height:75px;line-height:75px;text-align:center;font-size:30px;font-weight:300;border-bottom:2px solid #eee;background: #fff;}
          .qrbox{max-width: 900px;margin: 0 auto;padding:85px 20px 20px 50px;}

          .qrbox .left{width: 40%;
            float: left;
             display: block;
            margin: 0px auto;}
          .qrbox .left .qrcon{
            border-radius: 10px;
            background: #fff;
            overflow: visible;
            text-align: center;
            padding-top:25px;
            color: #555;
            box-shadow: 0 3px 3px 0 rgba(0, 0, 0, .05);
            vertical-align: top;
            -webkit-transition: all .2s linear;
            transition: all .2s linear;
          }
            .qrbox .left .qrcon .logo{width: 100%;}
            .qrbox .left .qrcon .title{font-size: 16px;margin: 10px auto;width: 100%;}
            .qrbox .left .qrcon .price{font-size: 22px;margin: 0px auto;width: 100%;}
            .qrbox .left .qrcon .bottom{border-radius: 0 0 10px 10px;
            width: 100%;
            background: #32343d;
            color: #f2f2f2;padding:15px 0px;text-align: center;font-size: 14px;}
           .qrbox .sys{width: 60%;float: right;text-align: center;padding-top:20px;font-size: 12px;color: #ccc}
           .qrbox img{max-width: 100%;}
           @media (max-width : 767px){
        .qrbox{padding:20px;}
            .qrbox .left{width: 90%;float: none;}
            .qrbox .sys{display: none;}
           }

           @media (max-width : 320px){

          }
          @media ( min-width: 321px) and ( max-width:375px ){

          }
    </style>
    </head>
    <body>
     <div class="xh-title"><img src="/images/<?php echo $param['type'];?>.png" alt="" style="vertical-align: middle"> <?php echo $title;?>支付收银台</div>
      <div class="qrbox clearfix">
      <div class="left">
         <div class="qrcon">
           <h5><img src="/images/<?php echo $param['type'];?>/logo.png" alt=""></h5>
             <div class="title"><?php print $data['title'];?></div>
             <div class="price"><?php echo $data['total_fee'];?> 元</div>
             <div align="center"><div id="wechat_qrcode" style="width: 250px;height: 250px;"><img id='qrcode' src="<?php echo $qrcode;?>"/></div></div>
             <div class="bottom">
                请使用<?php echo $title;?>扫一扫<br/>
                扫描二维码支付
             </div>
         </div>
  </div>
     <div class="sys"><img src="/images/<?php echo $param['type'];?>/<?php echo $param['type'];?>-sys.png" alt=""></div>
  </div>
<script src="js/jquery-2.1.4.min.js"></script>
<script type="text/javascript">
(function($){
	window.view={
		query:function () {
	        $.ajax({
	            type: "POST",
	            url: "<?php echo $recent_url.'/query.php?order_id='.$data['trade_order_id'].'&type='.$param['type'] ?>",
	            timeout:6000,
	            cache:false,
	            dataType:'text',
	            success:function(e){
            		if (e && e.indexOf('complete')!==-1) {
                        window.location.href = "<?php echo $recent_url.'/return.html' ?>";
                        return;
	                }
	            },
	            error:function(){
	            }
	        });
	    }
	};
	var timer;
	timer = setInterval(function(){window.view.query();}, 5000);
  setTimeout(function(){
      console.log("任务已停止。");
      clearInterval(timer); // 清除定时器
      $("#qrcode").attr('src','/images/error.png');
  }, 300 * 1000);
  window.view.query();
})(jQuery);
</script>
</body>
</html>