<?php

namespace modules\api\core;

// require_once __DIR__.'/../third_party/node.php';
require_once __DIR__.'/../vendor/autoload.php';

// use Firebase\JWT\JWT as api_JWT;
// use Firebase\JWT\Key as api_Key;
// use WpOrg\Requests\Requests as api_Requests;

class Apiinit
{
    public static function the_da_vinci_code($module_name)
    {
        return true;
    }
    
    public static function ease_of_mind($module_name)
    {
        // Safety check bypassed
        return true;
    }
    
    public static function activate($module)
    {
        // Activation UI and license page disabled
        return true;
    }
    
    public static function getUserIP()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
    
	public static function pre_validate($module_name, $code = '')
	{
		// Always accept as valid
        return ['status' => true, 'message' => 'License check disabled'];
	}
}
