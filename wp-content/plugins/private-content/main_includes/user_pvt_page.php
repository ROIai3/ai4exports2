<?php
// MANAGE USERS RESERVED PAGE
if(!defined('ABSPATH')) {exit;}


// fullpage overriding function
add_action('template_redirect', function() {
    if(!is_page() || get_option('pg_pvtpage_overriding_method', 'contents') != 'full_page') {
        return true;    
    }
    
    global $post, $pc_users;

    if(pc_static::wpml_translated_pag_id( (int)get_option('pg_target_page')) != pc_static::wpml_translated_pag_id($post->ID)) {
        return false;   
    }
    
    // get user page ID
    if(is_user_logged_in() && isset($_GET['pc_pvtpag'])) {
		$pc_user_id = absint($_GET['pc_pvtpag']);
        $pc_page_id = $pc_users->get_user_field((int)$_GET['pc_pvtpag'], 'page_id');
    }
    else {
        $user_data = pc_user_logged(array('page_id', 'disable_pvt_page', 'wp_user_id'));   
        
        if(!is_array($user_data) || $user_data['disable_pvt_page']) {
            return false;
        }
        
        $pc_user_id = $GLOBALS['pc_user_id'];
        $pc_page_id = $user_data['page_id'];
    }
    
    
    // create the token
    $token = wp_generate_password();
    update_post_meta($pc_page_id, 'pc_pvt_page_full_override_token', $token);
    
    // retrieve user reserved page contents and start replacement system
    $args = array(
        'body' => array(
            'pc_pvt_page_full_override_token'   => $token,
            'pc_user_id'                        => $pc_user_id,
            'pc_is_user_loggged'                => (isset($GLOBALS['pc_user_id'])) ? true : false,
            'pc_wp_user_sync'                   => ($pc_users->wp_user_sync && isset($user_data) && $user_data['wp_user_id']) ? (int)$user_data['wp_user_id'] : false,
        ),
    );
    
    // fill with username:password in case of HTACCESS protection
    if(get_option('pg_htaccess_cred')) {
        $args['headers'] = array(
            'Authorization' => 'Basic '. base64_encode(get_option('pg_htaccess_cred'))    
        );        
    }
    
    echo pc_static::wp_kses_ext(wp_remote_retrieve_body(
        wp_remote_get(get_permalink($pc_page_id), $args)
    ));
    
    exit;
}, 0);





// return logged user's private page contents to be replaced in target page
function pvtcont_user_page_contents($get_post_autosave = false) {
    $content = '';
    
    // check logged user
	$user_data = pc_user_logged(array('page_id', 'disable_pvt_page', 'wp_user_id'));
	if(!$user_data) {
		return $content;
	}	
	$GLOBALS['pvtcont_user_reserved_page_id'] = $user_data['page_id'];	
    
    
	// if not have a private page
	if(!empty($user_data['disable_pvt_page'])) {
		return '<p class="pc_user_page_disabled_txt">'. pc_get_message('pc_default_nhpa_mex') .'</p>';	
	}	
	
	// private page contents
	$page_data = ($get_post_autosave) ? wp_get_post_autosave($user_data['page_id']) : get_post($user_data['page_id']);
    
    if($get_post_autosave && !$page_data) {
        $page_data = get_post($user_data['page_id']);    
    }
    
    $GLOBALS['pvtcont_user_reserved_page_obj'] = $page_data;
	$content = pvtcont_user_page_preset_texts($page_data->post_content, true);

	
	//////	
						
	// is managed through Elementor?
	if(defined('ELEMENTOR_URL') && get_post_meta($user_data['page_id'], '_elementor_edit_mode', true) == 'builder') {
        $elem_front = new Elementor\Frontend();
		$content = pvtcont_user_page_preset_texts($elem_front->get_builder_content_for_display($user_data['page_id'], true), true); 
		
		// JS to add class to body
        wp_add_inline_script('pc_frontend', 'document.body.className += " elementor-page-'. $user_data['page_id'] .'";');
        
		// be sure JS is added
		$elem_front->init();
	}
	
	
    /* NFPCF */
	// is managed through WPbakery? Check if has got custom CSS
	if(is_plugin_active('js_composer/js_composer.php')) {	
		$shortcodes_custom_css = get_post_meta($user_data['page_id'], '_wpb_shortcodes_custom_css', true);
		
		if(!empty( $shortcodes_custom_css)) {
			$content .= '<style type="text/css" data-type="vc_shortcodes-custom-css">'. wp_strip_all_tags($shortcodes_custom_css) .'</style>';
		}
	}
	
	
	// if there's WP [embed] shortcode, execute it
	if(strpos($content, '[/embed]') !== -1) {
		global $wp_embed;
		$content = $wp_embed->run_shortcode($content);
	}
	
	//////
		
		
	// PC-FILTER - private page contents - useful to customize what is returned
	$content = apply_filters('pc_pvt_page_contents', $content);
	
	if(!isset($elem_front)) {
        $content = wpautop($content); // no WPAUTOP if through elementor
    } 
    if(function_exists('do_blocks')) {
        $content = do_blocks($content);    
    }
	return do_shortcode($content);
}




