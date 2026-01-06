<?php 
if(!defined('ABSPATH')) {exit;}


// user reserved page container - contents when unlogged
function pvtcont_so_pvtpag_content() {
	return array(
		'original_content'		=> esc_html__("Original content", 'pc_ml'),
		'original_plus_form'	=> esc_html__("Original content + login form", 'pc_ml'),
		'form_plus_original'	=> esc_html__("Login form + original content", 'pc_ml'),	
		'only_form'				=> esc_html__("Only login form", 'pc_ml'),		
	);	
}


// WP user sync - emulable roles
function pvtcont_wps_emulable_roles() {
	global $pc_wp_user;	
	$roles = array();
    
    if(!function_exists('get_editable_roles')) {
        return $roles;
    }
    
	foreach(get_editable_roles() as $role_id => $data) { 
		if(!in_array($role_id, $pc_wp_user->forbidden_roles)) {
			$roles[ $role_id ] = $data['name'];
		}
	}
    
    // bbPress
    if(function_exists('bbp_get_dynamic_roles')) {
        foreach(bbp_get_dynamic_roles() as $role_id => $data) {
            if(!in_array($role_id, $pc_wp_user->forbidden_roles)) {
                $roles[ $role_id ] = $data['name'];
            }       
        }
    }
    
    
    // PC-FILTER - manage available WP user Sync emulable roles (array(role_id => role_name))
    $roles = apply_filters('pc_wps_emulable_roles', $roles);
				  
	return $roles;
}



// WP roles (using capabilities)
function pvtcont_wp_roles($role = false) {
	$roles = array(
		'read' 				=> esc_html__('Subscriber', 'pc_ml'),
		'edit_posts'		=> esc_html__('Contributor', 'pc_ml'),
		'upload_files'		=> esc_html__('Author', 'pc_ml'),
		'edit_pages'		=> esc_html__('Editor', 'pc_ml'),
		'manage_options' 	=> esc_html__('Administrator', 'pc_ml')
	);
	
	return ($role) ? $roles[$role] : $roles;
}



// brief additional WP capabilities list for PC admin role
function pvtcont_admin_role_addit_caps() {
	$caps = array(
        'upload_files' 	    => esc_html__('Manage media files', 'pc_ml'),
        'moderate_comments' => esc_html__('Moderate comments', 'pc_ml'),
        'manage_categories' => esc_html__('Manage post categories', 'pc_ml'),
        
		'man_cpt_post' 	=> esc_html__('Manage Posts', 'pc_ml'),
        'man_cpt_page' 	=> esc_html__('Manage Pages', 'pc_ml'),
	);
    
    $args = array(
        '_builtin' => false
    );
    $cpt_obj = get_post_types($args, 'objects');
    
    foreach($cpt_obj as $id => $obj) {
        if($id == 'pg_user_page') {
            continue;    
        }
        
        $caps[ 'man_cpt_'. $id ] = esc_html__('Manage', 'pc_ml') .' '. $obj->labels->name;        
    }
	
	return $caps;
}





// password strength options
function pvtcont_psw_strength_opts() {
	return array(
		'chars_digits'	=> esc_html__('use characters and digits', 'pc_ml'),
		'use_uppercase'	=> esc_html__('use uppercase characters', 'pc_ml'),
		'use_symbols'	=> esc_html__('use symbols', 'pc_ml')
	);	
}





// Message styles
function pvtcont_mess_styles_opts() {
	return array(
		'outlined_w_icon'	=> esc_html__('Outlined with icon', 'pc_ml'),
        'soft_colors'       => esc_html__('Soft colors', 'pc_ml'),
        'soft_colors_w_icon'=> esc_html__('Soft colors with icon', 'pc_ml'),
        'bold_colors'       => esc_html__('Bold colors', 'pc_ml'),
        'bold_colors_w_icon'=> esc_html__('Bold colors with icon', 'pc_ml'),
        'minimal'           => esc_html__('Minimal', 'pc_ml'),
	);	
}