<?php
// DATABASE TABLE CREATION AND MAINTENANCE
//// ACTIONS PERFORMED ON PLUGINS ACTIVATION
if(!defined('ABSPATH')) {exit;}


require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
global $wpdb, $pc_users;


// main vars to perform actions
$db_version = 7.0;
$curr_vers = (float)get_option('pg_db_version', 0);

$PC_USERS_TABLE = $wpdb->prefix . "pc_users";
$PC_META_TABLE 	= $wpdb->prefix . "pc_user_meta";


/*** prior check - switch to v5 renaming pg table to pc ***/
if($curr_vers < 5 || isset($_GET['pc_update_db_v5'])) {
	$wpdb->query("SHOW TABLES LIKE '". esc_sql($wpdb->prefix) ."pg_users'");
    
	if($wpdb->num_rows) {
		$wpdb->query(
            $wpdb->prepare(
				"RENAME TABLE ". esc_sql($wpdb->prefix) ."pg_users TO %s",
				$PC_USERS_TABLE
			) 
        );	
	}
}




/*** manage main users table ***/
// check DB table existence
$wpdb->query("SHOW TABLES LIKE '". esc_sql($PC_USERS_TABLE) ."'");

// add or update DB table
if(!$wpdb->num_rows || !$curr_vers || $curr_vers < 5 || $curr_vers < $db_version || isset($_GET['pvtcont_db_check'])) {
	$sql = "
	CREATE TABLE ".$PC_USERS_TABLE." (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		insert_date datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
		name VARCHAR(150) DEFAULT '' NOT NULL,
		surname VARCHAR(150) DEFAULT '' NOT NULL,
		username VARCHAR(150) NOT NULL,
		psw text NOT NULL,
		categories text NOT NULL,
		email VARCHAR(255) NOT NULL,
		tel VARCHAR(20) NOT NULL,
		page_id int(11) UNSIGNED NOT NULL,
		wp_user_id mediumint(9) UNSIGNED NOT NULL,
		disable_pvt_page smallint(1) UNSIGNED NOT NULL,
		last_access datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
		status smallint(1) UNSIGNED NOT NULL,".
		
		"
		PRIMARY KEY (id),
		UNIQUE KEY pc_unique_keys (page_id, wp_user_id),
		INDEX pc_indexes (page_id, wp_user_id)
	) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;";
    
	dbDelta($sql);
}




/*** manage users meta table ***/
// check DB table existence
$wpdb->query("SHOW TABLES LIKE '". esc_sql($PC_META_TABLE) ."'");

// add or update DB table
if(!$wpdb->num_rows || !$curr_vers || $curr_vers < 5 || $curr_vers < $db_version || isset($_GET['pvtcont_db_check'])) {
	$sql = "
	CREATE TABLE ".$PC_META_TABLE." (
		meta_id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id mediumint(9) UNSIGNED NOT NULL,
		meta_key VARCHAR(255) DEFAULT '' NOT NULL,
		meta_value longtext DEFAULT '' NOT NULL,".
		
		"
		PRIMARY KEY (meta_id),
		INDEX pcum_indexes (user_id)
	) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;";

	dbDelta($sql);
}




/*** actions to perform if updating from old version ***/
// from 4.x versions
if($curr_vers < 5) {
	include_once(PC_DIR . '/classes/users_manag.php');

	/*** delete users having status == 0 - since v5.0 ***/
	$users = $wpdb->get_results("SELECT id FROM ". esc_sql($PC_USERS_TABLE) ." WHERE status = 0"); 
	foreach($users as $user) {
		$pc_users->delete_user($user->id);	
	}
	$wpdb->delete($PC_USERS_TABLE, array('status' => 0));
	
	/*** register first registration form in new format - since v5.0 ***/
	pc_reg_form_ct(); // init taxonomy
	$default = array(
		'include'=>array('username', 'psw'), 'require'=>array('username', 'psw')
	);
	$args = array(
		'description' => base64_encode(serialize(get_option('pg_registration_form', $default)))
	);
	$result = wp_insert_term('First form', 'pc_reg_form', $args);	
}	
	
	
	
	
// before 5.04 - store passwords as non-reversible hasings but without mcrypt
if($curr_vers < 5.04) {	
	$users = $wpdb->get_results("SELECT id, username, psw FROM ". esc_sql($PC_USERS_TABLE)); 
	foreach($users as $user) {
		
		// decrypt basing on version
		if($curr_vers < 5.0 || !function_exists('mcrypt_decrypt')) {
			$psw = base64_decode($user->psw);	
		} else {
			$key = strtolower($user->username);
			$psw = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5(sha1($key.'lcweb')), base64_decode($user->psw), MCRYPT_MODE_CBC, md5(md5($key))), "\0");	
		}
		
		$wpdb->update($PC_USERS_TABLE, array('psw' => $pc_users->encrypt_psw($psw)), array('id' => $user->id));	
	}
}



// before 5.11 - remove check_psw meta 
if($curr_vers < 5.11) {	
	$wpdb->delete($PC_META_TABLE, array('meta_key' => 'check_psw'));
}



// before 6.0 - create one lightbox only for warning box login
if($curr_vers < 6) {	
	
	// init taxonomy 
	pc_lightboxes_ct();
	
	$result = wp_insert_term( esc_html__('Warning box login', 'pc_ml'), 'pc_lightboxes', array(
		'description' => base64_encode('[pc-login-form]')
	));	
	
	if(!is_wp_error($result)) {
		$term_id = $result['term_id'];
		
		// if there was js login in v5 - set newly created lightbox as login
		if(get_option('pg_js_inline_login')) {
			update_option('pg_warn_box_login', $term_id, false);	
		}
	}
}



// before 7.0 - hash every password with WordPress algorithm
if($curr_vers < 7.0 || isset($_GET['pc_update_db_v7'])) {	

	$users = $wpdb->get_results("SELECT id, psw FROM ". esc_sql($PC_USERS_TABLE)); 
	foreach($users as $user) {
		
		// be sure it hasn't been converted yet (in case or further trials)
		if(base64_encode(base64_decode($user->psw)) !== $user->psw) {
			continue;	
		}
	
		// update
		$resp = $wpdb->update($PC_USERS_TABLE, array(
			'psw' => $pc_users->encrypt_psw( $pc_users->decrypt_psw( $user->psw ))
		), array(
			'id' => $user->id
		));	
	}
}


update_option('pg_db_version', $db_version, false);