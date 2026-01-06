<?php
/* NFPCF */

// PLUGIN CLASS MANAGING USER SESSION CHECKS THROUGH SESSION TOKENS
if(!defined('ABSPATH')) {exit;}


class pc_session_token {

    private $cookie_name = 'pc_session_token'; // (string)
    private $meta_name = 'pc_session_tokens'; // (string)
    
    private $user_id; // (int) 
    private $stored_tokens; // (array) 
    
    
    public function __construct($user_id) {
        global $pc_meta;
        $stored = $pc_meta->get_meta($user_id, $this->meta_name);
        
        $this->user_id = $user_id;
        $this->stored_tokens = (is_array($stored)) ? $stored : array();
    }
    
    
    
    
    
    /*
     * Check whether actual user has got a valid session token to maintain the login
     * @return (bool)
     */
    public function is_allowed_session() {
        if(!isset($GLOBALS['pc_user_id'])) {
            die('pd');    
        }
        
        if(empty($this->stored_tokens) || !isset($_COOKIE[ $this->cookie_name ])) {
            $to_return = false;   
        }
        
        else {
            // be sure to consider the right tokens number
            $allowed = array();
            $allowed_num = (int)get_option('pg_allowed_simult_sess', 1);

            for($a = 0; $a < $allowed_num; $a++) {
                if(isset($this->stored_tokens[$a])) {
                    $allowed[] = $this->stored_tokens[$a];    
                }
            }

            $curr_sess_id = sanitize_text_field(wp_unslash($_COOKIE[ $this->cookie_name ]));
            $to_return = (in_array($curr_sess_id, $allowed)) ? true : false;
        }
        
        
        // allow extra control over session check - must return boolean
        return (bool)apply_filters('pc_is_allowed_session', $to_return);
    }
    
    
    
    
    
    /*
     * Setup user session token in cookies and in the DB
     * @param (int) $cookie_expir_time - timestamp of the cookie expiration date (must match with login cookie)
     */
    public function setup_session_token($cookie_expir_time) {
        global $pc_meta;
        
        $new_token = md5( $this->user_id . gmdate('U') . wp_rand(0, 999) );
        $allowed_num = (int)get_option('pg_allowed_simult_sess', 1);
        
        $this->stored_tokens = array_merge(array($new_token), $this->stored_tokens);
        $this->stored_tokens = array_slice($this->stored_tokens, 0, $allowed_num);
        
        pc_static::setcookie($this->cookie_name, $new_token, $cookie_expir_time, SITECOOKIEPATH, COOKIE_DOMAIN);
        $pc_meta->update_meta($this->user_id, $this->meta_name, $this->stored_tokens);
        
        return $new_token;
    }
    
    
    
}
