<?php 
if(!defined('ABSPATH')) {exit;}


//// TABS

// PC-FILTER - manipulate user dashboard tabs (MAIN always prepended) - passes the user id we are editing
$tabs = apply_filters('pc_user_dashboard_tabs', array(), $GLOBALS['pvtcont_user_dashboard_user_id']);	

if(!is_array($tabs)) {
    $tabs = array();
}
if(isset($tabs['main'])) {
    unset($tabs['main']);
}

// prepend MAIN
$GLOBALS['pvtcont_user_dashboard_tabs'] = array('main' => esc_html__('Main Data', 'pc_ml')) + $tabs;







//// STRUCTURE - blocks enqueued through WP filter

/* tab_id => array( 
	 'sect_id' => array(
		'name'		=> (string) -> section name
		'classes'	=> (string) -> optional CSS classes to be applied to the wrapper
		'callback' 	=> (string) -> callback function name - the user id we are editing is passed as argument
		
   )
*/

$structure = array();


foreach($GLOBALS['pvtcont_user_dashboard_tabs'] as $tab_id => $tab_name) {
    
	// PC-FILTER - enqueues user dashboard sections for each tab - the filter name is dynamic basing on the tab ID - passes the user id we are editing
	$sections = apply_filters('pc_user_dashboard_'. $tab_id .'_tab_sections', array(), $GLOBALS['pvtcont_user_dashboard_user_id']);
	$structure[$tab_id] = (array)$sections;
	
	// main tab - always prepend main fields and WP user sync panel
	if($tab_id == 'main') {
		if(isset($structure[$tab_id]['main'])) 			{unset($structure[$tab_id]['main']);}
		if(isset($structure[$tab_id]['wp_user_sync'])) 	{unset($structure[$tab_id]['wp_user_sync']);}
		
		
		// add WP user sync?
		global $pc_users;
		$wps_array = (!$pc_users->wp_user_sync || !$GLOBALS['pvtcont_user_dashboard_user_id']) ? array() : array(
			'wp_user_sync' => array(
				'name' 		=> esc_html__('WordPress User Sync', 'pc_ml'),
                'classes'   => 'pc_ud_fullw_block',
				'callback' 	=> 'pvtcont_user_dashboard_wp_sync'
			)
		);
		
		
		// setup structure
		$structure[$tab_id] = array(
			'main' => array(
				'name' 		=> '',
				'classes'	=> 'pc_ud_2_cols_form pc_ud_fullw_block',
				'callback' 	=> 'pvtcont_user_dashboard_main_fields'
			),
		) + $wps_array + $structure[$tab_id];
	}	
}
$GLOBALS['pvtcont_user_dashboard_structure'] = $structure;









/////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////



