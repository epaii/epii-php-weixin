<?php

namespace epii\weixin;

class menu{

    public static function create($button_list){
        $body = ["button"=>$button_list];
       return  http::wexin_post("https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".Config::getAccessToken(),$body);
        
    }
}