<?php
if(!defined('ABSPATH')) {exit;}
dike_lc('lcweb', PC_DIKE_SLUG, true); /* NFPCF */


// USERS LIST PAGE
global $pc_users, $pc_wp_user;
$nonce = wp_create_nonce('pc_ulist');


// base page URL
$base_page_url = admin_url('admin.php?page=pc_user_manage');

// first/last name flag 
$fist_last_name = get_option('pg_use_first_last_name');

// user categories
$user_categories = pc_static::user_cats();

// WP user sync check
$wp_user_sync = $pc_users->wp_user_sync;



// WP user permissions
$cuc_edit = pc_wpuc_static::current_wp_user_can_edit_pc_user('some'); 



// advanced search - user export dispatch
if(isset($_GET['targeted_export']) && isset($_GET['aste_nonce'])) {
    if(!pc_static::verify_nonce($_GET['aste_nonce'], 'aste_nonce')) {
        echo '<div class="error"><p>Cheating?</p></div>';      
    }
    else {
        $format = (isset($_GET['aste_format']) && $_GET['aste_format'] == 'csv') ? 'csv' : 'excel';
        $targeted_export_args = unserialize(base64_decode(pc_static::sanitize_val($_GET['targeted_export'])));
        $pc_users->export_users($targeted_export_args, $format);
    }
}




/* 
 * PC-FILTER - add user attributes to be included in the advanced search form and to be queried as user meta key (eg. premium plans status). 
    Each array element must have a precise structure: 
    
    array(
        'key' => array(
            'name'  => // dropdown name
            'db_key'=> // database meta name to query
            'db_val'=> // database meta value to query
            'group' => // attributes group - a simple string to create the optgroup
        )
    );
 */
$adv_search_user_atts = (array)apply_filters('pc_ulist_adv_search_user_atts', array()); 



// bulk action dropdown
$bulk_act_dd = '';

if($cuc_edit) {
    $bulk_act_dd = '
    <div class="pc_ulist_bulk_act_wrap">
        <select name="pc_ulist_action" class="pc_ulist_action" autocomplete="off">
            <option value="">'. esc_html__('Bulk Actions', 'pc_ml') .'</option>';

            if(isset($_GET['status']) && ($_GET['status'] == 2 || $_GET['status'] == 3)) {
                $bulk_act_dd .= '
                <option value="enable">'. esc_html__('Enable', 'pc_ml') .' '. esc_html__('Users', 'pc_ml') .'</option>';
            }
            else {
                $bulk_act_dd .= '
                <option value="disable">'. esc_html__('Disable', 'pc_ml') .' '. esc_html__('Users', 'pc_ml') .'</option>';
            }

            $bulk_act_dd .= '
            <option value="delete">'. esc_html__('Delete', 'pc_ml') .' '. esc_html__('Users', 'pc_ml') .'</option>';

            if(!isset($_GET['status']) || $_GET['status'] != 3) { 
                $bulk_act_dd .= '
                <option value="cat_change">'. esc_html__('Change categories', 'pc_ml') .'</option>';
            }

        $bulk_act_dd .= '
        </select>
        <input type="button" value="'. esc_html__('Apply', 'pc_ml') .'" class="button-secondary pc_bulk_action_btn" name="ucat_action">
    </div>';
}
    

// current page
$curr_pag = (isset($_GET['pagenum']) && (int)$_GET['pagenum']) ? absint($_GET['pagenum']) : 1; 


// per page - controlled by screen options
$per_page = (int)get_user_meta(get_current_user_id(), 'pc_ulist_per_page', true);
if(empty($per_page) || $per_page < 1) {
    $per_page = 20;
}



//////////////////////////////////////////////////////////////////////////////




// current viewing status
if(!isset($_GET['status']) || isset($_GET['status']) && $_GET['status'] == 1) {
    $status = '1';
}
elseif($_GET['status'] == '2') {
    $status = '2';
}
else {
    $status = '3';
}




//////////////////////////////////////////////////////////////////////////////




// QUERY ARGS

$fixed_cols = array(
    'id' => array(
        'name' 		=> 'ID',
        'sortable' 	=> true,
        'width'		=> '45px',
        'is_date'   => false,
    ),
    'username' => array(
        'name' 		=> esc_html__('Username', 'pc_ml'),
        'sortable' 	=> true,
        'is_date'   => false,
    ),
);
$table_cols = array_merge($fixed_cols, pc_static::get_ulist_columns()); // prepend fixed keys
$all_potential_table_cols = array_merge($fixed_cols, pc_static::get_ulist_columns(true));




$args = array(
	'to_get'	=> array_merge(array_keys($table_cols), array('page_id', 'disable_pvt_page', 'wp_user_id')),
    'status'    => (int)$status,
	'limit' 	=> $per_page,
	'offset' 	=> ($curr_pag > 1) ? $per_page * ($curr_pag - 1) : 0,
	'search'	=> array()
);


