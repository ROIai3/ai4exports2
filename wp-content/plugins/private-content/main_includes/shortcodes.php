<?php
if(!defined('ABSPATH')) {exit;}


// [pc-login-form] 
// get the login form
add_shortcode('pc-login-form', function($atts, $content = null) {
	$atts = shortcode_atts(array(
		'redirect' 	=> '',
		'align'		=> 'center'
	), $atts, 'pc-login-form');
    
	return str_replace(array("\r", "\n", "\t", "\v"), '', pc_login_form($atts['redirect'], $atts['align']));
});






// [pc-logout-box] 
// get the logout box
add_shortcode('pc-logout-box', function($atts, $content = null) {	
	$atts = shortcode_atts(array(
		'redirect' => ''
	), $atts, 'pc-logout-box');
	
	return str_replace(array("\r", "\n", "\t", "\v"), '', pc_logout_btn($atts['redirect']));
});






// [pc-user-del-box] 
// prints the box allowing useres to delete themselves
add_shortcode('pc-user-del-box', function($atts, $content = null) {	
	$atts = shortcode_atts(array(
		'align' 	=> 'center',
		'redirect' 	=> ''
	), $atts, 'pc-user-del-box');
	
	
	$icon = (get_option('pg_user_del_btn_icon')) ? '<i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_user_del_btn_icon')) ) .'"></i>' : '';
	
    $psw_icon = get_option('pg_psw_icon');
    $icon_label = esc_attr__('Password', 'pc_ml');
    
    if(get_option('pg_single_psw_f_w_reveal')) {
        $psw_icon_class = 'far fa-eye';    
        $icon_label = esc_attr__('toggle password visibility', 'pc_ml');
    }
    $psw_icon = (isset($psw_icon_class)) ? '<span class="pc_field_icon" title="'. $icon_label .'"><i class="'. esc_attr($psw_icon_class) .'"></i></span>' : '';
    
	$psw_icon_wrap_class = ($psw_icon) ? 'pc_field_w_icon' : '';
	$redirect = (empty($atts['redirect'])) ? get_home_url() : $atts['redirect'];
	
	$form = 
	'<div class="pc_del_user_wrap">
		<div class="pc_warn_box">
			'. esc_html__('You are about to request a complete removal from', 'pc_ml').' '. get_bloginfo('name') .'
			<br/><br/>
			'. esc_html__('This action cannot be reverted', 'pc_ml') .'.<br/>
			'. esc_html__('To proceed, please insert your password and submit', 'pc_ml') .'.
		</div>
	
		<form class="pc_login_form pc_del_user_form" data-pc_redirect="'. esc_attr($redirect) .'">';
		
			$form .= '
			<div class="pc_login_row '. $psw_icon_wrap_class .'">
				<div class="pc_field_container">
					'. $psw_icon .'
					<input type="password" name="pc_del_user_psw" value="" placeholder="'. esc_attr__('password', 'pc_ml') .'" autocapitalize="off" autocomplete="new-password" autocorrect="off" />
				</div>
			</div>
            
            <div class="pc_user_del_message pc_user_del_message_mobile"></div>
			
			<button class="pc_del_user_btn" type="submit">
				<span class="pc_inner_btn">'. $icon. esc_html__('Delete my account', 'pc_ml') .'</span>
			</button>
            
            <div class="pc_user_del_message"></div>
		</form>
	</div>';
	
	$code = (!pc_user_logged(false) && !current_user_can(get_option('pg_min_role', 'upload_files'))) ? '' : pc_static::form_align($form, $atts['align']);
	return str_replace(array("\r", "\n", "\t", "\v"), '', $code);
});







// [pc-registration-form] 
// get the registration form
add_shortcode('pc-registration-form', function($atts, $content = null) {
	$atts = shortcode_atts(array(
		'id'				=> '',
        'form_id'           => '', // builder-proof index 
		'layout' 			=> '',
		'custom_categories' => '',
		'redirect' 			=> '',
		'align'				=> 'center'
	), $atts, 'pc-registration-form');

    if($atts['form_id'] && !$atts['id']) {
        $atts['id'] = $atts['form_id'];    
    }
    
	return str_replace(array("\r", "\n", "\t", "\v"), '', pc_registration_form($atts['id'], $atts['layout'], $atts['custom_categories'], $atts['redirect'], $atts['align']));
});






