<?php
if(!defined('ABSPATH')) {exit;}


/* 
 * CHECK WHETHER USER IS LOGGED 
 * @since 5.0
 *
 * @param (mixed) $get_data = whether to return logged in user data. 
 *	true (default) to return full user data + meta
 *	false to return only a boolean value
 *	fields array to return only them
 *
 * @return (mixed) 
 *	false if user is not logged, 
 *	true if user is logged and no data must be returned,
 *	associative array in case of multiple data to return (key => val)
 *	mixed if want to get only one value (directly returns value)	
 */
function pc_user_logged($get_data = true) {
	if(!isset($GLOBALS['pc_user_id'])) {
		return false;
	}
	else {
		global $pc_users;
		$user_id = absint($GLOBALS['pc_user_id']);
		
		//// check if actual user is active		
		// if just check user without getting data
		if(!$get_data) {
			if(isset($GLOBALS['pvtcont_ver_logged_user']) && $GLOBALS['pvtcont_ver_logged_user'] == $user_id) {
				return true;
			}
			$result = $pc_users->get_users(array('user_id' => $user_id, 'count' => true));
			
			// check only once in this case
			if($result && !isset($GLOBALS['pvtcont_ver_logged_user'])) {
				$GLOBALS['pvtcont_ver_logged_user'] = $user_id;
				return ($result) ? true : false;	
			}
			else {
				return true; // user already verified
			} 
		}
		else {
			$args = array('status' => 1);
			if($get_data !== true) {
                $get_data = (array)$get_data;
            }
			$args['to_get'] = $get_data;
			
			$result = $pc_users->get_user($user_id, $args);
			
			// if getting single field - return only that
			if(is_array($get_data) && count($get_data) === 1) {
				$result = $result[$get_data[0]];	
			}
			
			// if result is ok - set constant to check verified user logged
			if($result && !isset($GLOBALS['pvtcont_ver_logged_user'])) {
                $GLOBALS['pvtcont_ver_logged_user'] = $user_id;
            }
			return $result;	
		}
	}
}




/* CHECK IF CURRENT USER CAN ACCESS TO AN AREA
 * @since 5.0
 *
 * given the allowed param, check if user has right permissions - eventually checks WP users pass
 *
 * @param (array) allowed = allowed users - may contain
 *		all			= any logged in user
 *		unlogged 	= unlogged users
 *		user categories id or any custom key bindable through hook
 *
 * @param (array) blocked = specifically blocked users among allowed ones - may contain
 *		user categories id or any custom key bindable through hook
 *
 * @param (bool) wp_user_pass - whether to count logged WP users checking permission (check 'testing mode' in settings)
 * @param (bool) allow_filter - whether to apply filter if "all" and "unlogged" options are selected
 *
 *
 * @return (mixed)
 *	false = not logged
 *	2 = user doesn't have right permissions
 *	1 = has got permissions
 */
