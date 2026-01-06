<?php
// USER DASHBOARD - AJAX HANDLERS
if(!defined('ABSPATH')) {exit;}


////////////////////////////////////////////////
////// SAVE DATA (create/update user) //////////
////////////////////////////////////////////////

function pvtcont_save_user_dashboard_ajax() {
	global $pc_users, $pc_meta, $pc_wp_user;
	
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
		die(json_encode(array(
			'response' 	=> 'error',
			'text'		=> 'Cheating?'
		)));
	}
	
	// retrieve user ID
	$user_id = (isset($_POST['pc_user_id']) && (int)$_POST['pc_user_id']) ? absint($_POST['pc_user_id']) : false;
	$is_new_user = ($user_id) ? false : true;
	
	// if editing, does user exist?
	if($user_id && !$pc_users->get_user_field($user_id, 'id')) {
		die(json_encode(array(
			'response' 	=> 'error',
			'text'		=> esc_html__('User not found', 'pc_ml')
		)));
	}
    
    
    // check whethr current user can edit
    $cuc_user_id = ($user_id) ? $user_id : 'some';
	if(!pc_wpuc_static::current_wp_user_can_edit_pc_user($cuc_user_id)) {
		die(json_encode(array(
			'response' 	=> 'error',
			'text'		=> esc_html__('You are not allowed to edit users', 'pc_ml')
		)));	
	}
	
	
	// WP user sync check
	$wp_synced_id = false;
    if($user_id && $pc_users->wp_user_sync) {
		$wp_synced_id = $pc_wp_user->pvtc_is_synced($user_id, true);
	}
	

	//// Fetch and validate essential data
	include_once(PC_DIR .'/classes/pc_form_framework.php');
	$form_fw = new pc_form;
	
	$form_structure = array(
		'include' => array('name', 'surname', 'username', 'tel', 'email', 'psw', 'disable_pvt_page', 'categories')
	);
	
	
	// PC-FILTER - add fields to validate and save in "add user" page - passes form structure (must comply with form framework) and the eventual user ID we are editing
	$form_structure = apply_filters('pc_user_dashboard_validation', $form_structure, $user_id);
	
	
	// if WP synced, can't change username and may have custom roles
	if($wp_synced_id) {
		unset($form_structure['include'][2]);
		$form_structure['include'][] = 'specific_wp_roles';
	} 
	
	// setup validation
	$fdata = $form_fw->get_fields_data($form_structure['include']);
    
	// PC-FILTER - user dashboard form errors on submit - if editing, passes user ID and the eventual linked WP user
	$errors = implode('<br/>', apply_filters('pc_user_dashboard_errors', array(), $user_id, $wp_synced_id));
	
	
	// place a flag to recognize the adminn-side user addition
	$GLOBALS['pvtcont_adding_user_by_admin'] = true;
	
	
	// INSERT
	if($is_new_user && empty($errors)) {
		$user_id = $pc_users->insert_user($fdata, $status = 1, $allow_wp_sync_fail = true);
		$errors = $pc_users->validation_errors;
		
		if(empty($errors)) {
			$errors = $pc_users->wp_sync_error; 
		}
	}
	
	// UPDATE
	elseif(!$is_new_user && empty($errors)) {
		$result = $pc_users->update_user($user_id, $fdata);
		$errors = $pc_users->validation_errors;
	}
	
	
	// error report
	if(!empty($errors)) {
		die(json_encode(array(
			'response' 	=> 'error',
			'text'		=> $errors
		)));	
	}
	
	
	
	// WP user sync - save specific user roles
	if($wp_synced_id) {
		$pc_meta->update_meta($user_id, 'specific_wp_roles', $fdata['specific_wp_roles']);	
		$pc_wp_user->set_wps_custom_roles($wp_synced_id);
	}
	
	
	// PC-ACTION - allow add-ons to save custom user dashboard data on form submit - passes form data (only registered fields), user ID and true if is a new user
	//// registered pvtcontent fields are already saved by $pc_users class
	do_action('pc_user_dashboard_save', $fdata, $user_id, $is_new_user);
	
		
	// PC-ACTION - user has been successfully added through admin panel - passes new user ID
	if($is_new_user) {
		do_action('pc_user_added_by_admin', $user_id);
	}
	
	
	die(json_encode(array(
		'response' 	=> 'success',
		'user_id'	=> $user_id
	)));	
}
add_action('wp_ajax_pvtcont_save_user_dashboard_ajax', 'pvtcont_save_user_dashboard_ajax');








////////////////////////////////////////////////
////// CHANGE USER STATUS //////////////////////
////////////////////////////////////////////////

function pvtcont_user_dashboard_change_status() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        die('Cheating?');
    }
	global $pc_users;
	
	// retrieve user ID
	$user_id = (isset($_POST['pc_user_id'])) ? (int)$_POST['pc_user_id'] : false;
	if(!$user_id || !$pc_users->get_user_field($user_id, 'id')) {
		die(esc_html__('User not found', 'pc_ml'));
	}
    
    // WP user check
	if(!pc_wpuc_static::current_wp_user_can_edit_pc_user($user_id)) {
        die(esc_html__('You are not allowed to edit users', 'pc_ml'));
    }
	
	// retrieve new status
	$status = (isset($_POST['status'])) ? (int)$_POST['status'] : false;
	if(!in_array($status, array(0,1,2))) {
		die(esc_html__('Invalid status', 'pc_ml'));
	}
	
	
	// status == 0 - delete user
	if(!$status) {
		$pc_users->delete_user($user_id);	
	}
	else {
		$pc_users->change_status($user_id, $status);	
	}
	
	die('success');
}
add_action('wp_ajax_pvtcont_user_dashboard_change_status', 'pvtcont_user_dashboard_change_status');






