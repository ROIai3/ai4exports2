<?php
// INITIALIZE GUTEN BLOCKS AND DEFINE HANDLERS
if(!defined('ABSPATH')) {exit;}



// register blocks
add_action('init', function() {
    if(!function_exists('register_block_type')) {
        return;
    }
    
    //////// not for WP 5.8 widgets.. for now
    if(isset($_SERVER["REQUEST_URI"]) && $_SERVER["REQUEST_URI"] == '/wp-admin/widgets.php') {
        return;
    }
    ////////
	
	$shortcodes = array(
		'registr/registr',
		'login/login',
		'logout/logout',
		'user_del/user_del',
	);
	foreach($shortcodes as $ch) {
		include_once(PC_DIR .'/builders_integration/guten_elements/'. $ch .'.php');	
	}
});





// enqueue scripts in gutenberg 
add_action('enqueue_block_editor_assets', function() {
    global $current_screen;

    //////// not for WP 5.8 widgets.. for now
    if(isset($_SERVER["REQUEST_URI"]) && $_SERVER["REQUEST_URI"] == '/wp-admin/widgets.php') {
        return;
    }
    ////////
    
    $deps = array(
        'wp-blocks',
        'wp-i18n',
        'wp-element',
    );  
    if($current_screen->base != 'widgets') {
        $deps[] = 'wp-editor';     
    }
    
	wp_enqueue_script(
		'lc_guten_toolkit',
		PC_URL .'/builders_integration/guten_elements/common.js',
		$deps,
		'1.2.3',
		true
	);
	
	
	
	$shortcodes = array(
		'pc-registration-form'    => 'registr/registr',
		'pc-login-form'		      => 'login/login',
		'pc-logout-box'		      => 'logout/logout',
		'pc-user-del-box'		  => 'user_del/user_del',
	);
	foreach($shortcodes as $key => $script_name) {
		wp_enqueue_script(
			'lcweb/'. $key,
			PC_URL .'/builders_integration/guten_elements/'. $script_name .'.js',
			$deps,
			PC_VERS, 
			true
		);
	}
	
	
	
	// Block panels
	$panels = array(
	   'main' => array(
			'title' 	=> esc_html__('Main parameters', 'pc_ml'),
			'opened' 	=> true
		),
	);
	wp_localize_script('wp-blocks', 'pc_panels', $panels);
	
	
	
	// hook for additional scripts
	if(!did_action('lc_guten_scripts')) {
		$GLOBALS['lc_guten_scripts'] = true;
		do_action('lc_guten_scripts');
	}
}, 1); // important priority #1 to let add-ons to use its functions






// register GG blocks category
add_filter('block_categories_all', function($categories, $post) {
	return array_merge(
		$categories,
		array(
			array(
				'slug' 	=> 'lc-pvtcontent',
				'title' => 'PrivateContent',
			),
		)
	);
}, 10, 2);





// hook for custom scripts in gutenberg head
if(!did_action('lc_scripts_in_guten_head')) {
	add_action('admin_head', function() {
		do_action('lc_scripts_in_guten_head');
	}, 999);
}






// remote handler for ServerSideRender blocks
function pc_guten_handler_common() {
	$code = '';
	
	if(get_option('pg_inline_css')) {
		ob_start();
		pvtcont_inline_css();
		$code .= ob_get_clean();
	}
	return $code;
}

function pc_guten_atts_compile($atts) {
	$compiled = array();
	foreach($atts as $key => $val) {
		$val = ($val === true || $val === 'true') ? 1 : esc_attr($val);
        
        // SPECIAL FIX for "align" keyword
        if($key == 'pc_align') {
            $key = 'align';    
        }
        
		$compiled[] = $key .'="'. $val .'"'; 	
	}

	return implode(' ', $compiled);
}





// fixes WP > 5.5 fields type declaration deprecation 
function pc_fix_block_defs($array) {
    foreach($array as $fid => $fdata) {
        $array[$fid]['lc_type'] = $array[$fid]['type'];
        $array[$fid]['type'] = (in_array($array[$fid]['type'], array('number', 'slider'))) ? 'number' : 'string'; 
    }
    
    return $array;
}




// fixing Gutenberg front rendering issue with ToggleControl field
function pc_guten_ToggleControl_val_fix($parsed_block) {
    if(strpos((string)$parsed_block['blockName'], 'lcweb/') !== false && isset($parsed_block['attrs']) && is_array($parsed_block['attrs'])) {
        
        foreach($parsed_block['attrs'] as $key => $val) {
  
            if($val === true) {
                $parsed_block['attrs'][$key] = '1';    
            }
            elseif($val === false) {
                $parsed_block['attrs'][$key] = '';    
            }        
        } 
    }
    
    return $parsed_block;
}
add_filter('render_block_data', 'pc_guten_ToggleControl_val_fix', 10);





///////////////////////////////////////////////////////////






function pvtcont_registr_guten_handler($atts) {
	$code = pc_guten_handler_common();
	return $code . do_shortcode('[pc-registration-form '. pc_guten_atts_compile($atts) .']');
}


function pvtcont_login_guten_handler($atts) {
	$code = pc_guten_handler_common();
	return $code . do_shortcode('[pc-login-form '. pc_guten_atts_compile($atts) .']');
}


function pvtcont_logout_guten_handler($atts) {
	$code = pc_guten_handler_common();
	return $code . do_shortcode('[pc-logout-box '. pc_guten_atts_compile($atts) .']');
}


function pvtcont_user_del_guten_handler($atts) {
	$code = pc_guten_handler_common();
	return $code . do_shortcode('[pc-user-del-box '. pc_guten_atts_compile($atts) .']');
}