function pc_user_check($allowed = array('all'), $blocked = array(), $wp_user_pass = false, $allow_filter = true) {
	global $pc_users;
	
	// be sure to have only arrays
	if(!is_array($allowed)) {
        $allowed = explode(',', $allowed);
    }
	if(!is_array($blocked)) {
        $blocked = explode(',', $blocked);
    }
	
    // filter?
	if($allow_filter) {
        
        // allow/block contains only add-ons options
        $only_addons_opts = true;
        foreach($allowed as $opt) {
            if(is_integer($opt) || in_array($opt, array('all', 'unlogged'))) {
                $only_addons_opts = false;
                break;
            }
        }
        
        if($only_addons_opts) {
            foreach($blocked as $opt) {
                if(is_integer($opt) || in_array($opt, array('all', 'unlogged'))) {
                    $only_addons_opts = false;
                    break;
                }
            }       
        }
    }
    
    
    // be sure PvtContent has been initialized - if not (website has bad hooks order) - force init
    if(!did_action('pvtcont_init')) {
        pvtcont_db_constants();
        do_action('pvtcont_init');
    }
    
	
	// if WP user can pass
    pvtcont_setup_wp_use_pass();
	if($wp_user_pass && PVTCONT_WP_USER_PASS && !isset($GLOBALS['pc_user_id'])) {
        $wp_user_preview = get_user_meta(get_current_user_id(), 'pc_restr_preview_config', true);
        
        if(empty($wp_user_preview) || !is_array($wp_user_preview)) {
            return 1;   
        }
        elseif(in_array('unlogged', $wp_user_preview)) {
            return (in_array('unlogged', $allowed)) ? 1 : false;
        }
        else {
            if(in_array('unlogged', $allowed)) {
                $wpp_to_return = 2;   
            }
            elseif(in_array('all', $allowed)) {
                $wpp_to_return = (empty($blocked) || empty(array_intersect($wp_user_preview, $blocked))) ? 1 : 2;   
            }
            else {
                $wpp_to_return = (!empty(array_intersect($wp_user_preview, $allowed)) && empty(array_intersect($wp_user_preview, $blocked))) ? 1 : 2; 
            }
        }
        
        if($allow_filter) {
            $GLOBALS['pvtcont_wp_preview_check_already_passed'] = ($wpp_to_return === 1 && !$only_addons_opts) ? true : false;

            // PC-FILTER - give chance to perform further checks for the WP admin preview - passes default check result, emulated categories, allowed users, blocked users
            $wpp_to_return = ($allow_filter) ? apply_filters('pc_extra_wp_preview_check', $wpp_to_return, $wp_user_preview, $allowed, $blocked) : $wpp_to_return;

            unset($GLOBALS['pvtcont_wp_preview_check_already_passed']);
        }
        return $wpp_to_return;
	}
    
		
	///////////////////////////////////	
	
    
	// no allowed specified - pass 
	if(empty($allowed)) {
		return 1;
	}
	
	// if any logged is allowed - be sure it is the only field
	if(in_array('all', $allowed)) {
		$allowed = array('all');
		
		if(empty($blocked)) {
			return (pc_user_logged(false) !== false) ? 1 : false;		
		}
	}
	
	// if allowed only unlogged
	else if(implode('', $allowed) == 'unlogged') {
		return (pc_user_logged(false) === false) ? 1 : false;	
	}
	
    // if some clearance is required but user is unlogged
    elseif(!in_array('unlogged', $allowed) && pc_user_logged(false) === false) {
        return false;   
    }
    

	////////
	// user categories matching 	
	
	// cache user categories to avoid double calls
	if(isset($GLOBALS['pvtcont_curr_user_cats'])) {
		$user_cats = (array)$GLOBALS['pvtcont_curr_user_cats']; 	
	} else {
		$user_cats = (array)pc_user_logged('categories');
        if(!empty($user_cats)) {
            $GLOBALS['pvtcont_curr_user_cats'] = $user_cats;
        }
	}
    
	// no user logged == empty array or index #0 == false
	if(empty($user_cats) || $user_cats[0] == false) {
        return false;
    }

	
	// check blocked
	$blocked = (array)array_diff($blocked, $allowed); // strip allowed from blocked
	if(count($blocked) && count(array_diff($user_cats, $blocked)) != count($user_cats)) { // if a user category is among blocked
		return 2;	
	}
	
	
	// calculate privateContent (not add-ons) allowance - strip custom values assuming they are not pure numbers
	$pc_allowed = array();
	foreach($allowed as $aa) {
		if($aa == 'unlogged' || filter_var($aa, FILTER_VALIDATE_INT)) {
			$pc_allowed[] = $aa;	
		}
	}

	if(!count($pc_allowed)) { // no pc allowed specified - let it pass
		$to_return = 1;
	}
	elseif(in_array('unlogged', $pc_allowed) && pc_user_logged(false) === false) { // if unlogged users are allowed and user is not logged
		$to_return = 1;	
	}
	else {
		$to_return = (count(array_diff($user_cats, $pc_allowed)) != count($user_cats)) ? 1 : 2;
	}
    
	// filter?
	if($allow_filter) {
		$GLOBALS['pvtcont_user_check_already_passed'] = ($to_return === 1 && !$only_addons_opts) ? true : false;
        
		//// PC-FILTER - give chance to perform further checks - passes default check result, user categories, allowed users, blocked users
		// user is logged then ID can be retrieved through global and filter must return a proper value (1 or 2)
		$to_return = apply_filters('pc_extra_user_check', $to_return, $user_cats, $allowed, $blocked);
        
        unset($GLOBALS['pvtcont_user_check_already_passed']);
		return $to_return;
	}
	else {
		return $to_return;	
	}
}




/* GET LOGIN FORM
 * @since 5.0
 *
 * @param (string) redirect = forces a specific redirect after login - must be a valid URL or "refresh"
 * @param (string) align = form alignment - center/left/right
 *
 * @return (string) the login form code or empty if a logged user is found
 */
