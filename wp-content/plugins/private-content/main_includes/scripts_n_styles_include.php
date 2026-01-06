<?php
// MAIN SCRIPT & CSS INCLUDES
if(!defined('ABSPATH')) {exit;}


// global script enqueuing
function pvtcont_global_scripts() { 
	wp_enqueue_script("jquery"); 
	wp_enqueue_style('pc-fontawesome', PC_URL .'/css/fontAwesome/css/all.min.css', 999, '5.15.2');
    wp_enqueue_script('lc-select', PC_URL .'/js/lc-select/lc_select.min.js', 1, '1.1.10', true);
    wp_enqueue_script('lc-switch-v2', PC_URL .'/js/lc-switch/lc_switch.min.js', 200, '2.0.4', true);
    
    // magpop - frontend scripts register to be optionally enqueued
    wp_register_style('pc_lightbox', PC_URL .'/js/magnific_popup/magnific-popup.css', 100, '1.1.0');
    wp_register_script('pc_lightbox', PC_URL .'/js/magnific_popup/magnific-popup.pckg.js', 100, '1.1.0', true);
    
    
	$is_admin = is_admin();

	// admin css
	if($is_admin) {  
        global $current_screen;
        if(!$current_screen) {
            return;    
        }
        
		wp_enqueue_style('pc-admin', PC_URL .'/css/admin.css', 1500, PC_VERS);
        
		// add tabs scripts
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_script('jquery-ui-autocomplete'); 
        
        // LC tools
        wp_enqueue_script('lc-range-n-num',     PC_URL .'/js/lc-range-n-num/lc_range_n_num.min.js', 200, '1.0.1', true);
        wp_enqueue_script('lc-wp-popup-message',PC_URL .'/js/lc-wp-popup-message/lc_wp_popup_message.min.js', 200, '1.2.1', true);
        
        wp_enqueue_style('lcwp-lc-select',      PC_URL .'/js/lc-select/themes/lcwp_prefixed.css', 230, PC_VERS);
        
        
        // magpop
        wp_enqueue_style('lcwp_magpop', PC_URL .'/js/magnific_popup/magnific-popup.css');
        wp_enqueue_script('lcwp_magpop', PC_URL .'/js/magnific_popup/magnific-popup.pckg.js', 100, '1.1.0', true);
        
        
        // settings page scripts
        if($current_screen->base == 'privatecontent_page_pc_settings') {
            $baseurl = PC_URL .'/js';
            wp_enqueue_style('pc_settings', PC_URL .'/settings/settings_style.css', 999, PC_VERS);	
            wp_enqueue_script('lc-color-picker',    $baseurl .'/lc-color-picker/lc_color_picker.min.js', 200, '2.0.0', true);
            
            $cm_settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
            wp_localize_script('jquery', 'lc_settings_css_codemirror_config', $cm_settings);
            wp_enqueue_style('wp-codemirror');
        }
        
        
        // PvtContent Free - JS
        if(ISPCF) {
            wp_enqueue_script('pc-free-extra-js', PC_URL .'/js/admin.js', 999,  PC_VERS);

            wp_localize_script('pc-free-extra-js', 'nfpcf_vars', array(
                'tt_txt' => '<p>'. esc_html__('This feature is included in the premium version of PrivateContent', 'pc_ml') .'.</p>'. esc_html__('Click to read more!', 'pc_ml'),
            ));  
            
            
            wp_add_inline_style('pc_admin', '
            .pc_nfpcf_w_btn:not(.lcwp_sf_tr):after, 
            .pc_nfpcf_w_btn .lcwp_sf_field:after {
                content: "'. esc_attr__('Premium version only', 'pc_ml') .'";
            }');
            
            wp_add_inline_script('pc-free-extra-js', '
            (function($) {
                "use strict";

                $(document).on("click", ".pc_nfpcf", function() {
                    window.location.href = "'. esc_js(admin_url('admin.php?page=pc_premium_adv')) .'"; 
                });
            })(jQuery);');
        }
	}
	
	
	// frontend
	if(!$is_admin || isset($GLOBALS['lc_guten_scripts'])) {
		wp_enqueue_script('jquery');
        wp_enqueue_script('pc_frontend', PC_URL .'/js/frontend.min.js', array('jquery', 'lc-select', 'lc-switch-v2'), PC_VERS, true);	
		
        /* NFPCF */
		// if using recaptcha system
		if(get_option('pg_antispam_sys') == 'recaptcha') {
			wp_enqueue_script('grecaptcha', 'https://www.google.com/recaptcha/api.js?render='.get_option('pg_recaptcha_public'));	
		}
        elseif(get_option('pg_antispam_sys') == 'recaptcha_v2') {
			wp_enqueue_script('grecaptcha', 'https://www.google.com/recaptcha/api.js?render=explicit');	
		}
		
        
        
        // dynamic JS vars 
        $array = array(
            'uid'       => (isset($GLOBALS['pc_user_id'])) ? (int)$GLOBALS['pc_user_id'] : 0,
            'nonce'     => wp_create_nonce('pvtcont_ajax'),
            'ajax_url'  => untrailingslashit(admin_url('admin-ajax.php')),
            'use_pcac'  => (get_option('pg_do_not_use_pcac')) ? false : true,
            
            'dike_slug'         => PC_DIKE_SLUG, /* NFPCF */
            'lcslt_search'      => esc_attr__('search options', 'pc_ml'),
            'lcslt_add_opt'     => esc_attr__('add options', 'pc_ml'),
            'lcslt_select_opts' => esc_attr__('Select options', 'pc_ml'),
            'lcslt_no_match'    => esc_attr__('no matching options', 'pc_ml'),
            
            'antispam_sys'      => get_option('pg_antispam_sys', 'honeypot'),
            'recaptcha_sitekey' => (get_option('pg_antispam_sys', 'honeypot') == 'honeypot') ? '' : get_option('pg_recaptcha_public'),
            'fluid_form_thresh' => (int)get_option('pg_fluid_form_threshold', 315),
            'ajax_failed_mess'  => esc_attr__('Error performing the operation', 'pc_ml'), 
            'html5_validation'  => (get_option('pg_no_html5_validation')) ? false : true,
            'hide_reg_btn_on_succ' => (get_option('pg_hide_reg_btn_on_success', 1)) ? false : true,
            'revealable_psw'    => (get_option('pg_single_psw_f_w_reveal')) ? true : false,
            
            'abfa_blocked'      => (pc_abfa_static::visitor_is_blacklisted()) ? true : false,
            'abfa_error_mess'   => pc_abfa_static::error_message(),
        );
        $array = (array)apply_filters('pc_dynamic_js_vars', $array);
        wp_localize_script('pc_frontend', 'pc_vars', $array);  
	}
    
    
	
	// lightbox scripts - only if there are instances /* NFPCF */
	if(!is_admin() && wp_count_terms('pc_lightboxes', array('hide_empty' => false))) {
		$GLOBALS['pvtcont_has_lightboxes'] = true;
		
		wp_enqueue_style('pc_lightbox');	
		wp_enqueue_script('pc_lightbox');	
	}
	
    
    
	// custom frontend style - only if is not disabled by settings
	if((!$is_admin || isset($GLOBALS['lc_guten_scripts'])) && !get_option('pg_disable_front_css')) {
        pc_static::be_sure_dynamic_css_exists();
		wp_enqueue_style('pc_frontend', PC_URL .'/css/frontend.min.css', 998, PC_VERS);
        
        if(class_exists('DiviExtension')) {
            wp_enqueue_style('pc-divi-frontend', PC_URL .'/css/frontend.min_for_divi.css', 998, PC_VERS);	     
        }
        
        
		if(!get_option('pg_inline_css')) {
			wp_enqueue_style('pc_style', PC_URL .'/css/custom.css', 999, PC_VERS .'-'. get_option('pc_dynamic_scripts_id'));		
		} 
        else {
            pvtcont_inline_css();
		}
	}
}
add_action('wp_enqueue_scripts', 'pvtcont_global_scripts', 900);
add_action('admin_enqueue_scripts', 'pvtcont_global_scripts', 9999); // be sure to enqueue them later to allow pc_displaynone 
add_action('login_enqueue_scripts', 'pvtcont_global_scripts');
add_action('lc_guten_scripts', 'pvtcont_global_scripts');



// use custom style inline
function pvtcont_inline_css() {
	if(!isset($GLOBALS['pvtcont_printed_inline_css'])) { // avoid double enqueuing with Gutenberg
        $GLOBALS['pvtcont_printed_inline_css'] = true;

        ob_start();
        include_once(PC_DIR .'/main_includes/custom_style.php');
        wp_add_inline_style('pc_frontend', ob_get_clean());
    }
}



// forms style, bottom-border and no-label style - body class
add_filter('body_class', function($classes) { 
	if(get_option('pg_bottomborder')) {
		$classes[] = 'pc_bottomborder';	
	}
	if(get_option('pg_nolabel')) {
		$classes[] = 'pc_nolabel';	
	}
	return $classes;
});	