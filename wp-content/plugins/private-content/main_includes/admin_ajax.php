<?php
if(!defined('ABSPATH')) {exit;}



////////////////////////////////////////////////
////// SET PREDEFINED STYLES ///////////////////
////////////////////////////////////////////////

function pvtcont_set_predefined_style($style = false) {
	if($style) {
        $_POST['style'] = $style;    
    }
    
    if(!$style && (!isset($_POST['lcwp_nonce']) || !pc_static::verify_nonce($_POST['lcwp_nonce'], 'lcwp_nonce'))) {
        wp_die('Cheating?');
    }
	if(!isset($_POST['style'])) {
        wp_die('data is missing');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }

	require_once(PC_DIR .'/settings/preset_styles.php');
	
	$style_data = pc_preset_styles_data(sanitize_text_field(wp_unslash($_POST['style'])));
	if(empty($style_data)) {
        wp_die('Style not found');
    }
	
	
	// override values
	foreach($style_data as $key => $val) {
		update_option($key, $val);		
	}


	// if is not forcing inline CSS - create static file
	if(!get_option('pg_inline_css')) {
		pc_static::create_custom_style();
	}
	
    if(!$style) {
	   wp_die('success');
    }
}
add_action('wp_ajax_pvtcont_set_predefined_style', 'pvtcont_set_predefined_style');





////////////////////////////////////////////////
////// USERS LIST - UPDATE SHOWN COLUMNS ///////
////////////////////////////////////////////////

function pvtcont_ulist_update_user_cols() {
	if(!isset($_POST['nonce']) || !pc_static::verify_nonce($_POST['nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
	if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
    
    if(!isset($_POST['cols']) || !is_array($_POST['cols']) || empty($_POST['cols'])) {
        wp_die('Columns data missing');    
    }
    
    update_user_meta(get_current_user_id(), 'pc_ulist_columns', pc_static::sanitize_val((array)$_POST['cols']));
	wp_die('success');	
}
add_action('wp_ajax_pvtcont_ulist_update_user_cols', 'pvtcont_ulist_update_user_cols');





////////////////////////////////////////////////
////// USERS LIST - BULK ASSIGN CATEGORIES /////
////////////////////////////////////////////////

function pvtcont_bulk_cat_change() {
	if(!isset($_POST['nonce']) || !pc_static::verify_nonce($_POST['nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    
    if(
        (isset($GLOBALS['pvtcont_cuc_edit']) && !$GLOBALS['pvtcont_cuc_edit']) || 
        !pc_wpuc_static::current_wp_user_can_edit_pc_user('some')
    ) {
        wp_die('You do not have rights to edit users');        
    }
    
	$users = pc_static::sanitize_val((array)$_POST['users']);
	if(!count($users)) {
        wp_die('Select at least one user');
    }
	
	$cats = pc_static::sanitize_val((array)$_POST['cats']);
	if(!count($cats)) {
        wp_die('Select at least one category');
    }
    
    // sanitize data
    foreach($users as $key => $u_id) {
        $users[$key] = (int)$u_id;
        
        if(!pc_wpuc_static::current_wp_user_can_edit_pc_user((int)$u_id)) {
            wp_die(esc_html__('You are not allowed to manage one or more of these users', 'pc_ml'));
        }
    }
    
    foreach($cats as $key => $cat_id) {
        $cats[$key] = (int)$cat_id;
        
        // be sure current user can manage those categories
        if(!get_option('pg_tu_can_edit_user_cats') && PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any' && !in_array((int)$cat_id, (array)PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
            wp_die(esc_html__('You are not allowed to manage one or more of these categories', 'pc_ml'));
        }
        
        // cat IDs must be serialized as string
        $cats[$key] = (string)$cats[$key];
    }
	
	global $wpdb;
    $users_placeh = implode( ', ', array_fill(0, count($users), '%d'));
    $placeh_vals = array_merge(array(serialize($cats)), $users);
    
	$rows_affected = $wpdb->query(
		$wpdb->prepare(
			"UPDATE ". esc_sql(PC_USERS_TABLE) ." SET categories = %s WHERE id IN ($users_placeh)",
            $placeh_vals
		)
	);
	
	if((int)$rows_affected != count($users) && $wpdb->last_error) {
        echo esc_html($wpdb->last_query);
		wp_die(esc_html__('Error updating one or more users', 'pc_ml') .': '. esc_html($wpdb->last_error));	
	}
	
	
	/* PC-ACTION - triggered when categories have been changed to multiple users - passes users ID array */
	do_action('pc_bulk_cat_assign_done', $users);

	wp_die('success');	
}
add_action('wp_ajax_pvtcont_bulk_cat_change', 'pvtcont_bulk_cat_change');





////////////////////////////////////////////////////////////
////// USERS LIST - MANAGE USERS (DEL | ENABLE | DISABLE) //
////////////////////////////////////////////////////////////

function pvtcont_ulist_manage_users() {
	$processed = array();

    if (!isset($_POST['nonce']) || !pc_static::verify_nonce($_POST['nonce'], 'lcwp_ajax')) {
        $resp = array(
			'status'         => 'error',
			'message'        => 'Cheating?',
            'processed_users'=> $processed,
		);
		wp_die(json_encode($resp));	
    }
    if(
        (isset($GLOBALS['pvtcont_cuc_edit']) && !$GLOBALS['pvtcont_cuc_edit']) ||
        !pc_wpuc_static::current_wp_user_can_edit_pc_user('some')
    ) {  
        $resp = array(
			'status'         => 'error',
			'message'        => 'You do not have rights to edit users',
            'processed_users'=> $processed,
		);
		wp_die(json_encode($resp));	
    }
    
    $users = pc_static::sanitize_val((array)$_POST['users']);
	if(!count($users)) {
        $resp = array(
			'status'         => 'error',
			'message'        => 'Affect at least one user',
            'processed_users'=> $processed,
		);
		wp_die(json_encode($resp));	
    }
    
	$action = sanitize_text_field(wp_unslash($_POST['pc_cmd'])); 
	if(!in_array($action, array('enable', 'disable', 'delete'))) {
        $resp = array(
			'status'         => 'error',
			'message'        => 'Action not recognized',
            'processed_users'=> $processed,
		);
		wp_die(json_encode($resp));	
    }
	
    // perform
    global $pc_users;

    foreach($users as $uid) {
        $uid = (int)$uid;
        
        switch($action) {
            case 'delete' : 
                
                if($pc_users->delete_user($uid)) {
                    $processed[] = $uid;	
                }
                break;

            case 'disable' : 
            case 'activate' :     
            default :
                
                $new_status = ($action == 'disable') ? 2 : 1;
                
                if($pc_users->change_status($uid, $new_status)) {
                    $processed[] = $uid;	        
                }
                break;
        }
    }
    
    
    if(count($processed) == count($users)) {
        $resp = array(
			'status'         => 'success',
            'processed_users'=> $processed,
		);		    
    }
    else {
        $resp = array(
			'status'         => 'error',
			'message'        => esc_html__('Error performing the action for users', 'pc_ml') .' '. implode(', ', array_diff($users, $processed)),
            'processed_users'=> $processed,
		);    
    }

	wp_die(json_encode($resp));	
}
add_action('wp_ajax_pvtcont_ulist_manage_users', 'pvtcont_ulist_manage_users');






/*******************************************************************************************************************/





////////////////////////////////////////////////
/// WP USER SYNC - MANUALLY SYNC SINGLE USER ///
////////////////////////////////////////////////

function pvtcont_wp_sync_single_user() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user((int)$_POST['pc_user_id'])) {
        wp_die('You do not have rights to edit the user');        
    }
    
	global $pc_users, $pc_wp_user;
	$user_id = absint($_POST['pc_user_id']); 
	
	$args = array('to_get' => array('username', 'psw', 'email', 'name', 'surname'));
	$ud = $pc_users->get_user($user_id, $args);
	if(empty($ud)) {
        wp_die('user does not exist');
    }	
	
	$result = $pc_wp_user->sync_wp_user($ud, 0, true);	
	
	echo (!$result) ? esc_html($pc_wp_user->pwu_sync_error) : 'success';
	wp_die();	
}
add_action('wp_ajax_pvtcont_wp_sync_single_user', 'pvtcont_wp_sync_single_user');




////////////////////////////////////////////////
/// WP USER SYNC - SINGLE (WP) USER SYNC ///////
////////////////////////////////////////////////

function pvtcont_wp_to_pc_single_user_sync() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to edit the user');        
    }
    
	if(!isset($_POST['wp_user_id']) || !(int)$_POST['wp_user_id']) {
		$error = 'Missing user ID';	
	}
	if(!isset($_POST['pc_wps_eus_cat']) || !count((array)$_POST['pc_wps_eus_cat'])) {
		$error = 'Missing user categories';	
	}
	
	if(isset($error)) {
		$resp = array(
			'status'  => 'error',
			'message' => $error
		);
		wp_die(json_encode($resp));	
	}
	
	
	global $pc_wp_user;
	
	$wp_user_id = absint($_POST['wp_user_id']);
	$cats 		= pc_static::sanitize_val((array)$_POST['pc_wps_eus_cat']);
	$response 	= $pc_wp_user->pc_user_from_wp($wp_user_id, $cats);
	
	if(is_wp_error($response)) {
		$resp = array(
			'status'  => 'error',
			'message' => $response->get_error_message()
		);	
	}
	else {
		$resp = array(
			'status'  => 'success',
			'user_id' => $response
		);		
	}

	wp_die(json_encode($resp));	
}
add_action('wp_ajax_pvtcont_wp_to_pc_single_user_sync', 'pvtcont_wp_to_pc_single_user_sync');




////////////////////////////////////////////////
/// WP USER SYNC - BULK WP USERS IMPORT ////////
////////////////////////////////////////////////

function pvtcont_wp_to_pc_bulk_user_sync() {
	if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to edit users');        
    }
    
	if(!isset($_POST['pc_wp_imp_cat']) || !count( (array)$_POST['pc_wp_imp_cat'] )) {
		wp_die('User categories missing');	
	}
	if(!isset($_POST['pc_wp_imp_roles']) || !count( (array)$_POST['pc_wp_imp_roles'] )) {
		wp_die('User roles missing');	
	}
	
	$cats  = pc_static::sanitize_val((array)$_POST['pc_wp_imp_cat']);
	$roles = pc_static::sanitize_val((array)$_POST['pc_wp_imp_roles']);
	
	// get users
	$fetched_users = array();
	foreach($roles as $role) {
		
		$user_query = new WP_User_Query( array('role' => $role) );
		$results = $user_query->get_results(); 
		if( !empty($results) ) {
			$fetched_users = array_merge($fetched_users, $user_query->get_results());	
		}
	}
	
	if(empty($fetched_users)) {
		wp_die('<div class="pc_warn pc_error"><p>'. esc_html__('No users found', 'pc_ml') .'</p></div>');	
	}
	
	// start syncing
	global $pc_wp_user;
	$failed_sync = array(); 
	
	foreach($fetched_users as $to_sync) {
		$new_user_id = $pc_wp_user->pc_user_from_wp($to_sync->ID, $cats);
	
		if(is_wp_error($new_user_id)) {
			
			// TEMPORARY WORKAROUND - if "already synced" is returned is due to a past bug (August 2019) - set roles again
			if($new_user_id->get_error_code() == 'already synced') {
				$pc_wp_user->set_wps_custom_roles($to_sync->ID);
			}
			else {
				$failed_sync[ $to_sync->user_login ] = $new_user_id->get_error_message();	
			}
		}
        else {
            // PC-ACTION - allow custom operation for newly WP-to-PvtContent bulk-imported users. Passes new user ID, extra fields can be retrieved through $_POST
            do_action('wp_to_pc_bulk_synced_user', $new_user_id);
        }
	}
	

	// print results
	if((count($fetched_users) - count($failed_sync)) > 0) {
		echo '<div class="pc_warn pc_success"><p><strong>'. (count($fetched_users) - count($failed_sync)) .' '. esc_html__('synced users', 'pc_ml') .'</strong></p></div>';
	}
	
	if(count($failed_sync)) {
		echo '<div class="pc_warn pcma_warn_box pc_error">';
			echo '<p><strong>'. count($failed_sync) .' '. esc_html__('failed syncs', 'pc_ml') .':</strong></p>';
			
			foreach($failed_sync as $username => $message) {
				echo '<p><strong>'. esc_html($username) .'</strong> - '. wp_kses_post($message) .'</p>';	
			}
		echo '</div>';	
	}
	wp_die();
}
add_action('wp_ajax_pvtcont_wp_to_pc_bulk_user_sync', 'pvtcont_wp_to_pc_bulk_user_sync');




//////////////////////////////////////////////////
/// WP USER SYNC - MANUALLY DETACH SINGLE USER ///
//////////////////////////////////////////////////

function pvtcont_wp_detach_single_user() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user(absint($_POST['pc_user_id']))) {
        wp_die('You do not have rights to edit the user');        
    }
    
	global $pc_wp_user;
	$user_id = absint($_POST['pc_user_id']); 
	
	$result = $pc_wp_user->detach_wp_user($user_id);
	
	echo ($result === true) ? 'success' : esc_html($result);
	wp_die();	
}
add_action('wp_ajax_pvtcont_wp_detach_single_user', 'pvtcont_wp_detach_single_user');