function pc_login_form($redirect = '', $align = 'center') {
	include_once(PC_DIR.'/classes/pc_form_framework.php');

	$f_fw = new pc_form();
	
	$custom_redirect 	= (!empty($redirect)) ?  'data-pc_redirect="'.$redirect.'"' : '';
	$remember_me 		= get_option('pg_use_remember_me');
	$rm_class 			= ($remember_me) ? 'pc_rm_login' : '';
	$is_widget_class 	= (isset($GLOBALS['pvtcont_login_widget'])) ? 'pc_widget_login' : '';
	
	// fields icon
	$un_icon_class 	= (get_option('pg_username_icon')) ? 'pc_field_w_icon' : '';
	$psw_icon_class = (get_option('pg_psw_icon') || get_option('pg_single_psw_f_w_reveal')) ? 'pc_field_w_icon' : '';
    
	$un_icon = ($un_icon_class) ? '<span class="pc_field_icon" title="'. esc_attr__('Username', 'pc_ml') .'"><i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_username_icon')) ) .'"></i></span>' : '';
    
    $psw_icon = (get_option('pg_single_psw_f_w_reveal')) ? 'far fa-eye' : esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_psw_icon')) );
    $psw_icon_title = (get_option('pg_single_psw_f_w_reveal')) ? esc_attr__('toggle password visibility', 'pc_ml') : esc_attr__('Password', 'pc_ml');
    
	$psw_icon = ($psw_icon_class) ? '<span class="pc_field_icon" title="'. $psw_icon_title .'"><i class="'. $psw_icon .'"></i></span>' : '';
	
	// login also through e-mail
	if(get_option('pg_allow_email_login')) {
		$user_label 		= esc_html__('Username or e-mail', 'pc_ml');
		$long_labels_class 	= 'pc_lf_long_labels pc_forced_lf_long_labels'; // force long labels 		
	}
	else {
		$user_label 		= esc_html__('Username', 'pc_ml');
		$long_labels_class 	= (get_option('pg_fullw_login_fields')) ? 'pc_lf_long_labels pc_forced_lf_long_labels' : '';
	}
	
	// placeholders only if no-label is active
	$un_placeh 	= (get_option('pg_nolabel')) ? 'placeholder="'. esc_attr($user_label) .'"' : '';
	$psw_placeh = (get_option('pg_nolabel')) ? 'placeholder="'. esc_attr__('Password', 'pc_ml') .'"' : '';
	
	// button's icon
	$icon = (get_option('pg_login_btn_icon')) ? '<i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_login_btn_icon')) ) .'"></i>' : '';
	
    // fullwidth buttons?
    $fullw_btns_class = (get_option('pg_fullw_login_btn')) ? 'pc_fullw_login_btns' : '';
    
    // PC-FILTER - allow login form classes management - receives and must return an array
	$form_classes = array($rm_class, $is_widget_class, $long_labels_class, $fullw_btns_class);
    $form_classes = apply_filters('pc_login_form_classes', $form_classes);
        
	$form = '
	<form class="pc_login_form '. implode(' ', (array)$form_classes) .'" '. $custom_redirect .'>
		<div class="pc_login_row pc_lf_username '.$un_icon_class.'">
			<label>'. $user_label .'</label>
			
			<div class="pc_field_container">
				'. $un_icon .'
				<input type="text" name="pc_auth_username" value="" '.$un_placeh.' autocapitalize="off" autocomplete="off" autocorrect="off" aria-label="'. esc_attr($user_label) .'" maxlength="150" />
			</div>	
		</div>
		<div class="pc_login_row pc_lf_psw '.$psw_icon_class.'">
			<label>'. esc_html__('Password', 'pc_ml') .'</label>
			
			<div class="pc_field_container">
				'. $psw_icon .'
				<input type="password" name="pc_auth_psw" value="" '.$psw_placeh.' autocapitalize="off" autocomplete="off" autocorrect="off" aria-label="'. esc_attr__('Password', 'pc_ml') .'" />
			</div>
		</div>';
			
		//// anti-spam system
		$antispam = get_option('pg_antispam_sys', 'honeypot');
		if($antispam == 'honeypot') {
			$form .= $f_fw->honeypot_generator();
		}
		elseif($antispam == 'recaptcha') { // v3
			$form .= '
			<div class="pc_grecaptcha" data-sitekey="'. esc_attr(get_option('pg_recaptcha_public')) .'"></div>';
        }
        elseif($antispam == 'recaptcha_v2') { // v2
			$form .= '
			<div class="pc_grecaptcha" id="'. esc_attr(uniqid()) .'" data-callback="pc_gcaptcha_v2_validated" data-size="invisible"></div>';
        }

		$form .= '
        <div class="pc_lf_subfields">
            <div class="pc_login_smalls">';

              if($remember_me) {
                $form .= '
                <div class="pc_login_remember_me">
                    <input type="checkbox" name="pc_remember_me" value="1" autocomplete="off" aria-label="'. esc_attr__('remember me', 'pc_ml') .'" />
                    <small>'. esc_html__('remember me', 'pc_ml') .'</small>
                </div>';
              }

                //////////////////////////////////////////////////////////////
                // PSW RECOVERY TRIGGER - MAIL ACTIONS ADD-ON
                $form = apply_filters('pcma_psw_recovery_trigger', $form);	
                //////////////////////////////////////////////////////////////

            $form .= '</div>
            <div id="pc_auth_message"></div>
        </div>
            
		<button class="pc_auth_btn" type="submit">
            <span class="pc_inner_btn">'. $icon . esc_html__('Login', 'pc_ml') .'</span>
        </button>';
		
		
		//////////////////////////////////////////////////////////////
		// PSW RECOVERY CODE - MAIL ACTIONS ADD-ON
		$form = apply_filters('pcma_psw_recovery_code', $form);	
		//////////////////////////////////////////////////////////////
	
	$form .= '
	</form>';
    
    /* NFPCF - not yet */
    $form .= '
    <script type="text/javascript">
    (function() { 
        "use strict";

        const intval = setInterval(() => {
            if(typeof(pc_lf_overlapping_smalls_check) == "undefined") {
                return true;
            }
            else {
                clearTimeout(intval);
                window.pc_lf_overlapping_smalls_check();';
    
                if(!$long_labels_class) { 
                    $form .= 'window.pc_lf_labels_h_check();';   
                }
                
            $form .= '
            }
        }, 50);
    })();
    </script>';
	
    
    $logged_return = '';
    if(defined('ELEMENTOR_URL') && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
        $logged_return = '[login form - not shown for logged users]';    
    }
    
	return (pc_user_logged(false)) ? $logged_return : pc_static::form_align($form, $align);
}




