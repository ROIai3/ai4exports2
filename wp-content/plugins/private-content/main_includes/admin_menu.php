<?php
// setting up admin menu
function pc_users_admin_menu() {	

	$logo_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNiAxNiI+PHBvbHlnb24gcG9pbnRzPSIxMy4wMiAxIDMuMTMgMSAxIDIuNyAxLjE2IDIuNyAxNSAyLjcgMTMuMDIgMSIgc3R5bGU9ImZpbGw6IzljYTJhNyIvPjxwYXRoIGQ9Ik0xLDMuMzJWMTVIMTVWMy4zMlpNOCw0LjZBMi4yMiwyLjIyLDAsMSwxLDUuNzksNi44MiwyLjIyLDIuMjIsMCwwLDEsOCw0LjZabTMsOC44N0g0LjkxQS44Ny44NywwLDAsMSw0LDEyLjU5LDQsNCwwLDAsMSw2LjM2LDkuMzZhMS43OCwxLjc4LDAsMCwwLDMuMiwwLDQsNCwwLDAsMSwyLjMyLDMuMjNBLjg4Ljg4LDAsMCwxLDExLDEzLjQ3WiIgc3R5bGU9ImZpbGw6IzljYTJhNyIvPjwvc3ZnPg==';
    
    $addons_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNiAxNiI+PHBvbHlnb24gcG9pbnRzPSIxMy4wMiAxIDMuMTMgMSAxIDIuNyAxLjE2IDIuNyAxNSAyLjcgMTMuMDIgMSIgc3R5bGU9ImZpbGw6IzljYTJhNyIvPjxwYXRoIGQ9Ik0xLDMuMzJWMTVIMTVWMy4zMlpNMTEuMzEsMTBIOC42OHYyLjYyYS42Ni42NiwwLDEsMS0xLjMxLDBWMTBINC43NGEuNjYuNjYsMCwwLDEtLjY1LS42Ni42NS42NSwwLDAsMSwuNjUtLjY1SDcuMzdWNkEuNjYuNjYsMCwwLDEsOC42OCw2VjguNjZoMi42M2EuNjUuNjUsMCwwLDEsLjY1LjY1QS42Ni42NiwwLDAsMSwxMS4zMSwxMFoiIHN0eWxlPSJmaWxsOiM5Y2EyYTciLz48L3N2Zz4=';
	
    $cuc_edit = pc_wpuc_static::current_wp_user_can_edit_pc_user('some');
    if(!$cuc_edit) {
        return false;
    }
    
    $pc_cat_cap = (get_option('pg_pvtpage_overriding_method')) ? 'man_pg_user_categories' : 'manage_categories';    
      
    // get first user role to be used in add_menu_page()
    $curr_user = wp_get_current_user();
    $first_user_role = (count((array)$curr_user->roles)) ? reset($curr_user->roles) : false;  

    ### main menu ###
	add_menu_page('PrivateContent', 'PrivateContent', $first_user_role, 'pc_user_manage', 'pc_users_overview', $logo_svg, 46);
	
	### submenus ###
	$ulist_hook = add_submenu_page('pc_user_manage', esc_html__('Users List', 'pc_ml'), esc_html__('Users List', 'pc_ml'), $first_user_role, 'pc_user_manage', 'pc_users_overview');
    add_submenu_page('pc_user_manage', esc_html__('Add User', 'pc_ml'), esc_html__('Add User', 'pc_ml'), $first_user_role, 'pc_user_dashboard', 'pc_user_dashboard');	
	add_submenu_page('pc_user_manage', esc_html__('User Categories', 'pc_ml'), esc_html__('User Categories', 'pc_ml'), $pc_cat_cap, 'edit-tags.php?taxonomy=pg_user_categories');
    add_submenu_page('pc_user_manage', esc_html__('Import / Export Hub', 'pc_ml'), esc_html__('Import / Export Hub', 'pc_ml'), $first_user_role, 'pc_import_export', 'pc_import_export');
	add_submenu_page('pc_user_manage', esc_html__('Settings', 'pc_ml'), esc_html__('Settings', 'pc_ml'), 'manage_options', 'pc_settings', 'pc_settings');
	
    
    // inject pending users count in menu
    if(isset($GLOBALS['pvtcont_pending_users_count']) && (int)$GLOBALS['pvtcont_pending_users_count']) {
        pc_pending_menu_warn();
    }
	
	// add-ons adv
	if(!isset($GLOBALS['is_pc_bundle']) && !ISPCF) {
		$remaining = pc_static::addons_not_installed();
		if(!empty($remaining)) {
			
			$txt = '<strong class="pc_getaddons_menu"><span class="dashicons dashicons-star-filled"></span> '. esc_html__('Explore Add-ons', 'pc_ml') .'</strong>';
			add_submenu_page('pc_user_manage',$txt , $txt, $first_user_role, 'pc_addons_adv', 'pc_addons_adv');	
		}
	}
    
    // premium version adv
	if(ISPCF) {
		$txt = '<strong class="pc_getaddons_menu"><span class="dashicons dashicons-star-filled"></span> '. esc_html__('Switch to premium', 'pc_ml') .'</strong>';
        add_submenu_page('pc_user_manage',$txt , $txt, $first_user_role, 'pc_premium_adv', 'pc_premium_adv');
	}
    
    

	### add-ons ###
	add_menu_page('PvtCont Add-ons', 'PvtCont Add-ons', $first_user_role, 'pc_addons', '#', $addons_svg, 47);
	
	// PC-ACTION - offer an hook to add add-ons submenus and order them (passes menu slug)
	do_action('pc_addons_menu_ready', 'pc_addons');
	
	// be sure at least a submenu exists
	global $menu, $submenu;
	if(isset($submenu['pc_addons']) && count($submenu['pc_addons'])) {
		
		// prepend a fake element and hide second one to show every submenu's label
		$fake_submenu = array(
			'PvtCont Add-ons',
			$first_user_role,
			'javascript:void(0)',
			'PvtCont Add-ons'
		);
		array_unshift($submenu['pc_addons'], $fake_submenu);
		
		unset($submenu['pc_addons'][1]);
	}
	else {
		remove_menu_page('pc_addons');	
	}
    
    // users list screen options
    add_action("load-" . $ulist_hook, "pc_ulist_page_opts");
    add_filter('manage_'. $ulist_hook .'_columns', 'pc_ulist_man_columns');
}
add_action('admin_menu', 'pc_users_admin_menu', 999);