////////////////////////////////////////////////
/// WP USER SYNC - GLOBAL SYNC /////////////////
////////////////////////////////////////////////

function pvtcont_wp_global_sync() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to edit users');        
    }
    
	global $pc_wp_user;
	wp_die(wp_kses_post($pc_wp_user->global_sync()));
}
add_action('wp_ajax_pvtcont_wp_global_sync', 'pvtcont_wp_global_sync');




////////////////////////////////////////////////
/// WP USER SYNC - GLOBAL DETACH ///////////////
////////////////////////////////////////////////

function pvtcont_wp_global_detach() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to edit users');        
    }
    
	global $pc_wp_user;
	wp_die(wp_kses_post($pc_wp_user->global_detach()));
}
add_action('wp_ajax_pvtcont_wp_global_detach', 'pvtcont_wp_global_detach');




////////////////////////////////////////////////////
/// WP USER SYNC - SERACH & SYNC EXISTING MATCHES //
////////////////////////////////////////////////////

function pvtcont_wps_search_and_sync_matches() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to edit users');        
    }
    
	global $pc_wp_user;
	wp_die(wp_kses_post($pc_wp_user->search_and_sync_matches()));	
}
add_action('wp_ajax_pvtcont_wps_search_and_sync_matches', 'pvtcont_wps_search_and_sync_matches');





/*******************************************************************************************************************/





////////////////////////////////////////////////////
/// WP USERS SEARCH FOR AUTOCOMPLETE PICKER ////////
////////////////////////////////////////////////////