/* GET LOGOUT BUTTON
 * @since 5.0
 *
 * @param (string) redirect = forces a specific redirect after login - must be a valid URL
 * @return (string) the logout button code or empty if a logged user is found
 */
function pc_logout_btn($redirect = '') {
	$custom_redirect = (!empty($redirect)) ?  'data-pc_redirect="'.$redirect.'"' : '';
	
	// button's icon
	$icon = (get_option('pg_logout_btn_icon')) ? '<i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_logout_btn_icon')) ) .'"></i>' : '';
	
	$logout = '<span class="pc_logout_btn" '.$custom_redirect.'><span class="pc_inner_btn">'. $icon . esc_html__('Logout', 'pc_ml') .'</span></span>';
	
    
    $unlogged_return = '';
    if(defined('ELEMENTOR_URL') && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
        $unlogged_return = '[logout button - not shown for unlogged users]';    
    }
    
    return (!pc_user_logged(false)) ? $unlogged_return : $logout;
}




/* LOGGING IN USER - passing username and password, check and setup cookie and WP login 
 * @since 5.0
 *
 * @param (string) username - you may pass also user e-mail if related option is enabled in settings
 * @param (string) password
 * @param (bool) remember_me - whether to use extended cookies (6 months)
 * @return (mixed) 
 	false if not found
	true if logged sucessfully 
	otherwise user status (2 or 3) 
	or custom message for custom check
 */
