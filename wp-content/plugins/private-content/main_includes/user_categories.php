<?php
// USER CATEGORY TAXONOMY - REGISTER AND CUSTOMIZE
if(!defined('ABSPATH')) {exit;}


// REGISTER TAXONOMY 
add_action('init', 'pvtcont_user_cat_taxonomy', 0);
function pvtcont_user_cat_taxonomy() {
    $labels = array( 
        'name'          => esc_html__('User Categories', 'pc_ml' ),
        'singular_name' => esc_html__( 'User Category', 'pc_ml' ),
        'search_items'  => esc_html__( 'Search User Categories', 'pc_ml' ),
        'popular_items' => esc_html__( 'Popular User Categories', 'pc_ml' ),
        'all_items'     => esc_html__( 'All User Categories', 'pc_ml' ),
        'parent_item'   => esc_html__( 'Parent User Category', 'pc_ml' ),
        'parent_item_colon' => esc_html__( 'Parent User Category:', 'pc_ml' ),
        'edit_item'     => esc_html__( 'Edit User Category', 'pc_ml' ),
        'update_item'   => esc_html__( 'Update User Category', 'pc_ml' ),
        'add_new_item'  => esc_html__( 'Add New User Category', 'pc_ml' ),
        'new_item_name' => esc_html__( 'New User Category Name', 'pc_ml' ),
        'separate_items_with_commas'=> esc_html__( 'Separate user categories with commas', 'pc_ml' ),
        'add_or_remove_items'       => esc_html__( 'Add or remove user categories', 'pc_ml' ),
        'choose_from_most_used'     => esc_html__( 'Choose from the most used user categories', 'pc_ml' ),
        'menu_name'     => esc_html__( 'User Categories', 'pc_ml' ),
		'back_to_items' => esc_html__('Back to user Categories', 'pc_ml'),
    );
    
    
    // capability - use specific capability only from v8 settings
    $min_cap = (get_option('pg_pvtpage_overriding_method')) ? 'man_pg_user_categories' : 'manage_categories';    

    $args = array( 
        'labels'            => $labels,
        'public'            => false,
        'show_in_nav_menus' => false,
        'show_ui'           => true,
        'show_tagcloud'     => false,
        'hierarchical'      => false,
        'rewrite'           => false,
		'capabilities'      => array(
            'manage_terms' => $min_cap,
            'edit_terms' => $min_cap,
            'delete_terms' => $min_cap,
            'assign_terms' => $min_cap,
        ),
        'query_var'         => true
    );

    register_taxonomy('pg_user_categories', '', $args);	
}




// remove "articles" column from the taxonomy table
add_filter('manage_edit-pg_user_categories_columns', function($columns) {
    if(isset($columns['posts'])) {
        unset($columns['posts']); 
    }

    return $columns;
}, 10, 1);





// add custom fields
add_action('pg_user_categories_add_form_fields', 'pvtcont_ucat_fields', 10, 2);
add_action('pg_user_categories_edit_form_fields', 'pvtcont_ucat_fields', 10, 2);

