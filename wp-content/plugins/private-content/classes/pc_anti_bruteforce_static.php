<?php
// PLUGIN STATIC METHODS ABOUT ANTI BRUTEFORCE ATTACKS ON FORMS 
if(!defined('ABSPATH')) {exit;}


class pc_abfa_static {

    
    // get visitor IP address
    public static function client_ip_address() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        
        return sanitize_text_field($ipaddress);
    }
    
    
    
    
    // know whether current visitor is blacklisted
    public static function visitor_is_blacklisted() {
        if(is_user_logged_in() || isset($GLOBALS['pc_user_id'])) {
            return false;    
        }
        
        $ip = self::client_ip_address();
        $max_attempts = (int)get_option('pg_abfa_attempts', 8);
        
        if(!$max_attempts) {
            return false;    
        }
        
        $db = get_transient('pc_abfa_db');
        if(!is_array($db)) {
            return false;    
        }
        
        if(
            !isset($db[$ip]) || 
            (int)$db[$ip]['attempts'] <= $max_attempts ||
            (int)$db[$ip]['expir'] < current_time('timestamp') 
        ) {
            return false;    
        }
        
        return true;
    }
    
    
    
    
    // add visitor to blacklist - returns 1 if user reached the attempts limit
    public static function add_to_blacklist() {
        if(is_user_logged_in() || isset($GLOBALS['pc_user_id']) || isset($GLOBALS['pvtcont_skip_antibruteforce'])) {
            return 0;    
        }
        
        $ip = self::client_ip_address();
        $max_attempts = (int)get_option('pg_abfa_attempts', 8);
        
        if(!$max_attempts) {
            return 0;    
        }
        
        $db = get_transient('pc_abfa_db');
        if(!is_array($db)) {
            $db = array();   
        }

        $attempts = (isset($db[$ip])) ? (int)$db[$ip]['attempts'] + 1 : 1;
        $db[$ip] = array(
            'attempts'  => $attempts,
            'expir'     => current_time('timestamp') + 60 * 30 
        );

        set_transient('pc_abfa_db', $db, 60 * 60 * 24); // clean every 24h
        return ($attempts > $max_attempts) ? 1 : 0;
    }
    
    
    
    
    // return error message for blocked visitors
    public static function error_message() {
        return esc_attr__('Too many attempts, please try again in 30 minutes', 'pc_ml');    
    }
    
    
}