function pc_login($username, $psw, $remember_me = false) {
	global $wpdb, $pc_users, $pc_wp_user;
		
	if(isset($GLOBALS['pvtcont_is_logging_out'])) {
        return 'user is being logged out';
    }

    // PC-ACTION - hooking before pc_login() function acts
    do_action('pc_pre_user_login_checks', $username, $psw);
    
    
	// only username or also e-mail?
    if(get_option('pg_allow_email_login')) {
        $query = $wpdb->prepare(
			"SELECT id, username, psw, status, wp_user_id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE (username = %s OR email = %s) LIMIT 1",
			trim($username),
            trim($username)
		);
    }
    else {
        $query = $wpdb->prepare(
			"SELECT id, username, psw, status, wp_user_id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE username = %s LIMIT 1",
			trim($username)
		);   
    }

	// query
	$user_data = $wpdb->get_row($query);
	if(empty($user_data)) {
        return false;
    }
	
	// match password (using WP functions)
	if(
        (!isset($GLOBALS['pvtcont_encrypted_psw_login']) && !wp_check_password($psw, $user_data->psw)) || 
        (isset($GLOBALS['pvtcont_encrypted_psw_login']) && $psw != $user_data->psw)
    ) {
		return false;	
	}
    else {
	
        // PC-FILTER - allow control over login before the status check due to user status - passes false, user id and status - return message to abort login otherwise false
        $pre_status_check = apply_filters('pc_pre_status_login_check', false, $user_data->id, $user_data->status);
        if($pre_status_check !== false) {
            return $pre_status_check;		
        }
        
        
        // setup user login cookie (if no custom error is returned)
        if($user_data->status == 1) {

            // PC-FILTER - custom login control for custom checks - passes false and user id - return message to abort login otherwise false
            $custom_check = apply_filters('pc_login_custom_check', false, $user_data->id);

            if($custom_check !== false) {
                return $custom_check;		
            }

            // setup cookie
            if(!isset($GLOBALS['pvtcont_cookie_login'])) {
                $cookie_expir_time = pc_static::login_cookie_duration($remember_me);

                // cookie structure = user id - encrypted password
                $cookie_data = array($user_data->id, $user_data->psw);     
                pc_static::setcookie('pc_user', implode('|||', $cookie_data), $cookie_expir_time);

                // cookie used to refresh session for a correct amount of time
                if(!empty($remember_me)) {
                    pc_static::setcookie('pc_remember_login', 1, $cookie_expir_time);        
                } else {
                    pc_static::setcookie('pc_remember_login', 1, ((int)gmdate('u') - (3600 * 25)));         
                }

                // user session token check?
                if(get_option('pg_use_session_token') && class_exists('pc_session_token')) {
                    $pc_sess_tok = new pc_session_token($user_data->id);
                    $pc_sess_tok->setup_session_token($cookie_expir_time);
                }
            }


            // wp user sync - login also there
            if($pc_users->wp_user_sync && $user_data->wp_user_id) {
                $pc_wp_user->manual_user_login($user_data->wp_user_id, $user_data->username);
            }


            // setup global
            $GLOBALS['pc_user_id'] = $user_data->id;

            // update last login date
            $wpdb->update(PC_USERS_TABLE, array('last_access' => current_time('mysql')), array('id' => $user_data->id));

            // try avoiding page cache
            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

            // GA4 analytics event
            if(!isset($GLOBALS['pvtcont_handling_ajax_login_call']) && isset($GLOBALS['pvtcont_google_analytics'])) {
                $GLOBALS['pvtcont_google_analytics']->trigger_event('pc_user_login', array(), $user_data->id);
            }

            // PC-ACTION - user is logged in - passes user id and remember-me flag
            do_action('pc_user_login', $user_data->id, $remember_me);
            return true;
        }
        else {
            return $user_data->status;	
        }
    }
}




/* 
 * LOGGING OUT USER - deletes logged user session cookies 
 * @since 5.0
 */
