<?php
/**
 * Created by PhpStorm.
 * User: Lanxi
 * Date: 2020/2/26
 * Time: 9:37
 */

namespace libs\wx;


class MinAppTools
{
    private static $APPID = '';
    private static $SECRET = '';

    public static function init($APPID, $SECRET)
    {
        self::$APPID = $APPID;
        self::$SECRET = $SECRET;
    }

    public static function getSession($code)
    {
        $appid = self::$APPID;
        $secret = self::$SECRET;
        $URL = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code";

        $apiData = self::get_https_url($URL);


        return json_decode($apiData, true);
    }

    public static function getPhoneNumber($sessionKey, $encryptedData, $iv)
    {
        if (strlen($sessionKey) != 24) {
            return false;
        }
        $aesKey = base64_decode($sessionKey);


        if (strlen($iv) != 24) {
            return false ;
        }
        $aesIV = base64_decode($iv);

        $aesCipher = base64_decode($encryptedData);

        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);

        $dataObj = json_decode($result,true);

        if ($dataObj == NULL) {
            return false;
        }
        if ($dataObj["watermark"]["appid"]!= self::$APPID) {
            return false;
        }

        $dataObj["check_code"] = md5($dataObj["purePhoneNumber"].self::$SECRET);

        return $dataObj;
    }

    public static function checkPhone($phone,$check_code)
    {
        return $check_code===md5($phone.self::$SECRET);
    }


    public static function get_https_url($url)
    {
        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );
        return file_get_contents($url, false, stream_context_create($arrContextOptions));
    }
}