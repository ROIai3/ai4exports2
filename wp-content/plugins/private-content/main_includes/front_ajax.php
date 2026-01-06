<?php
if(!defined('ABSPATH')) {exit;}


////////////////////////////////////////////////
////// USER REGISTRATION ///////////////////////
////////////////////////////////////////////////

function pc_reg_form_submit() {
    global $wpdb, $pc_users;	
    require_once(PC_DIR .'/classes/pc_form_framework.php');

    
    ////////// VALIDATION ////////////////////////////////////

    if(!isset($_POST['pvtcont_nonce']) || !pc_static::verify_nonce($_POST['pvtcont_nonce'], 'pvtcont_ajax')) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => 'Missing or wrong nonce, please refresh the page and try again'
        )));
    }
    
    $form_id = (isset($_POST['form_id'])) ? (int)pc_static::decrypt_number(sanitize_text_field(wp_unslash($_POST['form_id']))) : false;
    $term = get_term_by('id', $form_id, 'pc_reg_form');

    if(!$form_id || !$term) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => esc_html__('Form not found', 'pc_ml')
        )));
    }

    $GLOBALS['pvtcont_custom_cat_name'] = true;
    $f_fw = new pc_form(array(
        'use_custom_cat_name' => true,
        'strip_no_reg_cats' => true
    ));
    $f_fw->form_term_id = $form_id;

    $form_structure = (array)unserialize(base64_decode($term->description));

    // PC-FILTER - manage registration form structure - passes structure array and form id
    $form_structure = apply_filters('pc_registration_form', $form_structure, $term->term_id);

    // PC-FILTER - custom validation indexes (must comply with Simple Form validator structure) - passes indexes array and form ID
    $custom_indexes = apply_filters('pc_reg_form_custom_valid', array(), $term->term_id);

    $indexes = $f_fw->generate_validator($form_structure, $custom_indexes);


    //// prior custom validation
    $cust_errors = array();

    $antispam_sys = get_option('pg_antispam_sys', 'honeypot');
    if($antispam_sys == 'honeypot') {
        if(!$f_fw->honeypot_validaton()) {
            $cust_errors[] = "Bot test not passed";	
        }
    }
    else {

        // check user token
        if(!isset($_POST['grecaptcha_token']) || empty($_POST['grecaptcha_token'])) {
            $cust_errors[] = "reCAPTCHA - missing user token!";		
        }
        else {
            /* NFPCF */
            // get google's answer
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'method' => 'POST',
                'body' => array(
                    'secret' 	=> get_option('pg_recaptcha_secret'), 
                    'response' 	=> pc_static::sanitize_val($_POST['grecaptcha_token'])
                ),
            ));	

            if(is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
                $cust_errors[] = "reCAPTCHA - ". esc_html__("error retrieving remote check", 'pc_ml');
            } 
            else {
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if(
                    !is_array($body) || !isset($body['success']) || !$body['success'] ||
                    ($antispam_sys == 'recaptcha' && (float)$body['score'] < 0.4)
                ) {
                    $cust_errors[] = "reCAPTCHA - ". esc_html__("bot detected", 'pc_ml') .' '. (string)$body['score'];
                }
            }
        }
    }

    // check disclaimer
    if(get_option('pg_use_disclaimer') && !isset($_POST['pc_disclaimer'])) {
        $cust_errors[] = esc_html__("Disclaimer", 'pc_ml') ." - ". esc_html__("must be accepted to proceed with registration", 'pc_ml');
    }

    // validation wrap-up
    $is_valid = $f_fw->validate_form($indexes, $cust_errors, false, false, $form_id, 'pc_reg_form');	
    $fdata = $f_fw->form_data;

    if(!$is_valid) {
        $error = $f_fw->errors;
    }
    else {
        $status = (get_option('pg_registered_pending')) ? 3 : 1;
        $allow_wp_sync_fail = (!get_option('pg_require_wps_registration')) ? true : false;

        // if no categories field - use forced or default ones
        if(!isset($fdata['categories'])) {
            $fdata['categories'] = (isset($_POST['pc_cc']) && !empty($_POST['pc_cc'])) ? 
                explode(',', sanitize_text_field(wp_unslash($_POST['pc_cc']))) : 
                get_option('pg_registration_cat');
            
            $GLOBALS['pvtcont_ignore_no_reg_cats'] = true; // flag to bypass reg cats restrictions
        }

        // private page switch - put in form data
        $fdata['disable_pvt_page'] = (get_option('pg_registered_pvtpage')) ? 0 : 1;

        // insert user
        $new_user_id = $pc_users->insert_user($fdata, $status, $allow_wp_sync_fail);
        if(!$new_user_id) {
            $error = $pc_users->validation_errors;	
        }
    }

    
    // errors?
    if(isset($error) && !empty($error)) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => $error
        )));
    }
    
    
    // PC-FILTER - extra way to abort registration and display error message once user has just been registered in frontend - passing a string, registration will be aborted and new user deleted
    $extra_error = apply_filters('pc_after_user_registr_error', '', $new_user_id, $status);
    if(!empty($extra_error)) {
        $pc_users->delete_user($new_user_id);

        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => $extra_error
        )));     
    }

    
    // PC-ACTION - registered user - passes new user ID and status
    do_action('pc_registered_user', $new_user_id, $status);	

    // auto-login user?
    if((get_option('pg_autologin_registered') && !get_option('pg_registered_pending')) || isset($GLOBALS['pvtcont_force_login_after_registr'])) {

        // PC-FILTER - allows extra control over registration auto-login system. Must return a bool
        if(apply_filters('pc_autologin_registered', true, $new_user_id)) {
            pc_login($fdata['username'], $fdata['psw']);        
        }
    }


    // success message
    wp_die(json_encode(array( 
        'resp' 		=> 'success',
        'mess' 		=> pc_get_message('pc_default_sr_mex'),
        'new_uid'   => $new_user_id,
        'redirect'	=> pc_man_redirects('pg_registered_user_redirect', $new_user_id),
        'fid'       => $form_id,
    )));
}
add_action('wp_ajax_pc_reg_form_submit', 'pc_reg_form_submit');
add_action('wp_ajax_nopriv_pc_reg_form_submit', 'pc_reg_form_submit');