function pvtcont_ucat_fields($tax_data) {
   	dike_lc('lcweb', PC_DIKE_SLUG, true); /* NFPCF */
    
    $login_redirect		= '';
	$registr_redirect 	= '';
	$no_registration 	= 0;
   
	//check for existing taxonomy meta for term ID
	if(is_object($tax_data)) {
		$term_id = $tax_data->term_id;
		
		$login_redirect 	= (string)pc_static::retrocomp_get_term_meta($term_id, 'pg_ucat_login_redirect', "pg_ucat_".$term_id."_login_redirect");
		$registr_redirect 	= (string)pc_static::retrocomp_get_term_meta($term_id, 'pg_ucat_registr_redirect', "pg_ucat_".$term_id."_registr_redirect");
		$no_registration 	= pc_static::retrocomp_get_term_meta($term_id, 'pg_ucat_no_registration', "pg_ucat_".$term_id."_no_registration");
        $manag_by_users 	= get_term_meta($term_id, 'pg_ucat_manag_by_users', true);
	}


	// creator layout
	if(!is_object($tax_data)) :
?>
        <div class="form-field">
            <hr/>
        </div>
		<div class="form-field">
            <label><?php esc_html_e('Custom redirect after login', 'pc_ml') ?></label>
           	<input type="text" name="pg_ucat_login_redirect" value="<?php echo esc_attr(trim($login_redirect)) ?>" autocomplete="off" placeholder="<?php esc_attr_e("Use a valid URL", 'pc_ml') ?>" /> 
            <p><?php esc_html_e('Set a custom login redirect for users belonging to this category', 'pc_ml') ?></p>
        </div>
        <div class="form-field">
            <label><?php esc_html_e('Custom redirect after registration', 'pc_ml') ?></label>
           	<input type="text" name="pg_ucat_registr_redirect" value="<?php echo esc_attr(trim($registr_redirect)) ?>" autocomplete="off" placeholder="<?php esc_attr_e("Use a valid URL", 'pc_ml') ?>" /> 
            <p><?php esc_html_e('Set a custom registration redirect for users belonging to this category', 'pc_ml') ?></p>
        </div>
        <div class="form-field">
            <hr/>
        </div>
        <div class="form-field pg_ucat_no_registration_wrap">
            <label><?php esc_html_e('Hidden in registration forms?', 'pc_ml') ?></label>
           	<input type="checkbox" name="pg_ucat_no_registration" value="1" <?php if($no_registration) echo 'checked="checked"' ?> autocomplete="off" /> 
            <p><?php esc_html_e("If checked, hides category from registration's form auto-selection dropdown", 'pc_ml') ?></p>
        </div>
        <div class="form-field">
            <hr/>
        </div>
        <div class="form-field pg_ucat_mbu_wrap">
            <label><?php esc_html_e('Targeted users allowed to manage PrivateContent users in this category', 'pc_ml') ?></label>
           	<?php echo pc_static::wp_kses_ext(pc_wpuc_static::autocomplete_users_search_n_pick('pg_ucat_manag_by_users')); ?>
        </div>

        <?php 
        // track term's addition and reset the custom fields 
        $inline_js = '
        (function($) { 
            "use strict";
            
            $(document).ajaxComplete(function(e, xhr, opt) {
                if (xhr && xhr.readyState === 4 && xhr.status === 200 && opt.data && opt.data.indexOf("action=add-tag") >= 0) {
                    const res = wpAjax.parseAjaxResponse(xhr.responseXML, "ajax-response");
                    if(!res || res.errors) {
                        return;
                    }
                    
                    lcs_off(document.querySelector(`input[name="pg_ucat_no_registration"]`));
                    
                    window.pc_ucat_reset_cust_fields = true;
                    $(`.pc_ucat_mbu_list .dashicons-no-alt`).trigger(`click`);
                    delete window.pc_ucat_reset_cust_fields;
                }
            });
        })(jQuery);';
        wp_add_inline_script('lcwp_magpop', $inline_js);

	else :
    
	?>
     
    <tr class="form-field">
        <td colspan="2">
            <hr/>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top"><label><?php esc_html_e('Custom redirect after login', 'pc_ml') ?></label></th>
        <td>
            <input type="text" name="pg_ucat_login_redirect" value="<?php echo esc_attr(trim($login_redirect)) ?>" autocomplete="off" placeholder="<?php esc_attr_e("Use a valid URL", 'pc_ml') ?>" /> 
            <p class="description"><?php esc_html_e('Set a custom login redirect for users belonging to this category', 'pc_ml') ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top"><label><?php esc_html_e('Custom redirect after registration', 'pc_ml') ?></label></th>
        <td>
            <input type="text" name="pg_ucat_registr_redirect" value="<?php echo esc_attr(trim($registr_redirect)) ?>" autocomplete="off" placeholder="<?php esc_attr_e("Use a valid URL", 'pc_ml') ?>" /> 
            <p class="description"><?php esc_html_e('Set a custom registration redirect for users belonging to this category', 'pc_ml') ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <td colspan="2">
            <hr/>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top"><label><?php esc_html_e('Hidden in registration forms?', 'pc_ml') ?></label></th>
        <td class="pg_ucat_no_registration_wrap">
            <input type="checkbox" name="pg_ucat_no_registration" value="1" <?php if($no_registration) echo esc_html('checked="checked"') ?> autocomplete="off" /> 
            <p class="description"><?php esc_html_e("If checked, hides category from registration's form auto-selection dropdown", 'pc_ml') ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <td colspan="2">
            <hr/>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top"><label><?php esc_html_e('Targeted users allowed to manage PrivateContent users in this category', 'pc_ml') ?></label></th>
        <td class="pg_ucat_mbu_wrap">
            <?php echo pc_static::wp_kses_ext(pc_wpuc_static::autocomplete_users_search_n_pick('pg_ucat_manag_by_users', $manag_by_users)); ?>
        </td>
    </tr>
<?php
	endif;  
    
    // nonce for everyone
    echo '<input type="hidden" name="pvtcont_nonce" value="'. esc_attr(wp_create_nonce('lcwp_ajax')) .'" />';
    
    $inline_js = '
    (function($) { 
        "use strict";

        $(document).ready(function() {
            lc_switch(`input[name=pg_ucat_no_registration]`, {
                on_txt      : `'. esc_js(strtoupper(esc_attr__('yes', 'pc_ml'))) .'`,
                off_txt     : `'. esc_js(strtoupper(esc_attr__('no', 'pc_ml'))) .'`,   
            });
        });
    })(jQuery);';
    wp_add_inline_script('lcwp_magpop', $inline_js);
}