// basic search
if(pc_static::get_param_exists('basic_search') || pc_static::get_param_exists('pc_cat')) {
	$args['search'][0] = array(
        'relation' => 'OR',
    );  
    
	if(pc_static::get_param_exists('basic_search')) {
		$search_in = array('username', 'name', 'surname', 'email');
		foreach($search_in as $key) {
			$args['search'][0][] = array('key'=>$key, 'val'=>'%'. sanitize_text_field(wp_unslash($_GET['basic_search'])) .'%', 'operator' => 'LIKE');	
		}
	}
	
	if(pc_static::get_param_exists('pc_cat')) {
        if(PVTCONT_CURR_USER_MANAGEABLE_CATS == 'any' || in_array((array)$_GET['pc_cat'], PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
            $args['categories'] = (is_array($_GET['pc_cat'])) ? array_map('sanitize_text_field', $_GET['pc_cat']) : sanitize_text_field($_GET['pc_cat']);
        } else {
            $args['categories'] = PVTCONT_CURR_USER_MANAGEABLE_CATS;    
        }
    }
}



// advanced search /* NFPCF */
elseif(pc_static::get_param_exists('advanced_search')) {
    $args['search'][0] = array(
        'relation' => (isset($_GET['pc_as_global_cond'])) ? sanitize_text_field($_GET['pc_as_global_cond']) : 'OR'
    ); 
    
    $sanitized_str = sanitize_text_field(str_replace(array('%5B', '%5D'), array('[', ']'), $_GET['advanced_search']));
	parse_str($sanitized_str, $as_params);
    
	// search structure
    if(is_array($as_params['pc_as_fields'])) {
        for($a=0; $a < count($as_params['pc_as_fields']); $a++) {	

            // operator translation
            switch($as_params['pc_as_cond'][$a]) {
                case 'different': $op = '!='; break;
                case 'bigger'	: $op = '>'; break;
                case 'smaller'	: $op = '<'; break;
                case 'like'		: $op = 'LIKE'; break;
                case 'not_like' : $op = 'NOT LIKE'; break;
                case '='        : 
                default         : $op = '='; break;	
            }

            if($as_params['pc_as_fields'][$a] == 'categories') {
                $op = ($as_params['pc_as_cond'][$a] == 'like') ? 'LIKE' : 'NOT LIKE'; 

                $args['search'][0][] = array(
                    'key'       => 'categories', 
                    'val'       => (array)$as_params['pc_as_cat_val'], 
                    'operator'  => $op
                );
            }
            else {
                // special case - ignore "last access" with no values
                if($as_params['pc_as_fields'][$a] == 'last_access') {
                    $args['search'][1] = array(array(
                        'key'       => $as_params['pc_as_fields'][$a], 
                        'val'       => array('0000-00-00 00:00:00', '1970-01-01 00:00:01'), 
                        'operator'  => 'NOT IN'
                    ));
                }
                
                $args['search'][0][] = array(
                    'key'       => $as_params['pc_as_fields'][$a], 
                    'val'       => (in_array($op, array('LIKE', 'NOT LIKE'))) ? "%". wp_unslash($as_params['pc_as_val'][$a]) ."%" : wp_unslash($as_params['pc_as_val'][$a]), 
                    'operator'  => $op
                );
            }
        }
    }
    
    
    // targeted attributes?
    if(pc_static::get_param_exists('pc_as_user_atts')) {
        $ta_query_array = array();
        
        foreach((array)$_GET['pc_as_user_atts'] as $asua) {
            $asus = sanitize_text_field($asua);
            if(!isset($adv_search_user_atts[$asua])) {
                continue;    
            }
            $data = $adv_search_user_atts[$asua];
            
            if(!is_array($data) || !isset($data['db_key']) || !isset($data['db_val'])) {
                continue;    
            }

            if(!isset($ta_query_array[ $data['db_key'] ])) {
                $ta_query_array[ $data['db_key'] ] = array();        
            }
            
            $ta_query_array[ $data['db_key'] ][] = $data['db_val'];
        }

        foreach($ta_query_array as $ta_key => $ta_val) {
            $args['search'][0][] = array(
                'key' => $ta_key, 
                'val' => $ta_val, 
                'operator' => 'IN'
            );        
        }
    }
}


// be sure to query only users belonging to assigned categories
if(!isset($args['categories']) && PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any') {
    $args['categories'] = PVTCONT_CURR_USER_MANAGEABLE_CATS;
}




// sorting
if(isset($_GET['orderby']) && !empty($_GET['orderby'])) {
	$args['orderby'] = sanitize_text_field($_GET['orderby']);	
}

if(isset($_GET['order']) && in_array(strtolower($_GET['order']), array('asc', 'desc')) ) {
	$args['order'] = strtolower(sanitize_text_field($_GET['order']));	
} else {
	$args['order'] = 'desc';	
}



/* PC-FILTER - allows users query parameters edit in users list */
$args = apply_filters('pc_users_list_query_args', $args);




//////////////////////////////////////////

$rows_count_args = $args;
$rows_count_args['to_get'] = array('id');
$rows_count_args['count'] = true;
$rows_count_args['limit'] = -1;
if(isset($rows_count_args['offset'])) {
    unset($rows_count_args['offset']);
}

// total rows for active users
$rows_count_args['status'] = 1;
$total_act_rows = $pc_users->get_users($rows_count_args);

// total rows for disabled users
$rows_count_args['status'] = 2;
$total_dis_rows = $pc_users->get_users($rows_count_args);

// total rows for pending users
$rows_count_args['status'] = 3;
$total_pen_rows = $pc_users->get_users($rows_count_args);



if 		($status == 1) 	{$total_rows = $total_act_rows;}
elseif 	($status == 2) 	{$total_rows = $total_dis_rows;}
else 					{$total_rows = $total_pen_rows;}




// re-check for current page (in case deleted user made total decrease)
$tot_pages = ceil((int)$total_rows / $per_page);
if($curr_pag > $tot_pages) {
    $args['offset'] = ($tot_pages > 1) ? $per_page * ($tot_pages - 1) : 0;
}

$user_query = $pc_users->get_users($args);
$pagbtn_code = pc_static::users_list_pag_block($curr_pag, $per_page, $total_rows);
?>

<div class="pc_ulist_big_spinner pc_displaynone">
    <div class="pc_spinner pc_spinner_big"></div>
</div>

<div class="wrap pc_form">  
	<?php
	// page title
	echo '
	<h2 class="pc_page_title">' . 
		esc_html__( 'PrivateContent Users', 'pc_ml' ) . 
		' <a class="add-new-h2 pc_add_user_btn" href="'. esc_url(admin_url()) .'admin.php?page=pc_user_dashboard">
            <i class="fas fa-plus-circle"></i>
            '. esc_html__( 'Add New', 'pc_ml') .'
        </a>
	</h2>'; 

	// MESSAGE IN RELATION TO THE ACTION PERFORMED
    if(isset($act_message)) { 
    	echo '<div class="updated"><p><strong>'. wp_kses_post($act_message) .'</strong></p></div>';	
	}
	
	
	// keep eventual search parameters changing status
	$clean_url = pc_static::man_url_attr('remove', 'pagenum');

	// STATUS LINKS ?>
    <h2 class="nav-tab-wrapper pc_ulist_statuses">
        <?php $url_arr['status'] = 1; ?>
        <a class="nav-tab <?php if($status == $url_arr['status']) echo 'nav-tab-active'; ?> pc_ulist_active_tab" href="<?php echo esc_attr(pc_static::man_url_attr('edit', 'status', $url_arr['status'], $clean_url)) ?>">
            <?php esc_html_e('Actives', 'pc_ml') ?> <span><?php echo absint($total_act_rows) ?></span>
        </a>
        
        <?php $url_arr['status'] = 2; ?>
        <a class="nav-tab <?php if($status == $url_arr['status']) echo 'nav-tab-active'; ?> pc_ulist_disabled_tab" href="<?php echo esc_attr(pc_static::man_url_attr('edit', 'status', $url_arr['status'], $clean_url)) ?>">
            <?php esc_html_e('Disabled', 'pc_ml') ?> <span><?php echo absint($total_dis_rows) ?></span>
        </a>
        
        <?php $url_arr['status'] = 3; ?>
        <a class="nav-tab <?php if($status == $url_arr['status']) echo 'nav-tab-active'; ?> pc_ulist_pending_tab" href="<?php echo esc_attr(pc_static::man_url_attr('edit', 'status', $url_arr['status'], $clean_url)) ?>">
            <?php esc_html_e('Pending', 'pc_ml') ?> <span><?php echo absint($total_pen_rows) ?></span>
        </a>
    </h2>
    
    
    
    <?php
    // TABLE TH-s
    $table_th = '
    <tr>';   
           
        if($cuc_edit) {
            $table_th .= '
            <th id="cb" class="manage-column column-cb check-column" scope="col">
                <input type="checkbox" autocomplete="off" />
            </th>
            <th>&nbsp;</th>'; // buttons
        } 
           
        $table_th .= '
        <th>
            <a class="pc_filter_th" rel="id">ID</a>
        </th>
        <th class="pc_ulist_badges_th">&nbsp;</th>'; // user badges
 
        foreach($table_cols as $key => $data) {
            if($key == 'id') {
                continue;
            }
            
            $width = (isset($data['width'])) ? 'style="width: '. esc_attr($data['width']) .';"' : '';
            $sortable = (isset($data['sortable']) && $data['sortable']) ? '<a class="pc_filter_th" rel="'. esc_attr($key) .'">'. $data['name'] .'</a>' : $data['name'];
            
            $table_th .= '<th '.$width.' class="pc_ulist_'. esc_attr($key) .'_th">'. $sortable .'</th>';	
        }
           
    $table_th .= '       
    </tr>';
    
    
    // TABLE START ?>
    <form method="get" id="pc_user_list_form" action="<?php echo esc_attr($base_page_url) ?>">
        <?php
        // advanced search summary
        if(pc_static::get_param_exists('advanced_search')) {
            $pre_bold_legend = ($as_params["pc_as_global_cond"] == 'AND') ? esc_html__('Searching users matching every condition', 'pc_ml') : esc_html__('Searching users matching at least one condition', 'pc_ml');
            
            echo '
            <div class="pc_ulist_as_summary">
                <h4>'. wp_kses_post($pre_bold_legend) .':</h4>
                <ul>';
                    
                    if(is_array($as_params['pc_as_fields'])) {
                        for($a=0; $a < count($as_params['pc_as_fields']); $a++) {	
                            $operator   = $as_params['pc_as_cond'][$a];
                            $data       = $all_potential_table_cols[ $as_params['pc_as_fields'][$a] ]; 

                            if($as_params['pc_as_fields'][$a] == 'categories') {
                                switch($operator) {
                                    case 'like'         : $cond_txt = esc_html__('contains', 'pc_ml'); break;   
                                    case 'different'    : $cond_txt = esc_html__('is different from', 'pc_ml'); break;       
                                }                
                            }    
                            else if(isset($data['is_date']) && $data['is_date']) {
                                switch($operator) {
                                    case 'like'     : $cond_txt = esc_html__('during', 'pc_ml'); break;     
                                    case 'bigger'   : $cond_txt = esc_html__('is sooner than', 'pc_ml'); break;     
                                    case 'smaller'  : $cond_txt = esc_html__('is older than', 'pc_ml'); break;     
                                }   
                            }
                            else {
                                switch($operator) {
                                    case 'equal'        : $cond_txt = esc_html__('is equal to', 'pc_ml'); break;     
                                    case 'different'    : $cond_txt = esc_html__('is different from', 'pc_ml'); break;     
                                    case 'bigger'       : $cond_txt = esc_html__('is greater than', 'pc_ml'); break;     
                                    case 'smaller'      : $cond_txt = esc_html__('is lower than', 'pc_ml'); break;     
                                    case 'like'         : $cond_txt = esc_html__('contains', 'pc_ml'); break;
                                    case 'not_like'     : $cond_txt = esc_html__('does not contain', 'pc_ml'); break;
                                }    
                            }
                            
                            $summary_val = ($as_params['pc_as_fields'][$a] == 'categories') ? $as_params['pc_as_cat_val'] : wp_unslash($as_params['pc_as_val'][$a]);
                            echo '<li>"'. esc_html($data['name']) .'" '. esc_html($cond_txt) .' '. esc_html($pc_users->data_to_human($as_params['pc_as_fields'][$a], $summary_val)) .'</li>';
                        }
                    }
            
                    // print atts search /* NFPCF */
                    if(pc_static::get_param_exists('pc_as_user_atts')) {
                        
                        foreach((array)$_GET['pc_as_user_atts'] as $asua) {
                            if(!isset($adv_search_user_atts[$asua])) {
                                continue;    
                            }
                            $data = $adv_search_user_atts[$asua];

                            if(!is_array($data) || !isset($data['name'])) {
                                continue;    
                            } 
                            
                            echo '<li>'. esc_html($data['name']) .'</li>';
                        }
                    }

                echo '
                </ul>
            </div>';    
        }
        ?>
        <div class="tablenav pc_users_list_navbar">
            <?php echo pc_static::wp_kses_ext($pagbtn_code); ?>

            <input type="hidden" name="page" value="pc_user_manage" />
            <input type="hidden" name="pagenum" value="1" /> <!-- set to one to reset pagination while searching -->
            <input type="hidden" name="status" value="<?php 
				if($status == 1) 	{echo 1;}
				elseif($status == 2){echo 2;}
				else 				{echo 3;}
				?>" 
            />
            
            
            <div class="pc_ulist_search_block">
                <?php if(!pc_static::get_param_exists('advanced_search')) : ?>
                    <input type="text" name="basic_search" value="<?php echo (isset($_GET['basic_search'])) ? esc_attr(wp_unslash($_GET['basic_search'])) : ''; ?>" size="25" class="pc_ulist_search_field" placeholder="🔍 <?php esc_attr_e('username, name, surname, e-mail', 'pc_ml') ?>" autocomplete="off" />
                    
                    <select name="pc_cat" id="pc_ulist_filter" autocomplete="off">
                        <option value=""><?php esc_html_e('All Categories', 'pc_ml') ?></option>
                        <?php
                        foreach($user_categories as $cat_id => $cat_name) {
                            
                            if(PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any' && !in_array($cat_id, (array)PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
                                continue;    
                            }
                            
                            $ucat_sel = (isset($_GET['pc_cat']) && (int)$_GET['pc_cat'] == $cat_id) ? 'selected="selected"' : '';
                            echo '<option value="'. absint($cat_id) .'" '. esc_html($ucat_sel) .'>'. esc_html($cat_name) .'</selected>';	
                        }
                        ?>
                    </select>

                <?php else :
                
                    // export button code
                    if(!empty($user_query)) : ?>    
                        <span class="pc_ulist_export_btns">
                            <select name="pc_ulist_export_cmd" autocomplete="off">
                                <option value="csv"><?php esc_html_e('CSV/Excel export', 'pc_ml') ?></option>
                                <option value="pvtc"><?php esc_html_e('PrivateContent export', 'pc_ml') ?></option>
                            </select>
                            <button type="button" title="<?php esc_attr_e('go to the export page', 'pc_ml') ?>" class="button-primary dashicons dashicons-arrow-right-alt2"></button>
                        </span>    
                    <?php endif; ?>       
                
                    <span><?php esc_attr_e('Advanced search', 'pc_ml'); ?></span>
                    <input type="button" value="<?php esc_attr_e('Clean', 'pc_ml'); ?>" class="button-secondary pc_clean_advanced_search" />
                <?php endif; ?>

                <?php if(!isset($_GET['advanced_search']) || empty($_GET['advanced_search'])) : ?>
                    <input type="submit" value="<?php esc_attr_e('Search', 'pc_ml'); ?>" class="button-secondary" name="ucat_filter">
                
                    <?php if(pc_static::get_param_exists('basic_search')) : ?>
                        <input type="button" value="<?php esc_attr_e('Clean search', 'pc_ml'); ?>" class="button-secondary pc_ulist_clean_search" />
                    <?php endif; ?>
                <?php endif; ?>

                
                <input type="button" value="<?php echo (isset($_GET['advanced_search']) && !empty($_GET['advanced_search'])) ? esc_attr__('Edit', 'pc_ml') : esc_attr__('Advanced search', 'pc_ml'); ?>" class="<?php echo (isset($as_params)) ? 'button-primary' : 'button-secondary'; ?> pc_advanced_search_btn <?php echo (ISPCF) ? 'pc_nfpcf disabled' : '' ?>" data-mfp-src="#pc_adv_search" />
            </div> 
            
            <?php 
            if($cuc_edit && !empty($user_query)) { 
                echo pc_static::wp_kses_ext($bulk_act_dd);
            }
            ?> 
    	</div>
        
        <?php 
		/************************************************************
		************************************************************/
        
        // print table only if users are found
        if(!empty($user_query)) :
		?>
            <table class="widefat pc_table pc_users_list">
                <thead>
                    <?php echo pc_static::wp_kses_ext($table_th) ?>
                </thead>
                <tfoot>
                    <?php echo pc_static::wp_kses_ext($table_th) ?>
                </tfoot>

                <tbody>
                  <?php 
                  foreach($user_query as $u) : 
                  ?>
                    <tr class="content_row" data-uid="<?php echo absint($u['id']) ?>">

                        <?php if($cuc_edit) : ?>  
                            <td class="pc_ulist_bulk_check_wrap">
                                <input type="checkbox" name="pc_users[]" value="<?php echo absint($u['id']) ?>" autocomplete="off" />
                            </td>
                            <td class="pc_ulist_icons">
                                <i class="fas fa-times-circle del_pc_user pc_delete_user_btn" title="<?php esc_attr_e('Delete user', 'pc_ml'); ?>"></i>

                            <?php // ENABLE / ACTIVATE / DISABLE USER
                            if(isset($_GET['status']) && $_GET['status'] == 2) : // enable ?>
                                <i class="fas fa-check-circle pc_enable_user_btn" title="<?php esc_attr_e('Enable user', 'pc_ml'); ?>"></i>

                            <?php 
                            elseif(isset($_GET['status']) && $_GET['status'] == 3) : // activate ?>
                                <i class="fas fa-check-circle pc_activate_user_btn" title="<?php esc_attr_e('Activate user', 'pc_ml'); ?>"></i>

                            <?php else: // disable ?>
                                <i class="fas fa-minus-circle pc_disable_user_btn" title="<?php esc_attr_e('Disable user', 'pc_ml'); ?>"></i>

                            <?php endif; ?>

                            <?php // EDIT USER PAGE
                            if(get_option('pg_target_page') && empty($u['disable_pvt_page']) && (!isset($_GET['status']) || $_GET['status'] != 3) ) : ?>
                                <a href="<?php echo esc_attr(admin_url()) ?>post.php?post=<?php echo absint($u['page_id']) ?>&action=edit" class="pc_edit_user_page_btn">
                                    <i class="fas fa-edit" title="<?php esc_attr_e('Edit user reserved page', 'pc_ml'); ?>"></i>
                                </a>
                            <?php endif; ?>   
                        </td>
                        <?php endif; // end cuc (curr user can) ?>

                        <td>
                            <?php echo absint($u['id']) ?>
                        </td>
                        <td class="pc_ulist_badges">
                            <?php 
                            $badges = '';

                            if($wp_user_sync && !empty($u['wp_user_id'])) {
                                 $badges = '<img src="'. PC_URL .'/img/wp_synced.png" title="'. esc_attr__('Synced with WP user', 'pc_ml').' - ID '. absint($u['wp_user_id']) .'" />';
                            }

                            // PC-FILTER - users list badges - show an image badge relatd to a user
                            echo pc_static::wp_kses_ext(
                                apply_filters('pc_users_list_badges', $badges, $u['id'])
                            );
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_attr(admin_url()) ?>admin.php?page=pc_user_dashboard&user=<?php echo absint($u['id']) ?>" class="pc_edit_user_link" title="<?php esc_attr_e('edit user', 'pc_ml') ?>">
                                <strong><?php echo esc_html($u['username']) ?></strong>
                            </a>
                        </td>

                        <?php 
                        foreach($table_cols as $key => $data) {
                            if(in_array($key, array('id', 'username'))) {
                                continue;
                            }
                            
                            if($key == 'categories') {
                                $managed = pc_static::ulist_user_cats_td($u[$key]);        
                            }
                            elseif($key == 'insert_date') {
                                $managed = '<time title="'. date_i18n($pc_users->wp_date_format.' - '.$pc_users->wp_time_format, strtotime($u[$key])) .' '. $pc_users->wp_timezone .' timezone">'. pc_static::elapsed_time($u[$key]).' '.esc_html__('ago', 'pc_ml').'</time>';  
                            }
                            else {
                                $managed = $pc_users->data_to_human($key, $u[$key]);    
                            }

                            // PC-FILTER - allow advanced management over specific user data shown in users list - passes managed val, user ID, column ID, raw DB val
                            $val = apply_filters('pc_users_list_table_fields_val', $managed, $u['id'], $key, $u[$key]); 

                            echo '<td class="pc_ulist_'. esc_attr($key) .'_td">'. pc_static::wp_kses_ext($val) .'</td>'; 	 
                        }
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php else : // no users are found  ?>
            <div class="pc_ulist_no_users">
                <i class="fas fa-users-slash"></i>
                <span>.. <?php esc_html_e('no users found', 'pc_ml') ?> ..</span>
            </div>

        <?php endif; // print table only if users are found ?>
	</form>
    
    
    <div class="tablenav">
        <?php 
        if($cuc_edit && !empty($user_query)) { 
            echo pc_static::wp_kses_ext($bulk_act_dd);
        }
        
        echo pc_static::wp_kses_ext($pagbtn_code); ?> 
    </div>
</div>






<?php // ADVANCED SEARCH FORM ?>
<div class="pc_displaynone">
    <form id="pc_adv_search">
        <button class="mfp-close" type="button" title="<?php esc_attr_e('Close (Esc)', 'pc_ml') ?>">×</button>
        <table class="widefat pc_table">
          <tr>
            <td colspan="2" data-for="pc_as_global_cond"><?php esc_html_e('Conditions matching', 'pc_ml') ?></td>
            <td>
                <select name="pc_as_global_cond" autocomplete="off">
                    <option value="OR"><?php esc_html_e('at least one must match', 'pc_ml') ?></option>
                    <option value="AND" <?php if(isset($as_params) && $as_params["pc_as_global_cond"] == 'AND') {echo 'selected="selected"';} ?>><?php esc_html_e('every condition must match', 'pc_ml') ?></option>
                </select>
            </td>
          </tr>
          <tr>
            <td colspan="2">
                <select name="pc_as_fields" class="pc_as_fields" autocomplete="off">
                    <?php 
                    foreach($all_potential_table_cols as $key => $val) {
                        if($key != 'categories') {
                            if(!isset($val['sortable']) || !$val['sortable']) { // only sortable/searchable fields
                                continue;
                            }
                            if(in_array($key, array('id'))) {
                                continue;
                            }
                        }

                        $is_date_attr = (isset($val['is_date']) && $val['is_date']) ? 1 : 0;
                        echo '<option value="'. esc_attr($key) .'" data-is-date="'. esc_attr($is_date_attr) .'">'. esc_html($val['name']) .'</option>';
                    }		
                    ?>
                </select>
            </td>
            <td><input type="button" name="pc_as_add_cond" value="<?php esc_attr_e('Add condition', 'pc_ml') ?>" class="button-secondary pc_as_add_cond" /></td>
          </tr>
        </table>

        <table id="pc_as_conds" class="widefat pc_table <?php if(!isset($as_params)) {echo 'pc_displaynone';} ?>">
        <?php 
        if(pc_static::get_param_exists('advanced_search')) { 
            if(is_array($as_params['pc_as_fields'])) {
                
                for($a=0; $a < count($as_params['pc_as_fields']); $a++) {	

                    $operator   = $as_params['pc_as_cond'][$a];
                    $data       = $all_potential_table_cols[ $as_params['pc_as_fields'][$a] ]; 
                    $f_name     = $data['name'];
                    $f_class    = str_replace(' ', '_', $as_params['pc_as_fields'][$a]);
                    $f_type     = (isset($data['is_date']) && $data['is_date']) ? 'date' : 'text';

                    if($as_params['pc_as_fields'][$a] == 'categories') {
                        $opts = '
                        <option value="like" '. selected('like', $operator, false) .'>'. esc_html__('contains', 'pc_ml') .'</option>
                        <option value="different" '. selected('different', $operator, false) .'>'. esc_html__('is different from', 'pc_ml') .'</option>';
                    }
                    elseif($f_type == 'date') {
                        $opts = '
                        <option value="like" '. selected('like', $operator, false) .'>'. esc_html__('during', 'pc_ml') .'</option>
                        <option value="bigger" '. selected('bigger', $operator, false) .'>'. esc_html__('is sooner than', 'pc_ml') .'</option>	
                        <option value="smaller" '. selected('smaller', $operator, false) .'>'. esc_html__('is older than', 'pc_ml') .'</option>';
                    }
                    else {
                        $opts = '
                        <option value="equal" '. selected('equal', $operator, false) .'>'. esc_html__('is equal to', 'pc_ml') .'</option>
                        <option value="different" '. selected('different', $operator, false) .'>'. esc_html__('is different from', 'pc_ml') .'</option>	
                        <option value="bigger" '. selected('bigger', $operator, false) .'>'. esc_html__('is greater than', 'pc_ml') .'</option>	
                        <option value="smaller" '. selected('smaller', $operator, false) .'>'. esc_html__('is lower than', 'pc_ml') .'</option>
                        <option value="like" '. selected('like', $operator, false) .'>'. esc_html__('contains', 'pc_ml') .'</option>
                        <option value="not_like" '. selected('not_like', $operator, false) .'>'. esc_html__('does not contain', 'pc_ml') .'</option>';
                    }

                    ?>
                    <tr class="<?php echo esc_attr($f_class) ?>"><td>
                        <span class="pc_as_remove_cond dashicons dashicons-no-alt" title="<?php esc_attr_e('remove condition', 'pc_ml') ?>"></span>
                        <h4><?php echo esc_html($f_name) ?></h4>
                        <div class="pc_as_conds_fwrap">
                            <input type="hidden" name="pc_as_fields[]" value="<?php echo esc_attr($as_params['pc_as_fields'][$a]) ?>" autocomplete="off" />

                            <select name="pc_as_cond[]">
                                <?php echo pc_static::wp_kses_ext($opts) ?>
                            </select>

                            <?php 
                            if($as_params['pc_as_fields'][$a] == 'categories') :
                                echo '
                                <select name="pc_as_cat_val[]" multiple="multiple" class="pc_as_cat_select" autocomplete="off">';

                                    foreach($user_categories as $cat_id => $cat_name) {
                                        if(PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any' && !in_array($cat_id, (array)PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
                                            continue;    
                                        }

                                        $ucat_sel = (is_array($as_params['pc_as_cat_val']) && in_array($cat_id, $as_params['pc_as_cat_val'])) ? 'selected="selected"' : '';
                                        echo '<option value="'. absint($cat_id) .'" '. esc_html($ucat_sel) .'>'. esc_html($cat_name) .'</option>';	
                                    }

                                echo '
                                </select>';

                            else : ?>
                                <input type="<?php echo esc_attr($f_type) ?>" name="pc_as_val[]" value="<?php echo esc_attr(wp_unslash($as_params['pc_as_val'][$a])) ?>" class="pc_as_input" autocomplete="off" />
                            <?php endif; ?>
                        </div>
                    </td></tr>
                    <?php
                }
            }
        }
        ?>
        </table>
        
        
        <?php 
        // specific user attributes passed via filter /* NFPCF */
        if(!empty($adv_search_user_atts)) : 
            $selected = (isset($_GET['pc_as_user_atts'])) ? pc_static::sanitize_val((array)$_GET['pc_as_user_atts']) : array();
        
            $grouped_asua = array();
            foreach($adv_search_user_atts as $key => $data) {
                $group = (isset($data['group'])) ? $data['group'] : 'Ungrouped';
                    
                if(!isset($grouped_asua[$group])) {
                    $grouped_asua[$group] = array();
                }
                
                $grouped_asua[$group][$key] = $data;
            }
            ksort($grouped_asua, SORT_NATURAL);
        
        ?>
        <table id="pc_as_user_atts" class="widefat pc_table">
            <tr class="username">
                <td>
                    <h4><?php esc_html_e('Target user attributes', 'pc_ml') ?></h4>
                    <select name="pc_as_user_atts[]" multiple="multiple" autocomplete="off">
                        <?php
                        foreach($grouped_asua as $group_name => $asua_opts) {
                            echo '<optgroup label="'. esc_attr($group_name) .'">';
                            
                            foreach($asua_opts as $key => $data) {
                                if(!is_array($data) || !isset($data['name'])) {
                                    continue;    
                                }

                                $val = $data['name'];
                                $sel = (in_array($key, $selected)) ? 'selected="selected"' : '';

                                echo '<option value="'. esc_attr($key) .'" '. esc_html($sel) .'>'. esc_html($val) .'</option>';         
                            }
                            
                            echo '</optgroup>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>    
        <?php endif; ?>
        
        <input type="button" name="pc_as_submit" value="<?php esc_attr_e('Search', 'pc_ml') ?>" id="pc_as_submit" class="button-primary <?php if(!isset($as_params)) {echo 'pc_displaynone';} ?>" />
    </form>
</div>







<?php // BULK CATEGORIES CHANGE FORM ?>
<div class="pc_displaynone">
    <form id="pc_bulk_cat_change">
        <button class="mfp-close" type="button" title="<?php esc_attr__('Close (Esc)', 'pc_ml') ?>">×</button>

        <select name="pc_bcc_cats" class="pc_lc_select pc_bcc_cats" data-placeholder="<?php esc_attr_e('Select categories', 'pc_ml') ?> .." autocomplete="off" multiple="multiple">
            <?php 
            foreach($user_categories as $cat_id => $cat_name) {
                echo '<option value="'. absint($cat_id) .'">'. esc_html($cat_name) .'</option>';	
            }
            ?>
        </select>
        <br/>
        
        <input type="button" value="<?php esc_attr_e('Set', 'pc_ml') ?>" class="button-primary pc_bcc_submit" />
        <span class="pc_bcc_response"></span>
    </form>
</div>


<?php
$inline_js = '
(function($) { 
    "use strict";    

	const nonce = "'. esc_js(wp_create_nonce('lcwp_ajax')) .'",
          user_sorted_cols = JSON.parse(`'. wp_json_encode(pc_static::get_wp_user_ulist_columns()) .'`);
    
    // pagenum change
    let pagenum_keyup_tout = false;
    $(document).on(`keyup`, `.pc_ulist_pagenum_input`, function(e) {
        if(e.keyCode === 13) { 
            e.preventDefault();    
        }
        
        const $this     = $(this),
              max_val   = parseInt( $this.data(`tot-pag`), 10);
        
        if(pagenum_keyup_tout) {
            clearTimeout(pagenum_keyup_tout);    
        }
        pagenum_keyup_tout = setTimeout(function() {
            let url = "'. esc_js(pc_static::man_url_attr('add', 'pagenum', 'JS-VAL')) .'";
            
            let new_pag = parseInt($this.val(), 10);
            if(new_pag < 1) {
                new_pag = 1;    
            }
            else if(new_pag > max_val) {
                new_pag = max_val;    
            }
            
            window.location.href = url.replace(`JS-VAL`, new_pag);
        }, 500);
    });
    
    
    
    // page settings - add specific class to manage columns toggles easier
    $(document).ready(function() {
        $(`.hide-column-tog`).each(function() {
            $(this).parent().addClass(`pc_ulist_col_toggle`).attr(`data-name`, $(this).val()).prepend(`<i></i>`);    
            $(this).attr(`autocomplete`, `off`);
        });
        $(".pc_ulist_col_toggle").wrapAll(`<div class="pc_ulist_col_toggle_wrap"></div>`);
        
        
        // sort following user choices
        if(typeof(user_sorted_cols) == `object`) {
            const reversed = user_sorted_cols.reverse();
            
            $.each(reversed, function(key, val) {
                const $col = $(`.pc_ulist_col_toggle[data-name="`+ val.id +`"]`).detach();
                $(`.pc_ulist_col_toggle_wrap`).prepend($col);
                
                if($(`.hide-column-tog[name="`+ val.id +`-hide"]`).length) {
                    $(`.hide-column-tog[name="`+ val.id +`-hide"]`)[0].checked = (parseInt(val.checked, 10)) ? true : false;
                }
            });
        }
        
        
        // custom restrictions - sortable
        $(".pc_ulist_col_toggle_wrap").sortable({ 
            handle: `i`,
            update: function() {
                save_ulist_cols();        
            },
        });
        $(".pc_ulist_col_toggle").disableSelection();
    });
    
    
    // save shown columns - on toggle
    $(document).on(`click`, `.pc_ulist_col_toggle`, function() {
        save_ulist_cols();    
    });
    
    
    // save shown columns and their order
    let save_ulist_cols_ajax = false;
    
    const save_ulist_cols = function() {
        const checkboxes = $(`#screen-options-wrap .metabox-prefs input[type="checkbox"]`);        
        let data = [];    
        
        $(`.pc_ulist_col_toggle`).each(function() {
            data.push({
                id      : $(this).find(`input`).val(),
                checked : ($(this).find(`input`).is(`:checked`)) ? 1 : 0,
            });   
        });
        
        if(save_ulist_cols_ajax) {
            save_ulist_cols_ajax.abort();    
        }
        
        let ajax_data = {
            action  : `pvtcont_ulist_update_user_cols`,
            cols    : data,
            nonce   : nonce
        };
        save_ulist_cols_ajax = $.post(ajaxurl, ajax_data, function(response) {
            if($.trim(response) != `success`) {
                lc_wp_popup_message(`error`, "'. esc_html__('Error updating shown columns', 'pc_ml') .'");
            }
        })
        .fail(function(e) {
            if(e.status) {
                console.error(e);
                lc_wp_popup_message(`error`, "'. esc_html__('Error updating shown columns', 'pc_ml') .'");
            }
        });
    };
    
    
    
    // select/deselect all
	$(`#cb input`).on(`click`, function() {
		($(this).is(`:checked`)) ? $(`.pc_ulist_bulk_check_wrap input`).prop(`checked`, `checked`) : $(`.pc_ulist_bulk_check_wrap input`).removeAttr(`checked`);
	});
    
    
    
    // retrieve selected user IDs
    const get_selected_users = function() {
        let users = [];
        
        $(`table.pc_users_list tbody tr`).each(function() {
            
            if($(this).find(`.pc_ulist_bulk_check_wrap input`).is(`:checked`)) {
                users.push( parseInt($(this).data(`uid`), 10));  
            }
        });  
        
        return users;
    };';
    
    
    
    /////////////////////////////////////////////////////////////////////////
    
    
    
    if($cuc_edit) {
        $inline_js .= '
        // bulk action - btn click handler
        $(document).on(`click`, `.pc_bulk_action_btn`, function() {
            const action    = $(this).parent().find(`.pc_ulist_action`).val(),
                  sel_users = get_selected_users();

            if(!action || !sel_users.length) {
                return false;    
            }

            // change categories
            if(action == `cat_change`) {
                $.magnificPopup.open({
                      items: {
                          src: `#pc_bulk_cat_change`,
                          type: `inline`
                      },
                      preloader: false,
                      mainClass: `pc_bulk_cat_change_lb`,
                      callbacks: {
                        beforeOpen: function() {
                          if($(window).width() < 800) {
                            this.st.focus = false;
                          }
                        },
                        open: function() {
                            new lc_select(`.pc_bulk_cat_change_lb .pc_lc_select`, {
                                wrap_width : `100%`,
                                addit_classes : [`lcslt-lcwp`, `pc_scw_field_dd`],
                            });
                        }
                      }
                });
            }

            else {
                manage_users(action, sel_users);
            }
        });



        // bulk cat change - perform
        $(document).on(`click`, `.pc_bcc_submit`, function() {
            var val = $(`.lcslt-f-pc_bcc_cats .pc_bcc_cats`).val();

            if(!val || !confirm("'. esc_attr__('Existing user categories will be overwritten. Continue?', 'pc_ml') .'")) {
                return false;
            }

            $(`.pc_bcc_response`).html(`<i class="pc_spinner"></i>`);

            const data = {
                action  : `pvtcont_bulk_cat_change`,
                users   : get_selected_users(),
                cats    : val,
                nonce   : nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) != `success`){
                    $(`.pc_bcc_response`).html(response);
                    return false;	
                }

                $(`.pc_bcc_response`).html("'. esc_attr__('Done', 'pc_ml') .'!");
                location.reload(); 
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    lc_wp_popup_message(`error`, "'. esc_html__('Error changing users categories', 'pc_ml') .'");
                }
            });
        });



        // single user management
        $(document).on(`click`, `.pc_delete_user_btn`, function() {
            const uid = $(this).parents(`tr`).data(`uid`);
            manage_users(`delete`, uid);
        });
        $(document).on(`click`, `.pc_disable_user_btn`, function() {
            const uid = $(this).parents(`tr`).data(`uid`);
            manage_users(`disable`, uid);
        });
        $(document).on(`click`, `.pc_enable_user_btn, .pc_activate_user_btn`, function() {
            const uid = $(this).parents(`tr`).data(`uid`);
            manage_users(`enable`, uid);
        }); 



        // manage users (disable | enable | delete) - handles single user ID or IDs array
        const manage_users = function(cmd, users) {
            if(!users) {
                return false;    
            }
            if(typeof(users) != `object`) {
                users = [users];    
            }

            if(cmd == `delete`) {
                if(users.length > 1) {
                    if(!confirm("'. esc_attr__('Do you really want to delete these users?', 'pc_ml') .'")) {
                        return false;
                    }
                }
                else {
                    if(!confirm("'. esc_attr__('Do you really want to delete the user?', 'pc_ml') .'")) {
                        return false;
                    }        
                }
            }


            $(`.pc_ulist_big_spinner`).fadeIn();

            const data = {
                action  : `pvtcont_ulist_manage_users`,
                users   : users,
                pc_cmd  : cmd,
                nonce   : nonce
            };
            $.post(ajaxurl, data, function(response) {
                try {
                    const resp = JSON.parse(response); 

                    if(resp.status != `success`) {
                        lc_wp_popup_message(`error`, resp.message);        
                    }

                    // remove table rows
                    $.each(resp.processed_users, function(i, uid) {
                        $(`table.pc_users_list tbody tr[data-uid="`+ uid +`"]`).remove();    
                    });

                    // change tab counter
                    $(`.pc_ulist_statuses .nav-tab-active span`).text( parseInt($(`.pc_ulist_statuses .nav-tab-active span`).text(), 10) - resp.processed_users.length);

                    if(cmd == `enable`) {
                        $(`.pc_ulist_active_tab span`).text( parseInt($(`.pc_ulist_active_tab span`).text(), 10) + resp.processed_users.length);        
                    }
                    else if(cmd == `disable`) {
                        $(`.pc_ulist_disabled_tab span`).text( parseInt($(`.pc_ulist_disabled_tab span`).text(), 10) + resp.processed_users.length);        
                    }

                    // no users left? show no-user graphic
                    if(!$(`table.pc_users_list tbody tr`).length) {
                        $(`table.pc_users_list`).replaceWith(`
                        <div class="pc_ulist_no_users">
                            <i class="fas fa-users-slash"></i>
                            <span>.. '. esc_html__('no users found', 'pc_ml') .' ..</span>
                        </div>`);   

                        $(`.pc_ulist_bulk_act_wrap`).remove();
                    }
                }
                catch(e) {
                    console.error(e);
                    lc_wp_popup_message(`error`, "'. esc_attr__('Error performing the action', 'pc_ml') .'");    

                    $(`.pc_ulist_big_spinner`).fadeOut(); 
                }
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    lc_wp_popup_message(`error`, "'. esc_html__('Error performing the action', 'pc_ml') .'");
                }
            })
            .always(function() {
                $(`.pc_ulist_big_spinner`).fadeOut();        
            });
        };';
    } // cuc
	
    
    /////////////////////////////////////////////////////////////////////////
               
    
    $inline_js .= '
    
	/* advanced search - submit form */
	$(document).on(`click`, `#pc_as_submit`, function() {
		
		// clean previous search parameters
		var url_arr = window.location.href.split(`&`);
		var new_arr = [];
		
		$.each(url_arr, function(i, v) {
			if(typeof(v) != `undefined`) {
                v = decodeURIComponent(v);
                
				if(v.indexOf(`advanced_search=`) === -1 && v.indexOf(`basic_search=`) === -1 && v.indexOf(`pc_cat=`) === -1 && v.indexOf(`pc_as_user_atts[`) === -1) {
					new_arr.push(v);
				}
			}
		});
		
        // targeting user attributes?
        let user_atts_part = ``;
        if($(`.pc_ulist_as_lb #pc_as_user_atts select`).length && $(`.pc_ulist_as_lb #pc_as_user_atts select`).val().length) {
            user_atts_part = `&`+ $(`.pc_ulist_as_lb #pc_as_user_atts select`).serialize();
        }
        
		window.location.href = 
            new_arr.join(`&`) + 
            user_atts_part + 
            `&pc_as_global_cond=`+ $(`.pc_ulist_as_lb select[name="pc_as_global_cond"]`).val() +
            `&advanced_search=` + encodeURIComponent($(`.pc_ulist_as_lb #pc_adv_search *:not([name="pc_as_user_atts[]"]) `).serialize()); 
	});
	
	
	/* advanced search - remove condition */
	$(document).on(`click`, `.pc_as_remove_cond`, function() {
        $(this).parents(`tr`).remove();
        
        if(!$(`#pc_as_conds tr`).length) {
            $(`#pc_as_conds`).addClass(`pc_displaynone`);
            
            if(!$(`select[name="pc_as_user_atts[]"]`).length || !$(`select[name="pc_as_user_atts[]"]`)[0].value) {
                $(`#pc_as_submit`).addClass(`pc_displaynone`);
            }
        }
	});
	
	
	/* advanced search - add condition */
	$(document).on(`click`, `.pc_as_add_cond`, function() {
		const val     = $(`.pc_as_fields`).val(),
              f_name  = $(`.pc_as_fields option[value="`+ val +`"]`).html(),
              f_class = val.replace(/ /g, `_`),
              f_type  = ($(`.pc_as_fields option[value="`+ val +`"]`).data(`is-date`)) ? `date` : `text`;
        
        let opts;
        if(val == `categories`) {
            opts = `
            <option value="like">'. esc_attr__('contains', 'pc_ml') .'</option>
            <option value="different">'. esc_attr__('is different from', 'pc_ml') .'</option>`;   
        }
        else if(f_type == `date`) {
            opts = `
            <option value="like">'. esc_attr__('during', 'pc_ml') .'</option>
            <option value="bigger">'. esc_attr__('is sooner than', 'pc_ml') .'</option>	
            <option value="smaller">'. esc_attr__('is older than', 'pc_ml') .'</option>`;
        }
        else {
            opts = `
            <option value="equal">'. esc_attr__('is equal to', 'pc_ml') .'</option>
            <option value="different">'. esc_attr__('is different from', 'pc_ml') .'</option>	
            <option value="bigger">'. esc_attr__('is greater than', 'pc_ml') .'</option>	
            <option value="smaller">'. esc_attr__('is lower than', 'pc_ml') .'</option>
            <option value="like">'. esc_attr__('contains', 'pc_ml') .'</option>
            <option value="not_like">'. esc_attr__('does not contain', 'pc_ml') .'</option>`;
        }
        
		let code = `
            <tr class="${ f_class }"><td>
                <span class="pc_as_remove_cond dashicons dashicons-no-alt" title="'. esc_attr__('remove condition', 'pc_ml') .'"></span>
                <h4>${ f_name }</h4>
                <div>
                    <input type="hidden" name="pc_as_fields[]" value="${ val }" autocomplete="off" />
                    <select name="pc_as_cond[]">
                        ${ opts }
                    </select>`

                        if(val == `categories`) {
                            code += `
                            <select name="pc_as_cat_val[]" multiple="multiple" class="pc_as_cat_select" autocomplete="off">';
                            
                                foreach($user_categories as $cat_id => $cat_name) {
                                    if(PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any' && !in_array($cat_id, (array)PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
                                        continue;    
                                    }
                                    $inline_js .= '<option value="'. absint($cat_id) .'">'. esc_html($cat_name) .'</option>';	
                                }
               
                            $inline_js .= '
                            </select>`;    
                        }
                        else {
                            code += `
                            <input type="${ f_type }" name="pc_as_val[]" value="" class="pc_as_input" autocomplete="off" />`;
                        }
                        
                code += `                
                </div>
            </td>
        </tr>`;
		
		$(`#pc_as_conds, #pc_as_submit`).removeClass(`pc_displaynone`); 
		$(`#pc_as_conds`).append(code);
        
        pc_as_lb_live_select();
	});
	
    
    /* advanced search - toggle button if user atts are selected */
    $(document).on(`change`, `select[name="pc_as_user_atts[]"]`, function(e) {
        if($(this)[0].value) {
            $(`#pc_as_submit`).removeClass(`pc_displaynone`);
        } else {
            if(!$(`#pc_as_conds tr`).length) {
                $(`#pc_as_submit`).addClass(`pc_displaynone`);
            }
        }
    });
    
	
	/* show lightbox for advanced search */ /* NFPCF */
	$(document).ready(function() {
        $(`.pc_advanced_search_btn`).magnificPopup({
            type: `inline`,
            mainClass: `pc_ulist_as_lb`,
            preloader: false,
            callbacks: {
                beforeOpen: function() {
                    if($(window).width() < 800) {
                        this.st.focus = false;
                    }
                },
                open: function() {
                    pc_as_lb_live_select();    
                }
            }
        });
    });
		
    
    /* clean basic search */
	$(document).on(`click`, `.pc_ulist_clean_search`, function() {
		var url_arr = window.location.href.split(`&`);
		var new_url = [];
		
		$.each(url_arr, function(i, v) {
			if(typeof(v) != `undefined`) {
				if(v.indexOf(`basic_search=`) === -1 && v.indexOf(`ucat_filter=`) === -1) {
					new_url.push(v);	
				}
			}
		});
		
		window.location.href = new_url.join(`&`); 
	});
    
	
	/* clean advanced search */
	$(document).on(`click`, `.pc_clean_advanced_search`, function() {
		var url_arr = window.location.href.split(`&`);
		var new_url = [];
		
		$.each(url_arr, function(i, v) {
			if(typeof(v) != `undefined`) {
				if(v.indexOf(`advanced_search=`) === -1 && v.indexOf(`pc_as_user_atts`) === -1 && v.indexOf(`pc_as_global_cond=`) === -1) {
					new_url.push(v);	
				}
			}
		});
		
		window.location.href = new_url.join(`&`); 
	});';

    
    /* NFPCF */
    /* export users related to advanced search */
    if(isset($_GET['advanced_search']) && !empty($user_query)) {
        $inline_js .= '
        $(document).on(`click`, `.pc_ulist_export_btns button`, function() {
            const th_label  = $(`.pc_ulist_as_summary h4`).text().replace(`:`, ``),
                  td_html   = $(`.pc_ulist_as_summary ul`).html(),

                  how_to_export = $(`.pc_ulist_export_btns select`).val(),

                  as_export_params = `'. esc_js(base64_encode(serialize(array(
                    'status' => $args['status'],
                    'search' => $args['search']
                  )))) .'`,

                  export_pag_url = (how_to_export == `csv`) ? `'. esc_attr(admin_url('admin.php?page=pc_import_export&csv_export')) .'` : `'. esc_attr(admin_url('admin.php?page=pc_import_export&pvtc_export')) .'`,

                  final_url = export_pag_url +`&pc_targeted_export=`+ encodeURIComponent(as_export_params) +`&pc_texp_label=`+ encodeURIComponent(th_label) +`&pc_texp_conds=`+ encodeURIComponent(td_html);

            window.open(final_url, `_blank`);
        });';
    }
    
    
    $inline_js .= '
    // multi-line selection clicking the checkbox while pressing SHIFT
    $(document).on(`mousedown`, `.pc_ulist_bulk_check_wrap input`, function(e) { 
        if(e.button !== 0) { // only left button
            return;   
        }

        if(!e.shiftKey || !$(`.pc_ulist_focused_tr`).length) {
            $(`.pc_ulist_focused_tr`).removeClass(`pc_ulist_focused_tr`);
            $(this).parents(`tr`).addClass(`pc_ulist_focused_tr`);  
        }
        else {
            e.preventDefault();

            const $focused = $(`.pc_ulist_focused_tr input`)[0],
                  check_it = ($focused.checked) ? true : false;

            let checkboxes = $(`.pc_ulist_bulk_check_wrap input`),
                start = checkboxes.index(this),
                end = checkboxes.index($focused),
                range = checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1);

            range.prop(`checked`, check_it);

            $(`.pc_ulist_focused_tr`).removeClass(`pc_ulist_focused_tr`);
            $(this).parents(`tr`).addClass(`pc_ulist_focused_tr`);

            // clicked element must be set to opposite - the click event will automatically fix it  
            if(check_it === this.checked) {
                this.checked = !this.checked;
            }
        }
    });
    
    
	
	/* sorting system */
	var order = `'. ((isset($_GET['order'])) ? esc_attr(pc_static::sanitize_val($_GET['order'])) : 'desc') .'`;
	var orderby = `'. ((isset($_GET['orderby'])) ? esc_attr(pc_static::sanitize_val($_GET['orderby'])) : 'id') .'`;
	
	$(`.pc_filter_th[rel=`+orderby+`]`).addClass(`active_`+order);
	
	$(document).on(`click`, `.pc_filter_th`, function() {
		var new_orderby = $(this).attr(`rel`);
		
		if(new_orderby == orderby) {
			var new_order = (order == `asc`) ? `desc` : `asc`;	
		} else {
			var new_order = `asc`;	
		}

		var sort_url = window.location.href;
		
		if(sort_url.indexOf(`orderby=`+orderby) != -1) {
			sort_url = sort_url.replace(`orderby=`+orderby, `orderby=`+new_orderby).replace(`order=`+order, `order=`+new_order);
		} else {
			sort_url = sort_url + `&orderby=`+ new_orderby +`&order=`+ new_order;	
		}';
		
		if(isset($_GET['pagenum'])) {
            $inline_js .= "sort_url = sort_url.replace('pagenum=". absint($_GET['pagenum']) ."', 'pagenum=1');"; // back to page 1
        }
		
        $inline_js .= '
		window.location.href = sort_url;
	});
    
    
    
    // lc select
	const pc_as_lb_live_select = function() { 
        new lc_select(`.pc_ulist_as_lb .pc_as_cat_select`, {
            wrap_width : `calc(100% - 10rem)`,
            addit_classes : [`lcslt-lcwp`, `pc_scw_field_dd`],
        });
        
        new lc_select(`#pc_as_user_atts td > select`, {
            wrap_width : `100%`,
            addit_classes : [`lcslt-lcwp`, `pc_scw_field_dd`],
        });
	};
    
})(jQuery);';
wp_add_inline_script('lcwp_magpop', $inline_js);