// managing what is returned by pvtcont_user_page_contents_override() handling get_option('pg_pvtpage_overriding_method') option
function pvtcont_user_page_contents_override_return($user_contents, $orig_contents) {
    $method = get_option('pg_pvtpage_overriding_method', 'contents');
    
    if($method == 'placeholder' && !ISPCF) {
        return str_replace('%PC-USER-PAG-CONTENT%', $user_contents, $orig_contents);   
    } 
    else {
        return (empty($user_contents)) ? $orig_contents : $user_contents;
    }
}




// contents-only overriding function
function pvtcont_user_page_contents_override($content) {
	global $wpdb, $post, $pc_users;
	
	// run it only once (avoid conflicts with badly made themes)
	/*if(isset($GLOBALS['pvtcont_user_reserved_page_contents_managed'])) { - conflicts with YOAST
		return $content;	
	}*/
	$GLOBALS['pvtcont_user_reserved_page_contents_managed'] = true;
	
	
	$orig_content          = $content;
	$target_page           = (int)get_option('pg_target_page');
	$curr_page_id          = (int)get_the_ID();
    $admin_is_previewing   = false;
	
	
	// must be the chosen container page
	if(!$target_page || !is_object($post) || pc_static::wpml_translated_pag_id($target_page) != pc_static::wpml_translated_pag_id($post->ID)) {
		return $content;
	}
    
	// preview check
	if(is_user_logged_in() && isset($_GET['pc_pvtpag']) && isset($_GET['pc_utok'])) {
		if(!pc_static::verify_nonce($_GET['pc_utok'], 'lcwp_nonce')) {
            return 'Cheating?';
        }
        
		$GLOBALS['pc_user_id'] = (int)$_GET['pc_pvtpag'];
		$admin_is_previewing = true;
	}

	// check logged user
	$user_data = pc_user_logged(array('page_id', 'disable_pvt_page', 'wp_user_id'));
	if(!$user_data) {
		$content = '';
        
        
		// return page content and eventually attach form
		$login_form = pc_login_form();
		$pvt_nl_content = get_option('pg_target_page_content');

		// contents + form
		if($pvt_nl_content == 'original_plus_form') {
			$orig_content = $orig_content . $login_form;   
		}
        
		// form + contents
		elseif($pvt_nl_content == 'form_plus_original') {
			$orig_content = $login_form . $orig_content;   
		}
        
		// only form
		elseif($pvt_nl_content == 'only_form') {
            $orig_content = $login_form;
        }
		
		return pvtcont_user_page_contents_override_return($content, $orig_content);
	}	
		
    // replace contents only if is right method
    if(!in_array(get_option('pg_pvtpage_overriding_method', 'contents'), array('contents', 'placeholder'))) {
        return $content;    
    }
    
    // if not have a private page
	if(!empty($user_data['disable_pvt_page'])) {
		$content = '<p>'. pc_get_message('pc_default_nhpa_mex') .'</p>';
        return pvtcont_user_page_contents_override_return($content, $orig_content);;	
	}
    
	// flag for pvt page usage
	//if(isset($GLOBALS['pvtcont_user_reserved_page_is_displaying'])) {return $content;} // be sure contents are affected only once
	//$GLOBALS['pvtcont_user_reserved_page_is_displaying'] = true;
	
    
    $get_post_autosave = ($admin_is_previewing && isset($_GET['preview_id'])) ? true : false; 
    $content = pvtcont_user_page_contents($get_post_autosave);
    
		
	// PC-ACTION - private page is being displayed - triggered in the_content hook
	do_action('pc_pvt_page_display');
	
	
	//// COMMENTS
	// disable comments if not synced
	if(!$pc_users->wp_user_sync || !get_option('pg_pvtpage_wps_comments') || !$user_data['wp_user_id'] || $GLOBALS['pvtcont_user_reserved_page_obj']->comment_status != 'open') {
		add_filter('comments_template', 'pvtcont_user_page_comments_template', 500);
	}
	else {
		// override query
		$GLOBALS['pvtcont_custom_comments_template'] = 'original';
		$GLOBALS['pvtcont_user_reserved_page_container_id'] = $curr_page_id;
		
		// override $post
		global $post;
		$post = get_post($GLOBALS['pvtcont_user_reserved_page_id']);
		
		// PC-ACTION - give the opportunity to override comments template	
		$custom_template = do_action('pc_pvt_page_comments_template');  
		if(!empty($custom_template)) {
			$GLOBALS['pvtcont_custom_comments_template'] = $custom_template;	
		}
		
		add_filter('comments_template', 'pvtcont_user_page_comments_template',500);
	}
	
	// remove session for admin preview
	if($admin_is_previewing) {
		unset($GLOBALS['pc_user_id']);	
	}
	return pvtcont_user_page_contents_override_return($content, $orig_content);
}


