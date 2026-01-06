<?php
// TOOLSET TO SYNC PVTCONTENT UESRS WITH WP ONES
if(!defined('ABSPATH')) {exit;}


include_once('users_manag.php');
class pc_wp_user extends pc_users {
	
	public $forbidden_roles = array('pvtcontent', 'administrator', 'editor', 'author', 'pvtcontent_admin'); // forbidden custom roles to be applied to synced users
	public $wps_roles = false; // custom roles to apply to synced WP users

	public $pwu_sync_error = false; // sync WordPress error

	
	/* Global sync - for every user */
	public function global_sync() {
		global $wpdb, $pc_users;
		
		// disable debug
		$GLOBALS['pvtcont_disable_debug'] = true;
		
		$user_query = $wpdb->get_results("SELECT username, psw, email, name, surname FROM ". esc_sql(PC_USERS_TABLE) ." WHERE status != 0 AND wp_user_id = 0", ARRAY_A);
                
		if(!is_array($user_query) || count($user_query) == 0) {
            return esc_html__('All users already synced', 'pc_ml');
        }
		
		$not_synced = 0;
		$synced = 0;
		foreach($user_query as $ud) {
			if(empty($ud['email'])) {
                $not_synced++;
            }
			else {
				$result	= $this->sync_wp_user($ud, 0, true); 
				
				if(!filter_var($result, FILTER_VALIDATE_INT)) {
                    $not_synced++
                        ;}
				else {
                    $synced++;
                }
			}
		}	
		
		$ns_mess = ($not_synced > 0) ? ' <em>('.$not_synced.' '.esc_html__("can't be synced because of their username or e-mail", 'pc_ml').')</em>' : '';
		return $synced.' '. esc_html__('Users synced successfully!', 'pc_ml') . $ns_mess;
	}
	
	
	/* 
	 * Sync a pvtContent user with a WP one (add or update)
	 *
	 * @param (array) $user_data - associative array containing data to use for new WP user. Indexes: username (required if not updating), email (required), psw (required if not updating), name, surname
	 * @param (int) $existing_id = WP user id to be updated
	 * @param (bool) $save_in_db = whether to save the created WP user id in pvtContent database 
	 *
	 * @return (bool/int) the created/updated user ID or false
	 */
	public function sync_wp_user($user_data = array(), $existing_id = 0, $save_in_db = false) {
		if(empty($existing_id)) {
			if(!isset($user_data['username'])) {
				$this->debug_note('WP-sync - username is mandatory to sync with WP user');
				return false;
			}
			if(!isset($user_data['email'])) {
				$this->debug_note('WP-sync - e-mail is mandatory to sync with WP user');
				return false;
			}
			if(!isset($user_data['psw'])) {
				$this->debug_note('WP-sync - password is mandatory to sync with WP user');
				return false;
			}
		}
		
		/* args composition */
		$args = array('role' => 'pvtcontent');
		if(empty($existing_id)) {
			$args['user_login'] = $user_data['username'];
			$args['user_email'] = $user_data['email'];
		}
		else {
			if(isset($user_data['email'])) 	{$args['user_email'] = $user_data['email'];}
		}
		
		if(isset($user_data['psw']) && empty($existing_id)) {$args['user_pass'] = wp_generate_password(8, true);} // use a fake one, password will be synced later
		if(isset($user_data['name'])) 		{$args['first_name'] = $user_data['name'];}
		if(isset($user_data['surname'])) 	{$args['last_name'] = $user_data['surname'];}
		
		// update user
		if(!empty($existing_id)) {
			add_filter('send_password_change_email', '__return_false', 999999);
			add_filter('send_email_change_email', '__return_false', 9999999);
			
			$GLOBALS['pvtcont_is_updating_wp_user'] = true;
			$args['ID'] = $existing_id;
			$wp_user_id = wp_update_user($args);
			unset($GLOBALS['pvtcont_is_updating_wp_user']);
		}
		else {
			$GLOBALS['pvtcont_wp_user_register'] = true;
			$wp_user_id = wp_insert_user($args);
		}


		if(is_wp_error($wp_user_id) ) {
			$this->pwu_sync_error = $wp_user_id->get_error_message();
			$this->debug_note('WP-sync - '. $this->pwu_sync_error );
			return false;
		}
		else {
			$this->wp_sync_error = false;

			// if not updating - add record in pvtcontent DB
			if(!$existing_id && $save_in_db) {
				global $wpdb;
				$wpdb->query( 
					$wpdb->prepare( 
						"UPDATE ". esc_sql(PC_USERS_TABLE) ." SET wp_user_id = %d WHERE username = %s AND status != 0",
						$wp_user_id,
						$user_data['username']
					) 
				);	
			}
            
            if(!$existing_id) {
				$this->set_wps_custom_roles($wp_user_id, true);
				
				// PC-ACTION - pvtcontent user has been synced with WP user
				do_action('pc_user_synced_with_wp', $user_data['username'], $wp_user_id);
			}
			else {
				// specific user roles application
				$this->set_wps_custom_roles($wp_user_id);
			}
			
			// PC v7 - update psw using already encrypted one
			if(isset($user_data['psw'])) {
				$this->sync_psw_to_wp($user_data['psw'], $wp_user_id);
			}
					
			return $wp_user_id;
		}	
	}
	
	
	
