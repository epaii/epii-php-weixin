<?php

namespace epii\weixin;

use epii\cache\Cache;

class H5Tools
{

    const SCOPE_snsapi_base = "snsapi_base";
    const SCOPE_snsapi_userinfo = "snsapi_userinfo";

    public static function init($APPID, $SECRET, $CACHE_DIR)
    {
        Config::init($APPID, $SECRET, $CACHE_DIR);
    }

    public static function getJsConfig($url)
    {
        $ticket = self::getTicket();
        if (!$ticket) {
            return false;
        }

        $timestamp = time();
        $nonceStr = self::createNonceStr();
        $str = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $sha_str = sha1($str);
        return ["appId" => self::$APPID, "timestamp" => $timestamp, "nonceStr" => $nonceStr, "signature" => $sha_str];
    }

    public static function getTicket()
    {
        $time = time();
        $token = Cache::getCacheForever("ac_ticket");
        $need_update = false;
        if (!$token) {
            $token = self::doGetticket();
            $need_update = true;
        } else {
            $token = json_decode($token, true);
            if ((!$token['expires_in']) || ($time - $token['expires_in'] >= 0)) {
                $token = self::doGetticket();
                $need_update = true;
            }
        }

        if ($need_update) {
            if (isset($token['expires_in'])) {
                $token['expires_in'] = time() + $token['expires_in'];
            }
            Cache::setCache("ac_ticket", json_encode($token, JSON_UNESCAPED_UNICODE));
        }

        return $token['ticket'];
    }

    private static function doGetticket()
    {
        $token = Config::getAccessToken();
        $ticket_url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=jsapi";
        $ticket_res = http::get_https_url($ticket_url);
        return json_decode($ticket_res, true);
    }

    public static function getAuthorizeUrl($callback_url, $SCOPE = self::SCOPE_snsapi_base)
    {
        $params = [];
        $params['appid'] = self::$APPID;
        $params['redirect_uri'] = $callback_url;

        $params['response_type'] = 'code';
        $params['scope'] = $SCOPE;
        $params['state'] = 'STATE';
        $urlParams = http_build_query($params);
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?' . $urlParams . '#wechat_redirect';
        return $url;
    }
    public static function getAuthorize($callback_url, $SCOPE = self::SCOPE_snsapi_base)
    {

        if (isset($_GET["__get_code"]) && ($_GET["__get_code"] - 1 == 0)) {
            if (isset($_GET["code"])) {
                return $_GET["code"];
            }
        } else {
            if (stripos($callback_url, "?") > 0) {
                $callback_url = $callback_url . "&__get_code=1";
            } else {
                $callback_url = $callback_url . "?__get_code=1";
            }
            header("location:" . self::getAuthorizeUrl($callback_url, $SCOPE));
            exit;
        }
    }

    //一次性获取token 和 openid
    public static function getAccessTokenAndOpenId($callback_url, $SCOPE = self::SCOPE_snsapi_base)
    {
        $code = self::getAuthorize($callback_url, $SCOPE);
        return self::getTokenOpenId($code);
    }
    public static function getOpenId($callback_url, $SCOPE = self::SCOPE_snsapi_base)
    {
        $info = self::getAccessTokenAndOpenId($callback_url, $SCOPE);
        if (!$info) {
            return false;
        }

        return $info["openid"];
    }
    public static function getUserInfo($callback_url, $SCOPE = self::SCOPE_snsapi_base)
    {
        $info = self::getAccessTokenAndOpenId($callback_url, $SCOPE);
        if (!$info) {
            return false;
        }

        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $info["access_token"] . "&openid=" . $info["openid"] . "&lang=zh_CN";
        $result = http::get_https_url($url);
        $result = json_decode($result, true);
        if (isset($result["errcode"])) {
            return false;
        }
        return $result;
    }
    public static function getTokenOpenId($code, $key = null) //access_token "openid"

    {
        $params = [];
        $params['appid'] = self::$APPID;
        $params['secret'] = self::$SECRET;
        $params['code'] = $code;
        $params['grant_type'] = 'authorization_code';
        $urlParams = http_build_query($params);
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . $urlParams;
        $result = http::get_https_url($url);
        $result = json_decode($result, true);
        if (!isset($result['access_token'])) {
            return false;
        }
        if (!$key) {
            return $result;
        }

        return $result[$key];
    }

    public static function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public static function getMedia($media_id)
    {
        $access_token = Config::getAccessToken();
        $url = "https://file.api.weixin.qq.com/cgi-bin/media/get?access_token=" . $access_token . "&media_id=" . $media_id;
        return file_get_contents($url);
    }

    /**
     *  发送客服消息
     * @apiParam {string} openIds 多个以逗号分割
     * @apiParam {array} data 发送的数据内容
     * data  其中data当中的touser 不用填写 参考链接 https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Service_Center_messages.html
     */

    public static function pushKfNotice($openIds, $data)
    {
        $AccessToken = Config::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $AccessToken;
        $openIdsArr = explode(',', $openIds);
        foreach ($openIdsArr as $item) {
            $data['touser'] = $item;
            http::curl_post($url, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 发送模板消息
     * @apiParam {string} openIds 多个以逗号分割
     * @apiParam {array} data 发送的数据内容
     *
     */
    public static function pushMbNotice($openIds, $data)
    {
        $AccessToken = Config::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $AccessToken;
        $openIdsArr = explode(',', $openIds);
        foreach ($openIdsArr as $item) {
            $data['touser'] = $item;
             http::curl_post($url, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
}
