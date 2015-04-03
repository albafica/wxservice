<?php

/**
 * Description of wxAPICallback
 * 微信回调接口类
 * @author albafica.wang
 */
class weixinAPI {

    protected $_token = 'dfasdgfdgfdsdfsdf';
    private $_postMsgObj = null;        //接受的消息对象
    protected $_appid = 'wxe3a55e8c7cd2d225';
    protected $_appsecret = '48be337ac894eb2c38066a9cb57492e2';

//    protected $_redirectURL = 'http://www.abdedemo.com/tools/openwx/oAuth/oauth2.php';       //oAuth授权认证回调地址
//    protected $_scope = 'snsapi_userinfo';             //应用授权作用域,snsapi_base, snsapi_userinfo
//    private $_ak = 'u9edtmk52mDkEDRia00NWBwf';

    public function __construct() {
        
    }

    /**
     * 验证微信信息正确性
     */
    public function valid($echoStr, $signature, $timestamp, $nonce) {
        if ($this->chkSign($signature, $timestamp, $nonce)) {
            return $echoStr;
        }
        return '';
    }

    /**
     * 检验微信传递过来的签名
     * @param string $signature         微信传递过来的签名
     * @param string $timestamp         生成签名用字段
     * @param string $nonce             生成签名用字段
     * @return boolean                  签名验证结果
     */
    public function chkSign($signature, $timestamp, $nonce) {
        if (empty($this->_token)) {
            return false;
        }
        $tmpArr = array($this->_token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = sha1(implode($tmpArr));
        return ($tmpStr === $signature) ? true : false;
    }

    /**
     * 回应消息
     */
    public function responseMsg() {
        //获取用户推送过来的信息
        $postStr = file_get_contents('php://input', 'r');
        if (empty($postStr)) {
            return '';
        }
        $this->_postMsgObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $message = "用户发来的消息：" . $postStr . "\r\n";
        $this->trace($message);
        $msgType = strtolower(trim($this->_postMsgObj->MsgType));
        $message = "消息类型：" . $msgType . "\r\n";
        $this->trace($message);
        switch ($msgType) {
            //文本消息
            case 'text':
                $result = $this->handleText();
                break;
            //图片消息
            case 'image':
                $result = $this->handleImage();
                break;
            //音频消息
            case 'voice':
                $result = $this->handleVoice();
                break;
            //视频消息
            case 'video':
                $result = $this->handleVideo();
                break;
            //位置消息
            case 'location':
                $result = $this->handleLocation();
                break;
            //链接消息
            case 'link':
                $result = $this->handleLink();
                break;
            case 'event':
                $result = $this->handleEvent();
                break;
            //非正常消息
            default:
                $result = '';
                break;
        }
        return $result;
    }

    /**
     * 获取应用accesstoken，7200秒内有效
     * @return string
     */
    public function getAccessToken() {
        //缓存token值保存在session中|memacache|redis中均可
        $mem = new Memcache();
        $mem->connect('127.0.0.1', 11211);
        $accessToken = $mem->get('wxaccesstoken');
        if (!empty($accessToken)) {
            return $accessToken;
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->_appid . '&secret=' . $this->_appsecret;
        $token = $this->getRequestByCurl($requestURL);
        if ($token === false) {
            //token获取失败
            return '';
        }
        $tokenArr = json_decode($token, true);
        //将accessToken存入memcache中
        $mem->set('wxaccesstoken', $tokenArr['access_token'], MEMCACHE_COMPRESSED, $tokenArr['expires_in'] - 300);
        return $tokenArr['access_token'];
    }

    /**
     * 获取oAuth授权的accessToken
     * @param string $code        获取token的票据
     */
    public function getOAuthAccessToken($code) {
        $requestURL = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $this->_appid . '&secret=' . $this->_appsecret . '&code=' . $code . '&grant_type=authorization_code';
        $result = $this->getRequestByCurl($requestURL);
        return json_decode($result, true);
    }

    /**
     * 刷新授权认证token
     * @param string $refreshToken
     */
    public function refreshOAuthAccessToken($refreshToken) {
        $requestURL = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=' . $this->_appid . '&grant_type=refresh_token&refresh_token=' . $refreshToken;
        $result = $this->getRequestByCurl($requestURL);
        return json_decode($result, true);
    }

    /**
     * 拉取用户信息(需scope为 snsapi_userinfo)
     * @param type $accessToken
     */
    public function getUserInfoByOAuthAccessToken($oauthAccessToken) {
        $requestURL = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $oauthAccessToken . '&openid=' . $this->_appid . '&lang=zh_CN';
        $result = $this->getRequestByCurl($requestURL);
        return json_decode($result, true);
    }

    /**
     * 根据用户标识openid获取用户信息
     * @param string $openid            用户唯一标识
     * @param string $accessToken       accestoken
     */
    public function getUserInfoByOpenid($openid, $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestUrl = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $accessToken . '&openid=' . $openid . '&lang=zh_CN';
        $result = $this->getRequestByCurl($requestUrl);
        return json_decode($result, true);
    }

    /**
     * 获取所有关注者openid，一次最多获取10000人，超出的使用nextopenid分批获取
     * @param string $nextOpenId          分批获取用openid，为空从头获取，一般为上一次获取到的直接拿来用即可
     * @param string $accessToken           accesstoken
     * @return type
     */
    public function getAllFollow($nextOpenId = '', $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestUrl = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token=' . $accessToken . '&next_openid=' . $nextOpenId;
        $result = $this->getRequestByCurl($requestUrl);
        return json_decode($result, true);
    }

    /**
     * 检验授权凭证是否有效
     * @param string $accessToken       授权凭证
     */
    public function checkOAuthAccessToken($accessToken, $openId) {
        $requestURL = 'https://api.weixin.qq.com/sns/auth?access_token=' . $accessToken . '&openid=' . $openId;
        $result = $this->getRequestByCurl($requestURL);
        return json_decode($result, true);
    }

    /**
     * 获取所有关注着分组
     * @param string $accessToken
     */
    public function getAllFollowerOrg($accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/groups/get?access_token=' . $accessToken;
        $result = $this->getRequestByCurl($requestURL);
        return json_decode($result, true);
    }

    /**
     * 添加分组
     * @param string $orgName
     */
    public function addFollowerOrg($orgName, $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/groups/create?access_token=' . $accessToken;
        $postData = array(
            'group' => array(
                'name' => $orgName,
            )
        );
        $postStr = json_encode($postData);
        $result = $this->getRequestByCurl($requestURL, $postStr);
        return json_decode($result, true);
    }

    /**
     * 获取用户所在的分组
     * @param string $openId
     * @param string $accessToken
     */
    public function getOrgByOpenid($openId, $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/groups/getid?access_token=' . $accessToken;
        $postData = array(
            'openid' => $openId,
        );
        $postStr = json_encode($postData);
        $result = $this->getRequestByCurl($requestURL, $postStr);
        return json_decode($result, true);
    }

    /**
     * 修改分组名称, 0,1,2为系统默认分组，不可修改
     * @param int $orgId
     * @param string $orgName
     * @param string $accessToken
     * @return type
     */
    public function modifyOrg($orgId, $orgName, $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/groups/update?access_token=' . $accessToken;
        $postData = array(
            'group' => array(
                'id' => $orgId,
                'name' => $orgName,
            )
        );
        $postStr = json_encode($postData);
        $result = $this->getRequestByCurl($requestURL, $postStr);
        return json_decode($result, true);
    }

    /**
     * 移动关注着到新的分组
     * @param string $openId                关注者id
     * @param int $orgId                    分组id
     * @param string $accessToken
     */
    public function moveUserToOrg($openId, $orgId, $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/groups/members/update?access_token=' . $accessToken;
        $postData = array(
            'openid' => $openId,
            'to_groupid' => $orgId,
        );
        $postStr = json_encode($postData);
        $result = $this->getRequestByCurl($requestURL, $postStr);
        return json_decode($result, true);
    }

    /**
     * 移动关注着到新的分组
     * @param string $openId                关注者id
     * @param int $orgId                    分组id
     * @param string $accessToken
     */
    public function bacthMoveUserToOrg($openId, $orgId, $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/groups/members/batchupdate?access_token=' . $accessToken;
        $postData = array(
            'openid_list' => $openId,
            'to_groupid' => $orgId,
        );
        $postStr = json_encode($postData);
        $result = $this->getRequestByCurl($requestURL, $postStr);
        return json_decode($result, true);
    }

    /**
     * 自定义菜单操作
     * @param string $accessToken
     * @param string $type          操作类型  create-创建菜单 get-查询菜单 delete-删除菜单
     * @param string $menu          菜单内容
     */
    public function handleMenu($type, $accessToken = '', $menu = '') {
        $postData = '';
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        switch ($type) {
            case 'create':
                //创建菜单
                $requestURL = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $accessToken;
                $postData = $menu;
                break;
            case 'get':
                //查询菜单
                $requestURL = 'https://api.weixin.qq.com/cgi-bin/menu/get?access_token=' . $accessToken;
                break;
            case 'delete':
                //删除菜单
                $requestURL = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $accessToken;
                break;
            default :
                $requestURL = '';
                break;
        }
        $result = $this->getRequestByCurl($requestURL, $postData);
        return $result;
    }

    /**
     * 获取所有客服列表
     * @param string $accessToken
     */
    public function getCustomList($accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/customservice/getkflist?access_token=' . $accessToken;
        $result = $this->getRequestByCurl($requestURL);
        $kfList = json_decode($result, true);
    }

    /**
     * 添加客服账号
     * @param string $account               客服账号
     * @param string $name                  客服昵称
     * @param string $pwd                   客服账号密码
     * @param string $accessToken           
     */
    public function addCustom($account, $name, $pwd, $accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $customData = array(
            "kf_account" => $account,
            "nickname" => $name,
            "password" => $pwd,
        );
        $postStr = json_encode($customData);
        $postStr = '{
    "kf_account" : test1@test,
    "nickname" : “客服1”,
    "password" : "pswmd5",
}';
        $requestURL = 'https://api.weixin.qq.com/customservice/kfaccount/add?access_token=' . $accessToken;
        $result = $this->getRequestByCurl($requestURL, $postStr);
        var_dump($result);
    }

    /**
     * 获取微信服务器IP地址
     * @param string $accessToken
     */
    public function getServiceIP($accessToken = '') {
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
        }
        $requestURL = 'https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token=' . $accessToken;
        $result = $this->getRequestByCurl($requestURL);
        return $result;
    }