function pvtcont_ausnp_search() {
	if (!isset($_POST['nonce']) || !pc_static::verify_nonce($_POST['nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to manage users');        
    }
    
    $to_match = (isset($_POST['search'])) ? pc_static::sanitize_val($_POST['search']) : '';
    if(empty($to_match)) {
        wp_die(json_encode(array()));        
    }
    
    $to_exclude = (isset($_POST['to_exclude'])) ? pc_static::sanitize_val((array)$_POST['to_exclude']) : array();
    
    
    $users = new WP_User_Query(array(
        'search'         => '*'. $to_match .'*',
        'search_columns' => array(
            'user_login',
            'user_nicename',
            'user_email',
        ),
        'role__not_in'  => 'Administrator',
        'exclude'       => $to_exclude
    ));
    
    $users = $users->get_results();
    $to_return = array();
    
    if(is_array($users)) {
        foreach($users as $u) {
            
            // ignore synced users!
            if(get_option('pg_wp_user_sync') && is_array($u->wp_capabilities) && !in_array('pvtcontent', $u->wp_capabilities)) {
                continue;        
            }
            
            $to_return[] = array(
                'id'	=> $u->ID, 
                'value'	=> '', 
                'label'	=> $u->user_login
            );	        
        }
    }

	echo wp_json_encode($to_return);
	wp_die();
}
add_action('wp_ajax_pvtcont_ausnp_search', 'pvtcont_ausnp_search');






/*******************************************************************************************************************/





////////////////////////////////////////////////////
/// REGISTRATION FORMS - ADD FORM //////////////////
////////////////////////////////////////////////////

/* NFPCF */
function pvtcont_add_reg_form() {
	if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
	if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
    
	$form_name = pc_static::sanitize_val($_POST['form_name']); 
	if(empty($form_name) || strlen($form_name) > 250) {
        wp_die( esc_html__('Please insert a valid form name', 'pc_ml') );
    }
	
	$result = wp_insert_term($form_name, 'pc_reg_form', array(
		'description' => base64_encode(serialize( array(
			'include' => array('username', 'psw'), 'require' => array('username', 'psw')
		)))
	));	
	
	echo (is_wp_error($result)) ? wp_kses_post($result->get_error_message()) : absint($result['term_id']);
	wp_die();	
}
add_action('wp_ajax_pvtcont_add_reg_form', 'pvtcont_add_reg_form');




////////////////////////////////////////////////////
/// REGISTRATION FORMS - SHOW BUILDER //////////////
////////////////////////////////////////////////////

function pvtcont_reg_form_builder() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
    
	include_once(PC_DIR .'/classes/pc_form_framework.php');
	$f_fw = new pc_form;	
	
	if(!absint($_POST['form_id'])) {
        wp_die('Invalid form ID');
    }
    $form_id = absint($_POST['form_id']);
    
	$term = get_term($form_id, 'pc_reg_form');
	$structure = unserialize(base64_decode($term->description));

	echo '
	<table id="pc_rf_add_f_table" class="widefat pc_table">
	  <tbody>
	  	<tr>
		  <td class="pc_label_td">'. esc_html__('Form name', 'pc_ml') .'</td>
		  <td class="pc_field_td">
		  	<input type="text" name="pc_rf_name" id="pc_rf_name" value="'. esc_attr($term->name) .'" placeholder="'. esc_attr__("New form's name", 'pc_ml').'" autocomplete="off" />
		  </td>
		</tr>
		<tr>
		  <td class="pc_label_td"><input type="button" name="pc_rf_add_field" id="pc_rf_add_field" class="button-secondary" value="'. esc_attr__('Add field', 'pc_ml') .'" /></td>
		  <td class="pc_field_td">
		  	<select name="pc_form_fields_dd" class="lcwp_sf_select pc_form_fields_dd" data-placeholder="'. esc_attr__('Add fields', 'pc_ml') .' .." autocomplete="off">
				<option value="custom|||text">- '. esc_html__('TEXT BLOCK', 'pc_ml') .'</option>
				<option value="custom|||page">- '. esc_html__('PAGINATOR', 'pc_ml') .'</option>
                <option value="custom|||sep">- '. esc_html__('SEPARATOR BAR', 'pc_ml') .'</option>';
			
			$unsorted_fields = $f_fw->fields;
			ksort($unsorted_fields);
			foreach($unsorted_fields as $index => $data) {

				if(in_array($index, (array)$f_fw->no_wizard_indexes)) {continue;}
				echo '<option value="'. esc_attr($index) .'">'. esc_html($data['label']) .'</option>';	
			}
			
			echo '	
			</select>
		  </td>
		</tr>  
	  </tbody>
	</table>
	
	<table id="pc_rf_builder_table" class="widefat pc_table">
	  <thead>
		<tr>
		  <th></th>
		  <th></th>
		  <th>'. esc_html__('Field', 'pc_ml') .'</th>
		  <th>'. esc_html__('Required?', 'pc_ml') .'</th>
		</tr>
	  </thead>
	  <tbody>';
	
	$txt_id = 0;	
	foreach($structure['include'] as $field) {
		// if field is not registered - skip
		if(substr($field, 0, 13) != 'custom|||text' && substr($field, 0, 12) != 'custom|||sep' && $field != 'custom|||page' && !isset($f_fw->fields[$field])) {
            continue;
        }
		
		$required = (in_array($field, (array)$structure['require']) || in_array($field, array('username', 'psw', 'categories'))) ? 'checked="checked"' : '';
		$disabled = (in_array($field, array('username', 'psw', 'categories'))) ? 'disabled="disabled"' : '';
		
		$del_code = (in_array($field, array('username', 'psw'))) ? '' : '<span class="pc_del_field dashicons dashicons-no-alt" title="'. esc_attr__('remove field', 'pc_ml') .'"></span>';
		
		// text block
		if(substr($field, 0, 13) == 'custom|||text') {
			$content = (isset($structure['texts']) && is_array($structure['texts']) && isset($structure['texts'][$txt_id])) ? $structure['texts'][$txt_id] : '';
			
			$code = '
			<td colspan="2">
				<input type="hidden" name="pc_reg_form_field[]" value="'. esc_attr($field) .'" class="pc_reg_form_builder_included" />
				<textarea name="pc_reg_form_texts[]" placeholder="'. esc_attr__('Supports HTML and shortcodes', 'pc_ml') .'">'. esc_textarea($content) .'</textarea>
			</td>';
			
			$txt_id++;
		}
        
        // separator bar
		elseif(substr($field, 0, 12) == 'custom|||sep') {
			$code = '
			<td colspan="2">
				<input type="hidden" name="pc_reg_form_field[]" value="'. esc_attr($field) .'" class="pc_reg_form_builder_included" />
				<strong>'. esc_html__('SEPARATOR BAR', 'pc_ml') .'</strong>
			</td>';
			
			$txt_id++;
		}
		
		// paginator block
		elseif($field == 'custom|||page') {
			$code = '
			<td colspan="2" class="pc_paginator_td">
				<input type="hidden" name="pc_reg_form_field[]" value="'. esc_attr($field) .'" class="pc_reg_form_builder_included" />
				<strong>'. esc_html__('PAGINATOR', 'pc_ml') .'</strong>
			</td>';
		}
		
		// standard part
		else {
			$code = '
			<td>
				<input type="hidden" name="pc_reg_form_field[]" value="'. esc_attr($field) .'" class="pc_reg_form_builder_included" />
				'. $f_fw->get_field_name($field) .'
			</td>
			<td>
				<input type="checkbox" name="pc_reg_form_req[]" value="'. esc_attr($field) .'" '. esc_html($required) .' '. esc_html($disabled) .' class="lcwp_sf_check pc_reg_form_builder_required" autocomplete="off" />
			</td>';
		}
		
		echo '
		<tr rel="'. esc_attr($field) .'">
			<td>'. wp_kses_post($del_code) .'</td>
			<td><span class="pc_move_field dashicons dashicons-move" title="'. esc_attr__('sort field', 'pc_ml') .'"></span></td>
			'. pc_static::wp_kses_ext($code) .'
		</tr>';
	}
		
	echo '</tbody>
	</table>
	
	<input type="button" class="pc_reg_form_save button-primary" value="'. esc_attr__('Save Form', 'pc_ml') .'">
	<hr id="pc_save_reg_f_hr" />';
    
	wp_die();	
}
add_action('wp_ajax_pvtcont_reg_form_builder', 'pvtcont_reg_form_builder');




////////////////////////////////////////////////////
/// REGISTRATION FORMS - UPDATE FORM ///////////////
////////////////////////////////////////////////////