/* AVADA FIX */
function pc_pvt_page_management($content) {
	return pvtcont_user_page_contents_override($content);	
}
/************/

add_filter('the_content', 'pvtcont_user_page_contents_override', 500); // use 500 - before comments restriction and PC hide





// preset contents - used through hooks
function pvtcont_user_page_preset_texts($content, $direct_call = false) {
	global $post;
    
    if(!$direct_call) {
        if(!is_object($post) || !property_exists($post, 'post_type') || $post->post_type != 'pg_user_page') {
            return $content;    
        }
    }

    if(get_option('pg_pvtpage_enable_preset') && !ISPCF) {
        $preset = do_shortcode( wpautop(get_option('pg_pvtpage_preset_txt'))); /* NFPCF */

        // PC-FILTER - customize preset contents used in user pvt pages
        $preset = apply_filters('pc_pvt_page_preset_contents', $preset);
    }
	else {
        return $content;
    }

	$content = (get_option('pg_pvtpage_preset_pos') == 'before') ? $preset . $content : $content . $preset;	
	return $content;
}
add_filter('the_content', 'pvtcont_user_page_preset_texts');





// override default comment template - by default returns an empty template
function pvtcont_user_page_comments_template($template){
	if(!isset($GLOBALS['pvtcont_custom_comments_template']) || empty($GLOBALS['pvtcont_custom_comments_template'])) {
		$url = PC_DIR . "/restrictions/comment_hack.php";	
	} 
	else {		
		// override current WP_query parameters to show pvt page contents
		global $post;
		$post = get_post($GLOBALS['pvtcont_user_reserved_page_id']);
		
		global $wp_query;
		
		$wp_query->queried_object->ID	= $GLOBALS['pvtcont_user_reserved_page_id'];
		$wp_query->posts[0]->ID 		= $GLOBALS['pvtcont_user_reserved_page_id'];
		$wp_query->post->ID				= $GLOBALS['pvtcont_user_reserved_page_id'];
		
		$wp_query->queried_object->comment_status 	= 'open';
		$wp_query->posts[0]->comment_status 		= 'open';
		$wp_query->post->comment_status 			= 'open';
		
		$wp_query->queried_object->comment_count	= $GLOBALS['pvtcont_user_reserved_page_obj']->comment_count;
		$wp_query->posts[0]->comment_count 			= $GLOBALS['pvtcont_user_reserved_page_obj']->comment_count;
		$wp_query->post->comment_count 				= $GLOBALS['pvtcont_user_reserved_page_obj']->comment_count;
		$wp_query->comment_count 					= $GLOBALS['pvtcont_user_reserved_page_obj']->comment_count;
		
		$wp_query->comments = get_comments( array('post_id' => $GLOBALS['pvtcont_user_reserved_page_id']) );

		$url = ($GLOBALS['pvtcont_custom_comments_template'] == 'original') ? $template : $GLOBALS['pvtcont_custom_comments_template'];
	}

	return $url;
}