    /**
     * 根据经纬度获取调用Geocoding API v2获取
     * @param type $lat                 纬度
     * @param type $lng                 经度
     * @param type $coordtype           
     */
    public function getPositionByGeocoding($lat, $lng, $coordtype = 'gcj02ll') {
        $requestURL = 'http://api.map.baidu.com/geocoder/v2/?ak=' . $this->_ak . '&coordtype=' . $coordtype . '&location=' . $lat . ',' . $lng . '&output=json&pois=0';
        $result = $this->getRequestByCurl($requestURL);
        $position = json_decode($result, true);
        if ($position['status'] != 0) {
            return '';
        }
        return '您当前所处坐标为：纬度：' . $lat . '，经度：' . $lng . '实际位置为：' . $position['result']['formatted_address'];
    }

    /**
     * 回应文本消息
     */
    private function handleText($keyWord = '') {
        if (empty($keyWord)) {
            $keyWord = trim($this->_postMsgObj->Content);
        }
        if ($keyWord == '图文' || $keyWord == '单图文') {
            $content = array();
            $content[] = array("Title" => "单图文标题",
                "Description" => "单图文内容",
                "PicUrl" => "http://discuz.comli.com/weixin/weather/icon/cartoon.jpg",
                "Url" => "http://m.cnblogs.com/?u=txw1958");
            $result = $this->sentImageText($content);
        } elseif ($keyWord == '多图文') {
            //回复多图文消息
            $content = array();
            $content[] = array("Title" => "多图文1标题", "Description" => "", "PicUrl" => "http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" => "http://m.cnblogs.com/?u=txw1958");
            $content[] = array("Title" => "多图文2标题", "Description" => "", "PicUrl" => "http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" => "http://m.cnblogs.com/?u=txw1958");
            $content[] = array("Title" => "多图文3标题", "Description" => "", "PicUrl" => "http://g.hiphotos.bdimg.com/wisegame/pic/item/18cb0a46f21fbe090d338acc6a600c338644adfd.jpg", "Url" => "http://m.cnblogs.com/?u=txw1958");
            $result = $this->sentImageText($content);
        } elseif ($keyWord == '音乐') {
            //回复音乐消息
            $content = array("Title" => "好久不见",
                "Description" => "歌手：eason，我最喜欢的歌",
                "MusicUrl" => "http://yinyueshiting.baidu.com/data2/music/134369899/29276650400128.mp3?xcode=8505c383ed642cbe8c9f4973686ee072e6bd1fd285a802a4",
                "HQMusicUrl" => "http://yinyueshiting.baidu.com/data2/music/134369899/29276650400128.mp3?xcode=8505c383ed642cbe8c9f4973686ee072e6bd1fd285a802a4");
            $result = $this->sentMusic($content);
        } elseif ($keyWord == '认证') {
            $content = 'oAuth认证测试地址<a href="https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->_appid . '&redirect_uri=' . $this->_redirectURL . '&response_type=code&scope=' . $this->_scope . '&state=' . rand() . '#wechat_redirect">点击授权</a>';
            $result = $this->sentText($content);
        } else {
            $content = '你发送的是文本消息，消息内容为：' . $keyWord;
            $result = $this->sentText($content);
        }
        return $result;
    }

