<?php
/* 
Plugin Name: PrivateContent
Plugin URI: https://lcweb.it/privatecontent-multilevel-content-plugin/
Description: The WordPress multilevel contents restriction solution
Author: Luca Montanari (LCweb)
Author URI: https://lcweb.it
Version: 9.3.11
Requires at least: 5.0
Requires PHP: 7.0
WC requires at least: 7.0
WC tested up to: 9.8
*/  

if(!defined('ABSPATH')) {exit;}


/////////////////////////////////////////////
/////// MAIN DEFINES ////////////////////////
/////////////////////////////////////////////

// plugin path
$wp_plugin_dir = substr(plugin_dir_path(__FILE__), 0, -1);
define('PC_DIR', $wp_plugin_dir);

// plugin url
$wp_plugin_url = substr(plugin_dir_url(__FILE__), 0, -1);
define('PC_URL', $wp_plugin_url);



// plugin version
define('PC_VERS', '9.3.11');

// indovina indovinello
define('ISPCF', false);





/////////////////////////////////////////////
/////// FORCING DEBUG ///////////////////////
/////////////////////////////////////////////

/* NFPCF */
if(isset($_REQUEST['pc_php_debug'])) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);	
}





/////////////////////////////////////////////
/////// MULTILANGUAGE SUPPORT ///////////////
/////////////////////////////////////////////

add_action('init', function() {
    if(isset($GLOBALS['is_pc_bundle'])) {
        return false;
    }
    
    $ml_key = 'pc_ml';
    $basedir = PC_DIR;
    
    if(version_compare($GLOBALS['wp_version'], '6.7', '<')) {
        $param_array = explode('/', $basedir);

        if(is_admin()) {
            load_plugin_textdomain($ml_key, false, end($param_array) .'/lang_admin');  
        }
        load_plugin_textdomain($ml_key, false, end($param_array) .'/languages');
    }
    else {
        if(is_admin()) {
            load_textdomain($ml_key, $basedir .'/lang_admin/'. $ml_key .'-'. determine_locale() .'.mo');
        }
        load_textdomain($ml_key, $basedir .'/languages/'. $ml_key .'-'. determine_locale() .'.mo');
    }
}, 1);





/////////////////////////////////////////////
/////// DATABASE MANAGEMENT /////////////////
/////////////////////////////////////////////

// database table constants
function pvtcont_db_constants() {
	global $wpdb;
	
	if(!defined('PC_USERS_TABLE')) {
		define('PC_USERS_TABLE', $wpdb->prefix . "pc_users");
		define('PC_META_TABLE', $wpdb->prefix . "pc_user_meta");
	}
}
add_action('init', 'pvtcont_db_constants', 1);

// on specific recall
if(isset($_GET['pvtcont_db_check'])) {
    include_once(PC_DIR .'/classes/upgrader_operations.php');
    add_action('admin_init', 'pvtcont_upgrader_operations::db_manag', 1);
}





//////////////////////////////////////////////
// DEFINING A SAFE HOOK FOR INIT OPERATIONS //
//////////////////////////////////////////////

add_action('wp_loaded', function() {
    if(!did_action('pvtcont_init')) {
        do_action('pvtcont_init');
    }
}, 1);





///////////////////////////////////////////////////////////////
/////// FLAG FOR LOGGED WP USER - IF CAN BYPASS RESTRICTIONS //
///////////////////////////////////////////////////////////////

function pvtcont_setup_wp_use_pass() {
    if(!defined('PVTCONT_CURR_USER_MANAGEABLE_CATS')) {
        $pc_cats_for_curr_user = pc_wpuc_static::get_wp_user_editable_pc_cats();  
        define('PVTCONT_CURR_USER_MANAGEABLE_CATS', $pc_cats_for_curr_user);
    }
    
    if(!defined('PVTCONT_WP_USER_PASS')) {
		$cuc_see = pc_wpuc_static::current_wp_user_bypass_restrictions();
		$val = (!is_user_logged_in() || !$cuc_see) ? false : true;
		define('PVTCONT_WP_USER_PASS', $val);
	}
}
add_action('pvtcont_init', 'pvtcont_setup_wp_use_pass', 1);





///////////////////////////////////////////////////////////////
//// CUSTOM WP USER ROLE TO ALLOW PVTCONTENT-ONLY MANAGEMENT //
///////////////////////////////////////////////////////////////

add_role('pvtcontent_admin', 'PrivateContent Admin',
    array(
        'read' => true,
        'view_admin_dashboard' => true, // woocommerce trick to allow backend reaching
    )
);


