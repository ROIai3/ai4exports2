<?php
// CUSTOM POST TYPES AND TAXONOMIES DECLARATION
if(!defined('ABSPATH')) {exit;}


// LIGHTBOX INSTANCES TAXONOMY
function pc_lightboxes_ct() {
    $labels = array( 
        'name' => 'PrivateContent lightboxes',
        'singular_name' => 'PrivateContent lightbox',
        'search_items' => 'Search PrivateContent lightboxes',
        'popular_items' => 'Popular PrivateContent lightboxes',
        'all_items' => 'All PrivateContent lightboxes',
        'parent_item' => 'Parent PrivateContent lightbox',
        'parent_item_colon' => 'Parent PrivateContent lightbox:',
        'edit_item' => 'Edit PrivateContent lightbox',
        'update_item' => 'Update PrivateContent lightbox',
        'add_new_item' => 'Add New PrivateContent lightbox',
        'new_item_name' => 'New PrivateContent lightbox',
        'separate_items_with_commas' => 'Separate privatecontent lightboxes with commas',
        'add_or_remove_items' => 'Add or remove PrivateContent lightboxes',
        'choose_from_most_used' => 'Choose from most used PrivateContent lightboxes',
        'menu_name' => 'PrivateContent lightboxes',
    );

    $args = array( 
        'labels' => $labels,
        'public' => false,
        'show_in_nav_menus' => false,
        'show_ui' => false,
        'show_tagcloud' => false,
        'show_admin_column' => false,
        'hierarchical' => false,
        'rewrite' => false,
        'query_var' => false
    );
    register_taxonomy('pc_lightboxes', null, $args);
}
add_action('init', 'pc_lightboxes_ct', 1);







// REGISTATION FORMS TAXONOMY
function pc_reg_form_ct() {
    $labels = array( 
        'name' => 'PrivateContent registration forms',
        'singular_name' => 'PrivateContent registration form',
        'search_items' => 'Search PrivateContent registration forms',
        'popular_items' => 'Popular PrivateContent registration forms',
        'all_items' => 'All PrivateContent registration forms',
        'parent_item' => 'Parent PrivateContent registration form',
        'parent_item_colon' => 'Parent PrivateContent registration form:',
        'edit_item' => 'Edit PrivateContent registration form',
        'update_item' => 'Update PrivateContent registration form',
        'add_new_item' => 'Add New PrivateContent registration form',
        'new_item_name' => 'New PrivateContent registration form',
        'separate_items_with_commas' => 'Separate privatecontent registration forms with commas',
        'add_or_remove_items' => 'Add or remove PrivateContent registration forms',
        'choose_from_most_used' => 'Choose from most used PrivateContent registration forms',
        'menu_name' => 'PrivateContent registration forms',
    );

    $args = array( 
        'labels' => $labels,
        'public' => false,
        'show_in_nav_menus' => false,
        'show_ui' => false,
        'show_tagcloud' => false,
        'show_admin_column' => false,
        'hierarchical' => false,
        'rewrite' => false,
        'query_var' => false
    );
    register_taxonomy('pc_reg_form', null, $args);
}
add_action('init', 'pc_reg_form_ct', 1);










/////////////////////////////////////////
/// USER RESERVED PAGE - CPT ////////////
/////////////////////////////////////////

add_action('init', 'register_pg_user_page', 1);
function register_pg_user_page() {
    $cpt   = 'pg_user_page';
    
    $labels = array( 
        'name'          => 'PrivateContent - '. esc_html__('User Reserved Pages', 'pc_ml'),
        'singular_name' => esc_html__('User reserved page', 'pc_ml'),
        'add_new'       => 'Add new user reserved page',
        'add_new_item'  => 'Add new user reserved page',
        'edit_item'     => esc_html__('Edit user reserved page', 'pc_ml'),
        'new_item'      => 'New user reserved page',
        'view_item'     => esc_html__('View user reserved page', 'pc_ml'),
        'search_items'  => 'Search user reserved page',
        'not_found'     => 'No user reserved page',
        'not_found_in_trash' => 'No user reserved pages found in trash',
        'parent_item_colon' => esc_html__('Parent User Page:', 'pc_ml'),
        'menu_name'     => 'PrivateContent - '. esc_html__('User Reserved Pages', 'pc_ml'),
    );

    $args = array( 
        'labels' => $labels,
        'hierarchical'      => false,
        'description'       => 'PrivateContent users reserved area',
        'supports'          => array('title', 'editor', 'thumbnail', 'revisions'),
        'show_in_rest'      => true, // Gutenberg
        
        'public'            => true,
        'show_ui'           => true,
		'show_in_menu'          => false,
        'show_in_nav_menus'     => false,
        'publicly_queryable'    => true,
        'exclude_from_search'   => true,
        'has_archive'       => false,
        'query_var'         => true,
        'can_export'        => true,
        'rewrite'           => false,
        'capability_type'   => $cpt,
		'map_meta_cap'      => true,
    );
    
    if(!ISPCF) {
        $args['supports'][] = 'comments';   
    }
    register_post_type($cpt, $args);
}