    /**
     * 回应图片消息
     */
    private function handleImage() {
        $content = array(
            'MediaId' => $this->_postMsgObj->MediaId,
        );
        return $this->sentImage($content);
    }

    /**
     * 回应音频消息
     */
    private function handleVoice() {
        if (isset($this->_postMsgObj->Recognition)) {
            //获取到语音识别结果
            return $this->handleText(trim($this->_postMsgObj->Recognition));
        }
        //未获取到语音识别结果，将语音发回去
        $content = array(
            'MediaId' => $this->_postMsgObj->MediaId,
        );
        return $this->sentVoide($content);
    }

    /**
     * 回应视频消息
     */
    private function handleVideo() {
        $content = array(
            'MediaId' => $this->_postMsgObj->MediaId,
            'ThumbMediaId' => $this->_postMsgObj->ThumbMediaId,
            'Title' => '标题',
            'Description' => '这是用户上传的',
        );
        return $this->sentVideo($content);
    }

    /**
     * 回应地理位置消息
     */
    private function handleLocation() {
        $content = '你发送的是位置消息，纬度为：' . $this->_postMsgObj->Location_X . ',经度为：' . $this->_postMsgObj->Location_Y . '，缩放级别为：' . $this->_postMsgObj->Scale . ',位置为：' . $this->_postMsgObj->Label;
        return $this->sentText($content);
    }

