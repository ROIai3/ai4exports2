<?php
/* NFPCF */

// WPbakery (formerly Visual Composer) integration
if(!defined('ABSPATH')) {exit;}


// user categories array builder for VC
function pc_vc_uc_array($bulk_opts = true, $apply_filter = true) {

	// fix for PCPP
	if(function_exists('pcpp_is_integrated_flag')) {
		pcpp_is_integrated_flag();	
	}
	
	$raw = pc_static::restr_opts_arr($bulk_opts, $apply_filter);
	
	$arr = array();
	foreach($raw as $block) {
		foreach($block['opts'] as $id => $name) {
			$arr[$name] = $id;	
		}
	}
	return $arr;
}


// revert key => val array into val => key array for WPbakery
function pc_vc_invert_arr_keys($array) {
	$final = array();
	
	foreach($array as $key => $val) {
		$final[$val] = $key;	
	}
	
	return $final;
}



function pc_on_visual_composer() {
    include_once(PC_DIR .'/main_includes/user_categories.php'); // be sure tax are registered
	
	pvtcont_user_cat_taxonomy();
	$lb_instances = array('' => esc_html__('As default', 'pc_ml'), 'none' => esc_html__('No login button', 'pc_ml')) + pc_static::get_lb_instances();
	
	
	#########################################
	######## LOGIN FORM #####################
	#########################################
	
	// parameters
	$params = array(
		array(
			'type' 			=> 'textfield',
			'heading' 		=> esc_html__('Custom Redirect', 'pc_ml'),
			'param_name' 	=> 'redirect',
			'admin_label' 	=> true,
			'description'	=> esc_html__('Custom redirect (use a valid URL or "refresh" keyword)', 'pc_ml'),
		),
		array(
			'type' 			=> 'dropdown',
			'heading' 		=> esc_html__('Alignment', 'pc_ml'),
			'param_name' 	=> 'align',
			'admin_label' 	=> true,
			'value' 		=> array(
				esc_html__('Center', 'pc_ml') 	=> 'center',
				esc_html__('Left', 'pc_ml') 	=> 'left',
				esc_html__('Right', 'pc_ml') 	=> 'right',
			),
		),
	);
	
	// compile
	vc_map(
        array(
            'name' 			=> 'PC - '. esc_html__('Login Form', 'pc_ml'),
			'description'	=> esc_html__("Displays PrivateContent login form to unlogged users", 'pc_ml'),
            'base' 			=> 'pc-login-form',
            'category' 		=> "PrivateContent",
			'icon'			=> PC_URL .'/img/vc_icon.png',
            'params' 		=> $params,
        )
    );
	
	
	
	
	
	#########################################
	####### LOGOUT BUTTON ###################
	#########################################
	
	// parameters
	$params = array(
		array(
			'type' 			=> 'textfield',
			'heading' 		=> esc_html__('Custom Redirect', 'pc_ml'),
			'param_name' 	=> 'redirect',
			'admin_label' 	=> true,
            
            /* translators: 1: html code, 2: html code, 3: html code, 4: html code. */
			'description'	=> sprintf(esc_html__('%1$sNOTE:%2$s is possible to directly logout users adding %3$s?pc_logout%4$s to any site URL', 'pc_ml'), '<strong>', '</strong>', '<em>', '</em>') . '.<br/>'. esc_html__('Example', 'pc_ml') .': <em>http://www.mysite.com/?pc_logout</em>'
		),
	);
	
	if(isset($ggom_param)) {
		$params[] = $ggom_param;	
	}
		  
	// compile
	vc_map(
        array(
            'name' 			=> 'PC - '. esc_html__('Logout Box', 'pc_ml'),
			'description'	=> esc_html__("Displays PrivateContent logout box", 'pc_ml'),
            'base' 			=> 'pc-logout-box',
            'category' 		=> "PrivateContent",
			'icon'			=> PC_URL .'/img/vc_icon.png',
            'params' 		=> $params,
        )
    );
	
	
	
	
	
	#########################################
	######## USER DELETION BOX ##############
	#########################################
	
	// parameters
	$params = array(
		array(
			'type' 			=> 'textfield',
			'heading' 		=> esc_html__('Custom Redirect', 'pc_ml'),
			'param_name' 	=> 'redirect',
			'admin_label' 	=> true,
			'description'	=> esc_html__('Custom redirect (use a valid URL or "refresh" keyword)', 'pc_ml'),
		),
		array(
			'type' 			=> 'dropdown',
			'heading' 		=> esc_html__('Alignment', 'pc_ml'),
			'param_name' 	=> 'align',
			'admin_label' 	=> true,
			'value' 		=> array(
				esc_html__('Center', 'pc_ml') 	=> 'center',
				esc_html__('Left', 'pc_ml') 	=> 'left',
				esc_html__('Right', 'pc_ml') 	=> 'right',
			),
		),
	);
	
	// compile
	vc_map(
        array(
            'name' 			=> 'PC - '. esc_html__('User Deletion', 'pc_ml'),
			'description'	=> esc_html__("Displays PrivateContent box allowing user's deletion", 'pc_ml'),
            'base' 			=> 'pc-user-del-box',
            'category' 		=> "PrivateContent",
			'icon'			=> PC_URL .'/img/vc_icon.png',
            'params' 		=> $params,
        )
    );
	
	
	


	#########################################
	####### PVT BLOCK SHORTCODE #############
	#########################################
	
	// parameters
	$params = array(
		array(
			'type' 			=> 'checkbox',
			'heading' 		=> esc_html__('Who can see contents?', 'pc_ml'),
			'param_name' 	=> 'allow',
			'admin_label' 	=> true,
			'edit_field_class' => 'vc_col-xs-12 vc_column-with-padding pc_vc_multichoice',
			'value' 		=> pc_vc_uc_array()
		),
		array(
			'type' 			=> 'checkbox',
			'heading' 		=> esc_html__('Who to block? (optional)', 'pc_ml'),
			'param_name' 	=> 'block',
			'admin_label' 	=> true,
			'edit_field_class' => 'vc_col-xs-12 vc_column-with-padding pc_vc_multichoice',
			'value' 		=> pc_vc_uc_array(false)
		),
		array(
			'type' 			=> 'checkbox',
			'param_name' 	=> 'warning',
			'value' 		=> array(
				'<strong>'. esc_html__('Hide warning box?', 'pc_ml') .'</strong>' => 0
			),
			'description'	=> esc_html__('By default a yellow warning box is displayed', 'pc_ml'),
		),
		array(
			'type' 			=> 'textfield',
			'heading' 		=> esc_html__('Custom message for not allowed users', 'pc_ml'),
			'param_name' 	=> 'message',
			'value' 		=> '',
		),
		array(
			'type' 			=> 'dropdown',
			'heading' 		=> esc_html__("Login button's lightbox", 'pc_ml'),
			'param_name' 	=> 'login_lb',
			'admin_label' 	=> true,
			'value' 		=> pc_vc_invert_arr_keys($lb_instances),
		),
		array(
			'type' 			=> 'dropdown',
			'heading' 		=> esc_html__("Registration button's lightbox", 'pc_ml'),
			'param_name' 	=> 'registr_lb',
			'admin_label' 	=> true,
			'value' 		=> pc_vc_invert_arr_keys($lb_instances),
		),
		
		
		array(
            "type" => "textarea_html",
            "holder" => "div",
            "heading" => esc_html__("Contents", "'pc_ml'"),
            "param_name" => "content",
            "value" => '',
            "description" => esc_html__("Protected content", "'pc_ml'")
         )
	);
  
	// compile
	vc_map(
        array(
            'name' 			=> 'PC - '. esc_html__('Private Block', 'pc_ml'),
			'description'	=> esc_html__("Hide contents", 'pc_ml'),
            'base' 			=> 'pc-pvt-content',
            'category' 		=> "PrivateContent",
			'icon'			=> PC_URL .'/img/vc_icon.png',
            'params' 		=> $params,
        )
    );
	
	
	

	
	#########################################
	### REGISTRATION FORM SHORTCODE #########
	#########################################

	// forms list
	pc_reg_form_ct();
	$reg_forms = get_terms(array(
        'taxonomy'   => 'pc_reg_form',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));
	
	$reg_form_array = array();
	foreach($reg_forms as $rf) {
		$reg_form_array[ $rf->name ] = $rf->term_id;
	}
		

	// parameters
	$params = array(
		array(
			'type' 			=> 'dropdown',
			'heading' 		=> esc_html__('Which form to use?', 'pc_ml'),
			'param_name' 	=> 'id',
			'admin_label' 	=> true,
			'value' 		=> $reg_form_array,
		),
		array(
			'type' 			=> 'dropdown',
			'heading' 		=> esc_html__('Layout', 'pc_ml'),
			'param_name' 	=> 'layout',
			'admin_label' 	=> true,
			'value' 		=> array(
				esc_html__('Default one', 'pc_ml') => '',
				esc_html__('Single column', 'pc_ml') => 'one_col',
				esc_html__('Fluid (multi column)', 'pc_ml') => 'fluid',
			),
		),
		array(
			'type' 			=> 'checkbox',
			'heading' 		=> esc_html__('Custom categories assignment (ignored if field is in form)', 'pc_ml'),
			'param_name' 	=> 'custom_categories',
			'admin_label' 	=> true,
			'edit_field_class' => 'vc_col-xs-12 vc_column-with-padding pc_vc_multichoice',
			'value' 		=> pc_vc_uc_array(false)
		),
		array(
			'type' 			=> 'textfield',
			'heading' 		=> esc_html__('Custom redirect (use a valid URL)', 'pc_ml'),
			'param_name' 	=> 'redirect',
			'admin_label' 	=> true,
			'value' 		=> '',
		),
		array(
			'type' 			=> 'dropdown',
			'heading' 		=> esc_html__('Alignment', 'pc_ml'),
			'param_name' 	=> 'align',
			'admin_label' 	=> true,
			'value' 		=> array(
				esc_html__('Center', 'pc_ml') 	=> 'center',
				esc_html__('Left', 'pc_ml') 	=> 'left',
				esc_html__('Right', 'pc_ml') 	=> 'right',
			),
		),
	);
	
  
	// compile
	vc_map(
        array(
            'name' 			=> 'PC - '. esc_html__('Registration form', 'pc_ml'),
			'description'	=> esc_html__("Displays PrivateContent registration form", 'pc_ml'),
            'base' 			=> 'pc-registration-form',
            'category' 		=> "PrivateContent",
			'icon'			=> PC_URL .'/img/vc_icon.png',
            'params' 		=> $params,
        )
    );
	

}
add_action('vc_before_init', 'pc_on_visual_composer');