// MAIN USER FIELDS
function pvtcont_user_dashboard_main_fields($user_id = false) {
	include_once(PC_DIR . '/classes/pc_form_framework.php');
	
	global $pc_users, $pc_wp_user;
	$form_fw = new pc_form;
	
	// first/last name flag 
	$fist_last_name = get_option('pg_use_first_last_name');
	
	// user data base array
	$ud = array(
		'username' 	=> '', 
		'name' 		=> '', 
		'surname' 	=> '', 
		'tel' 		=> '', 
		'email' 	=> '', 
		'page_id'	=> '',
		'disable_pvt_page' 	=> '', 
		'categories' 		=> '',
		'status'	=> ''
	); 

	// if editing - fill it
	if($user_id) {
		$ud = $pc_users->get_user($user_id, array(
			'to_get' => array_keys($ud)
		));
	}
	?>
    
    <table class="widefat">
      <tbody>
        <tr>
          <th><?php esc_html_e("Username", 'pc_ml'); ?> <span class="pc_req_field">*</span></th>
          <td>
              <?php 
              // lock username if is synced
              if($user_id && $pc_users->wp_user_sync && $pc_wp_user->pvtc_is_synced($user_id)) : ?>
              
                  <?php echo esc_html($ud['username']) ?><br/><small>( <?php esc_html_e("username can't be changed for WP synced users", 'pc_ml') ?>)</small>
              <?php else : ?>
              
                  <input type="text" name="username" value="<?php echo esc_attr($ud['username']) ?>"  maxlength="150" autocomplete="off" tabindex="1" />
              <?php endif; ?>
          </td>
          
          <th>
              <?php esc_html_e("E-mail", 'pc_ml'); if($form_fw->fields['email']['sys_req']) : ?> <span class="pc_req_field">*</span><?php endif; ?>
          </th>
          <td>
              <input type="text" name="email" value="<?php echo esc_attr($ud['email']) ?>" maxlength="255" autocomplete="off" tabindex="5" />
          </td>
        </tr>
        
        <tr>
          <th><?php ($fist_last_name) ? esc_html_e('First name', 'pc_ml') : esc_html_e('Name', 'pc_ml'); ?></th>
          <td>
              <input type="text" name="name" value="<?php echo esc_attr($ud['name']) ?>" maxlength="150" autocomplete="off" tabindex="2" />
          </td>
          
          <th><?php esc_html_e("Telephone", 'pc_ml'); ?></th>
          <td>
              <input type="text" name="tel" value="<?php echo esc_attr($ud['tel']) ?>" maxlength="20" autocomplete="off" tabindex="6" />
          </td>
        </tr>
        <tr>
          <th><?php ($fist_last_name) ? esc_html_e('Last name', 'pc_ml') : esc_html_e('Surname', 'pc_ml'); ?></th>
          <td>
              <input type="text" name="surname" value="<?php echo esc_attr($ud['surname']) ?>" maxlength="150" autocomplete="off" tabindex="3" />
          </td>
          
          <th><?php esc_html_e("Disable user's private page?", 'pc_ml'); ?></th>
          <td>
              <input type="checkbox" name="disable_pvt_page" value="1" <?php if($user_id && $ud['disable_pvt_page'] == 1) {echo 'checked="checked"';} ?> class="lcwp_sf_check" autocomplete="off" />
              
			  <?php if($user_id && !$ud['disable_pvt_page'] && (int)$ud['status'] != 3 && $GLOBALS['pvtcont_ud_cuc_edit'] && get_option('pg_target_page')) : ?>
              <button type="button" class="button-secondary pc_user_dashboard_edit_pp_btn" onclick="parent.open('<?php echo esc_js(admin_url()) ?>post.php?post=<?php echo absint($ud['page_id']) ?>&action=edit')">
                    <span class="dashicons dashicons-edit"></span> <?php esc_html_e('Edit Contents', 'pc_ml'); ?>
              </button>
              <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th><?php echo ($user_id) ? esc_html__("Update password", 'pc_ml') : esc_html__("Password", 'pc_ml'); ?> <?php if(!$user_id) : ?><span class="pc_req_field">*</span><?php endif; ?></th>
          <td>
              <input type="password" name="psw" value="" maxlength="100" autocomplete="off" tabindex="4" />
              <span class="fas fa-magic pc_psw_generator" title="<?php esc_attr_e('generate password', 'pc_ml') ?>" data-psw="<?php echo esc_attr($form_fw->generate_psw()) ?>"></span>
              <span class="dashicons dashicons-visibility pc_toggle_psw_vis" title="<?php esc_attr_e('toggle password visibility', 'pc_ml') ?>"></span>
              <small classs="pc_ud_psw_requirements"><?php echo esc_html(pc_form::psw_requiremens()) ?></small>
          </td>
          
          <th><?php esc_html_e("Categories", 'pc_ml'); ?> <span class="pc_req_field">*</span></th>
          <td>
              <?php
              $user_categories = get_terms(array(
                    'taxonomy'   => 'pg_user_categories',
                    'orderby'    => 'name',
                    'hide_empty' => 0,
              ));
              
              if(!count($user_categories)) {
                  echo '<li><a href="'. esc_attr(admin_url()) .'edit-tags.php?taxonomy=pg_user_categories" class="pc_user_dashboard_nocat_warn">'. esc_html__('Create at least one user category', 'pc_ml') .'</a></li>';
              }
              else {
                  $disabled = ($user_id && !get_option('pg_tu_can_edit_user_cats') && PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any') ? 'pc_displaynone' : '';
                
                  // PC admin that can't edit cats - show only texts 
                  if($disabled && $user_id) {
                      $textual_cats = array();
                      
                      foreach(pc_static::user_cats() as $cat_id => $cat_name) {
                        if(is_array($ud['categories']) && in_array($cat_id, $ud['categories'])) {
                            
                            $textual_cats[] = $cat_name;    
                        }
                      }
                      
                      echo esc_html(implode(', ', $textual_cats));       
                  }
                  
                  echo '
                  <div class="'. esc_attr($disabled) .'">
                      <select name="categories[]" multiple="multiple" class="pc_lc_select" data-placeholder="'. esc_attr__('Select categories', 'pc_ml') .' .." autocomplete="off" tabindex="7">';

                        foreach(pc_static::user_cats() as $cat_id => $cat_name) {

                            if(!$user_id && !get_option('pg_tu_can_edit_user_cats') && PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any' && !in_array($cat_id, (array)PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
                                continue;    
                            }

                            $selected = ($user_id && is_array($ud['categories']) && in_array($cat_id, $ud['categories'])) ?  'selected="selected"' : '';
                            echo '<option value="'. absint($cat_id) .'" '. esc_html($selected) .'>'. esc_html($cat_name) .'</option>';  
                        }

                      echo '
                      </select>
                  </div>';  
              }
              ?>
          </td>
        </tr>
      </tbody>  
    </table>  
    <?php	
}







// WP USER SYNC BOX
function pvtcont_user_dashboard_wp_sync($user_id = false) {
	global $pc_users, $pc_wp_user;
	$ud = $pc_users->get_user($user_id, array(
		'to_get' => array('email', 'specific_wp_roles')
	));
	$wp_synced_id = $pc_wp_user->pvtc_is_synced($user_id, true); 	
		
		
	if(empty($ud['email'])) :	
		?>
		<div class="pc_warn pc_wps_warn pc_warning">
			<?php esc_html_e("User cannot be sinced, e-mail is required", 'pc_ml') ?>
        </div>
		<?php
		
	elseif($wp_synced_id) :
		include_once(PC_DIR .'/settings/field_options.php'); // recall available WP roles
		
		$avail_roles = pvtcont_wps_emulable_roles();
		$emulated_roles = (array)get_option('pg_custom_wps_roles', array());
		
		$emulated_arr = array();
		foreach($emulated_roles as $id) {
			if(!isset($avail_roles[$id])) {continue;}
			$emulated_arr[] = $avail_roles[$id]; 	
		}
		
		$emulated_str = (empty($emulated_arr)) ? '' : ' &nbsp; - &nbsp; '. esc_html__("Globally emulated roles", 'pc_ml') .':&nbsp;<strong>'. implode(', ', $emulated_arr).'</strong>';
		
		?>
		<div class="pc_warn pc_wps_warn pc_success pc_ud_2_cols_form">
            <table class="widefat">
              <tbody>
                <tr>
                  <td>
                  	<p title="<?php echo esc_attr__('Synced with WP user', 'pc_ml').' - ID '. absint($wp_synced_id) ?>"><?php echo esc_html__("User synced", 'pc_ml') . wp_kses_post($emulated_str); ?></p>
                    
                    <button class="button-secondary" id="pc_wps_wp_fields" type="button">
                        <a href="<?php echo esc_attr(admin_url() .'user-edit.php?user_id='. $wp_synced_id) ?>&wp_http_referer=pvtcontent">
                            <?php esc_html_e('Manage WP Fields', 'pc_ml') ?>
                        </a>
                    </button>
                    
                    <button class="button-secondary" id="pc_detach_from_wp" type="button"><?php esc_html_e('Detach', 'pc_ml') ?></button>
                  </td> 
                  
                  <th><?php esc_html_e("Custom roles association", 'pc_ml'); ?> <span title="<?php esc_attr_e('Overrides global roles defined in settings. Leave empty to follow them', 'pc_ml') ?>" class="dashicons dashicons-editor-help"></span>
                  </th>
                  <td>
                  
                  	<select name="specific_wp_roles[]" multiple="multiple" autocomplete="off" class="pc_lc_select">
                        <?php	
                        foreach($avail_roles as $role_id => $role_name) {

                            $sel = (isset($ud['specific_wp_roles']) && is_array($ud['specific_wp_roles']) && in_array($role_id, $ud['specific_wp_roles'])) ? 'selected="selected"' : '';
                            echo '<option value="'. esc_attr($role_id) .'" '. esc_html($sel) .'>'. esc_html($role_name) .'</option>';	
                        }
						?>
                  	</select>
                  </td>
                </tr>
              </tbody>
            </table>
        </div>

        <?php 
        if(ISPCF) :
            $inline_js = '
            (function() { 
                "use strict";
                window.nfpcf_inject_infobox(".pc_warn.pc_wps_warn tr > *:last-child");
            })();';
            wp_add_inline_script('lcwp_magpop', $inline_js);
        endif;
			
	else :
		?>
		<div class="pc_warn pc_wps_warn pc_warning">
			<?php esc_html_e("User not synced", 'pc_ml') ?>
            <button class="button-secondary" id="pc_sync_with_wp"><?php esc_html_e('Sync Now', 'pc_ml') ?></button>
        </div>
		<?php
	endif;
}