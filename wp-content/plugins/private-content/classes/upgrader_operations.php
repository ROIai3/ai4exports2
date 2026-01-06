<?php
// wrapping up operations to be done on plugin's update/activation
if(!defined('ABSPATH')) {exit;}


class pvtcont_upgrader_operations {
    public static function run() {
        foreach(get_class_methods(__CLASS__) as $method) {
            if($method == 'run') {
                continue;   
            }
            call_user_func(array(__CLASS__, $method));
        }
    }
    ////////////////////////////////////////////////////////////////////////
    
    
    
    public static function db_manag() {
        include_once(PC_DIR .'/main_includes/db_manag.php');
    }
    
    
    
    /* NFPCF */
    public static function v8_update() {

        // update v6.1 - reset antispam - recaptcha must be configured!
        if(!get_option('pg_recaptcha_public')) {
            delete_option('pg_antispam_sys');	
        }


        // v8 update - update fields gap value
        if(!is_array(get_option('pg_reg_fblock_gap'))) {
            update_option('pg_reg_fblock_gap', array(
                get_option('pg_reg_fblock_gap', 20),
                35
            ), false);    
        }

        // v8 update - if pg_style option exists, overwrite the related styles using the new preset
        if(get_option('pg_style') && get_option('pg_style') != 'custom') {
            pvtcont_set_predefined_style(get_option('pg_style'));   
            delete_option('pg_style');
        }
    }
    
}