function pvtcont_update_reg_form() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
	if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
    
	include_once(PC_DIR .'/classes/simple_form_validator.php');
	$validator = new simple_fv('pc_ml');	
		
	$indexes = array();
	$indexes[] = array('index'=>'form_id', 'label'=>'form id', 'type'=>'int', 'required'=>true);
	$indexes[] = array('index'=>'form_name', 'label'=>'form name', 'required'=>true, 'max_len'=>250);
	$indexes[] = array('index'=>'fields_included', 'label'=>'fields included', 'required'=>true);
	$indexes[] = array('index'=>'fields_required', 'label'=>'fields required', 'required'=>true);
	$indexes[] = array('index'=>'texts', 'label'=>'text blocks');
	
	$validator->formHandle($indexes);
	$fdata = $validator->form_val;

	// check username and password fields existence
	if(!is_array($fdata['fields_included']) || !in_array('username', $fdata['fields_included']) || !in_array('psw', $fdata['fields_included'])) {
		$validator->custom_error[ esc_html__("Form structure", 'pc_ml') ] = esc_html__("Username and password fields are mandatory", 'pc_ml');	
	}
	
	$error = $validator->getErrors();
	if(!$error) {
		// clean texts from slashes
		if(!empty($fdata['texts'])) {
			$escaped = array();
			
			foreach((array)$fdata['texts'] as $val) {
				$escaped[] = wp_unslash($val);
			}
			
			$fdata['texts'] = $escaped;
		}
        
        
        // sync texts with WPML/Polylang 
        if(is_array($fdata['texts'])) {
            foreach($fdata['texts'] as $num => $ft) {
                $key = 'Form #'. (int)$fdata['form_id'].' - text block #'. $num;

                if(function_exists('icl_register_string')) {
                    icl_register_string('PrivateContent Forms', $key, $ft);
                }
                if(function_exists('pll_register_string')) {
                    pll_register_string('PrivateContent Forms', $ft, $key);	
                }
            }
        }
        
        
		// setup array - user base64_encode to prevent WP tags cleaning
		$descr = base64_encode(
			serialize( 
				array(
					'include' => $fdata['fields_included'], 'require'=>$fdata['fields_required'], 'texts'=>$fdata['texts']
				)
			)
		);

		// update	
		$result = wp_update_term($fdata['form_id'], 'pc_reg_form', array(
			'name' => $fdata['form_name'],
			'description' => $descr
		));
		
		echo (is_wp_error($result)) ? wp_kses_post($result->get_error_message()) : 'success';
	}
	else {
		echo wp_kses_post($error);	
	}
	wp_die();
}
add_action('wp_ajax_pvtcont_update_reg_form', 'pvtcont_update_reg_form');




////////////////////////////////////////////////////
/// REGISTRATION FORMS - DELETE FORM ///////////////
////////////////////////////////////////////////////

/* NFPCF */
function pvtcont_del_reg_form() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
	
	$form_id = absint($_POST['form_id']); 
	if(!$form_id) {
        wp_die('Invalid form ID');
    }

	echo (wp_delete_term($form_id, 'pc_reg_form')) ? 'success' : 'Error deleting form';	
	wp_die();	
}
add_action('wp_ajax_pvtcont_del_reg_form', 'pvtcont_del_reg_form');





/*******************************************************************************************************************/





////////////////////////////////////////////////////
/// LIGHTBOX INSTANCES - CREATE INSTANCE TERM //////
////////////////////////////////////////////////////

/* NFPCF */
function pvtcont_add_lightbox() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
		
	$result = wp_insert_term('|||pclbft|||', 'pc_lightboxes', array(
		'slug' => uniqid()
	));	
	if(is_wp_error($result)) {
        wp_die('error');
    }

	?>
	<li>
		<aside>
			<span class="pc_del_field dashicons dashicons-no-alt" rel="<?php echo absint($result['term_id']) ?>" title="<?php esc_attr_e('remove restriction', 'pc_ml') ?>"></span>
            <input type="hidden" name="pc_lb_id[]" value="<?php echo absint($result['term_id']) ?>" />
		</aside>   
        <div class="pc_lb_inst_toprow">
        	<table><tr>
            	<td>
                	<input type="text" name="pc_lb_note[]" class="pc_lb_note" value="" placeholder="<?php esc_attr_e("Lightbox title (required)", 'pc_ml') ?>" maxlength="250" autocomplete="off" />
                </td>
                <td>
                	<span><strong><?php esc_html_e('Trigger class', 'pc_ml') ?>:</strong> <em>pc_lb_trig_<?php echo absint($result['term_id']) ?></em></span>
                    <strong>|</strong>
                    <strong><span><?php esc_html_e('Lightbox class', 'pc_ml') ?>:</strong> <em>pc_lb_<?php echo absint($result['term_id']) ?></em></span>
                </td>
			</table></tr>
		</div>                
        <div class="pclb_editow_wrap">
        	<textarea id="pclb_<?php echo absint($result['term_id']) ?>" class="theEditor mceEditor" name="pc_lb_contents[]"></textarea>
			<strong><em><?php esc_html_e('Save settings to use full-featured editor', 'pc_ml') ?></em></strong>
            
			<script type="text/javascript">
            (function($) { 
                "use strict";  
                
                $(document).ready(function(e) {
                    tinymce.init(tinyMCEPreInit.mceInit.pg_pvtpage_default_content); // use another editor to setup these dynamic ones
                    tinymce.execCommand("mceRemoveEditor", true, "pclb_<?php echo absint($result['term_id']) ?>");
                    tinymce.execCommand("mceAddEditor", true, "pclb_<?php echo absint($result['term_id']) ?>");
                });
            }) (jQuery);
			</script>
       	</div>
	</li>
    
    <?php
	wp_die();	
}
add_action('wp_ajax_pvtcont_add_lightbox', 'pvtcont_add_lightbox');




////////////////////////////////////////////////////
/// LIGHTBOX INSTANCES - DELETE INSTANCE TERM //////
////////////////////////////////////////////////////

/* NFPCF */
function pvtcont_del_lightbox() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
	
	$lb_id = absint($_POST['lb_id']); 
	if(!$lb_id) {
        wp_die('Invalid lightbox ID');
    }

	
	// delete WPML translation record
	if(function_exists('icl_unregister_string')) {
		icl_unregister_string('PrivateContent Lightboxes', 'Lightbox #'.$lb_id);	
	}


	echo (wp_delete_term($lb_id, 'pc_lightboxes')) ? 'success' : 'Error deleting lightbox';	
	wp_die();		
}
add_action('wp_ajax_pvtcont_del_lightbox', 'pvtcont_del_lightbox');





/*******************************************************************************************************************/





////////////////////////////////////////////////////
/// MENU MANAGEMENT - LOAD MENU ITEM RESTRICTIONS //
////////////////////////////////////////////////////

function pvtcont_menu_item_restrict() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die('You do not have rights to use the plugin');        
    }
	
	$menu_items = pc_static::sanitize_val($_POST['menu_items']); 
	if(!is_array($menu_items)) {
        wp_die('Invalid data');
    }

	$vals = array();
	foreach($menu_items as $item_id) {
		$val = get_post_meta($item_id, '_menu_item_pg_hide', true);
		$vals[$item_id] = (empty($val)) ? array('') : $val;
	}
	
	echo wp_json_encode($vals);
	wp_die();	
}
add_action('wp_ajax_pvtcont_menu_item_restrict', 'pvtcont_menu_item_restrict');





/*******************************************************************************************************************/





////////////////////////////////////////////////////
/// LIVE RESTRICTIONS PREVIEW - SAVE USER PREFS ////
////////////////////////////////////////////////////