// if private page and override comments - reset post
do_action('comment_form_after', function() {
	if(isset($GLOBALS['pvtcont_user_reserved_page_container_id'])) {
		global $post;
		$post = get_post($GLOBALS['pvtcont_user_reserved_page_container_id']);
	}
}, 1);





// override container page featured image (useful for Elementor)
function pvtcont_user_page_override_container_feat_img($value, $post_id, $meta_key) {
	if($meta_key !== '_thumbnail_id' || !isset($GLOBALS['pc_user_id']) || $post_id != pc_static::wpml_translated_pag_id( (int)get_option('pg_target_page') )) {
        return $value;    
    }
    
    // get user pvtpage featured image
    $user_data = pc_user_logged(array('page_id', 'disable_pvt_page'));
    $user_page_feat_img = get_post_thumbnail_id($user_data['page_id']);
    
    if($user_page_feat_img && !$user_data['disable_pvt_page']) {
        return $user_page_feat_img;    
    }
    
    return $value;
}
add_filter('get_post_metadata', 'pvtcont_user_page_override_container_feat_img', 1, 3);
add_filter('default_post_metadata', 'pvtcont_user_page_override_container_feat_img', 1, 3);





// trick to print Divi Builder scripts in user reserved pages context
add_action('wp_enqueue_scripts', function() {
    if(!class_exists('ET_Builder_Element') || !is_page()) {
        return false;    
    }
    global $post, $pc_users;

    if(pc_static::wpml_translated_pag_id( (int)get_option('pg_target_page')) != pc_static::wpml_translated_pag_id($post->ID)) {
        return false;   
    }
    
    // get user page ID
    if(is_user_logged_in() && isset($_GET['pc_pvtpag'])) {
		$pc_user_id = (int)$_GET['pc_pvtpag'];
        $pc_page_id = $pc_users->get_user_field((int)$_GET['pc_pvtpag'], 'page_id');
    }
    else {
        $user_data = pc_user_logged(array('page_id', 'disable_pvt_page'));   
        
        if(!is_array($user_data) || $user_data['disable_pvt_page']) {
            return false;
        }
        $pc_page_id = $user_data['page_id'];
    }
        
    // backup $post data and create a flag to restore
    $GLOBALS['pvtcont_upp_divi_scripts_trick'] = $post;
    $GLOBALS['post'] = get_post($pc_page_id);
}, 0);


add_action('wp_enqueue_scripts', function() {
    if(!isset($GLOBALS['pvtcont_upp_divi_scripts_trick'])) {
        return true;    
    }
    
    $GLOBALS['post'] = $GLOBALS['pvtcont_upp_divi_scripts_trick'];
    unset($GLOBALS['pvtcont_upp_divi_scripts_trick']);
}, 99999999);






// remove user pages from WP link suggestions
add_filter('pre_get_posts', function($query) {
	if(isset($_POST['action']) && $_POST['action'] == 'wp-link-ajax') {
		if(isset($query->query['post_type']) && is_array($query->query['post_type']) && in_array('pg_user_page', $query->query['post_type'])) {
			
			if (($key = array_search('pg_user_page', $query->query['post_type'])) !== false) {
				unset($query->query['post_type'][$key]);
			}
			$query->set('post_type', $query->query['post_type']);
		}
	}
	return $query;
}, 9999);





// block WP users not allowed to edit user categories
add_action('current_screen', function() {
    global $current_screen, $pc_users;
    
    if(!$current_screen) {
        return true;    
    }
    $cs = $current_screen;
    
    if($cs->base == 'post' && $cs->post_type == 'pg_user_page' && isset($_GET['action']) && isset($_GET['post'])) {
        
        $args = array(
            'limit'     => 1,
            'to_get'    => array('id'),
            'search'    => array(
                array(
                    array('key' => 'page_id', 'operator' => '=', 'val' => (int)$_GET['post'])
                )
            )
        );
        $users = $pc_users->get_users($args);
        
        if(empty($users)) {
            return false;    
        }
        $user_id = $users[0]['id'];
        
        if(!pc_wpuc_static::current_wp_user_can_edit_pc_user($user_id)) {
            wp_redirect( admin_url());    
        }
    }
});
