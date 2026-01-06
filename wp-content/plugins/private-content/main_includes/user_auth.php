<?php
/* 
 * SETUP AND KEEP USER SESSION COOKIE
 * HANDLES URL-PARAMETER REQUEST TO LOGOUT USERS
 * READS USER'S COOKIE TO RE-LOG
 */
if(!defined('ABSPATH')) {exit;}



// setting up session | then check for logged user through session or cookie
function pvtcont_init_session_check_cookie() {
	global $wpdb, $pc_users, $pc_wp_user;
    
    // PC-ACTION - hooking before pvtContent checks for a user session and sets up $GLOBALS['pc_user_id']
    do_action('pc_pre_user_session_check');
    
	// WP synced user is logged - be sure it is logged also in pvtContent
	if(is_user_logged_in() && current_user_can('pvtcontent') && !isset($_COOKIE['pc_user'])) {
		$wp_user_id = get_current_user_id();
        $user_data = $pc_wp_user->wp_user_is_linked($wp_user_id);
        
		if(is_object($user_data) && $user_data->status == 1) {
            
            // PC-FILTER - allow PvtContent "user login if synced WP user is logged" function abort. Passes PC user ID and WP user ID
            if(apply_filters('pc_abort_log_user_if_synced_is_logged', false, $user_data->id, $wp_user_id)) {
                return false;    
            }
            $GLOBALS['pc_user_id'] = absint($user_data->id);

            $remember_me = (isset($_COOKIE['pc_remember_login'])) ? true : false;
			$cookie_data = $user_data->id .'|||'. $user_data->psw;
            
			pc_static::setcookie('pc_user', $cookie_data, pc_static::login_cookie_duration($remember_me));
		}	
	}
	
	// perform a session cookie check
	elseif(isset($_COOKIE['pc_user'])) {
		
		// get user ID and crypted password
		$c_data = explode('|||', sanitize_text_field($_COOKIE['pc_user']));
        if(count($c_data) < 2) {
            return false;
        }
		
		$user_data = $wpdb->get_row(
			$wpdb->prepare( 
				"SELECT id, username, psw, status, wp_user_id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE status = 1 AND id = %d AND psw = %s LIMIT 1",
				$c_data[0],
				$c_data[1]
			)
		);
        
		// user found 
		if($wpdb->num_rows && $user_data->status == 1) {
			
			// PC-FILTER - custom login control for custom checks - passes false and user id - return message to abort login otherwise false
			$custom_check = apply_filters('pc_login_custom_check', false, $user_data->id);
			if($custom_check !== false) {
				return false;	
			}
			
			
			// setup login elements
			$GLOBALS['pvtcont_cookie_login'] = true;

			// wp user sync - login also there
			if(
                $pc_users->wp_user_sync && 
                $user_data->wp_user_id && 
                (!is_user_logged_in() || !current_user_can('pvtcontent')) && 
                apply_filters('pc_login_also_on_wp', true)
            ) {
				$pc_wp_user->manual_user_login($user_data->wp_user_id, $user_data->username);
			}
			
			// update last login date
			$wpdb->update(PC_USERS_TABLE, array('last_access' => current_time('mysql')), array('id' => $user_data->id));
            
			// setup global
			$GLOBALS['pc_user_id'] = absint($user_data->id);
            
            
            // refresh it
            $remember_me = (isset($_COOKIE['pc_remember_login'])) ? true : false; 
            $cookie_expir_time = pc_static::login_cookie_duration($remember_me);
            
            pc_static::setcookie('pc_user', sanitize_text_field($_COOKIE['pc_user']), $cookie_expir_time);
            if($remember_me) {
                pc_static::setcookie('pc_remember_login', 1, $cookie_expir_time);
            }
		}
	}	


	############################################################################
	
	
    // user session token check
    if(get_option('pg_use_session_token') && isset($GLOBALS['pc_user_id']) && $GLOBALS['pc_user_id'] && class_exists('pc_session_token')) {
        $pc_sess_tok = new pc_session_token($GLOBALS['pc_user_id']);
        
        if(!$pc_sess_tok->is_allowed_session()) {
            pc_logout();    
        }
    }
    

	############################################################################
	
	
	// try forcing (damn) WP-supercache cleaning on loading
	if(function_exists('wp_cache_clear_cache')) {
		wp_cache_clear_cache();	
	}
	
	
	############################################################################
	
	
	// PC-ACTION - give an hook to safely perform operations after session and cookie check - passes logged user ID or false 
	$uid = (isset($GLOBALS['pc_user_id']) && $GLOBALS['pc_user_id']) ? $GLOBALS['pc_user_id'] : false; 
	do_action('pc_user_session_checked', $uid);

	// add body class
	add_filter('body_class', 'pvtcont_user_status_body_class', 10);	
}
add_action('pvtcont_init', 'pvtcont_init_session_check_cookie', 1);




// add body class informing if user is logged or not and its categories
function pvtcont_user_status_body_class($classes) {
	global $pc_users;
    $classes[] = (isset($GLOBALS['pc_user_id']) && $GLOBALS['pc_user_id']) ? 'pc_logged' : 'pc_unlogged';
    
    if(isset($GLOBALS['pc_user_id']) && $GLOBALS['pc_user_id']) {
        $cats = $pc_users->get_user_field($GLOBALS['pc_user_id'], 'categories');
        foreach($cats as $ucat) {
            $classes[] = 'pc_ucat_'. $ucat;       
        }
    }
    
	return $classes;	
}




////////////////////////////////////////////////////////////////

    


// WP 6.8+ disable speculative loading for logged users
add_action('pvtcont_init', function($user_id) {
    if(!pc_user_logged(false)) {
        return false;   
    }
    
    add_filter('wp_speculation_rules_href_exclude_paths', function($exclude_paths, $mode) {
        $exclude_paths[] = '/*';
        return $exclude_paths;
    }, 9999999, 2);
});




////////////////////////////////////////////////////////////////




// execute logout through URL parameter
add_action('wp', function() {
	if(!isset($_REQUEST['pc_logout']) && !isset($_REQUEST['pg_logout'])) {
        return false;   
    }

    $GLOBALS['pvtcont_handling_ajax_logout_call'] = true;
    pc_logout();
    
    // perform redirect stripping logout parameter and adding anti-cache
    $url = pc_static::curr_url();
    $url = remove_query_arg(array('pc_logout', 'pcac'), $url);
    
    if(!get_option('pg_do_not_use_pcac')) {
        $url = add_query_arg('pcac', uniqid(), $url);
    }
    
	wp_safe_redirect($url);
	exit;	
}, 9999); // be sure also WP user session is set up