function pvtcont_set_live_restr_preview() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    
    pvtcont_setup_wp_use_pass();
    if(!PVTCONT_WP_USER_PASS) {
        wp_die('You do not have rights to use the plugin');
    }
	
	$to_emulate = json_decode(wp_unslash($_POST['to_emulate'])); 
	if(!is_array($to_emulate)) {
        wp_die('Invalid data');
    }
    $to_emulate = array_map('sanitize_text_field', $to_emulate);
    
    $allowed_vals = pc_static::onlyvals_restr_opts_arr(false, true);
    $allowed_vals['unlogged'] = 'unlogged';
    
    $to_save = array();
    
    foreach($to_emulate as $te) {
        if(isset($allowed_vals[$te])) {
            $to_save[] = $te;    
        }
    }
    
    update_user_meta(get_current_user_id(), 'pc_restr_preview_config', $to_save);
	wp_die('success');
}
add_action('wp_ajax_pvtcont_set_live_restr_preview', 'pvtcont_set_live_restr_preview');





/*******************************************************************************************************************/





////////////////////////////////////////////////////
/// QUICK RESTRICTION WIZARD IN POSTS/TERMS LIST ///
////////////////////////////////////////////////////

function pvtcont_qe_restr_wiz_in_list_form() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
		)));	
    }
    
    if(!isset($_POST['subj']) || !in_array($_POST['subj'], array('post', 'term'))) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Subject is missing',
		)));
    }
    if(!isset($_POST['subj_ids']) || !is_array($_POST['subj_ids']) || empty($_POST['subj_ids'])) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Subject IDs are missing',
		)));
    }
    if(!isset($_POST['subj_name']) || empty($_POST['subj_name'])) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Subject name is missing',
		)));
    }
    
    $subj = sanitize_text_field(wp_unslash($_POST['subj']));
    $wiz_code_subj = ($subj == 'term') ? 'tax' : $subj;
    $subj_ids = pc_static::sanitize_val((array)$_POST['subj_ids']);
    $subj_name = pc_static::sanitize_val($_POST['subj_name']);
    
    if(
        ($subj == 'post' && !current_user_can('edit_post', (int)$subj_ids[0])) || 
        ($subj == 'term' && !current_user_can('edit_term', (int)$subj_ids[0])) 
    ) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to manage this subject',
		)));   
    }
    
    if(count($subj_ids) > 1) {
        $restr_wiz_subj_id = false;
        $bulk_restr_update = true;
        $heading = esc_html__('Bulk restrictions management', 'pc_ml');
    } 
    else {
        $restr_wiz_subj_id = (int)$subj_ids[0];
        $bulk_restr_update = false;
        
        $head_subj_name = ($subj == 'term') ? get_term_by('id', $restr_wiz_subj_id, $subj_name)->name : get_post_field('post_title', $restr_wiz_subj_id);
        
        /* translators: 1: heading subject name. */
        $heading = sprintf( esc_html__("%s restrictions management", 'pc_ml'), $head_subj_name);
    }
    
    $pc_restr_wiz = new pc_restr_wizard;
    $code = '
    <h4>'. $heading .'</h4>
    <form>
        <input type="hidden" name="subj_type" value="'. esc_attr($subj) .'" />
        <input type="hidden" name="subj_ids" value="'. esc_attr(implode(',', $subj_ids)) .'" />

        '. $pc_restr_wiz->wizard_code($wiz_code_subj, $restr_wiz_subj_id, $bulk_restr_update) .'

        <div class="pc_qe_restr_wiz_in_list_btns_wrap">
            <input type="submit" value="'. esc_attr__('Update', 'pc_ml') .'" class="button-primary" />
            <input type="button" value="'. esc_attr__('Cancel', 'pc_ml') .'" class="button-secondary" />
        </div>
    </form>';
    
    
    wp_die(json_encode(array(
        'status' => 'success',
        'contents'=> $code,
    ))); 
}
add_action('wp_ajax_pvtcont_qe_restr_wiz_in_list_form', 'pvtcont_qe_restr_wiz_in_list_form');




/////////////////////////////////////////////////////////
/// SAVE QUICK RESTRICTION WIZARD IN POSTS/TERMS LIST ///
/////////////////////////////////////////////////////////

function pvtcont_qe_restr_wiz_in_list_update() {
	if (!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
		)));	
    }
    
    if(!isset($_POST['subj_type']) || !in_array($_POST['subj_type'], array('post', 'term'))) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Subject is missing',
		)));
    }
    if(!isset($_POST['subj_ids']) || empty($_POST['subj_ids'])) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Subject IDs are missing',
		)));
    }
    
    $subj = sanitize_text_field(wp_unslash($_POST['subj_type']));
    $subj_ids = explode(',', pc_static::sanitize_val($_POST['subj_ids']));
    
    if(
        ($subj == 'post' && !current_user_can('edit_post', (int)$subj_ids[0])) || 
        ($subj == 'term' && !current_user_can('edit_term', (int)$subj_ids[0])) 
    ) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to manage this subject',
		)));   
    }
    
    
    $pc_restr_wiz = new pc_restr_wizard;
    $new_restr_helpers = array();
    
    foreach($subj_ids as $sid) {
        if($pc_restr_wiz->save_restrictions($subj, $sid, true)) {
            $new_restr_helpers[$sid] = $pc_restr_wiz->postNterms_list_pc_col_txt('', 'pvtcontent', $sid, $subj);
        }
    }
    
    wp_die(json_encode(array(
        'status' => 'success',
        'new_helpers' => $new_restr_helpers,
    ))); 
}
add_action('wp_ajax_pvtcont_qe_restr_wiz_in_list_update', 'pvtcont_qe_restr_wiz_in_list_update');





/*******************************************************************************************************************/





///////////////////////////////////////////////////
/// PVTC-TO-PVTC IMPORT - UPLOAD AND CHECK JSON ///
///////////////////////////////////////////////////

