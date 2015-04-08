<?php

/**
 * Description of Weather
 * 中国气象局公共气象服务接口
 * @author albafica.wang
 */
class Weather {

    private $_privateKey;
    private $_appid;
    public $_setProxy = false;
    public $_proxy = '10.100.10.100:3128';
    protected $_apitype = 'common';             //数据类型 common常规 base基础

    public function __construct($privateKey, $appid, $setProxy = false) {
        $this->_privateKey = $privateKey;
        $this->_appid = $appid;
        $this->_setProxy = $setProxy;
    }

    /**
     * 生成接口用加密令牌
     * @param string $areaid        地区编码
     */
    private function generateUrl($areaid, $callType) {
        if ($callType == 'weather') {
            $type = $this->_apitype == 'base' ? 'forecast_f' : 'forecast_v';
        } else {
            $type = $this->_apitype == 'base' ? 'index_f' : 'index_v';
        }
        $date = date("YmdHi");
        $publicKey = "http://open.weather.com.cn/data/?areaid=" . $areaid . "&type=" . $type . "&date=" . $date . "&appid=" . $this->_appid;
        $key = base64_encode(hash_hmac('sha1', $publicKey, $this->_privateKey, TRUE));
        return "http://open.weather.com.cn/data/?areaid=" . $areaid . "&type=" . $type . "&date=" . $date . "&appid=" . substr($this->_appid, 0, 6) . "&key=" . urlencode($key);
    }

    /**
     * 获取3天常规预报
     * @param string $areaid        城市编码
     */
    public function getWeatherReport($areaid) {
        $URL = $this->generateUrl($areaid, 'weather');
        $string = $this->getRequestByCurl($URL);
        print_r(json_decode($string, true));
    }

    /**
     * 获取当天指数数据
     * @param string $areaid    城市编码
     */
    public function getWeatherPoint($areaid) {
        $URL = $this->generateUrl($areaid, 'point');
        $string = $this->getRequestByCurl($URL);
        print_r(json_decode($string, true));
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
        if ($this->_setProxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->_proxy);  //公司环境，设置代理
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //将curl_exec()获取的信息以文件流的形式返回，而不是直接输出
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);                  //设置超时时间（单位：秒）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        //不验证证书下同
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);        //
        $result = curl_exec($ch);                                 //执行会话

        if (curl_errno($ch)) {
            //获取错误信息
//            $info = curl_getinfo($ch);
        }
        curl_close($ch);
        return $result;
    }

}

set_time_limit(0);
$weather = new Weather('fa31b4_SmartWeatherAPI_504c86f', '35b756e2e8acc9ab', true);
$weather->getWeatherReport('101021300');
$weather->getWeatherPoint('101021300');