function pc_logout() {
    global $pc_users;
    
    $GLOBALS['pvtcont_is_logging_out'] = true;
	$wp_user_id = pc_user_logged('wp_user_id');
    
    $del_cookie_time = (int)gmdate('u') - (3600 * 25); 
    pc_static::setcookie('pc_user', '', $del_cookie_time, SITECOOKIEPATH, COOKIE_DOMAIN);
    pc_static::setcookie('pc_remember_login', '', $del_cookie_time, SITECOOKIEPATH, COOKIE_DOMAIN);
    pc_static::setcookie('pc_session_token', '', $del_cookie_time, SITECOOKIEPATH, COOKIE_DOMAIN);
    
	if($wp_user_id !== false) {
        
        // GA4 analytics event
        if(!isset($GLOBALS['pvtcont_handling_ajax_logout_call']) && isset($GLOBALS['pvtcont_google_analytics'])) {
            $GLOBALS['pvtcont_google_analytics']->trigger_event('pc_user_logout', array());
        }
        
		// PC-ACTION - user is logged out - passes user id
		do_action('pc_user_logout', $GLOBALS['pc_user_id']);
		
		// try avoiding page cache
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		
		if(isset($GLOBALS['pc_user_id'])) {
            unset($GLOBALS['pc_user_id']);
        }
        
        
		// wp user sync - unlog if WP logged is the one synced
		if($pc_users->wp_user_sync) {
            
			$current_user = wp_get_current_user();
			if($current_user && $wp_user_id == $current_user->ID) {
				wp_destroy_current_session();
	
				setcookie( AUTH_COOKIE,        ' ', (int)gmdate('u') - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH,   COOKIE_DOMAIN, is_ssl(), true);
				setcookie( SECURE_AUTH_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH,   COOKIE_DOMAIN , is_ssl(), true);
				setcookie( AUTH_COOKIE,        ' ', (int)gmdate('u') - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN , is_ssl(), true);
				setcookie( SECURE_AUTH_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN , is_ssl(), true);
				setcookie( LOGGED_IN_COOKIE,   ' ', (int)gmdate('u') - YEAR_IN_SECONDS, COOKIEPATH,          COOKIE_DOMAIN , is_ssl(), true);
				setcookie( LOGGED_IN_COOKIE,   ' ', (int)gmdate('u') - YEAR_IN_SECONDS, SITECOOKIEPATH,      COOKIE_DOMAIN , is_ssl(), true);
			
				// Old cookies
				setcookie( AUTH_COOKIE,        ' ', (int)gmdate('u') - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN , is_ssl(), true);
				setcookie( AUTH_COOKIE,        ' ', (int)gmdate('u') - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN , is_ssl(), true);
				setcookie( SECURE_AUTH_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN , is_ssl(), true);
				setcookie( SECURE_AUTH_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN , is_ssl(), true);
			
				// Even older cookies
				setcookie( USER_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN , is_ssl(), true);
				setcookie( PASS_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN , is_ssl(), true);
				setcookie( USER_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN , is_ssl(), true);
				setcookie( PASS_COOKIE, ' ', (int)gmdate('u') - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN , is_ssl(), true);
				
				// NB: don't use wp_clear_auth_cookie() to avoid interferences with do_action('clear_auth_cookie');	
			}
		}
		
		if(isset($GLOBALS['pvtcont_ver_logged_user'])) {
			unset($GLOBALS['pvtcont_ver_logged_user']);
		}
	}
	
	return true;	
}




/* REGISTRATION FORM 
 * @since 5.0
 *
 * @param (int) form_id = registration form ID to use - if invalid or null, uses first form in DB
 * @param (string) layout = form layout to use, overrides global one (one_col or fluid) 
 * @param (string) forced_cats = user category ID or IDs list (comma split) to assign to registered users
 * @param (string) redirect = custom form redirect for registered users (url or "refresh")
 * @param (string) align = form alignment - center/left/right
 *
 * @return (string) the registration form's code
 */