function pvtcont_pvtc_import_json_upload() {
	if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
		)));	
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to use the plugin',
		)));    
    }
    
    global $pc_users;
    include_once(PC_DIR .'/classes/users_import_export.php');
    $uie = new pvtcont_users_import_export;
    
    
    $contents = $uie->validate_pvtc_import_file();
    if(!is_array($contents)) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> $contents,
		)));
    }
    
    
    $user_cats = array(
        'new' => '('. esc_html__('create new category', 'pc_ml') .')',
    );
    $user_cats_by_slug = array();
    $ucats = get_terms(array(
        'taxonomy'   => 'pg_user_categories',
        'orderby'    => 'name',
        'hide_empty' => 0,
    ));
    
    foreach($ucats as $ucat) {
        if(!get_option('pg_tu_can_edit_user_cats') && PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any' && !in_array($ucat->term_id, (array)PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
            continue;    
        }
        $user_cats[ (int)$ucat->term_id ] = esc_html($ucat->name);
        $user_cats_by_slug[ (int)$ucat->term_id ] = $ucat->slug;
    }
    
    
    $cat_assign_table = '
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><strong>'. esc_html__('Originary category name/slug', 'pc_ml') .'</strong></th>
                <th></th>
                <th><strong>'. esc_html__('Destination category', 'pc_ml') .'</strong></th>
            </tr>
        </thead>
        <tbody>';
            
        foreach($contents['main']['pc_categories'] as $ocat_term_id => $ocat_data) {
            $direct_match_cat_id = false;
            foreach($user_cats as $cat_id => $cat_name) {
                if($ocat_data['slug'] == $user_cats_by_slug[$cat_id]) {
                    $direct_match_cat_id = $cat_id;
                    break;
                }
            }
            
            $cat_assign_table .= '
            <tr>
                <td>
                    '. $ocat_data['name'] .'<br/><small>'. $ocat_data['slug'] .'</small>
                    <input type="hidden" name="pc_pvtc_import_ocat_id[]" value="'. (int)$ocat_term_id .'" required />
                </td>
                <td><span class="dashicons dashicons-controls-forward"></span></td>
                <td>';
            
                    if($direct_match_cat_id) {
                        $cat_assign_table .= '
                        <input type="hidden" name="pc_pvtc_import_dcat_id[]" value="'. $direct_match_cat_id .'" required />
                        <a href="'. admin_url('term.php?taxonomy=pg_user_categories&tag_ID='. $direct_match_cat_id) .'" target="_blank" title="'. esc_attr__('go to user category page', 'pc_ml') .'">'. $user_cats[$direct_match_cat_id] .'</a>'; 
                    }
                    else {
                        $cat_assign_table .= '
                        <select name="pc_pvtc_import_dcat_id[]" class="pc_lc_select" autocomplete="off">';

                            foreach($user_cats as $cat_id => $cat_name) {
                                $cat_assign_table .= '<option value="'. $cat_id .'">'. $cat_name .'</option>';
                            }
                        $cat_assign_table .= '
                        </select>';
                    }
            
                $cat_assign_table .= '        
                </td>
            </tr>';
            
        }
    
    $cat_assign_table .= '
        </tbody>
    </table>';
    
    
    // are there existing users?
    $existing_users = array(); // origin user ID => existing user ID
    $allow_duplicated_emails = get_option('pg_allow_duplicated_mails');
    
    foreach($contents['users'] as $orig_uid => $orig_udata) {
        $search_param = array(
            array('key' => 'username', 'operator' => '=', 'val' => $orig_udata['username'])
        );
        if(!$allow_duplicated_emails && !empty($orig_udata['email'])) {
            $search_param[] = array('key' => 'email', 'operator' => '=', 'val' => $orig_udata['email']);
        }
        
        $args = array(
            'limit'     => 1,
            'to_get'    => array('id', 'username', 'email'),
            'search'    => $search_param,
            'search_operator'   => 'OR',
        );
        $users = $pc_users->get_users($args);
        if(!empty($users)) {
            $existing_users[ $orig_uid ] = $users[0];
        }
    }
    
    
    $existing_users_table = '
    <table class="widefat fixed striped" data-users-to-import="'. count($contents['users']) .'">';
    
        if(empty($existing_users)) {
            $existing_users_table .= '
            <thead>
                <tr>
                    <th>
                        <strong>'. /* translators: 1: users count. */ sprintf(esc_html__("You are about to import %s users", 'pc_ml'), count($contents['users'])) .'</strong>
                    </th>
                </tr>
            </thead>';
        }
        else {
            $existing_users_table .= '
            <thead>
                <tr>
                    <th colspan="3">
                        <strong>'. /* translators: 1: users count. */ sprintf(esc_html__("You are about to import %s users", 'pc_ml'), count($contents['users'])) .'. '. /* translators: 1: users count. */ sprintf(esc_html__("Among them %s have correspondences in this website. How to proceed?", 'pc_ml'), count($existing_users)) .'</strong>
                    </th>
                </tr>
                <tr>
                    <th><strong>'. esc_html__('User to import', 'pc_ml') .'</strong></th>
                    <th><strong>'. esc_html__('Existing user', 'pc_ml') .'</strong></th>
                    <th>
                        <strong>'. esc_html__('Action', 'pc_ml') .'</strong>
                        <span class="pc_pvtc_imp_bulk_act_wrap">
                            <button type="button" title="'. esc_attr__('discard all', 'pc_ml') .'" class="button-secondary" data-bulk-act="discard">
                                <i class="dashicons dashicons-dismiss"></i>
                            </button>
                            <button type="button" title="'. esc_attr__('overwrite all', 'pc_ml') .'" class="button-secondary" data-bulk-act="override">
                                <i class="dashicons dashicons-yes-alt"></i>
                            </button>
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>';
                
                foreach($existing_users as $orig_uid => $exist_udata) {
                    $orig_user_ref = $contents['users'][$orig_uid]['username'];
                    $exist_user_ref = '<a href="'. admin_url('admin.php?page=pc_user_dashboard&user='. $exist_udata['id']) .'" target="_blank" title="'. esc_attr__('go to user dashboard', 'pc_ml') .'">'. $exist_udata['username'] .'</a>';
                    
                    if(!$allow_duplicated_emails) {
                        $orig_user_ref .= '<br/><small>'. $contents['users'][$orig_uid]['email'] .'</small>';
                        $exist_user_ref .= '<br/><small>'. $exist_udata['email'] .'</small>';
                    }
                    
                    $existing_users_table .= '
                    <tr>
                        <td>
                            '. $orig_user_ref .'
                            <input type="hidden" name="pc_pvtc_import_orig_uid[]" value="'. (int)$orig_uid .'" required />
                        </td>
                        <td>
                            '. $exist_user_ref .'
                            <input type="hidden" name="pc_pvtc_import_exist_uid[]" value="'. (int)$exist_udata['id'] .'" required />
                        </td>
                        <td>
                            <select name="pc_pvtc_import_exist_user_action[]" class="pc_lc_select" autocomplete="off" required>
                                <option value="discard">'. esc_html__('Do not import', 'pc_ml') .'</option>
                                <option value="override">'. esc_html__('Overwrite data', 'pc_ml') .'</option>
                            </select>
                        </td>
                    </tr>';   
                }
                
                $existing_users_table .= '
            </tbody>';
            }
    
    $existing_users_table .= '
    </table>';
    
    
    wp_die(json_encode(array(
        'status' => 'success',
        'cat_assign_table' => $cat_assign_table,
        'exist_user_table' => $existing_users_table,
    ))); 
}
add_action('wp_ajax_pvtcont_pvtc_import_json_upload', 'pvtcont_pvtc_import_json_upload');





///////////////////////////////////////////////////
/// PVTC-TO-PVTC IMPORT - PROCEED /////////////////
///////////////////////////////////////////////////

function pvtcont_pvtc_import() {
	if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
            'back_to_upload' => false,
		)));	
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to use the plugin',
            'back_to_upload' => false,
		)));    
    }
    
    global $pc_users;
    include_once(PC_DIR .'/classes/simple_form_validator.php');
    
    include_once(PC_DIR .'/classes/users_import_export.php');
    $uie = new pvtcont_users_import_export;
    
    
    $contents = $uie->validate_pvtc_import_file();
    if(!is_array($contents)) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> $contents,
            'back_to_upload' => true,
		)));
    }
    
	$validator = new simple_fv('pc_ml');		
	$indexes = array();
	$indexes[] = array('index'=>'pc_pvtc_import_ocat_id', 'label'=>'origin category ID');
	$indexes[] = array('index'=>'pc_pvtc_import_dcat_id', 'label'=>'destination category ID'); // could be also "new"
    
    $indexes[] = array('index'=>'pc_pvtc_import_orig_uid', 'label'=>'origin user ID');
    $indexes[] = array('index'=>'pc_pvtc_import_exist_uid', 'label'=>'existing user ID');
    $indexes[] = array('index'=>'pc_pvtc_import_exist_user_action', 'label'=>'existing user import behavior');
    
    $validator->formHandle($indexes);
	$fdata = $validator->form_val;
    
    // validate data
    if(empty($fdata['pc_pvtc_import_ocat_id']) && empty($fdata['pc_pvtc_import_dcat_id'])) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> 'Please define the user categories assignment',
            'back_to_upload' => false,
        )));
    }
    else {
        if(count((array)$fdata['pc_pvtc_import_ocat_id']) != count((array)$fdata['pc_pvtc_import_dcat_id'])) {
            wp_die(json_encode(array(
                'status' => 'error',
                'message'=> 'Categories matching - fields options count does not match',
                'back_to_upload' => false,
            )));
        }
        
        foreach($fdata['pc_pvtc_import_ocat_id'] as $key => $orig_cat_id) {
            if(!isset( $contents['main']['pc_categories'][$orig_cat_id] )) {
                wp_die(json_encode(array(
                    'status' => 'error',
                    'message'=> 'Categories matching - origin category #'. (string)$orig_cat_id .' not found',
                    'back_to_upload' => false,
                )));
            }
            
            $dest_cat_id = $fdata['pc_pvtc_import_dcat_id'][$key];
            if($dest_cat_id != 'new' && !get_term_by('id', $dest_cat_id, 'pg_user_categories')) {
                wp_die(json_encode(array(
                    'status' => 'error',
                    'message'=> 'Categories matching - destination category #'. (string)$dest_cat_id .' not found',
                    'back_to_upload' => false,
                ))); 
            }
        }
    }
    
    
    if(!empty($fdata['pc_pvtc_import_orig_uid']) || !empty($fdata['pc_pvtc_import_exist_uid']) || !empty($fdata['pc_pvtc_import_exist_user_action'])) {
        if(
            count((array)$fdata['pc_pvtc_import_orig_uid']) != count((array)$fdata['pc_pvtc_import_exist_uid']) || 
            count((array)$fdata['pc_pvtc_import_orig_uid']) != count((array)$fdata['pc_pvtc_import_exist_user_action'])
        ) {
            wp_die(json_encode(array(
                'status' => 'error',
                'message'=> 'Existing users actions - fields options count does not match',
                'back_to_upload' => false,
            )));
        }

        foreach($fdata['pc_pvtc_import_orig_uid'] as $key => $orig_user_id) {
            if(!isset( $contents['users'][$orig_user_id] )) {
                wp_die(json_encode(array(
                    'status' => 'error',
                    'message'=> 'Existing users actions - origin user #'. (string)$orig_user_id .' not found',
                    'back_to_upload' => false,
                )));
            }
            
            $action = $fdata['pc_pvtc_import_exist_user_action'][$key];
            if(!in_array($action, array('discard', 'override'))) {
                wp_die(json_encode(array(
                    'status' => 'error',
                    'message'=> 'Existing users actions - action "'. $action .'" not among allowed ones',
                    'back_to_upload' => false,
                )));
            }
            
            $dest_user_id = $fdata['pc_pvtc_import_exist_uid'][$key];
            if($action == 'override' && !$pc_users->id_to_username($dest_user_id)) {
                wp_die(json_encode(array(
                    'status' => 'error',
                    'message'=> 'Existing users actions - existing user #'. (string)$dest_user_id .' not found',
                    'back_to_upload' => false,
                ))); 
            }
        }
    }
    
    $response = $uie->pvtc_import($contents, $fdata);
    wp_die(json_encode($response));
}
add_action('wp_ajax_pvtcont_pvtc_import', 'pvtcont_pvtc_import');





