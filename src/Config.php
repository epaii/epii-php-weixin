<?php

namespace epii\weixin;

use epii\cache\Cache;


class Config{

    public static $APPID = '';
    public static $SECRET = '';
    public static $AcGetter = null;
    public static $AcTokenCache = null;
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

    public static function setAccessTokenGetter(callable $getter){
        self::$AcGetter = $getter;
    }

    public static function getAccessToken()
    {
        if(self::$AcTokenCache){
            return self::$AcTokenCache;
        }
        if(self::$AcGetter && is_callable(self::$AcGetter)){
            $geter = self::$AcGetter;
            self::$AcTokenCache =$geter();
            return self::$AcTokenCache;
        }

        $time = time();
        $token = Cache::getCacheForever("ac_token");
        $need_update = false;
        if (!$token) {
            $token = self::doGet();
            $need_update = true;

        } else {
            $token = json_decode($token, true);
            if ((!$token['expires_in']) || ($time - $token['expires_in'] >= 0)) {
                $token = self::doGet();
                $need_update = true;
            }
          
        }

        if($need_update){
            if(isset($token['expires_in'])){
                $token['expires_in'] = time()+$token['expires_in'];
            }
            Cache::setCache("ac_token", json_encode($token, JSON_UNESCAPED_UNICODE));
        }
       
        return $token['access_token'];
    }

    private static function doGet()
    {
        $token = http::get_https_url("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . self::$APPID . "&secret=" . self::$SECRET);
        return json_decode($token, true);
    }
    
}