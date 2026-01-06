<?php
if(!defined('ABSPATH')) {exit;}


// CONTROL LOGIN, HIDE WP PVTCONTENT USERS AND TURN THEM AS EXTERNAL VISITORS ALSO IF LOGGED
// file called only if sync is active



// WP USERS SYNC initialization
add_action('init', function() {
	global $pc_users;
	
	if($pc_users->wp_user_sync) {
		add_role('pvtcontent', 'PrivateContent',
			array(
				'read'         => true,
				'edit_posts'   => false,
				'delete_posts' => false
			)
		);
	} else {
		remove_role('pvtcontent');
	}
}, 1);



// login control - check pvtContent user status
add_action('wp_login', function($user_login, $user) {
	global $wpdb, $pc_wp_user;
	
	// if login is performed by PvtContent - skip
	if(isset($GLOBALS['pvtcont_manual_wp_user_login'])) {
		return true;	
	}
	
	$user_data = $pc_wp_user->wp_user_is_linked($user->ID);
	
	if($user_data) {
		
		// PC-FILTER - custom login control for custom checks - passes false, user id - return message to abort login otherwise false
		$custom_check = apply_filters('pc_login_custom_check', false, $user_data->id);

		// check whether perform redirects - avoid on ajax forms submission
		if(
            defined('WC_DOING_AJAX') || 
			(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
			strpos(pc_static::curr_url(), '/admin-ajax.php') !== false	
		) {
			$is_ajax_call = true;	
		}
		else {
            $is_ajax_call = false;
        }
        
		
        // PC-FILTER - allow control over login before the status check due to user status - passes false, user id and status - return message to abort login otherwise false
        $pre_status_check = apply_filters('pc_pre_status_login_check', false, $user_data->id, $user_data->status);
        if($pre_status_check !== false) {
            
            // Clear cookies -> log user out
			wp_clear_auth_cookie();
            
            $GLOBALS['pvtcont_wp_login_error'] = array(
                'reason'    => 'custom',
                'message'   => $pre_status_check,
            );
            
            if(!$is_ajax_call) {
                $login_url = add_query_arg('pc_wp_login_cust_err', base64_encode(urlencode($pre_status_check)), $login_url);
                wp_redirect($login_url);
                exit;
            }
        }
         
        
		//// check status
		// login failed
		if((int)$user_data->status !== 1 || $custom_check !== false) {
			// Clear cookies -> log user out
			wp_clear_auth_cookie();
			
            // redirect adding disabling parameter - for PC login message management
            $login_url = site_url('wp-login.php', 'login');

            if(!$custom_check) {
                $GLOBALS['pvtcont_wp_login_error'] = array(
                    'reason'    => 'disabled',
                    'status'    => $user_data->status,
                );

                $login_url = add_query_arg('pc_wp_login_status_err', $user_data->status, $login_url);
            } 
            else {
                $GLOBALS['pvtcont_wp_login_error'] = array(
                    'reason'    => 'custom',
                    'message'   => $custom_check,
                );

                $login_url = add_query_arg('pc_wp_login_cust_err', base64_encode(urlencode($custom_check)), $login_url);	
            }
			
            if(!$is_ajax_call) {
				wp_redirect($login_url);
				exit;
			}
		}
		
		// login ok
		else {

			//// login in pvtContent	
			// setup user session cookie and global
			$GLOBALS['pc_user_id'] = $user_data->id;
            
			// set cookie
            $remember_me = (isset($_POST['rememberme'])) ? true : false;
            $cookie_expir_time = pc_static::login_cookie_duration($remember_me);
			pc_static::setcookie('pc_user', $user_data->id.'|||'.$user_data->psw, $cookie_expir_time);
			
            // user session token check?
            if(get_option('pg_use_session_token') && class_exists('pc_session_token')) {
                $pc_sess_tok = new pc_session_token($user_data->id);
                $pc_sess_tok->setup_session_token($cookie_expir_time);
            }
            
			// update last login date
			$wpdb->update(PC_USERS_TABLE, array('last_access' => current_time('mysql')), array('id' => $user_data->id));
            do_action('pc_user_login', $user_data->id, $remember_me);
            
			
			//// redirect after login
			if(!$is_ajax_call) {

				// pvtContent login redirect
				$pc_login_redirect = pc_man_redirects('pg_logged_user_redirect', $GLOBALS['pc_user_id']);
				
                if(get_option('pg_redirect_back_after_login') && isset($_COOKIE['pc_last_restricted_page']) && filter_var($_COOKIE['pc_last_restricted_page'], FILTER_VALIDATE_URL)) {
                    $redirect_url = esc_url($_COOKIE['pc_last_restricted_page']);
                    pc_static::setcookie('pc_last_restricted_page', '', -1);  
                }
				elseif($pc_login_redirect) {
					$redirect_url = $pc_login_redirect;		
				}
				elseif(isset($_REQUEST['redirect_to']) && filter_var($_REQUEST['redirect_to'], FILTER_VALIDATE_URL)) {
					$redirect_url = sanitize_text_field($_REQUEST['redirect_to']);	
				}	
				else {
                    $redirect_url = pc_static::curr_url();
                }	
				
				wp_redirect($redirect_url);
				exit;
			}
		}
	}
}, 10, 999);



// login message management
add_filter('authenticate', function($user, $username, $password) {
    
	if(isset($_GET['pc_wp_login_status_err']) && ((int)$_GET['pc_wp_login_status_err'] === 3 || (int)$_GET['pc_wp_login_status_err'] == 2)) {
		$error = new WP_Error();
		$error->add('pc_login_error', pc_get_message('pc_default_pu_mex')); // pending user message
        
        $GLOBALS['pvtcont_wp_login_mess_set'] = true;
		return $error;
	}
	
	elseif(isset($_GET['pc_wp_login_cust_err'])) {
		$error = new WP_Error();
		$error->add('pc_login_error', urldecode(base64_decode(pc_static::sanitize_val($_GET['pc_wp_login_cust_err'])))); // custom errors
        
        $GLOBALS['pvtcont_wp_login_mess_set'] = true;
		return $error;
	}
	
    
    if(isset($GLOBALS['pvtcont_wp_login_error'])) {
        global $pvtcont_wp_login_error;
        $error = new WP_Error();

        if($pvtcont_wp_login_error['reason'] == 'disabled' && ($pvtcont_wp_login_error['status'] === 3 || $pvtcont_wp_login_error['status'] == 2)) {	
            $error->add('pc_login_error', pc_get_message('pc_default_pu_mex'));
            
            $GLOBALS['pvtcont_wp_login_mess_set'] = true;
            return $error;
        }
        elseif($pvtcont_wp_login_error['reason'] == 'custom') {	
            $error->add('pc_login_error', $pvtcont_wp_login_error['message']);
            
            $GLOBALS['pvtcont_wp_login_mess_set'] = true;
            return $error;
        }
    }

	return $user;
}, 999, 3);



// login message management for WooCommerce form
add_action('woocommerce_before_customer_login_form', function() {
    if(isset($GLOBALS['pvtcont_wp_login_mess_set'])) {
        return true;   
    }
    
    if(isset($_GET['pc_wp_login_status_err']) && ((int)$_GET['pc_wp_login_status_err'] === 3 || (int)$_GET['pc_wp_login_status_err'] == 2)) {
		wc_add_notice(pc_get_message('pc_default_pu_mex'), 'error');
        $GLOBALS['pvtcont_wp_login_mess_set'] = true;
	}
	
	elseif(isset($_GET['pc_wp_login_cust_err'])) {
		wc_add_notice( esc_html(urldecode(base64_decode(pc_static::sanitize_val($_GET['pc_wp_login_cust_err'])))), 'error');
        $GLOBALS['pvtcont_wp_login_mess_set'] = true;
	}
}, 1);



// if WP "remember me" is still active and pvtContent is not logged - re-log on PrivateContent (might happen using WP forms)
add_action('pvtcont_init', function() {
	global $pc_wp_user, $pc_users;
	
	if(isset($GLOBALS['pc_user_id'])) {
		return true;	
	}
	
	$user = wp_get_current_user();
	if(!$user->exists()) { // no user logged
        return true;
    } 
	
	if(isset($user->ID) && !empty($user->ID)) {
		$user_data = $pc_wp_user->wp_user_is_linked($user->ID);
		
		if($user_data) {
           
            // PC-FILTER - allow PvtContent "user login if synced WP user is logged" function abort. Passes PC user ID and WP user ID
            if(apply_filters('pc_abort_log_user_if_synced_is_logged', false, $user_data->id, $user->ID)) {
                return false;    
            }
            
            
			if(pc_user_logged(false)) { // also pvtContent user is logged
                return true;
            } 
			
			// synced user isn't active - logout WP user
			if($user_data->status !== 1) {
				pc_logout();			
			}
			
			// perform login
			else {
                $GLOBALS['pvtcont_encrypted_psw_login'] = true;
				pc_login($user_data->username, $user_data->psw);
                unset($GLOBALS['pvtcont_encrypted_psw_login']);
			}
		}
	}
}, 100);




// manage WP logout - if is linked to a pvtContent user
function pvtcont_wp_user_logout($user_id = false) { // param set only by "wp_logout" hook
    global $wpdb, $pc_wp_user;
	
	// if is updating user - avoid
	if(isset($GLOBALS['pvtcont_updating_user']) && $GLOBALS['pvtcont_updating_user']) {
        return false;
    }
	if(isset($GLOBALS['pvtcont_is_updating_wp_user']) && $GLOBALS['pvtcont_is_updating_wp_user']) {
        return false;
    }
	
    if(!$user_id) {
        $user = wp_get_current_user();
	
        if(!is_object($user) || !isset($user->ID) || empty($user->ID)) {
            return false;
        }
        $user_id = $user->ID;
    }
    $user_data = $pc_wp_user->wp_user_is_linked($user_id);

    if($user_data) {
        if(!isset($GLOBALS['pvtcont_only_wp_logout'])) {
            pc_logout();
        }

        // check if a redirect is needed
        if(!isset($GLOBALS['pvtcont_only_wp_logout']) && get_option('pg_logout_user_redirect')) {
            $redirect_url = pc_man_redirects('pg_logout_user_redirect');
            wp_redirect($redirect_url);
            exit;
        }
    }
}
add_action('clear_auth_cookie', 'pvtcont_wp_user_logout', 1);
add_action('wp_logout', 'pvtcont_wp_user_logout', 1);



//////////////////////////////////////////////////////////////////////////



// disable admin bar
add_action('pvtcont_init', function() {
	if(current_user_can('pvtcontent') && isset($GLOBALS['pc_user_id'])) {	
	
		show_admin_bar(false);
		add_filter('show_admin_bar', '__return_false', 99999); 
	}
}, 2);



// avoid pvtcontent users to go into default WP dashboard
add_action('admin_enqueue_scripts', function() {
	if(is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
		global $current_user;
		if(isset($current_user) && isset($current_user->caps) && isset($current_user->caps['pvtcontent']) && $current_user->caps['pvtcontent']) {
			ob_start();
			ob_clean();
            
            wp_safe_redirect(site_url());
		}
	}
}, 1);



//////////////////////////////////////////////////////////////////////////



// track synced user edit - edit main fields also on pvtContent user
add_action('profile_update', function($user_id, $old_user_data) {
	global $wpdb, $pc_wp_user;
	
	// not if profile updated by pvtContent
	if(isset($GLOBALS['pvtcont_is_updating_wp_user'])) {
		return false;	
	}
	
	$synced_user_data = $pc_wp_user->wp_user_is_linked($user_id);
	if($synced_user_data) {
		
		$wp_user_data = get_userdata($user_id);
		$wp_user_meta = get_user_meta($user_id);
		
		$args = array(
			'username'	=> $wp_user_data->user_login, 
			'psw'		=> $wp_user_data->user_pass, 
			'email'		=> $wp_user_data->user_email, 
			'psw'		=> $wp_user_data->user_pass, 
			'name'		=> (string)$wp_user_meta['first_name'][0],
			'surname'	=> (string)$wp_user_meta['last_name'][0],
		);
		$response = $wpdb->update(PC_USERS_TABLE, $args, array('id' => $synced_user_data->id));

		// force pvtContent capabilities!
		$pc_wp_user->set_wps_custom_roles($user_id);
	}
}, 100, 2);



//////////////////////////////////////////////////////////////////////////



// Allow direct pvtContent sync in "edit user" page 
function pvtcont_wps_edit_user_pc_sync($user_data) {
	global $wpdb, $pc_wp_user;

	if($pc_wp_user->wp_user_is_linked($user_data->ID) || (isset($GLOBALS['pvtcont_cuc_edit']) && !current_user_can($GLOBALS['pvtcont_cuc_edit'])) ) {
		return false;	
	}
	
	// admins can't be synced
	if(in_array('administrator', $user_data->roles)) {
		return false;	
	}
	?>
	
    <div id="pc_wps_eus_wrap" class="form-field">
		<h2 id="pc_wps_eus_heading">PrivateContent - <?php esc_html_e('User Sync', 'pc_ml') ?></h2>
			
        <table class="form-table" id="pc_wps_eus_table"> 
           <tbody>
            <tr>
                <th>
                	<label><?php esc_html_e('User categories', 'pc_ml') ?></label>
                </th>
                <td>
                    <select id="pc_wps_eus_field" name="pc_wps_eus_cat[]" multiple="multiple" autocomplete="off">
                        <?php
						foreach(pc_static::user_cats() as $cat_id => $cat_name) {
							echo '<option value="'. absint($cat_id) .'">'. esc_html($cat_name) .'</option>';  
						}	
						?>
                    </select>
                </td>
            </tr>
            <tr>
            	<th>
                	<input type="button" class="button-secondary" name="pc_wps_eus_btn" value="<?php esc_attr_e('Sync user', 'pc_ml') ?>" />
                </th>
                <td id="pc_wps_eus_response"></td>
            </tr>
          </tbody>
        </table>
    </div>
	<?php
                            
    $inline_js = '
    (function($) { 
        "use strict";    

        let pc_wps_is_syncing = false;

        // sync
        $(document).on(`click`, `[name=pc_wps_eus_btn]`, function(e) {
            var cats = $(`#pc_wps_eus_field`).serialize();
            if(!cats || pc_wps_is_syncing) {
                return false;
            }

            if(confirm("'. esc_attr__("Do you really want to turn this user into a PrivateContent user? Won't be possible to have it back in future", 'pc_ml') .'")) {
                pc_wps_is_syncing = true;
                var $btn = $(this);

                $(`#pc_wps_eus_response`).empty();
                $btn.fadeTo(200, 0.7);		

                var data = `action=pvtcont_wp_to_pc_single_user_sync&pc_nonce='. esc_js(wp_create_nonce('lcwp_ajax')) .'&wp_user_id='. absint($user_data->ID) .'&`+ cats;

                $.post(ajaxurl, data, function(response) {
                    var resp = $.parseJSON(response);

                    if(resp.status == `success`) {
                        $(`#pc_wps_eus_response`).html("'. esc_attr__('User synced successfully!', 'pc_ml') .'");

                        setTimeout(function() {
                            window.location.href = `'. esc_js(admin_url()) .'admin.php?page=pc_user_dashboard&user=`+ resp.user_id;
                        }, 1000);
                    }
                    else {
                        pc_wps_is_syncing = false;
                        $btn.fadeTo(200, 1);	

                        $(`#pc_wps_eus_response`).html(resp.message);
                    }
                });	
            }
        });


        // init LC select for live elements
        $(document).ready(function() {
            new lc_select(`#pc_wps_eus_table select`, {
                wrap_width : `100%`,
                addit_classes : [`lcslt-lcwp`],
            });
        });
    })(jQuery);';
    wp_add_inline_script('lcwp_magpop', $inline_js);
}
add_action('show_user_profile', 'pvtcont_wps_edit_user_pc_sync', 999);
add_action('edit_user_profile', 'pvtcont_wps_edit_user_pc_sync', 999);




// Automatic sync for WP users registered in frontend
add_filter('registration_errors', function($errors, $sanitized_user_login, $user_email) {
	global $pc_users, $pc_wp_user;

	if($pc_wp_user->wp_user_sync && get_option('wp_to_pc_sync_on_register')) {
			
		// can't detect roles - then match against any user	
		$args = array(
			'limit'  			=> 1,
			'count'				=> true,
			'search' 			=> array(
                array(
                    'relation' => 'OR',
                    array('key' => 'username', 'operator' => '=', 'val' => $sanitized_user_login),
                    array('key' => 'email',    'operator' => '=', 'val' => $user_email),
                ),
			)
		);
		if($pc_users->get_users($args)) {
			$errors->add('pc wps - username or mail exists', esc_html__("Another user has this username or e-mail", 'pc_ml'));		
		}
	}
    return $errors;
}, 999, 3);


add_action('user_register', function($wp_user_id) {
	global $pc_wp_user;

	if(!$pc_wp_user->wp_user_sync || !get_option('wp_to_pc_sync_on_register') || is_admin() || isset($GLOBALS['pvtcont_wp_user_register'])) {
		return false;
	}
	
	// match against role?
	$allowed_roles = get_option('wp_to_pc_sync_on_register_roles', array());
	if(!empty($allowed_roles)) {
		
		$user_data = new WP_User($wp_user_id);
		$matched = false;
		
		foreach($allowed_roles as $ar) {
			if(in_array($ar, $user_data->roles)) {
				$matched = true;
				break;	
			}
		}
		
		if(!$matched) {
			return false;	
		}
	}
	
	// sync
	$pc_user_id = $pc_wp_user->pc_user_from_wp($wp_user_id, get_option('wp_to_pc_sync_on_register_cats', array()) );
	if(!is_wp_error($pc_user_id)) {
		
        // GA4 analytics event
        if(isset($GLOBALS['pvtcont_google_analytics'])) {
            $GLOBALS['pvtcont_google_analytics']->trigger_event('pc_user_created_from_wp_registr', array(
                'wp_user_id' => $wp_user_id,
            ), $pc_user_id);
        }
        
		// PC-ACTION - pvtcontent user created from WP user registered on frontend
        $bkp_val = (isset($GLOBALS['pc_user_id'])) ? $GLOBALS['pc_user_id'] : $GLOBALS['pc_user_id'];
        $GLOBALS['pc_user_id'] = $pc_user_id; // emulate for PCUA add-on
            
		do_action('pc_user_created_from_wp_register', $pc_user_id, $wp_user_id);
        
        if($bkp_val) {
            $GLOBALS['pc_user_id'] = $bkp_val;            
        } else {
            unset($GLOBALS['pc_user_id']);    
        }
	}
}, 999999);




// user changes its password via WP - sync it
add_action('after_password_reset', function($user, $new_pass) {
    global $pc_wp_user;
    
    // know whether user is synced
    $wp_user_id = $user->ID;
    $query = $pc_wp_user->wp_user_is_linked($wp_user_id);
    
    if(!$query) {
        return false;    
    }
    
    $pc_wp_user->sync_psw_to_pc($wp_user_id, $query->id);
}, 10, 2);




//////////////////////////////////////////////////////////////////////////




// hide privateContent from dropdown choiches in users.php
// remove ability to edit or delete user
add_action('admin_footer', function() {
	global $current_screen;

	if(isset($current_screen->base) && $current_screen->base == 'users') {
		$inline_js = '
        (function($) { 
            "use strict";
            
            $(document).ready(function(e) {
                $("select#new_role option[value=pvtcontent]").remove();  

                $("#the-list tr").each(function() {
                    var $row = $(this);
                    if($row.find(".column-role").text() == "PrivateContent") {
                        $row.find(".check-column").empty();
                        $row.find(".row-actions").remove();

                        $row.find(".username a").each(function() {
                            var content = $(this).contents();
                            $(this).replaceWith(content);
                        });	
                    }
                });
            });
        })(jQuery);';
        wp_add_inline_script('lc-wp-popup-message', $inline_js);
	}
    
	elseif(isset($current_screen->base) && ($current_screen->base == 'user-edit' || $current_screen->base == 'user')) {
		$inline_js = '
        (function($) { 
            "use strict";    
            
            $(document).ready(function(e) {
                $("select#role option[value=pvtcontent]").remove();  
            });
        })(jQuery);';
        wp_add_inline_script('lc-wp-popup-message', $inline_js);
	}
}, 1);




// avoid users to edit synced through user-edit.php interface
add_action('admin_enqueue_scripts', function() {
	$curr_url = pc_static::curr_url();

	if(strpos($curr_url, 'user-edit.php') !== false) {
		global $pc_wp_user;
		$pc_user_data = $pc_wp_user->wp_user_is_linked(absint($_REQUEST['user_id']));
		if(!empty($pc_user_data)) {
			
            // be sure it is in user dashboard lightbox
            if(
                (isset($_SERVER['HTTP_SEC_FETCH_DEST']) || $_SERVER['HTTP_SEC_FETCH_DEST'] != 'iframe') && 
                (!isset($_GET['wp_http_referer']) || strpos($_GET['wp_http_referer'], 'pvtcontent') === false)
            ) {
                wp_safe_redirect( admin_url('admin.php?page=pc_user_dashboard&user='. $pc_user_data->id) );        
            }
            
			pvtcont_wps_edit_user_special_codes();
		}
	}
}, 99999999);




// special inline CSS and JS in WP user edit to be used in lightbox - leave only custom fields
function pvtcont_wps_edit_user_special_codes() {
    $inline_css = '
	#adminmenuwrap,
	#adminmenuback,
	#wpadminbar,
	#wpfooter,
	#wpcontent > :not(#wpbody),
	#contextual-help-link-wrap,
	.wp-heading-inline,
	a.page-title-action,
	hr.wp-header-end,
	#message p:nth-of-type(2) {
		display: none !important;	
	}
	#wpcontent {
		margin-left: 0 !important;	
	}
	html.wp-toolbar {
		padding-top: 0 !important;	
	}
	#your-profile {
		opacity: 0;	
	}
	#your-profile > h2:not(:first-of-type) {
		margin-top: 40px;	
	}';
    wp_add_inline_style('pc-admin', $inline_css);


	$inline_js = '
    (function($) { 
        "use strict"; 
        
        $(document).ready(function($) {

            // form action must point to the "iframe-safe" URL
            $("#your-profile").attr("action", $("#your-profile").attr("action") + "?wp_http_refere=pvtcontent");
            
            
            // cycle sections and hide fields that must NOT be managed
            $("#your-profile > h2").each(function(i, v) {
                $(this).next().hide();

                // keep "Personal Options" heading	
                if(i != 0) {
                    $(this).hide();
                }

                // stop when finding password
                if( $(this).next().find("#password").length ) {

                    $("#your-profile").fadeTo(300, 1);
                    return false;	
                }
            });


            // keep "language display name" fields
            $("#your-profile > h2").first().after(`<table class="form-table pc_wps_ftk"><tbody></tbody></table>`);

            $("#locale, #display_name").each(function() {
                var classes = $(this).attr("class");
                var html  = $(this).parents("tr").html();
                $(this).parents("tr").remove();

                $(".pc_wps_ftk").append(`<tr class="`+ classes +`">`+ html +`</tr>`);
            });


            // add multiple "update user" buttons to be quicker
            var cloned_btn = $("<div />").append($(`p.submit`).clone()).html();

            // cycle sections and hide fields that must NOT be managed
            $(`#your-profile > h2, #your-profile > h3`).each(function(i, v) {

                if( i && $(this).is(`:visible`) ) {
                    $(this).before(cloned_btn);
                }
            });
        });
    })(jQuery);';	
    wp_add_inline_script('lc-wp-popup-message', $inline_js);
}