/////////////////////////////////////////////
/// CSV IMPORT - UPLOAD AND CHECK CSV ///////
/////////////////////////////////////////////

function pvtcont_csv_import_csv_upload() {
	if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
		)));	
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to use the plugin',
		)));
    }
    if(!isset($_POST['pc_csv_imp_user_cats']) || !is_array($_POST['pc_csv_imp_user_cats']) || empty($_POST['pc_csv_imp_user_cats'])) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> esc_html__('Please select at least one user category to assign', 'pc_ml'),
		)));
    }
    
    include_once(PC_DIR .'/classes/users_import_export.php');
    include_once(PC_DIR .'/classes/pc_form_framework.php');
    
    $uie = new pvtcont_users_import_export;
    $csv_data = $uie->validate_csv_import_file();
    
    if(!is_array($csv_data)) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> $csv_data,
		)));
    }
    
    $form_fw = new pc_form;
    $auto_psw = (isset($_POST['pc_csv_imp_create_new_psw']) && $_POST['pc_csv_imp_create_new_psw']) ? true : false;
    
    $mandatory_assign = array(wp_strip_all_tags($form_fw->fields['username']['label']));
    if(!$auto_psw) {
        $mandatory_assign[] = wp_strip_all_tags($form_fw->fields['psw']['label']);
    }
    if($form_fw->mail_is_required) {
        $mandatory_assign[] = wp_strip_all_tags($form_fw->fields['email']['label']);
    }
    
    $data_map_table = '
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><strong>'. esc_html__('CSV column number/value', 'pc_ml') .'</strong></th>
                <th></th>
                <th>
                    <strong>'. esc_html__('Destination user field', 'pc_ml') .'</strong>
                    <span class="pc_pvtc_imp_bulk_act_wrap">
                        <button type="button" title="'. esc_attr__('Retrieved data must match with related field parameters. Mandatory assignments:', 'pc_ml') .' '. esc_attr(implode(', ', $mandatory_assign)) .'" class="button-secondary">
                            <i class="dashicons dashicons-warning"></i>
                        </button>
                    </span>
                </th>
            </tr>
        </thead>
        <tbody>';

        foreach($csv_data[0] as $col_num => $col_val) {
            $data_map_table .= '
            <tr>
                <td>
                    <span class="pc_csv_imp_colnum">'. $col_num .'</span>'. esc_html($col_val) .' 
                    <input type="hidden" name="pc_csv_imp_row_colnum[]" value="'. (int)$col_num .'" required />
                </td>
                <td><span class="dashicons dashicons-controls-forward"></span></td>
                <td>
                    <select name="pc_csv_imp_dest_field[]" class="pc_lc_select" autocomplete="off">
                        <option value="">('. esc_html__('do not import', 'pc_ml') .')</option>';

                        // PC-FILTER - define which privateContent fields (registered through pc_form_fields_filter) must not be included in the CSV import assignment form
                        $ignore_fields = apply_filters('pc_csv_import_ignored_fields', array());
                        $ignore_fields = array_merge($ignore_fields, array('categories', 'pc_disclaimer'));
                        if($auto_psw) {
                            $ignore_fields[] = 'psw';
                        }
            
                        foreach($form_fw->fields as $f_id => $f_data) {
                            if(!in_array($f_id, (array)$ignore_fields)) {
                                $data_map_table .= '<option value="'. esc_attr($f_id) .'">'. esc_html($f_data['label']) .'</option>';
                            }
                        }
            
                    $data_map_table .= '
                    </select>      
                </td>
            </tr>';
            
        }
    
    $data_map_table .= '
        </tbody>
    </table>';
    
    
    wp_die(json_encode(array(
        'status' => 'success',
        'data_map_table' => $data_map_table,
    ))); 
}
add_action('wp_ajax_pvtcont_csv_import_csv_upload', 'pvtcont_csv_import_csv_upload');





//////////////////////////////////////////////
/// CSV IMPORT - PROCEED /////////////////////
//////////////////////////////////////////////