////////////////////////////////////////////////
////// LOGIN THROUGH FRONT FORM ////////////////
////////////////////////////////////////////////

function pc_login_form_submit() {
	global $wpdb, $pc_users;

    if(!isset($_POST['pvtcont_nonce']) || !pc_static::verify_nonce($_POST['pvtcont_nonce'], 'pvtcont_ajax')) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => 'Missing or wrong nonce, please refresh the page and try again'
        )));
    }
    
    include_once(PC_DIR .'/classes/pc_form_framework.php');
    include_once(PC_DIR .'/classes/simple_form_validator.php');

    // anti-bruteforce - is IP already blacklisted?
    if(pc_abfa_static::visitor_is_blacklisted()) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => pc_abfa_static::error_message()
        )));   
    }


    // form data process
    $f_fw = new pc_form();
    $validator = new simple_fv('pc_ml');
    $indexes = array();

    $indexes[] = array('index'=>'pc_auth_username', 'label'=>'username', 'required' => true);
    $indexes[] = array('index'=>'pc_auth_psw', 'label'=>'psw', 'required' => true);
    $indexes[] = array('index'=>'pc_remember_me', 'label' => 'remember me');

    $validator->formHandle($indexes);
    $error = $validator->getErrors();
    $fdata = $validator->form_val;
    
    // antispam
    $antispam_sys = get_option('pg_antispam_sys', 'honeypot');
    if($antispam_sys == 'honeypot') {
        if(!$f_fw->honeypot_validaton()) {
            wp_die(json_encode(array( 
                'resp' => 'error',
                'mess' => "Bot test not passed"
            )));
        }
    }
    else {

        /* NFPCF */
        // check user token
        if(!isset($_POST['grecaptcha_token']) || empty($_POST['grecaptcha_token'])) {
            wp_die(json_encode(array( 
                'resp' => 'error',
                'mess' => "reCAPTCHA - missing user token!"
            )));
        }
        else {

            // get google's answer
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'method' => 'POST',
                'body' => array(
                    'secret' 	=> get_option('pg_recaptcha_secret'), 
                    'response' 	=> sanitize_text_field(wp_unslash($_POST['grecaptcha_token']))
                ),
            ));	

            if(is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
                wp_die(json_encode(array( 
                    'resp' => 'error',
                    'mess' => "reCAPTCHA - ". strip_html__("error retrieving remote check", 'pc_ml')
                )));	
            } 
            else {
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if(
                    !is_array($body) || !isset($body['success']) || !$body['success'] ||
                    ($antispam_sys == 'recaptcha' && (float)$body['score'] < 0.4)
                ) {
                    wp_die(json_encode(array( 
                        'resp' => 'error',
                        'mess' => "reCAPTCHA - ". esc_html__("bot detected", 'pc_ml') .' '. (string)$body['score']	
                    )));	  
                }
            }
        }
    }

    
    // error message
    if($error) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => esc_html__('Invalid username or password', 'pc_ml'),
            'abfa' => pc_abfa_static::add_to_blacklist(), // something's wrong - anti-bruteforce acts
        )));
    }
    
    //// try to login
    $GLOBALS['pvtcont_handling_ajax_login_call'] = true;
    $response = pc_login($fdata['pc_auth_username'], $fdata['pc_auth_psw'], $fdata['pc_remember_me']); // password must be "addslashed" to comply with WP

    // something's wrong - anti-bruteforce acts
    $anti_bruteforce_block = 0;
    if($response !== true) {  
        $anti_bruteforce_block = pc_abfa_static::add_to_blacklist();    
    }


    // user not found
    if(!$response) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => esc_html__('Incorrect username or password', 'pc_ml'),
            'abfa' => $anti_bruteforce_block, 
        )));
    }

    // disabled/pending user
    elseif((int)$response === 3) {
        wp_die(json_encode(array(
            'resp' => 'error',
            'mess' => pc_get_message('pc_default_pu_mex'),
            'abfa' => $anti_bruteforce_block, 
        )));
    }

    // disabled user
    elseif((int)$response == 2) {
        wp_die(json_encode(array(
            'resp' => 'error',
            'mess' => pc_get_message('pc_default_du_mex'),
            'abfa' => $anti_bruteforce_block, 
        )));
    }

    // custom error
    elseif($response !== true) {
        wp_die(json_encode(array(
            'resp' => 'error',
            'mess' => $response,
            'abfa' => $anti_bruteforce_block, 
        )));
    }

    // successfully logged
    else {
        // redirect logged user to pvt page
        if(get_option('pg_redirect_back_after_login') && isset($_COOKIE['pc_last_restricted_page']) && filter_var($_COOKIE['pc_last_restricted_page'], FILTER_VALIDATE_URL)) {
            $redirect_url = esc_url($_COOKIE['pc_last_restricted_page']);
            pc_static::setcookie('pc_last_restricted_page', '', -1);  
        }
        else {
            $redirect_url = pc_man_redirects('pg_logged_user_redirect', $GLOBALS['pc_user_id']);	
        }

        wp_die(json_encode(array(
            'resp' => 'success',
            'mess' => pc_get_message('pc_login_ok_mex'),
            'uid'  => $GLOBALS['pc_user_id'],
            'redirect' => $redirect_url
        )));
    }
}
add_action('wp_ajax_pc_login_form_submit', 'pc_login_form_submit');
add_action('wp_ajax_nopriv_pc_login_form_submit', 'pc_login_form_submit');