// avoid users to delete synced through user-edit.php interface
add_action('admin_init', function() {
	$curr_url = pc_static::curr_url();

	if(strpos($curr_url, 'users.php') !== false && strpos($curr_url, 'action=delete') !== false) {
		global $pc_wp_user;
		
		if(isset($_REQUEST['user'])) {
            $users = array(sanitize_text_field($_REQUEST['user']));
        }
		elseif(isset($_REQUEST['users'])) {
			$users = sanitize_text_field($_REQUEST['users']);	
		}

		foreach($users as $user_id) {
			$user_data = get_userdata($user_id);
			
			if(isset($user_data->caps['pvtcontent'])) {
				ob_start();
				ob_clean();
                
                wp_safe_redirect(admin_url('users.php'));
				break;
			}
		}
	}
}, 1);




// hide pvtcontent user filter from wp-admin/users.php 
add_filter('views_users', function($roles) {
	if(isset($roles['pvtcontent'])) {
		unset( $roles['pvtcontent'] );	
	}
	
    return $roles;
});





// Hide users with "pvtcontent" role from WP users table
add_action('pre_get_users', function($query) {
    if(!function_exists('get_current_screen')) {
		return false;	
	}
	
	$screen = get_current_screen();
    if(is_admin() && is_object($screen) && 'users' == $screen->base){
        $query->set('role__not_in', 'pvtcontent');
    }
});




// Hide users with "pvtcontent" role from WP users table count
add_filter('query', function($query) {
	global $current_screen;
	
	if(is_null($current_screen) || $current_screen->base != 'users') {
		return $query;	
	}
	
	if(strpos($query, "COUNT(NULLIF(") === false || strpos($query, "pvtcontent") === false) {
		return $query;	
	}
	

	$query = str_replace(
		"WHERE meta_key = 'wp_capabilities'",
		"WHERE meta_key = 'wp_capabilities' AND meta_value NOT LIKE '%pvtcontent%'",
		$query
	);
	return $query;
});