// be sure WP doesn't assign extra capabilities to the role
add_filter('user_has_cap', function($allcaps, $caps, $args, $class) {
    if(!is_user_logged_in()) {
        return $allcaps;    
    }
    
    $user_meta = get_userdata(get_current_user_id());
    if(!is_object($user_meta)) {
        return $allcaps;
    }
    
    $user_roles = $user_meta->roles;
    if(!is_array($user_roles) || !in_array('pvtcontent_admin', $user_roles)) {
        return $allcaps;    
    }

    foreach($allcaps as $cap => $bool) {
        if(
            $cap != 'read' && $cap != 'view_admin_dashboard' && $cap != 'pvtcontent_admin' &&
            strpos($cap, 'read_') === false && strpos($cap, 'pg_user_pages') === false
        ) {
            
            $allcaps[$cap] = false;    
        }
    }
    
    return $allcaps;
}, 9999, 4);





///////////////////////////////////////////////////////////////
//// CUSTOM LABELS IN PAGES LIST TO HELP ORIENTATING //////////
///////////////////////////////////////////////////////////////

add_filter('display_post_states', function($states, $post) {
    
    // user pvt page container
    if($post->ID == get_option('pg_target_page')) {
        $states['pc_upp_container'] = esc_html__('User Reserved Pages Container', 'pc_ml');    
    }
    
    // main redirect target
    if($post->ID == get_option('pg_redirect_page')) {
        $states['pc_redirect_page'] = esc_html__('Redirect Restriction Target', 'pc_ml');    
    }
 
    return $states;
}, 10, 2);





///////////////////////////////////////////////
//// PLUGINS LIST - CUSTOM LINKS //////////////
///////////////////////////////////////////////

if(ISPCF) {
    add_filter('plugin_row_meta', function($links, $file) {
        if($file != 'private-content-free/private-content-free.php') {
            return $links;    
        }

        $links['pcf_doc_link'] = '<a href="https://charon.lcweb.it/910ca31f" target="_blank" class="pcf_pl_links"><i class="dashicons dashicons-book"></i>'. esc_html__('Documentation', 'pc_ml') .'</a>';

        $links['pcf_go_premium_link'] = '<a href="https://charon.lcweb.it/8260d9df?ref=pc_addons_adv" target="_blank" class="pcf_pl_links pcf_pl_links_filled"><i class="dashicons dashicons-star-filled"></i>'. esc_html__('Switch to premium', 'pc_ml') .'</a>';

        return $links;
    }, 100, 2);
}





//////////////////////////////////////////////////
/////////// MAIN INCLUDES ////////////////////////
//////////////////////////////////////////////////

include_once(PC_DIR .'/classes/pc_static.php');

$to_not_include = array(
    'pc_static',
    'users_import_export',
    'engine_import_export',
    'datetime_getimestamp_fix',
    'lc_fontAwesome_helper',
    'PhpXlsxGenerator',
    'simple_form_validator',
);  
pc_static::include_all(PC_DIR .'/classes', $to_not_include);


$to_not_include = array(
    'addons_adv',
    'premium_adv',
    'custom_style',
    'db_manag',
    'upgrader_operations',
    'users_list',
); 
pc_static::include_all(PC_DIR .'/main_includes', $to_not_include);


$to_not_include = array(
    'comment_hack',
); 
pc_static::include_all(PC_DIR .'/restrictions', $to_not_include);


$to_not_include = (isset($_SERVER["REQUEST_URI"]) && $_SERVER["REQUEST_URI"] == '/wp-admin/widgets.php') ? array('gutenberg') : array();
pc_static::include_all(PC_DIR .'/builders_integration', $to_not_include);


include_once(PC_DIR .'/user_dashboard/ajax.php');





/////////////////////////////////////////////
////// ACTIONS ON PLUGIN ACTIVATION /////////
/////////////////////////////////////////////

register_activation_hook(__FILE__, function() {
    if(!defined('ABSPATH')) {
        return false;   
    }
    
	// prevent multisite activation
    if(is_multisite()) {
        wp_die('PrivateContent cannot be network activated. Please enable it into subsites panel');
    }

    // create custom CSS
    pc_static::be_sure_dynamic_css_exists();
    delete_option('pc_recheck_dynamic_css'); // TODO - delete in a far future /* NFPCF */
	
    include_once(PC_DIR .'/classes/upgrader_operations.php');
	pvtcont_upgrader_operations::run();
});