////////////////////////////////////////////////
////// LOGOUT BUTTON'S HANDLER /////////////////
////////////////////////////////////////////////

function pc_logout_btn_handler() {
    if(!isset($_POST['pvtcont_nonce']) || !pc_static::verify_nonce($_POST['pvtcont_nonce'], 'pvtcont_ajax')) {
        wp_die('Missing or wrong nonce, please refresh the page and try again');
    }
    
    $GLOBALS['pvtcont_handling_ajax_logout_call'] = true;
    pc_logout();

    // output the redirect url
    wp_die(esc_html(pc_man_redirects('pg_logout_user_redirect')));
}
add_action('wp_ajax_pc_logout_btn_handler', 'pc_logout_btn_handler');
add_action('wp_ajax_nopriv_pc_logout_btn_handler', 'pc_logout_btn_handler');





////////////////////////////////////////////////
////// USER COMPLETE DELETION //////////////////
////////////////////////////////////////////////

function pc_user_del_ajax() {
	global $wpdb, $pc_users;
	
	// be sure user is logged
	if(!isset($GLOBALS['pc_user_id'])) {
		wp_die(json_encode(array(
			'resp' => 'error',
			'mess' => esc_html__('No user logged', 'pc_ml')
		)));	
	}
	$user_id = (int)$GLOBALS['pc_user_id'];
	
    // nonce?
    if(!isset($_POST['pvtcont_nonce']) || !pc_static::verify_nonce($_POST['pvtcont_nonce'], 'pvtcont_ajax')) {
        wp_die(json_encode(array( 
            'resp' => 'error',
            'mess' => 'Missing or wrong nonce, please refresh the page and try again'
        )));
    }
    
	// get submitted password
	$psw = (isset($_POST['pc_ud_psw'])) ? sanitize_text_field(wp_unslash($_POST['pc_ud_psw'])) : false;
	if(empty($psw)) {
		wp_die(json_encode(array(
			'resp' => 'error',
			'mess' => esc_html__('Please insert your password', 'pc_ml')
		)));	
	}
	
	// query the database to check password
	$db_psw = $pc_users->get_user_field($user_id, 'psw'); 
	if(!wp_check_password($psw, $db_psw)) {
		wp_die(json_encode(array(
			'resp' => 'error',
			'mess' => esc_html__('Wrong password', 'pc_ml')
		)));		
	}
	
	
	// PC-ACTION - action right before an user is deleted by its own choice
	do_action('pc_pre_self_user_del', $user_id);

	
	// unlog and delete user
	if($pc_users->delete_user($user_id)) {
		pc_logout();
		
		// success message	
		wp_die(json_encode(array(
			'resp' => 'success',
			'mess' => esc_html__('Account successfully deleted!', 'pc_ml')
		)));		
	}
	
    wp_die(json_encode(array(
        'resp' => 'error',
        'mess' => 'Error deleting user - please contact the administator'
    )));
}
add_action('wp_ajax_pc_user_del_ajax', 'pc_user_del_ajax');
add_action('wp_ajax_nopriv_pc_user_del_ajax', 'pc_user_del_ajax');