    /**
     * 回应地理位置消息
     */
    private function handlePushLocation() {
        $content = $this->getPositionByGeocoding($this->_postMsgObj->Latitude, $this->_postMsgObj->Longitude, 'wgs84ll');
        return $this->sentText($content);
    }

    /**
     * 回应链接消息
     */
    private function handleLink() {
        $content = '你发送的是链接消息，标题为：' . $this->_postMsgObj->Title . ',描述为：' . $this->_postMsgObj->Description . ',链接地址为：' . $this->_postMsgObj->Url;
        return $this->sentText($content);
    }

    /**
     * 处理接受时间推送
     */
    private function handleEvent() {
        $event = strtolower($this->_postMsgObj->Event);
        $message = "时间类型：" . $event . "\r\n";
        $this->trace($message);
        switch ($event) {
            case 'subscribe':
                //用户订阅
                $content = '欢迎订阅albafica的公众账号';
                break;
            case 'unsubscribe':
                //用户取消订阅
                $content = '';
                break;
            case 'click':
                return $this->handleClick();
            case 'location':
                return $this->handlePushLocation();
            default :
                $content = '';
                break;
        }
        return $this->sentText($content);
    }

    /**
     * 处理用户点击事件
     */
    private function handleClick() {
        switch ($this->_postMsgObj->EventKey) {
            case "天气状况":
                $contentStr[] = array("Title" => "公司简介",
                    "Description" => "方倍工作室提供移动互联网相关的产品及服务",
                    "PicUrl" => "http://discuz.comli.com/weixin/weather/icon/cartoon.jpg",
                    "Url" => "http://m.cnblogs.com/?u=txw1958");
                break;
            default:
                $contentStr[] = array("Title" => "默认菜单回复",
                    "Description" => "您正在使用的是方倍工作室的自定义菜单测试接口",
                    "PicUrl" => "http://discuz.comli.com/weixin/weather/icon/cartoon.jpg",
                    "Url" => "http://m.cnblogs.com/?u=txw1958");
                break;
        }
        return $this->sentImageText($contentStr);
    }