function pc_registration_form($form_id = '', $layout = '', $forced_cats = false, $redirect = false, $align = 'center') {
	include_once(PC_DIR.'/classes/pc_form_framework.php');

	// if is not set the target user category, return an error
	if(!get_option('pg_registration_cat')) {
		return esc_html__('You have to set registered users default category in settings', 'pc_ml');
	}
	else {
		$f_fw = new pc_form(array(
			'use_custom_cat_name' => true,
			'strip_no_reg_cats' => true
		));
		
		//// get form structure
		// if form not found - get first in list
		if(!(int)$form_id) {
			$rf = get_terms(array(
                'taxonomy'   => 'pc_reg_form',
                'hide_empty' => 0,
                'orderby'    => 'name',
                'order'      => 'ASC',
                'number'     => 1,
            ));
            
			if(empty($rf)) {
                return esc_html__('No registration forms found', 'pc_ml');
            }
			
			$rf = $rf[0];		
		}
		else {
			$rf = get_term($form_id, 'pc_reg_form');	
			
			if(empty($rf)) {
				$rf = get_terms(array(
                    'taxonomy'   => 'pc_reg_form',
                    'hide_empty' => 0,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ));
                
				if(empty($rf)) {
                    return esc_html__('No registration forms found', 'pc_ml');
                }
				
				$rf = $rf[0];		
			}
		}
        $f_fw->form_term_id = $rf->term_id;
			
		$form_structure = unserialize(base64_decode($rf->description));	
		if(!is_array($form_structure) || !in_array('username', $form_structure['include']) || !in_array('psw', $form_structure['include'])) {
			return esc_html__('Username and password fields are mandatory', 'pc_ml');
		}
		
		// disclaimer inclusion
		if(get_option('pg_use_disclaimer')) {
			$form_structure['include'][] = 'pc_disclaimer';
		}

		// PC-FILTER - manage registration form structure - passes structure array and form id
		$form_structure = apply_filters('pc_registration_form', $form_structure, $rf->term_id);
		
		
        // be sure e-mail is in, it is required
        if(!in_array('email', (array)$form_structure['include'])) {
            $form_structure['include']['email'];
            $form_structure['require']['email'];
        }
        
		
		// layout class
		$layout = (empty($layout)) ? get_option('pg_reg_layout', 'one_col') : $layout; 
		$layout_class = 'pc_'. $layout .'_form';
		

        // mail-only registration form? Add class
        $onlymail_reg_class = (!get_option('pg_allow_duplicated_mails') && get_option('pg_onlymail_registr')) ? 'pc_onlymail_reg' : '';
        
        
		// custom category parameter
		if(!empty($forced_cats) && !in_array("categories", $form_structure['include'])) {
			$cat_attr = 'data-pc_cc="'.$forced_cats.'"'; 	
		}
		else {
            $cat_attr = '';
        }
		
		// custom redirect attribute
		if(!empty($redirect)) {
			$redir_attr = 'data-pc_redirect="'.$redirect.'"';		
		}
		else {
            $redir_attr = '';
        }
		
		
		$uniqid = 'pc_rf_'.uniqid(); 
        
		//// init structure
		$form = '
		<form id="'.$uniqid.'" class="pvtcont_form pc_registration_form '. $layout_class .' '. $onlymail_reg_class .'" '. $cat_attr .' '. $redir_attr .' data-form-pag="1" data-form-id="'. pc_static::encrypt_number($rf->term_id) .'" novalidate>';
			$custom_fields = '';
			
			//// anti-spam system
			$antispam = get_option('pg_antispam_sys', 'honeypot');
			if($antispam == 'honeypot') {
				$custom_fields .= $f_fw->honeypot_generator();
			}
            elseif($antispam == 'recaptcha') { // v3
                $custom_fields .= '
                <div class="pc_grecaptcha" data-sitekey="'. get_option('pg_recaptcha_public') .'"></div>';
            }
            elseif($antispam == 'recaptcha_v2') { // v2
                $custom_fields .= '
                <div class="pc_grecaptcha" id="'. esc_attr(uniqid()) .'" data-callback="pc_gcaptcha_v2_validated" data-size="invisible"></div>';
            }
        
			$form .= $f_fw->form_code($form_structure, $custom_fields);
			
			$form .= '<div class="pc_form_response pc_reg_message"></div>';
	
			// has pages?
			if($f_fw->form_pages > 1) {
				$pag_btns = '
				<input type="button" value="'. esc_attr__('Previous', 'pc_ml') .'" class="pc_pag_btn pc_pag_prev pc_pag_btn_hidden" />
				<input type="button" value="'. esc_attr__('Next', 'pc_ml') .'" class="pc_pag_btn pc_pag_next" />';
				
				$pag_submit_class 	= 'pc_pag_submit';	
				$pag_submit_vis		= 'pc_displaynone';	
			}
			else {
				$pag_btns = '';
				$pag_submit_class 	= '';
				$pag_submit_vis		= '';	
			}
	
	
			// button's icon
			$icon = (get_option('pg_register_btn_icon')) ? '<i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_register_btn_icon')) ) .'"></i>' : '';
	
			$form .= '
			<button class="pc_reg_btn '.$pag_submit_class.' '.$pag_submit_vis.'" type="submit">
                <span class="pc_inner_btn">'.$icon. esc_html__('Submit', 'pc_ml') .'</span>
            </button>
			'. $pag_btns .'
		</form>';
        
        
        /* NFPCF - not yet */
        // fluid form columnization
        if($layout == 'fluid') {
            $form .= '
            <script type="text/javascript">
            (function() { 
                "use strict";  
                
                const intval = setInterval(() => {
                    if(typeof(jQuery) == "undefined" || typeof(pc_fluid_form_columnizer) == "undefined") {
                        return true;
                    }
                    else {
                        clearTimeout(intval);
                        pc_fluid_form_columnizer();
                    }
                }, 50);
            })();
            </script>';
        }
		
		return pc_static::form_align($form, $align);
	}
}