function pvtcont_avoid_duplicates() {
    $btn = '<br/><a href="'. esc_attr(network_admin_url('plugins.php')) .'">'. esc_html__('Return to plugins page', 'pc_ml') .'</a>';

    if(is_plugin_active('pvtcontent_bundle/pvtcontent_bundle.php') && strpos(__FILE__, '/pvtcontent_bundle/') === false) {
        deactivate_plugins(plugin_basename( __FILE__ ));
        wp_die(esc_html__('The PrivateContent bundle pack is already activated, PrivateContent cannot be activated', 'pc_ml') . wp_kses_post($btn));
    }
    elseif(ISPCF && strpos(__FILE__, '/private-content-free/') !== false && is_plugin_active('private-content/private_content.php')) {
        deactivate_plugins(plugin_basename( __FILE__ ));
        wp_die(esc_html__('The premium version of PrivateContent is already activated, PrivateContent Free cannot be activated', 'pc_ml') . wp_kses_post($btn)); 
    }
}
register_activation_hook(__FILE__, 'pvtcont_avoid_duplicates');
add_action('admin_init', 'pvtcont_avoid_duplicates', 1);





////////////
// DIKE WP DASHBOARD
/* NFPCF */

$pc_dike_slug = (isset($GLOBALS['is_pc_bundle'])) ? 'pcbp' : 'pc';
define('PC_DIKE_SLUG', $pc_dike_slug);

function pc_dike_updater_data($data) {
    include_once(PC_DIR .'/classes/upgrader_operations.php');
    
    $data['pc'] = array(
        'callback' => 'pvtcont_upgrader_operations::run',
        'no_files_del' => false,
    );
    return $data;
}
add_filter('dike_lcweb_updater', 'pc_dike_updater_data');


function pc_dike_plc_sc($sc) {
    if(!isset($sc[PC_DIKE_SLUG])) {
        $sc[PC_DIKE_SLUG] = array();    
    }
    
    $sc[PC_DIKE_SLUG] = array_merge($sc[PC_DIKE_SLUG], array('pc-login-form', 'pc-logout-box', 'pc-user-del-box', 'pc-registration-form', 'pc-pvt-content', 'pc-user-pvt-page-contents'));
    return $sc;
}
add_filter('dike_lcweb_sc', 'pc_dike_plc_sc');
    
include_once(PC_DIR .'/DIKE/register.php');

////////////






// declare Woo HPOS compatibility
add_action('before_woocommerce_init', function() {
	if(class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class) && !isset($GLOBALS['is_pc_bundle'])) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});





/* NFPCF */
// be sure add-ons are up to date to avoid fatal errors
add_action('admin_init', function() {
    if(isset($GLOBALS['is_pc_bundle'])) {
        return true;   
    }
    if(get_option('pc_forced_addon_deactiv')) {
        $involved_addons = array_map('esc_html', (array)get_option('pc_forced_addon_deactiv'));
        
        /* translators: 1: version number. */
        $error = '<h3>'. sprintf(esc_html__('These add-ons are incompatible with PrivateContent %s and have been disabled. Please update them to the last version before enabling again!', 'pc_ml'), PC_VERS) .'</h3>
        
        <ul><li>'. implode('</li><li>', $involved_addons) .'</li></ul>
        <p><a href="'. esc_url(admin_url('plugins.php')) .'">'. esc_html__('Check plugins list', 'pc_ml') .' &raquo;</a></p>';
        
        delete_option('pc_forced_addon_deactiv');
        wp_die($error);
    }

    
    $requests = array(
        'PCUD_VER' => array(
            'min_v' => '3.5.8',
            'name' => 'User Data add-on',
            'basename' => 'private-content-user-data/pc_user_data.php'
        ),
        'PCMA_VER' => array(
            'min_v' => '2.4.4',
            'name' => 'Mail Actions add-on',
            'basename' => 'private-content-mail-actions/pc_mail_actions.php'
        ),
        'PCPP_VER' => array(
            'min_v' => '2.9.4',
            'name' => 'Premium Plans add-on',
            'basename' => 'private-content-premium-plans/pc_premium_plans.php'
        ),
        'PCFM_VER' => array(
            'min_v' => '1.7.7',
            'name' => 'Files Manager add-on',
            'basename' => 'private-content-files-manager/pc_files_manager.php'
        ),
        'PCUA_VER' => array(
            'min_v' => '1.1.3',
            'name' => 'User Activities add-on',
            'basename' => 'private-content-user-activities/pc_user_activities.php'
        ),
    );

    $deactivated = array();
    foreach($requests as $ver_const => $data) {
        if(defined($ver_const) && version_compare(constant($ver_const), $data['min_v'], '<')) {
            deactivate_plugins($data['basename']);
            $deactivated[] = $data['name'];
        }
    }

    if(!empty($deactivated)) {
        update_option('pc_forced_addon_deactiv', $deactivated, false);
    }
}, 1);