	/*
	 * Sync password from pvtContent to WP - since PV v7 hashes systems are the same
	 * 
	 * @param (string) $psw - hashed password
	 * @param (int) $wp_user_id - wordpress user ID
	 *
	 * @return (mixed) wpdb operation response
	 */
	public function sync_psw_to_wp($psw, $wp_user_id) {
		global $wpdb;
		
		return $wpdb->update(
			$wpdb->users, 
			array('user_pass' 	=> $psw), 
			array('ID' 			=> $wp_user_id)
		);	
	}
	
    
    
    /*
	 * Sync password from WP user to pvtContent
	 * 
	 * @param (int) $wp_user_id - wordpress user ID
     * @param (int) $pc_user_id - pvtcontent user ID
	 *
	 * @return (bool)
	 */
	public function sync_psw_to_pc($wp_user_id, $pc_user_id) {
		global $wpdb, $pc_users;
		
        $new_psw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_pass FROM ". esc_sql($wpdb->users) ." WHERE ID = %d",
                absint($wp_user_id)
            )
        );
        if(empty($new_psw)) {
            return false;    
        }
        
        $response = $wpdb->update(
            PC_USERS_TABLE, 
            array('psw'=> $new_psw), 
            array('id' => absint($pc_user_id))
        );
        
        if(!$response) {
            return false;    
        }
        
        // user is logged? update cookie!
        if(isset($GLOBALS['pc_user_id']) && $GLOBALS['pc_user_id'] == $pc_user_id) {
            $remember_me = (isset($_COOKIE['pc_remember_login'])) ? true : false;
            $cookie_data = $pc_user_id .'|||'. $new_psw;
            
            pc_static::setcookie('pc_user', $cookie_data, pc_static::login_cookie_duration($remember_me));
        }

		return true;
	}
    
    
	
	/* Search existing pvtContent -> WP matches and sync */
	public function search_and_sync_matches() {
		global $wpdb;
		
		$user_query = $wpdb->get_results("SELECT username, psw, email, name, surname FROM ". esc_sql(PC_USERS_TABLE) ." WHERE status != 0 AND wp_user_id = 0 AND email != ''");
        
        if(!is_array($user_query) || count($user_query) == 0) {
            return esc_html__('All users already synced', 'pc_ml');
        }
		
		$synced = 0;
		foreach($user_query as $ud) {
			$existing_username = username_exists($ud->username);
			$existing_mail = email_exists($ud->email);
				
			if($existing_username && $existing_username == $existing_mail) {
				add_filter('send_password_change_email', '__return_false', 999);
				add_filter('send_email_change_email', '__return_false', 999);
				
				$userdata = array(
					'ID' 			=> $existing_username,
					'user_pass'		=> wp_generate_password(8, true),
					'first_name'	=> $ud->name,
					'last_name'		=> $ud->surname,
					'role'			=> 'pvtcontent'
				);	
				$wp_user_id = wp_update_user($userdata);
				
				// successful sync
				if(filter_var($wp_user_id, FILTER_VALIDATE_INT)) {
					$synced++;
				
					// PC v7 - update psw using already encrypted one
					$this->sync_psw_to_wp($ud->psw, $wp_user_id);
					
					// store synced user ID
					$wpdb->query( 
						$wpdb->prepare( 
							"UPDATE ". esc_sql(PC_USERS_TABLE) ." SET wp_user_id = %d WHERE username = %s AND status != 0",
							$wp_user_id,
							$ud->username
						) 
					);	
					
					$this->set_wps_custom_roles($wp_user_id);
				}
			}
		}	
		
		return $synced .' '. esc_html__('matches found and syncs performed', 'pc_ml');
	}
	
	
	
	/* 
	 * Creates a pvtContent user starting from a WP one
	 *
	 * @param (int) $wp_user_id
	 * @param (array) $pc_cats = privatecontent categories ID to be assigned to newly created user
	 *
	 * @return (obj/int) the created user ID or a WP error 
	 */
	public function pc_user_from_wp($wp_user_id, $pc_cats) {
		global $wpdb;
		$user_data = new WP_User($wp_user_id);
		
		// prior checks
    	if(empty($user_data->roles) || !is_array($user_data->roles)) {
			return new WP_Error('not found', esc_html__("User not found", 'pc_ml'));
		}
		elseif(in_array('administrator', $user_data->roles)) { // admins can't be synced!
			return new WP_Error('is admin', esc_html__("Administrators can't be synced", 'pc_ml'));
		}
        elseif(in_array('pvtcontent_admin', $user_data->roles)) { // PC admins can't be synced!
			return new WP_Error('is pvtcontent admin', esc_html__("PvtContent admins can't be synced", 'pc_ml'));
		}
        	
		// check if user is already sinced 
		if($this->wp_user_is_linked($wp_user_id)) {
			return new WP_Error('already synced', esc_html__("User is already synced", 'pc_ml'));
		}
		
		
		// check whether a PC user with this username/email already exists
		$args = array(
			'limit'  			=> 1,
			'count'				=> true,
			'search' 			=> array(
                array(
                    'relation' => 'OR',
                    array('key' => 'username', 'operator' => '=', 'val' => $user_data->user_login),
                    array('key' => 'email',    'operator' => '=', 'val' => $user_data->user_email),
                ),
			)
		);
		if($this->get_users($args)) {
			return new WP_Error('username or mail exists', esc_html__("Another user has this username or e-mail", 'pc_ml'));		
		}
		
		
		// be sure PC cats exists
		if(empty($pc_cats) || !is_array($pc_cats)) {
			return new WP_Error('no cats', esc_html__("At least one category must be specified", 'pc_ml'));	
		}
		
		foreach($pc_cats as $cat_id) {
			$te = term_exists((int)$cat_id, 'pg_user_categories');
			if(empty($te)) {
                /* translators: 1: category ID. */
				return new WP_Error('cat not exists', sprintf( esc_html__("Category %s doesn't exist", 'pc_ml'), $cat_id));		
			}
		}
		
		
		// prepare to insert
		$psw = wp_generate_password(8, true);
		$data = array(
			'name' 				=> get_user_meta($wp_user_id, 'first_name', true),
			'surname' 			=> get_user_meta($wp_user_id, 'last_name', true),
			'username'			=> $user_data->user_login,
			'tel'				=> '',
			'email'				=> $user_data->user_email, 
			'psw'				=> $psw,
			'check_psw'			=> $psw,
			'disable_pvt_page' 	=> 0,
			'categories' 		=> $pc_cats 
		);
		$pc_user_id = $this->insert_user($data, $status = 1, $allow_wp_sync_fail = true);
		
		if(!$pc_user_id) {
			return new WP_Error('user insert error', $this->validation_errors);		
		}
		
		
		// user added - hardcode password and WP synced ID
		$wpdb->update(
			PC_USERS_TABLE, 
			array(
				'psw' 			=> $user_data->user_pass,
				'wp_user_id'	=> $wp_user_id
			), 
			array('id' => $pc_user_id)
		);	
		
		$this->set_wps_custom_roles($wp_user_id);
		
		
		// PC-ACTION - pvtcontent user created from WP user
		do_action('pc_user_created_from_wp', $pc_user_id, $wp_user_id);
		
		return $pc_user_id;
	}
	
	
	
	/* Global detach */
	public function global_detach() {
		global $wpdb;
		$user_query = $wpdb->get_results("SELECT id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE wp_user_id != 0 AND status != 0");
        
		if(!is_array($user_query) || count($user_query) == 0) {
            return esc_html__('All users already detached', 'pc_ml');
        }
		
		foreach($user_query as $ud) {
			$result	= $this->detach_wp_user($ud->id);
		}	
		
		return esc_html__('Users detached successfully!', 'pc_ml');
	}
	
	
	/* 
	 * Detach a pvtContent user with related WP one and delete it
	 * (int) $user_id = privatecontent user id
	 * (bool) $save_in_db = whether update sync record in pvtContent database 
	 * (int) $wp_user_id = used to pass directly synced wp user ID to delete
	 */
	public function detach_wp_user($user_id, $save_in_db = true, $wp_user_id = false) {
		if(!$wp_user_id) {
			$wp_user_id = $this->get_user_field($user_id, 'wp_user_id');
		}
		if(empty($wp_user_id)) {
            return true;
        }
		
		// PC-ACTION - pvtcontent user is being detached from WP user. Used right before WP user deletion
		do_action('pc_user_detached_from_wp', $user_id, $wp_user_id);
		
		
		// be sure user functions exist
		if(!function_exists('wp_delete_user')) {
			return false;
		}
		
		
		// take care of WP multisite
		if(is_multisite()) {
			wpmu_delete_user($wp_user_id);
		} else {
			wp_delete_user($wp_user_id, false);	
		}
		
		if($save_in_db) {
			global $wpdb;
			$wpdb->query( 
				$wpdb->prepare( 
					"UPDATE ". esc_sql(PC_USERS_TABLE) ." SET wp_user_id = 0 WHERE id = %d AND status != 0",
					$user_id
				) 
			);			
		}
		return true;
	}
	
	
	
	/* 
	 * Check if a wp user is linked to a pvtcontent user
	 *
	 * @param (int) $wp_user_id = wordpress user id
	 * @return (bool/obj) false if user is not synced otherwise the query object
	 */
	public function wp_user_is_linked($wp_user_id) {
		global $wpdb;
		if(empty($wp_user_id)) {
            return false;
        }
		
		$user_data = $wpdb->get_row( 
			$wpdb->prepare(
				"SELECT id, username, categories, psw, status FROM ". esc_sql(PC_USERS_TABLE) ." WHERE wp_user_id = %d LIMIT 1",
				$wp_user_id
			) 
		);
		return $user_data;
	}
	
	
	
	/* 
	 * Check whether a pvtcontent user is synced 
	 * @param (int) $user_id = privatecontent user id
	 * @params (bool) $return_id = whether to return found WP user id directly
	 * @return (bool/obj) false if not synced, otherwise the synced user data (or the direct WP user ID, if parameter says so)
	 */
	public function pvtc_is_synced($user_id, $return_id = false) {
		global $wpdb;
		if(empty($user_id)) {
            return false;
        }
		
		$user = $wpdb->get_row( 
			$wpdb->prepare(
				"SELECT wp_user_id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE id = %d AND status != 0 LIMIT 1",
				$user_id
			) 
		);

		$exists = get_userdata($user->wp_user_id);
		if($return_id) {
			return (!$exists) ? false : $exists->ID;
		} else {
			return (!$exists) ? false : $exists;
		}
	}
	
	
	
	/*
	 * Manually logs a WP user (used by api and cookie login)
	 *
	 * @param (int) $wp_user_id = wordpress user id
	 * @param (string) $username
	 * @param (bool) $remember_me = whether to login the user with remember me instruction
	 */
	public function manual_user_login($wp_user_id, $username, $remember_me = false) {

		// if a user is already logged - unlog
		if(is_user_logged_in()) {
            if(get_current_user_id() == $wp_user_id) {
                return true;    
            }
            
			$GLOBALS['pvtcont_only_wp_logout'] = true;
			wp_destroy_current_session();
			wp_clear_auth_cookie();		
		}
		
		$GLOBALS['pvtcont_manual_wp_user_login'] = true;
		wp_set_current_user($wp_user_id, $username);
		wp_set_auth_cookie($wp_user_id, $remember_me);
		
		$user_data = get_userdata($wp_user_id);
		do_action('wp_login', $username, $user_data);	
	}
	
	
	
	/* Update WP user nicename */
	public function update_nicename($wp_user_id) {
		$ud = get_userdata($wp_user_id);
		
		$nicename = $ud->user_firstname .' '. $ud->user_lastname;
		if(empty($nicename)) {$nicename = $ud->user_login;}
		
		wp_update_user(array(
			'ID'=>$wp_user_id, 
			'user_nicename' => $nicename, 
			'display_name' => $nicename
		));
	}
	
	
	
	/* Check if new e-mail is ok for an existing WP user */
	public function new_mail_is_ok($wp_user_id, $email) {
		$exists = email_exists($email);
		return (!$exists || $exists == $wp_user_id) ? true : false;
	}
	
	
	
	/* Check whether username or password already exists for a WP user 
	 * @return (bool) true if exists, otherwise false
	 */
	public function wp_user_exists($username, $email) {
		return (!username_exists($username) && !email_exists($email)) ? false : true; 	
	}
	
	
	
	/* Get global custom roles assigned to synced WP users - set it also in $this->wps_roles
	 * @return (array) array of WP roles to assign
	 */
	public function get_wps_custom_roles() {
		if($this->wps_roles) {
            return $this->wps_roles;
        }
		
		$this->wps_roles = array_unique( array_merge(array('pvtcontent'), (array)get_option('pg_custom_wps_roles', array()) ));
		return $this->wps_roles;
	}
	
	
	/* Apply custom roles - performs a DB query replacing original values as serialized array
	 * @param (int/array) $user_id = single WP user id to update or multiple IDs - by default updates any synced user
	 * @param (bool) $is_new_user = if true, avoid update when role is only pvtcontent
	 *
	 * @return (bool) true if successful otherwise false 
	 */
	public function set_wps_custom_roles($wp_user_id = false, $is_new_user = false) {
		global $pc_meta;
        
        if(ISPCF) {
            return true;   
        }
        /* NFPCF */
        
		// get roles to be assigned to users
		$this->get_wps_custom_roles();	
		
		// be sure pvtContent role is in
		if(!in_array('pvtcontent', $this->wps_roles)) {
			$this->wps_roles = array_merge(array('pvtcontent'), $this->wps_roles);	
		}
		
		// single user - know which roles the user has got. If new user and only pvtcontent - do nothing
		if($wp_user_id) {
            $user_meta = get_userdata($wp_user_id);
            $user_roles = $user_meta->roles;

            if($is_new_user && $this->wps_roles === array('pvtcontent') && in_array('pvtcontent', (array)$user_roles)) {
                return false;	
            }
        }
		
        // update users-specific roles
		if(!$wp_user_id) {
			// get all synced users
			$users = $this->get_users(array(
				'limit'		=> -1,
				'search' 	=> array(
                    array(array('key'=>'wp_user_id', 'operator'=>'!=', 'val'=>0))
                ),
				'to_get' 	=> array('id', 'wp_user_id')
			));
			
			// build WP users ID array
			if(!count($users)) {
				return true;
			}
			
            $pc_users_id = array();
            $wp_users_id = array();
			
			foreach($users as $u) {
				$pc_users_id[] = $u['id']; 
                $wp_users_id[] = $u['wp_user_id'];	
			}
		}
		else{
            $pc_user_data = $this->wp_user_is_linked($wp_user_id);
            if(!$pc_user_data) {
                return false;    
            }
            
            $pc_users_id = array($pc_user_data->id);
			$wp_users_id = array($wp_user_id);	
		}
		
		
		//// setup roles array
		// consider user specific roles
		$to_return = true;
        
		foreach($pc_users_id as $index => $pc_uid) {
            $usr = $pc_meta->get_meta($pc_uid, 'specific_wp_roles');
            $roles_to_be_added = (!empty($usr) && is_array($usr)) ? $usr : $this->wps_roles; 

            $roles_array = array('pvtcontent' => true);

            foreach($roles_to_be_added as $role) {
                if($role && !isset($roles_array[$role]) && !in_array($role, $this->forbidden_roles)) {
                    $roles_array[$role] = true;
                }
            }

            // perform
            $result = $this->db->query( 
                $this->db->prepare( 
                    "UPDATE ". $this->db->prefix ."usermeta SET meta_value = %s WHERE meta_key = '". $this->db->prefix ."capabilities' AND user_id = %d",
                    serialize($roles_array),
                    $wp_users_id[$index]
                ) 
            );

            if($result === false) {
                $this->debug_note('Error updating WP user #'. $wp_users_id[$index] .' capabilities');	
                $to_return = false;
            }
        }
        
		return (!$to_return) ? false : true;
	}
}


$GLOBALS['pc_wp_user'] = new pc_wp_user;