// save fields
add_action('created_pg_user_categories', 'pvtcont_save_ucat_fields', 10, 2);
add_action('edited_pg_user_categories', 'pvtcont_save_ucat_fields', 10, 2);

function pvtcont_save_ucat_fields($term_id) {
    if(!isset($_POST['pvtcont_nonce']) || !pc_static::verify_nonce($_POST['pvtcont_nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    if(PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any') {
        wp_die('You do not have rights to use the plugin');   
    }
    
    
    $additional_metas = array(
		'Login redirect' 	=> false,
		'Registration redirect'	=> false,
	);
	
	
	// login redirect
	if(isset($_POST['pg_ucat_login_redirect']) && (filter_var($_POST['pg_ucat_login_redirect'], FILTER_VALIDATE_URL) || empty($_POST['pg_ucat_login_redirect']))) {
        
        update_term_meta($term_id, 'pg_ucat_login_redirect', pc_static::sanitize_val($_POST['pg_ucat_login_redirect']));  
        $additional_metas['Login redirect'] = pc_static::sanitize_val($_POST['pg_ucat_login_redirect']);
    } 
	
	// registration redirect
	if(isset($_POST['pg_ucat_registr_redirect']) && (filter_var($_POST['pg_ucat_registr_redirect'], FILTER_VALIDATE_URL) || empty($_POST['pg_ucat_registr_redirect']))) {
        
        update_term_meta($term_id, 'pg_ucat_registr_redirect', pc_static::sanitize_val($_POST['pg_ucat_registr_redirect'])); 
		$additional_metas['Registration redirect'] = pc_static::sanitize_val($_POST['pg_ucat_registr_redirect']);
    } 
	
	// no registration
	if(isset($_POST['pg_ucat_no_registration']) ) {
        update_term_meta($term_id, 'pg_ucat_no_registration', 1); 
    } else {
        delete_term_meta($term_id, 'pg_ucat_no_registration');    
    }
	
    // targeted users allowed to edit users in the category
    $ucat_manag_by_users = (isset($_POST['pg_ucat_manag_by_users'])) ? array_map('sanitize_text_field', (array)$_POST['pg_ucat_manag_by_users']) : array();
    update_term_meta($term_id, 'pg_ucat_manag_by_users', $ucat_manag_by_users); 
    
    // sync metas with WPML
	pvtcont_cat_wpml_sync($additional_metas);
}






/////////////////////////////
// manage taxonomy table
add_filter('manage_edit-pg_user_categories_columns', 'pvtcont_cat_order_column_headers', 10, 1);
add_filter('manage_pg_user_categories_custom_column', 'pvtcont_cat_order_column_row', 10, 3);


// add the table column
function pvtcont_cat_order_column_headers($columns) {
	if(isset($columns['slug'])) {unset($columns['slug']);}
	
	// re-write cols injec
	$a = 0;
	$cols = array();
	foreach($columns as $key => $val) {
		if($a == 0) {
			$cols[$key] = $val;
			$cols['pc_cat_id_col'] = 'ID';
		}
		else {
			$cols[$key] = $val;	
		}
		$a++;
	}

	$columns_local = array();
    $columns_local['login_redirect'] 	= esc_html__("Login Redirect", 'pc_ml');
	$columns_local['registr_redirect'] 	= esc_html__("Registration Redirect", 'pc_ml');
	$columns_local['no_registration'] 	= esc_html__("No Registration", 'pc_ml');
    
    include_once(PC_DIR .'/settings/field_options.php');
    
    /* translators: 1: role name. */
    $helper = sprintf(esc_html__("Globally, any %s can manage every PrivateContent user", 'pc_ml'), strtolower(pvtcont_wp_roles(get_option('pg_min_role_tmu', 'edit_pages'))) );
    $columns_local['manag_by_users'] = esc_html__("Manageable by", 'pc_ml') .'<span class="dashicons dashicons-editor-help" title="'. esc_attr($helper) .'"></span>';
    
    $columns_local['users_count'] = '<span class="dashicons dashicons-admin-users" title="'. esc_attr__('users count', 'pc_ml') .'"></span>';
    return array_merge($cols, $columns_local);
}



// fill the custom column row
function pvtcont_cat_order_column_row($row_content, $column_name, $term_id) {
	global $pc_users;
    
	if($column_name == 'pc_cat_id_col') {
		return $term_id;
	}
	else if($column_name == 'login_redirect') {
		return pc_static::retrocomp_get_term_meta($term_id, 'pg_ucat_login_redirect', "pg_ucat_".$term_id."_login_redirect");
	}
	else if($column_name == 'registr_redirect') {
		return pc_static::retrocomp_get_term_meta($term_id, 'pg_ucat_registr_redirect', "pg_ucat_".$term_id."_registr_redirect");
	}
	else if($column_name == 'no_registration') {
		return (pc_static::retrocomp_get_term_meta($term_id, 'pg_ucat_no_registration', "pg_ucat_".$term_id."_no_registration")) ? '&#10003;' : '';
	}
    else if($column_name == 'users_count') {
        $args = array(
            'categories'=> $term_id,
            'count'     => true,
        );
		return $pc_users->get_users($args);
	}
    else if($column_name == 'manag_by_users') {
        $mbu = get_term_meta($term_id, 'pg_ucat_manag_by_users', true);
        
        if(empty($mbu)) {
            return '';    
        }
        else {
            $users = new WP_User_Query(array(
                'role__not_in'  => array('Administrator', 'pvtcontent'),
                'include'       => (array)$mbu
            ));  
            
            $users = $users->get_results();
            $code  = '<ul class="pc_ucat_mbu_list">';
            
            foreach($users as $u) {
                $code .= '<li data-uid="'. $u->ID .'">'. $u->user_login .'</li>';  
            }
            return $code . '</ul>';
        }  
    }
	
    return '&nbsp;';
}




/////////////////////////////////////////////////




//// WPML & Polylang compatibility - save/update categories name and redirects as single strings
function pvtcont_cat_wpml_sync($additional_metas) {
	
	// WPML
	if(function_exists('icl_register_string')) {
		$user_categories = get_terms(array(
            'taxonomy'   => 'pg_user_categories',
            'orderby'    => 'name',
            'hide_empty' => 0,
        ));
		
		if(!is_wp_error($user_categories)) {
			foreach ($user_categories as $ucat) {
				icl_register_string('PrivateContent Categories', 'Category #'.$ucat->term_id, $ucat->name);	
				
				// additional metas
				foreach($additional_metas as $key => $val) {
					if($val) {
						icl_register_string('PrivateContent Categories - '.$key, 'Category #'.$ucat->term_id, $val);		
					}
				}
			}
		}
	}

	
	// polylang
	if(function_exists('pll_register_string')) {
		$user_categories = get_terms(array(
            'taxonomy'   => 'pg_user_categories',
            'orderby'    => 'name',
            'hide_empty' => 0,
        ));
		
		if (!is_wp_error($user_categories)) {
			foreach ($user_categories as $ucat) {
				pll_register_string('PrivateContent Categories', $ucat->name, $ucat->term_taxonomy_id);	
				
				// additional metas
				foreach($additional_metas as $key => $val) {
					if($val) {
						pll_register_string('PrivateContent Categories - '.$key, $val, $ucat->term_taxonomy_id);		
					}
				}
			}
		}
	}
}



//// WPML compatibility - delete cat name string during deletion
add_action('delete_term_taxonomy', function($cat_id) {
	if(function_exists('icl_unregister_string')) {
		icl_unregister_string('PrivateContent Categories', $cat_id);	
		icl_unregister_string('PrivateContent Categories - Login redirect', $cat_id);	
		icl_unregister_string('PrivateContent Categories - Registration redirect', $cat_id);	
	}
});





/////////////////////////////////////////////////




// apply custom redirects
add_filter('pc_custom_redirect_url', function($url, $redir_index, $user_id) {
	global $pc_users;	
		
	if(!in_array($redir_index, array('pc_logged_user_redirect', 'pc_registered_user_redirect'))) {
		return $url;	
	}
	$subj 		= ($redir_index == 'pc_logged_user_redirect') ? 'login' : 'registr';
	$wpml_subj 	= ($redir_index == 'pc_logged_user_redirect') ? 'Login' : 'Registration';
	
	
	// retrieve user categories
	$cats = $pc_users->get_user_field($user_id, 'categories');
	if(!is_array($cats)) {
		return $url;
	}
	
	
	asort($cats);
	foreach($cats as $term_id) {
		if(!term_exists((int)$term_id, 'pg_user_categories')) {
            continue;    
        }
        $new_url = pc_static::retrocomp_get_term_meta($term_id, 'pg_ucat_'. $subj .'_redirect', "pg_ucat_".$term_id."_". $subj ."_redirect");
		
		// WPML - Polylang compatibility
		if(function_exists('icl_t')){
			$new_url = icl_t('PrivateContent Categories - '. $wpml_subj .' redirect', 'Category #'.$term_id, $new_url);
		}
		else if(function_exists('pll__')){
			$new_url = pll__($new_url);
		}
		
		if(!empty($new_url)) {
            return $new_url;
			break;	
		}
	}
	
	return $url;
}, 1, 3);