    /**
     * 发送文本消息
     * @param string $content   文本内容
     * @return string
     */
    private function sentText($content) {
        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[%s]]></Content>
</xml>";
        return sprintf($textTpl, $this->_postMsgObj->FromUserName, $this->_postMsgObj->ToUserName, time(), $content);
    }

    /**
     * 发送图片消息
     * @param array $image      图片相关参数
     * @return string
     */
    private function sentImage($image) {
        $itemTpl = "<Image>
    <MediaId><![CDATA[%s]]></MediaId>
</Image>";
        $item_str = sprintf($itemTpl, $image['MediaId']);
        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[image]]></MsgType>
$item_str
</xml>";
        return sprintf($textTpl, $this->_postMsgObj->FromUserName, $this->_postMsgObj->ToUserName, time());
    }

    /**
     * 发送音频消息
     * @param array $voice 音频消息参数
     * @return string
     */
    private function sentVoide($voice) {
        $itemTpl = "<Voice>
    <MediaId><![CDATA[%s]]></MediaId>
</Voice>";
        $item_str = sprintf($itemTpl, $voice['MediaId']);
        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[voice]]></MsgType>
$item_str
</xml>";
        return sprintf($textTpl, $this->_postMsgObj->FromUserName, $this->_postMsgObj->ToUserName, time());
    }

    /**
     * 发送视频消息
     * @param array $video      视频消息参数
     * @return string
     */
    private function sentVideo($video) {
        $itemTpl = "<Video>
    <MediaId><![CDATA[%s]]></MediaId>
    <ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
    <Title><![CDATA[%s]]></Title>
    <Description><![CDATA[%s]]></Description>
</Video>";
        $item_str = sprintf($itemTpl, $video['MediaId'], $video['ThumbMediaId'], $video['Title'], $video['Description']);
        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[video]]></MsgType>
$item_str
</xml>";
        return sprintf($textTpl, $this->_postMsgObj->FromUserName, $this->_postMsgObj->ToUserName, time());
    }

    /**
     * 发送音乐消息
     * @param array $music  音乐参数
     * @return string
     */
    private function sentMusic($music) {
        $itemTpl = "<Music>
    <Title><![CDATA[%s]]></Title>
    <Description><![CDATA[%s]]></Description>
    <MusicUrl><![CDATA[%s]]></MusicUrl>
    <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
</Music>";
        $item_str = sprintf($itemTpl, $music['Title'], $music['Description'], $music['MusicUrl'], $music['HQMusicUrl']);
        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[music]]></MsgType>
$item_str
</xml>";
        return sprintf($textTpl, $this->_postMsgObj->FromUserName, $this->_postMsgObj->ToUserName, time());
    }

    /**
     * 发送图文消息
     * @param array $imageText          图文消息参数
     * @return string
     */
    private function sentImageText($imageText) {
        if (!is_array($imageText) || empty($imageText)) {
            return '';
        }
        $itemTpl = "<item>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
    </item>";
        $item_str = "";
        foreach ($imageText as $item) {
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $newsTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
<Content><![CDATA[]]></Content>
<ArticleCount>%s</ArticleCount>
<Articles>
$item_str
    </Articles>
</xml>";
        $result = sprintf($newsTpl, $this->_postMsgObj->FromUserName, $this->_postMsgObj->ToUserName, time(), count($imageText));
        return $result;
    }

    /**
     * cURL执行操作
     * @param string $url           cURL执行的地址
     * @param mixed $string         post提交的数据，为空则不提交post数据
     * @return type
     */
    public function getRequestByCurl($url, $string = '') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);                    //请求URL
        if (!empty($string)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $string);      //post参数
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //将curl_exec()获取的信息以文件流的形式返回，而不是直接输出
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);                  //设置超时时间（单位：秒）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        //不验证证书下同
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);        //
        $result = curl_exec($ch);                                 //执行会话

        if (curl_errno($ch)) {
            //获取错误信息
            $info = curl_getinfo($ch);
            var_dump($info);
            echo curl_errno($ch), '<br/>';
            echo curl_errno($ch);
        }
        curl_close($ch);
        return $result;
    }

}