/* NFPCF */
// [pc-pvt-content][/pc-pvt-content]  
// hide shortcode content if user is not logged and is not of the specified category or also if is logged
add_shortcode('pc-pvt-content', function($atts, $content = null) {
	$atts = shortcode_atts( array(
		'allow' 	=> 'all',
		'block'		=> '',
		'warning'	=> '1',
		'message'	=> '',
		'login_lb'	=> '',
		'registr_lb'=> ''
	), $atts, 'pc-pvt-content');
	
    $allow 	    = $atts['allow'];
    $block		= $atts['block'];
    $warning	= $atts['warning'];
    $message	= $atts['message'];
    $login_lb	= $atts['login_lb'];
    $registr_lb = $atts['registr_lb'];
    
    
	$custom_message = $message;
	
    // PC-FILTER - extra control on pvt block shortcode warning message
    $custom_message = apply_filters('pc_pvt_block_sc_custom_message', $custom_message);
    
    
	// if nothing is specified, return the content
	if(empty($allow)) {
        return do_shortcode($content);
    }


	// MESSAGES
	// print something only if warning is active
	if($warning == '1') {
		
		// check login lightbox association
		$final_login_lb = get_option('pg_warn_box_login');
		if($login_lb) {
            $final_login_lb = ($login_lb == 'none') ? '' : $login_lb;
        }
		if($final_login_lb && !get_term_by('id', $final_login_lb, 'pc_lightboxes')) {
            $final_login_lb = '';
        }
        
		$login_icon = ($final_login_lb && get_option('pg_login_btn_icon')) ? '<i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_login_btn_icon')) ) .'"></i>' : '';
		
		// check login lightbox association
		$final_registr_lb = get_option('pg_warn_box_registr');
		if($registr_lb) {
            $final_registr_lb = ($registr_lb == 'none') ? '' : $registr_lb;
        }
		if($final_registr_lb && !get_term_by('id', $final_registr_lb, 'pc_lightboxes')) {
            $final_registr_lb = '';
        }
		$registr_icon = ($final_registr_lb && get_option('pg_register_btn_icon')) ? '<i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_register_btn_icon')) ) .'"></i>' : '';
		
		// buttons code
		if($final_login_lb && pc_user_logged(false) === false) {
			$login = '<span class="pc_warn_box_btn pc_login_trig pc_lb_trig_'. $final_login_lb .'">'. $login_icon . esc_html__('Login', 'pc_ml') .'</span>';
			pc_static::enqueue_lb($final_login_lb);
		} else {
			$login = '';
		}
		
		if($final_registr_lb && pc_user_logged(false) === false) {
			$registration = '<span class="pc_warn_box_btn pc_registr_trig pc_lb_trig_'. $final_registr_lb .'">'. $registr_icon . esc_html__('Register', 'pc_ml') .'</span>';
			pc_static::enqueue_lb($final_registr_lb);
		} else {
			$registration = '';
		}
		$buttons = ($login || $registration) ? '<div class="pc_warn_box_btn_wrap">'. $login . $registration .'</div>' : '';
		
		
		// prepare the message if user is not logged
		$message = '<div class="pc_warn_box">'. pc_get_message('pc_default_nl_mex', $custom_message) . $buttons .'</div>';
		
		// prepare message if user has not the right category
		$not_has_level_err = '<div class="pc_warn_box">'. pc_get_message('pc_default_uca_mex', $custom_message)  .'</div>';
	} 
	else {
		$message = '';	
		$not_has_level_err = '';
        
        if(defined('ELEMENTOR_URL') && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            $message = '[private block - actually hidden]';    
            $not_has_level_err = $message;
        }
	}
	
	
	// check user allowance
	$response = pc_user_check($allow, $block, $wp_user_pass = true); 	
    
	if($response === 1) {
		return do_shortcode($content);
	}
	elseif($response === 2) {
		return $not_has_level_err;
	}
	else {
		// if contents have to be shown to unlogged users -> returns text only if custom message exists
		if($allow == 'unlogged') {
			return (!empty($custom_message)) ? '<div class="pc_login_block"><p>'. $custom_message .'</p></div>' : '';
		}

		return $message;
	}
});







/* NFPCF */
// [pc-user-pvt-page-contents] 
// get user's private page contents
add_shortcode('pc-user-pvt-page-contents', function($atts, $content = null) {
	return pvtcont_user_page_contents();
});