/* RETRIEVES REDIRECT URL CONSIDERING CUSTOM ONES AND WPML or POLYLANG
 * @since 5.0
 *
 * @param (string) $key = redirect index to retrieve - uses DB ones
 * @param (false|int) $user_id =
 *
 *	- pc_redirect_page				=> main redirect for restricted areas
 *	- pc_blocked_users_redirect		=> redirect target for blocked users
 *	- pc_logged_user_redirect		=> redirect for logged in users
 *	- pc_logout_user_redirect		=> redirect for logged out users
 *	- pc_registered_user_redirect	=> redirect for registered users
 *
 * @return (string) redirect url
 */
function pc_man_redirects($key, $user_id = false) {

	// prefix retrocompatibility
	$key = str_replace('pc_', 'pg_', $key);
	
	$baseval = get_option($key);
	if($baseval == '') {
        $url = '';
    }
	
	if($baseval == 'custom') {
		$url = get_option($key.'_custom');
	}
	elseif($baseval == 'use_main') { // redirect for blocked users - use main
		$url = pc_man_redirects('pc_redirect_page');	
	}
	else {
		// WPML - Polylang integration
		$baseval = pc_static::wpml_translated_pag_id($baseval); 
		$url = get_permalink($baseval);
	}
	
	
	// PC-FILTER - allow custom redirect url return - passes current URL, redirect index and the eventually passed user ID
	// @since v5.1
	$url = apply_filters('pc_custom_redirect_url', $url, str_replace('pg_', 'pc_', $key), $user_id);
    
    // add anti-cache parameter 
    if(strpos($url, site_url()) !== false && !get_option('pg_do_not_use_pcac')) {
        $url = add_query_arg('pcac', uniqid(), $url);
    }
	return $url;
}




/* RETRIEVES USER MESSAGES AND GIVES ABILITY TO SET CUSTOM ONES 
 * @since 5.0
 *
 * @param (string) subj - message index to retrieve - uses DB ones
 *	- pc_default_nl_mex		=> Message for not logged users
 *	- pc_default_uca_mex	=> Message if user doesn't have right permissions
 
 *	- pc_default_hc_mex		=> Message if user can't post comments
 *	- pc_default_hcwp_mex	=> Message if user doesn't have permissions to post comments 
 
 *	- pc_default_nhpa_mex	=> Message if user doesn't have reserved area
 
 *	- pc_login_ok_mex		=> Message for successful login
 *	- pc_default_pu_mex		=> Message for pending users trying to login
 *	- pc_default_du_mex		=> Message for disabled users trying to login
 
 *	- pc_default_sr_mex		=> Message if successfully registered
 *
 * @param (string) custom_txt - custom message overriding default and DB set ones
 * @return (string) the message
 */
function pc_get_message($subj, $custom_txt = '') {
	if(!empty($custom_txt)) {return $custom_txt;}
	
	// prefix retrocompatibility
	$subj = str_replace('pg_', 'pc_', $subj);
	
	$subjs = array(
		'pc_default_nl_mex'		=> esc_html__('You must be logged in to view this content', 'pc_ml'),
		'pc_default_uca_mex'	=> esc_html__("Sorry, you don't have the right permissions to view this content", 'pc_ml'),
		
		'pc_default_hc_mex'		=> esc_html__("You must be logged in to post comments", 'pc_ml'),
		'pc_default_hcwp_mex'	=> esc_html__("Sorry, you don't have the right permissions to post comments", 'pc_ml'),
		
		'pc_default_nhpa_mex'	=> esc_html__("You don't have a reserved area", 'pc_ml'),
		
		'pc_login_ok_mex'		=> esc_html__('Logged successfully, welcome!', 'pc_ml'),
		'pc_default_pu_mex'		=> esc_html__('Sorry, your account has not been activated yet', 'pc_ml'),
		'pc_default_du_mex'		=> esc_html__('Sorry, your account has been disabled', 'pc_ml'),
		
		'pc_default_sr_mex'		=> esc_html__('Registration was successful. Welcome!', 'pc_ml'),
	);
	
	foreach($subjs as $key => $default_mess) {
		if($subj == $key) {
			
			// options still use PG
			$key = str_replace('pc_', 'pg_', $subj);
			$db_val = trim(get_option($key, ''));

			$mess = (!empty($db_val)) ? $db_val : $default_mess;
			
			// PC-FILTER - customize messages - passes message text and key
			return do_shortcode( apply_filters('pc_customize_message', $mess, $subj));
		}
	}
	
	return '';
}
function pg_get_nl_message($mess = '') {return pc_get_message('pc_default_nl_mex', $mess);}
function pg_get_uca_message($mess = '') {return pc_get_message('pc_default_uca_mex', $mess);}