// fix to set the taxonomy and user pages as menu page sublevel
function user_cat_tax_menu_correction($parent_file) {
	global $current_screen;

	// hack for taxonomy
	if(isset($current_screen->taxonomy)) {
		$taxonomy = 'pg_user_categories';
		if($taxonomy == $current_screen->taxonomy) {
			$parent_file = 'pc_user_manage';
		}	
	}
	
	// hack for user pages
	if(isset($current_screen->base)) {
		$page_type = 'pg_user_page';
		if($current_screen->base == 'post' && $current_screen->id == $page_type) {
			$parent_file = 'pc_user_manage';
		}
	}
	
	return $parent_file;
}
add_action('parent_file', 'user_cat_tax_menu_correction');





////////////////////////////////////////////
// USER MANAGEMENT PAGES ///////////////////
////////////////////////////////////////////

// users list
function pc_users_overview() {
    include_once(PC_DIR . '/main_includes/users_list.php');
}

// add user - user dashboard
function pc_user_dashboard() {
    include_once(PC_DIR . '/user_dashboard/view.php');
}

// import and export users
function pc_import_export() {
    include_once(PC_DIR . '/users_import_export/imp_exp_hub.php');
}

// settings
function pc_settings() {
    include_once(PC_DIR.'/settings/view.php');
}  

// addons adv
function pc_addons_adv() {
    include_once(PC_DIR.'/main_includes/addons_adv.php');
}

// premium version adv
function pc_premium_adv() {
    include_once(PC_DIR.'/main_includes/premium_adv.php');
} 




////////////////////////////////////////////////////////////////////////////////////////




// export users security trick - avoid issues related to php warnings
function pc_export_buffer() {
	ob_start();
}
add_action('admin_init', 'pc_export_buffer', 2);




// AVOID issues with bad servers in settings redirect
// + export users security trick - avoid issues related to php warnings
function pc_settings_redirect_trick() {
    if(
        (isset($_GET['page']) && $_GET['page'] == 'pc_settings') ||
        (isset($_GET['page']) && $_GET['page'] == 'pc_import_export')
    ) {
	   ob_start();
    }
}
add_action('admin_init', 'pc_settings_redirect_trick', 1);





////////////////////////////////////////////////////////////////////////////////////////





// users list - custom screen options
function pc_ulist_page_opts() {
	$args = array(
		'label' => esc_html__('Users per page', 'pc_ml'),
		'default' => 20,
		'option' => 'pc_ulist_per_page'
	);
	add_screen_option('per_page', $args);
}