// Avoid direct page creation
function pc_avoid_manual_pvt_page_creation() {
	global $post_type;

    if('pg_user_page' == $post_type) {
		wp_die("Direct creation forbidden!");
	}
}
add_action('admin_head-post-new.php', 'pc_avoid_manual_pvt_page_creation', 1);




// Block CPT posts list page 
function pc_user_page_no_admin_list($current_screen) {
    
    if($current_screen->id == 'edit-pg_user_page') {
        wp_redirect( admin_url() . 'admin.php?page=pc_user_manage');
        exit;    
    }
}
add_action('current_screen', 'pc_user_page_no_admin_list');




// dynamically manage redirect to reach the user pvt page preview
function pc_user_page_preview_url() {
    global $post, $wpdb;
    
    if(!$post || !is_object($post)) {
        return false;    
    }
    if($post->post_type != 'pg_user_page') {
        return false;           
    }
    
    // full page override - GET request - ignore
    if(isset($_REQUEST['pc_pvt_page_full_override_token'])) {
        $stored_meta = get_post_meta($post->ID, 'pc_pvt_page_full_override_token', true);

        if($stored_meta == $_REQUEST['pc_pvt_page_full_override_token']) {
            if($_REQUEST['pc_is_user_loggged']) {
                $GLOBALS['pc_user_id'] = absint($_REQUEST['pc_user_id']);    
                
                if($_REQUEST['pc_wp_user_sync']) {
                    wp_set_auth_cookie(absint($_REQUEST['pc_wp_user_sync']));
                }
            }
            
            delete_post_meta($post->ID, 'pc_pvt_page_full_override_token');
            return false;    
        }
    }
    
    
    // ignore in Elementor builder
    if(defined('ELEMENTOR_URL') && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
        return false;
    }
    
    // ignore in Divi builder
    if(class_exists('ET_Builder_Element') && isset($_GET['et_fb']) && $_GET['et_fb'] === '1') {
        return false;
    }
    
    // ignore in WPbakery builder (front builder)
    if(is_plugin_active('js_composer/js_composer.php') && isset($_GET['vc_editable']) && isset($_GET['vc_post_id'])) {
        return false;
    }
    
    $target_page = (int)get_option('pg_target_page');
    if(!$target_page) {
        wp_safe_redirect(site_url());    
    }
    
    $user_data = $wpdb->get_row( $wpdb->prepare( 
        "SELECT id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE page_id = %d",
        $post->ID
    ) );
    
    if(!is_object($user_data)) {
        return false;    
    }
    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user($user_data->id)) {
        return false;   
    }
    
    if(!$target_page) {
        wp_safe_redirect(site_url());    
    }

    $link = get_permalink($target_page);
    $conj = (strpos($link, '?') === false) ? '?' : '&'; 

    $preview_link = $link.$conj. 'pc_pvtpag='. $user_data->id . '&pc_utok='.wp_create_nonce('lcwp_nonce');
    
    if(isset($_GET['preview_id'])) {
        $preview_link .= '&preview_id='. absint($_GET['preview_id']);     
    }
   
    wp_safe_redirect($preview_link);
    exit;
}
add_action('template_redirect', 'pc_user_page_preview_url', 1);





// Edit custom post type edit page 

// FIX FOR QTRANSLATE - to avoid qtranslate JS error i have to add title support to post type
// but I've hidden them with the CSS

// edit submitbox - hide minor submit minor-publishing and delete page

// Gutenberg tricks

