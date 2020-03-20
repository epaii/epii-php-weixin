<?php

namespace epii\weixin;

use epii\cache\Cache;


/**
 * Created by PhpStorm.
 * User: Lanxi
 * Date: 2020/2/2
 * Time: 21:19
 */
class H5Tools
{
    private static $APPID = '';
    private static $SECRET = '';

    public static function init($APPID, $SECRET, $CACHE_DIR)
    {
        self::$APPID = $APPID;
        self::$SECRET = $SECRET;
        Cache::initDir($CACHE_DIR);

    }

    public static function isWeixinVisit()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        } else {
            return false;
        }
    }

    public static function getAccessToken()
    {
        $time = time();
        $token = Cache::getCacheForever("ac_token");
        if (!$token) {
            $token = self::doGet();


        } else {
            $token = json_decode($token, true);
            if ((!$token['expires_in']) || ($time - $token['expires_in'] >= 0)) {
                $token = self::doGet();
            }
        }

        Cache::setCache("ac_token", json_encode($token, JSON_UNESCAPED_UNICODE));
        return $token['access_token'];
    }

    public static function getJsConfig($url)
    {
        $ticket = self::getTicket();
        if (!$ticket) return false;
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
        if (!$token) {
            $token = self::doGetticket();


        } else {
            $token = json_decode($token, true);
            if ((!$token['expires_in']) || ($time - $token['expires_in'] >= 0)) {
                $token = self::doGetticket();
            }
        }
        Cache::setCache("ac_ticket", json_encode($token, JSON_UNESCAPED_UNICODE));
        return $token['ticket'];
    }

    private static function doGet()
    {
        $token = self::get_https_url("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . self::$APPID . "&secret=" . self::$SECRET);
        return json_decode($token, true);
    }


    private static function doGetticket()
    {
        $token = self::getAccessToken();
        $ticket_url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=jsapi";
        $ticket_res = self::get_https_url($ticket_url);
        return json_decode($ticket_res, true);

    }

    public static function getAuthorizeUrl($callback_url)
    {
        $params = [];
        $params['appid'] = self::$APPID;
        $params['redirect_uri'] = $callback_url;


        $params['response_type'] = 'code';
        $params['scope'] = 'snsapi_base';
        $params['state'] = 'STATE';
        $urlParams = http_build_query($params);
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?' . $urlParams . '#wechat_redirect';
        return $url;
    }


    public static function getTokenOpenId($code, $key = null)//access_token "openid"
    {
        $params = [];
        $params['appid'] = self::$APPID;
        $params['secret'] = self::$SECRET;
        $params['code'] = $code;
        $params['grant_type'] = 'authorization_code';
        $urlParams = http_build_query($params);
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . $urlParams;
        $result = self::get_https_url($url);
        $result = json_decode($result, true);
        if (!$result['access_token']) {
            return false;
        }
        if (!$key)
            return $result;


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
        $access_token = self::getAccessToken();
        $url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=" . $access_token . "&media_id=" . $media_id;
        return file_get_contents($url);
    }

    public static function get_https_url($url)
    {
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );
        return file_get_contents($url, false, stream_context_create($arrContextOptions));
    }


}