// users list - custom screen options - use WP egnine to manage PC columns
function pc_ulist_man_columns($columns) {
    $columns = array(
        //'_title'    => 'Columns',
        
        'pc_name'     => (get_option('pg_use_first_last_name')) ? esc_html__('First name', 'pc_ml') : esc_html__('Name', 'pc_ml'), // use a different index to avoid interferences
		'surname'     => (get_option('pg_use_first_last_name')) ? esc_html__('Last name', 'pc_ml') : esc_html__('Surname', 'pc_ml'),
		'categories'  => esc_html__('Categories', 'pc_ml'),
		'email'       => esc_html__('E-Mail', 'pc_ml'), 
		'tel'         => esc_html__('Telephone', 'pc_ml'),
        
		'insert_date' => esc_html__('Registered on', 'pc_ml'), 
		'last_access' => esc_html__('Last access', 'pc_ml'),
    );
    
    // PC-FILTER - allow custom columns to be shown in users list page and manageable in screen options
    return apply_filters('pc_users_list_so_fields', $columns);
}


// users list - custom screen options - hidden PC columns
function pc_ulist_hidden_columns($hidden, $screen, $use_defaults) {
    if($screen->base == 'toplevel_page_pc_user_manage') {
        return array();   
        // actually uses pc_static::get_wp_user_ulist_columns();
    }
    return $hidden;
}
add_filter('hidden_columns', 'pc_ulist_hidden_columns', 10, 3);



// users list - custom screen options - save
function pc_ulist_page_opts_save($status, $option, $value) {
	if($option == 'pc_ulist_per_page') {
        return $value;
    }
}
add_filter('set-screen-option', 'pc_ulist_page_opts_save', 10, 3);





////////////////////////////////////////////////////////////////////////////////////////





// if there are pending users, show them on the WP dashboard
function pc_pending_users_warning() {	
	global $total_pen_rows, $wpdb;

    if(!pc_wpuc_static::current_wp_user_can_edit_pc_user('some')) {
        return false;   
    }
    
	// pending users only if they exists
	$wpdb->query("SELECT ID FROM ". esc_sql(PC_USERS_TABLE) ." WHERE status = 3");
	$total_pen_rows = $wpdb->num_rows;
	
	if($total_pen_rows > 0) {
        $GLOBALS['pvtcont_pending_users_count'] = $total_pen_rows;
        
		// add wp admin bar alert
		add_action('admin_bar_menu', 'pc_pending_bar_warn', 500);  
	}	
}
add_action('pvtcont_init', 'pc_pending_users_warning', 800);



// PC menu item
function pc_pending_menu_warn() {
	global $pvtcont_pending_users_count;
    
    $curr_user = wp_get_current_user();
    $first_user_role = (count((array)$curr_user->roles)) ? reset($curr_user->roles) : false;  
    
	$dot = '<span class="update-plugins"><span class="plugin-count">'. $pvtcont_pending_users_count .'</span></span>';
	add_submenu_page('pc_user_manage', esc_html__('Pending Users', 'pc_ml') .' '.$dot, esc_html__('Pending Users', 'pc_ml') .' '. $dot, $first_user_role, 'admin.php?page=pc_user_manage&status=3');	
}

// admin bar notice
function pc_pending_bar_warn() {
	global $wp_admin_bar, $pvtcont_pending_users_count;

    if(is_admin_bar_showing() && is_object($wp_admin_bar)) {
        $txt = (absint($pvtcont_pending_users_count) > 1) ? esc_html__('Pending users', 'pc_ml') : esc_html__('Pending user', 'pc_ml');
        
        $wp_admin_bar->add_menu( array( 
            'id'    => 'pc_pending_users', 
            'title' => '<span class="pc_pending_users_topbar_warn" title="PrivateContent"><span id="ab-updates">'. $pvtcont_pending_users_count .' '. $txt .'</span></span>', 
            'href'  => get_admin_url() . 'admin.php?page=pc_user_manage&status=3' 
        ));
    }
}





////////////////////////////////////////////////////////////////////////////////////////





// user dashboard - change browser's title for Edit user
function pc_edit_user_page_title($admin_title, $title) {
    global $current_screen, $pc_users;
    
    if(!is_object($current_screen) || $current_screen->base != 'privatecontent_page_pc_user_dashboard' || !isset($_GET['user'])) {
        return $admin_title;   
    }
    
    $username = $pc_users->get_user_field(absint($_GET['user']), 'username');
    if(!$username) {
        return $admin_title;   
    }
    
    $endpart = (strpos($admin_title, '&lsaquo;') === false) ? get_bloginfo('name') : trim(explode('&lsaquo;', $admin_title)[1]);
    
    /* translators: 1: username. */
    return sprintf(esc_html__('Edit user %s', 'pc_ml'), $username) .' &lsaquo; '. $endpart;
}
add_filter('admin_title', 'pc_edit_user_page_title', 1100, 2);