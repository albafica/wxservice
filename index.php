<?php

/**
 *  微信回调接口
 */
define(ROOT_PATH, dirname(__FILE__));
include ROOT_PATH . '/API/weixinAPI.class.php';
$wechatObj = new weixinAPI();
//验证结果不是微信消息，直接返回空值
if (!$wechatObj->chkSign($_GET['signature'], $_GET['timestamp'], $_GET['nonce'])) {
    echo '';
    exit();
}

if (isset($_GET['echostr'])) {
    echo $_GET['echostr'];
} else {
    echo $wechatObj->responseMsg();
}
