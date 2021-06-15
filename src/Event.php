<?php

namespace epii\weixin;
 
class Event
{

    const EVENT_subscribe = "subscribe";
    const EVENT_unsubscribe = "unsubscribe";
    const EVENT_CLICK = "CLICK";
    const EVENT_VIEW = "VIEW";
    const EVENT_LOCATION = "LOCATION";

    const MSG_TYPE_text = "text";
    const MSG_TYPE_image = "image";
    const MSG_TYPE_voice = "voice";
    const MSG_TYPE_video = "video";
    const MSG_TYPE_shortvideo = "shortvideo";
    const MSG_TYPE_location = "location";
    const MSG_TYPE_link = "link";

    private static $is_handler = false;

    private static $handler = ["event" => []];

    private static $APP_ID = null;
    private static $Token = null;
    private static $EncodingAESKey = null;

    public static function init($APP_ID, $Token, $EncodingAESKey)
    {
        self::$APP_ID = $APP_ID;
        self::$Token = $Token;
        self::$EncodingAESKey = $EncodingAESKey;
    }

    public static function onMsg(callable $handler)
    {
        self::$handler["all"] = $handler;
    }
    public static function onMsgType(string $msg_type, callable $handler)
    {
        self::$handler[$msg_type] = $handler;
    }
    public static function onEventName(string $evnet_name, callable $handler)
    {
        self::$handler["event"][$evnet_name] = $handler;
    }
    public static function onEvent(callable $handler)
    {
        self::$handler["event"]["all"] = $handler;
    }
    public static function handle()
    {
 
        if (self::$is_handler) {
            return;
        }

        self::$is_handler = true;
        //解析收到的所数据
        $wxContent = self::getRequstData();
        if (! $wxContent ) {
            return false;
        }
       
       
        
        $data = new EventData( $wxContent);
        $fun = null;
        if ($data->isEvent()) {
            if (isset(self::$handler["event"][$data->Event()])) {
                $fun = self::$handler["event"][$data->Event()];
            } else if (isset(self::$handler["event"]["all"])) {
                $fun = self::$handler["event"]["all"];
            }
        } else {
            if (isset(self::$handler[$data->MsgType()])) {
                $fun = self::$handler[$data->MsgType()];
            } else if (isset(self::$handler["all"])) {
                $fun = self::$handler["all"];
            }
        }
        
        if ($fun && is_callable($fun)) {
            $ret = $fun($data);
            
            $_timeStamp = time();
            if ($ret) {
                $ret=array_merge([
                    'ToUserName'=>$data->FromUserName(),
                    'FromUserName'=>$data->ToUserName(),
                    'CreateTime'=>$_timeStamp
                ],$ret);
                $replyMsgXml=self::arrayToXml($ret);
                
                if(self::$EncodingAESKey){
                    echo  self::encryptReponse($replyMsgXml, $_timeStamp);
                }else{
                    echo   $replyMsgXml;
                }
                exit;
            }
        }
        echo "success";
        exit;

    }
    public static function response($MsgType, array $data)
    {
        $data["MsgType"] = $MsgType;
        return $data;
    }
    public static function responseText( $Content)
    {
        $data=[];
        $data["MsgType"] = self::MSG_TYPE_text;
        $data["Content"] = $Content;
        return $data;
    }
    private static function getRequstData()
    {
        if (!isset($_GET["nonce"])) {
            return false;
        }

        
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"]."";
        $nonce = $_GET["nonce"]."";
        $echostr = null;
        if (isset($_GET["echostr"])) {
            $echostr = $_GET["echostr"];
        } else {
            $signature = $_GET["msg_signature"];
        }
        $tmpArr = array(self::$Token, $timestamp, $nonce);

        $wxContent = self::xmlToArray(file_get_contents('php://input'));
       

        if (($echostr === null) && isset($wxContent['Encrypt'])) {
            array_push($tmpArr, $wxContent['Encrypt']);
        }
       
        $tmpStr = self::getSHA1($tmpArr);
       
        if ($tmpStr == $signature) {
            if ($echostr) {
                echo $echostr;
                exit;
            }
            if (isset($wxContent['Encrypt'])) {
                $wxContent = self::PKCS7decrypt($wxContent['Encrypt']);
            }
            return  $wxContent;
        } else {
            if ($echostr) {
                echo "error";
                exit;
            }
            return false;
        }
    }
    private static function getSHA1($arr)
    {
        //排序
        sort($arr, SORT_STRING);
        $str = implode($arr);
        return sha1($str);
    }
    private static function xmlToArray($xml)
    {
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }

    private static function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";

            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }

        }
        $xml .= "</xml>";
        return $xml;
    }
    private static  function encryptReponse($replyMsgXml, $timeStamp){
        $encrypt=self::PKCS7encrypt($replyMsgXml);
        $nonce=self::getRandomStr();
        $tempArr= array(self::$Token,$timeStamp, $nonce, $encrypt);
        $signature = self::getSHA1($tempArr);
        $generate=[
            'Encrypt'=>$encrypt,
            'MsgSignature'=>$signature,
            'TimeStamp'=>$timeStamp,
            'Nonce'=>$nonce,
        ];
        return self::arrayToXml($generate);
    }
    //PKCS7解密方法
    private static function PKCS7decrypt($encrypted)
    {

        $key = base64_decode(self::$EncodingAESKey . "=");
        $iv = substr($key, 0, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_ZERO_PADDING, $iv);
        $result = self::decode($decrypted);

        if (strlen($result) < 16) {
            return "";
        }

        $content = substr($result, 16, strlen($result));
        $len_list = unpack("N", substr($content, 0, 4));
        $xml_len = $len_list[1];
        $xml_content = substr($content, 4, $xml_len);
        $from_appid = substr($content, $xml_len + 4);
        if ($from_appid == self::$APP_ID) {

            return self::xmlToArray($xml_content);
        } else {
            return false;
        }
    }

    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */

    public static function PKCS7encrypt($content)
    {

        $key = base64_decode(self::$EncodingAESKey . "=");
        $random = self::getRandomStr();
        $text = $random . pack("N", strlen($content)) . $content . self::$APP_ID;
        $iv = substr($key, 0, 16);

        $text = self::encode($text);

        $encrypted = openssl_encrypt($text, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_ZERO_PADDING, $iv);
        return $encrypted;
    }

    private static function decode($text)
    {

        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }

    private static function encode($text)
    {
        $block_size = 32;
        $text_length = strlen($text);
        //计算需要填充的位数
        $amount_to_pad = $block_size - ($text_length % $block_size);
        if ($amount_to_pad == 0) {
            $amount_to_pad = $block_size;
        }

        //获得补位所用的字符
        $pad_chr = chr($amount_to_pad);
        $tmp = "";
        for ($index = 0; $index < $amount_to_pad; $index++) {
            $tmp .= $pad_chr;
        }

        return $text . $tmp;
    }

    private static function getRandomStr()
    {
        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }

}
/**
 * Undocumented class
 * @method mixed Ticket()
 * @method mixed Content()
 * @method mixed PicUrl()
 * @method mixed MediaId()
 * @method mixed MsgId()
 * @method mixed MediaId()
 * @method mixed Format()
 * @method mixed Recognition()
 * @method mixed ThumbMediaId()
 * @method mixed Location_X()
 * @method mixed Location_Y()
 * @method mixed Scale()
 * @method mixed Label()
 * @method mixed Description()
 * @method mixed Title()
 * @method mixed Url()
 * @method mixed EventKey()
 * @method mixed Latitude()
 * @method mixed Longitude()
 * @method mixed Precision()
 * @method mixed FromUserName()
 * @method mixed CreateTime()
 * @method mixed MsgType()
 * @method mixed ToUserName()
 */
class EventData
{
    private $data = [];
    public function __construct($data)
    {
        $this->data = $data;
    }
    public function Event()
    {
        return $this->MsgType() == "event" ? $this->data["Event"] : null;
    }
    public function isEvent()
    {
        return $this->MsgType() == "event";
    }
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }
    public function __call($name, $arguments)
    {

        return isset($this->data[$name]) ? $this->data[$name] : null;
    }
}

register_shutdown_function(function () {
    Event::handle();
});