add_action('admin_head-post.php', 'user_page_admin_script', 15);
function user_page_admin_script() {
    global $post_type, $wpdb;

	// frontend WPbakery exception
	if(is_plugin_active('js_composer/js_composer.php') && isset($_REQUEST['vc_action']) && $_REQUEST['vc_action'] == 'vc_inline') {
		return true;	
	}

    if('pg_user_page' == $post_type) {
		$inline_css = '
		.page-title-action,
		.add-new-h2,
		#titlediv,
		#slugdiv.postbox,
		.qtrans_title_wrap,
		.qtrans_title {
			display: none;	
		}
		
		#submitpost .misc-pub-post-status,
		#submitpost #visibility,
		#submitpost .misc-pub-curtime,
		#minor-publishing-actions,
		#delete-action {
			display: none;	
		}
		
		.updated.notice.notice-success a {
			display: none !important;
		}';
        wp_add_inline_style('pc-admin', $inline_css);
        
		
		// append username to the edit-page title 
		$user_data = $wpdb->get_row( $wpdb->prepare( 
			"SELECT id, username FROM ". esc_sql(PC_USERS_TABLE) ." WHERE page_id = %d",
			absint($_REQUEST['post'])
		) );
		$username = $user_data->username;
		
		$inline_js = '
        (function($) { 
            "use strict";  

            $(document).ready(function(){
                
                // Gutenberg
                if($(`body.block-editor-page`).length || $(`html.interface-interface-skeleton__html-container`).length ) {
                    let pc_upp_guten_title_trick_intval;
                    
                    pc_upp_guten_title_trick_intval = setInterval(function() {
                        if($(`.block-editor-block-list__layout.is-root-container`).length) {
                            $(`.block-editor-block-list__layout.is-root-container`).before(`
                                <div class="edit-post-visual-editor__post-title-wrapper">
                                    <h2 class="wp-block editor-post-title editor-post-title__block pc_upp_gutenberg_title">'. esc_attr($username) .'</h2>
                                </div>`); 
                            
                            clearInterval(pc_upp_guten_title_trick_intval);
                        }
                        
                        // remove useless commands
                        $(`body`).addClass(`pc_upp_guten`);
                    }, 50);
                }
                
                // old WP editor
                $(".wrap > h1, .wrap > h2").append(" - '. esc_attr($username) .'");
            });
        })(jQuery);';
        wp_add_inline_script('lc-wp-popup-message', $inline_js);
		
		
		// add preview link
		$container_id = get_option('pg_target_page');
		if(!empty($container_id)) {
			$link = get_permalink($container_id);
			$conj = (strpos($link, '?') === false) ? '?' : '&'; 
			
			$preview_link = $link.$conj. 'pc_pvtpag='.$user_data->id. '&pc_utok='.wp_create_nonce('lcwp_nonce');
			
			$inline_js = '
            (function($) { 
                "use strict";  
                
                $(document).ready(function(){
                    const pc_live_preview = 
                    `<a href="'. esc_attr($preview_link) .'" target="_blank" id="pc_pp_preview_link">'. esc_attr__("Live preview", 'pc_ml') .' &raquo;</a>`;

                    $(`#major-publishing-actions`).prepend(pc_live_preview);
                });
            })(jQuery);';
            wp_add_inline_script('lc-wp-popup-message', $inline_js);

		} // if pvt pag container exists - end
	}
}



/////////////////////////////////////////////////////////////////////////



// comments reply fix on pvt pages - always redirect to container
function pc_pvtpag_comment_redirect_fix() {
	$pvt_pag_id = get_option('pg_target_page');
	
	// Elementor preview exception
	if(pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        return true;
    }
	
	if(isset($_REQUEST['pg_user_page']) && !empty($pvt_pag_id) && !isset($_REQUEST['pc_pvt_page_full_override_token'])) {
		wp_safe_redirect(get_permalink($pvt_pag_id));	
        exit;
	}
}
add_action('template_redirect', 'pc_pvtpag_comment_redirect_fix', 1);




/////////////////////////////////////////////////////////////////////////




### REMOVE POST TYPE FROM YOAST SEO ###
function pc_exclude_upp_from_yoast_sitemaps($bool, $post_type) {
	if($post_type == 'pg_user_page') {
		return true;
	}
	return $bool;
}
add_filter('wpseo_sitemap_exclude_post_type', 'pc_exclude_upp_from_yoast_sitemaps', 99999, 2);


function pc_exclude_yoast_metabox_from_upp() {
	remove_meta_box('wpseo_meta', 'pg_user_page', 'normal');
}
add_action('add_meta_boxes', 'pc_exclude_yoast_metabox_from_upp', 999);
