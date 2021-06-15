<?php

namespace epii\weixin;

class Result{
    private $ret = [];
    private $__ret = "";
    public function __construct($ret)
    {
        $this->ret = json_decode($ret,true);
        $this->__ret = $ret;
    }
    public function isSuccess(){
        return $this->ret["errcode"]-0===0;
    }
    public function getErrMsg(){
        return $this->ret["errmsg"];
    }
    public function getErrCode(){
        return $this->ret["errcode"];
    }
    public function get($key){
        return $this->ret[$key];
    }
    public function __toString()
    {
        return  $this->__ret;
    }
}