function pvtcont_csv_import() {
	if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
            'back_to_upload' => false,
		)));	
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to use the plugin',
            'back_to_upload' => false,
		)));    
    }	
    
    global $pc_users;
    include_once(PC_DIR .'/classes/simple_form_validator.php');
    include_once(PC_DIR .'/classes/users_import_export.php');
    include_once(PC_DIR .'/classes/pc_form_framework.php');
    
    $uie = new pvtcont_users_import_export;
    $csv_data = $uie->validate_csv_import_file();
    
    if(!is_array($csv_data)) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> $csv_data,
            'back_to_upload' => true,
		)));
    }
    
    
	$validator = new simple_fv('pc_ml');		
	$indexes = array();
    
    $indexes[] = array('index'=>'pc_csv_imp_user_cats', 'label'=>'Assigned user categories');
	$indexes[] = array('index'=>'pc_csv_imp_have_pvt_pag', 'label'=>'Enable user private page');
    $indexes[] = array('index'=>'pc_csv_imp_create_new_psw', 'label'=>'Create new password');
    $indexes[] = array('index'=>'pc_csv_imp_ignore_first_row', 'label'=>'Ignore first CSV row');  
    
	$indexes[] = array('index'=>'pc_csv_imp_row_colnum', 'label'=>'CSV row column number');
	$indexes[] = array('index'=>'pc_csv_imp_dest_field', 'label'=>'destination field ID'); // could be also "new"
    
    $validator->formHandle($indexes);
	$fdata = $validator->form_val;
    
    // validate data
    if(empty($fdata['pc_csv_imp_row_colnum']) && empty($fdata['pc_csv_imp_dest_field'])) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> 'Please define the data assignment',
            'back_to_upload' => false,
        )));
    }
    if(count((array)$fdata['pc_csv_imp_row_colnum']) != count((array)$fdata['pc_csv_imp_dest_field'])) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> 'Columns number does not match with assigned fields',
            'back_to_upload' => false,
        )));
    }
     
    
    if($fdata['pc_csv_imp_ignore_first_row']) {
        unset($csv_data[0]);
    }
    
    $form_fw = new pc_form;  
    $auto_psw = ($fdata['pc_csv_imp_create_new_psw']) ? true : false;
    
    // PC-FILTER - define which privateContent fields (registered through pc_form_fields_filter) must not be included in the CSV import assignment form
    $ignore_fields = apply_filters('pc_csv_import_ignored_fields', array());
    $ignore_fields = array_merge($ignore_fields, array('categories', 'pc_disclaimer'));
    if($auto_psw) {
        $ignore_fields[] = 'psw';
    }
    
    $data_assign = array();    
    foreach($fdata['pc_csv_imp_row_colnum'] as $col_num) {
        $assign = $fdata['pc_csv_imp_dest_field'][ (int)$col_num ];
        
        if(empty($assign)) {
            continue;
        }
        if(!isset($form_fw->fields[$assign]) || in_array($assign, $ignore_fields)) {
            wp_die(json_encode(array(
                'status' => 'error',
                'message'=> '<strong>'. $assign .'</strong> - '. esc_html__('Unknown or forbidden field', 'pc_ml'),
                'back_to_upload' => false,
            ))); 
        }
        if(in_array($assign, $data_assign)) {
            wp_die(json_encode(array(
                'status' => 'error',
                'message'=> '<strong>'. $form_fw->fields[$assign]['label'] .'</strong> - '. esc_html__('another column has been already associated with this field', 'pc_ml'),
                'back_to_upload' => false,
            ))); 
        }
        
        $data_assign[ (int)$col_num ] = $assign;
    }
    
    if(!in_array('username', $data_assign)) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> esc_html__('Username field assignment is mandatory', 'pc_ml'),
            'back_to_upload' => false,
        ))); 
    }
    if($form_fw->mail_is_required && !in_array('email', $data_assign)) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> esc_html__('E-mail field assignment is mandatory', 'pc_ml'),
            'back_to_upload' => false,
        ))); 
    }
    if(!$auto_psw && !in_array('psw', $data_assign)) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> esc_html__('Password field assignment is mandatory', 'pc_ml'),
            'back_to_upload' => false,
        ))); 
    }
    
    $response = $uie->csv_import($csv_data, $data_assign, $fdata, $form_fw);
    wp_die(json_encode($response));
}
add_action('wp_ajax_pvtcont_csv_import', 'pvtcont_csv_import');





///////////////////////////////////////////////////
/// ENGINE IMPORT - UPLOAD AND CHECK JSON /////////
///////////////////////////////////////////////////

function pvtcont_engine_import_json_upload() {
    if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
		)));	
    }
    if(!current_user_can('manage_options')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to use this plugin section',
		)));    
    }
    
    include_once(PC_DIR .'/classes/engine_import_export.php');
    $eie = new pvtcont_engine_import_export;
    
    $contents = $eie->validate_import_file();
    if(!is_array($contents)) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> $contents,
		)));
    }
    
    $found_subjs_table = '
    <table class="widefat pc_imp_exp_table pc_engine_ie_table">
        <tbody>';
    
        foreach($eie->engine_ei_structure as $prod_name => $subjs) {
            $prod_subjs_involved = array();
            
            $prod_block = '
            <tr>
                <th>'. $prod_name .'</th>
                <td>
                    <ul class="pc_engine_ie_optslist">';

                    foreach($subjs as $subj_id => $subj_name) {
                        if(!isset($contents['subjs'][$subj_id])) {
                            continue;
                        }
                        $prod_subjs_involved[] = $subj_id;

                        $prod_block .= '
                        <li>
                            <span>'. $subj_name .'</span>
                            <input type="hidden" name="pc_engine_import_subj[]" value="'. esc_attr($subj_id) .'" />
                            
                            <select name="pc_engine_import_composition[]" class="pc_lc_select" autocomplete="off">
                                <option value="skip">'. esc_html__('do not import', 'pc_ml') .'</option>';
                                
                                if(in_array($subj_id, $eie->subjs_w_override_confirm)) {
                                    $prod_block .= '
                                    <option value="import">'. esc_html__('import - skip elements matching existing ones', 'pc_ml') .'</option>
                                    <option value="import_n_override">'. esc_html__('import - override elements matching existing ones', 'pc_ml') .'</option>';
                                }
                                else {
                                    $prod_block .= '
                                    <option value="import">'. esc_html__('Import', 'pc_ml') .'</option>'; 
                                }
                        
                        $prod_block .= '
                            </select>
                            <div class="pc_engine_import_report" data-subj-id="'. esc_attr($subj_id) .'"></div>
                        </li>';
                    }

            $prod_block .= '
                    </ul>
                </td>
            </tr>';
            
            if(!empty($prod_subjs_involved)) {
                $found_subjs_table .= $prod_block;   
            }
        }
    
    $found_subjs_table .= '
        </tbody>
    </table>';
    
    
    wp_die(json_encode(array(
        'status' => 'success',
        'found_subjs_table' => $found_subjs_table
    ))); 
}
add_action('wp_ajax_pvtcont_engine_import_json_upload', 'pvtcont_engine_import_json_upload');





///////////////////////////////////////////////////
/// PC ENGINE IMPORT - PROCEED ////////////////////
///////////////////////////////////////////////////

function pvtcont_engine_import() {
    if(!isset($_POST['pc_nonce']) || !pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'Cheating?',
            'back_to_upload' => false,
		)));	
    }
    if(!current_user_can('manage_options')) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> 'You do not have rights to use this plugin section',
            'back_to_upload' => false,
		)));    
    }
    
    include_once(PC_DIR .'/classes/simple_form_validator.php');
    include_once(PC_DIR .'/classes/engine_import_export.php');
    $eie = new pvtcont_engine_import_export;
    
    $contents = $eie->validate_import_file();
    if(!is_array($contents)) {
        wp_die(json_encode(array(
			'status' => 'error',
			'message'=> $contents,
		)));
    }
    
	$validator = new simple_fv('pc_ml');		
	$indexes = array();
	$indexes[] = array('index'=>'pc_engine_import_subj', 'label'=>'import subject');
	$indexes[] = array('index'=>'pc_engine_import_composition', 'label'=>'import composition');
    
    $validator->formHandle($indexes);
	$fdata = $validator->form_val;
    
    // validate data
    if(empty($fdata['pc_engine_import_subj']) && empty($fdata['pc_engine_import_composition'])) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> 'No import subjects chosen',
            'back_to_upload' => false,
        )));
    }
    else {
        if(count((array)$fdata['pc_engine_import_subj']) != count((array)$fdata['pc_engine_import_composition'])) {
            wp_die(json_encode(array(
                'status' => 'error',
                'message'=> 'Subjects and actions count do not match',
                'back_to_upload' => false,
            )));
        }
    }
    
    
    $to_import = array();
    $to_override = array();
    $avail_subjs = $eie->get_subjs();
    
    foreach($fdata['pc_engine_import_subj'] as $key => $subj_id) {
        if(!isset($avail_subjs[$subj_id])) {
            continue;   
        }
        $action = $fdata['pc_engine_import_composition'][$key];
        
        if($action == 'import') {
            $to_import[] = $subj_id;
        }
        elseif($action == 'import_n_override') {
            $to_import[] = $subj_id;
            $to_override[] = $subj_id;
        }
    }
    
    
    if(empty($to_import)) {
        wp_die(json_encode(array(
            'status' => 'error',
            'message'=> esc_html__('Please select something to import', 'pc_ml'),
            'back_to_upload' => false,
        )));
    }
    
    $response = $eie->import($contents, $to_import, $to_override);
    wp_die(json_encode($response));
}
add_action('wp_ajax_pvtcont_engine_import', 'pvtcont_engine_import');