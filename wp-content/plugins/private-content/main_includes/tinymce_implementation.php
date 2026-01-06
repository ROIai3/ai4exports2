<?php
// implement tinymce button
if(!defined('ABSPATH')) {exit;}


add_action('admin_init', function() {
	if(!current_user_can('edit_posts') && !current_user_can('edit_pages')){
		return;
    }

	if(get_user_option('rich_editing') == 'true') {
		add_filter('mce_external_plugins', function($plugins) {
            $plugins['PrivateContent'] = PC_URL . '/js/tinymce_btn.js';
            return $plugins;
        });
		
        
        add_filter( 'mce_buttons', function($buttons) {
            array_push($buttons, '|', 'pc_btn');
            return $buttons;
        });
	}
});
	



add_action('admin_footer', function() {
	global $current_screen;
	
	if(
		strpos($_SERVER['REQUEST_URI'], 'post.php') || 
		strpos($_SERVER['REQUEST_URI'], 'post-new.php') || 
		$current_screen->id == 'pvtcont-add-ons_page_pcma_settings' ||
		$current_screen->id == 'privatecontent_page_pc_settings' ||
		$current_screen->id == 'pvtcont-add-ons_page_pcma_quick_mail'
	) :
	
		// lightbox instances
		$lb_instances = pc_static::get_lb_instances();
	
		// PC-FILTER - add tabs in shortcode wizard
		//// structure array(tab-id => array('name' => ... , 'contents' => ... )
		$add_tabs = apply_filters('pc_tinymce_tabs', array());
		if(!is_array($add_tabs)) {
            $add_tabs = array();
        }
	?>
    
    <div id="pvtcontent_sc_wizard" class="pc_displaynone">
    	<div class="pc_scw_choser_wrap pc_scw_choser_wrap">
            <select name="pc_scw_choser" class="pc_scw_choser" autocomplete="off">
                <?php if(!ISPCF) : ?>
                <option value="#pc_pvt_block"><?php esc_html_e('Private Block', 'pc_ml') ?></option>
                <?php endif; ?>
            
                <option value="#pc_sc_reg"><?php esc_html_e('Registration Form', 'pc_ml') ?></option>
                <option value="#pc_login"><?php esc_html_e('Login Form', 'pc_ml') ?></option>
                <option value="#pc_logout"><?php esc_html_e('Logout Button', 'pc_ml') ?></option>
                <option value="#pc_user_del"><?php esc_html_e('User Deletion Box', 'pc_ml') ?></option>
                <?php
                foreach($add_tabs as $tab_id => $tab) {
                    echo '<option value="#'. esc_attr($tab_id) .'">'. esc_html($tab['name']) .'</option>';	
                }
                ?>	
            </select>	
        </div>
        
        
        <?php if(!ISPCF) : ?>
        <div id="pc_pvt_block" class="pc_scw_block pc_scw_block"> 
            <ul>
                <li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Who can can see contents?', 'pc_ml') ?></label>
               		<select name="pc_sc_allow" id="pc_sc_allow" multiple="multiple" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select categories', 'pc_ml') ?> .." autocomplete="off">
						<?php 
                        echo pc_static::wp_kses_ext(pc_static::user_cat_dd_opts());
                        ?>
                	</select>
                </li>
                <li class="pc_scw_field pc_scw_field pc_sc_block_wrap pc_displaynone">
                	<label><?php esc_html_e('Among them - want to block someone?', 'pc_ml') ?></label>
               		<select name="pc_sc_block" id="pc_sc_block" multiple="multiple" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select categories', 'pc_ml') ?> .." autocomplete="off">
						<?php 
                        echo pc_static::wp_kses_ext(pc_static::user_cat_dd_opts(false, false));
                        ?>
                	</select>
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Hide warning box?', 'pc_ml') ?></label>
                    <input type="checkbox" id="pg-hide-warning" name="pg-hide-warning" value="1" class="pc_lc_switch" autocomplete="off" />
                </li>
                
                <li class="pc_scw_field pc_scw_field pc_scw_wb_row">
                	<label><?php esc_html_e('Custom message for not allowed users', 'pc_ml') ?></label>
                    <textarea id="pg-text" name="pg-text" autocomplete="off"></textarea>
                </li>
                <li class="pc_scw_field pc_scw_field pc_scw_wb_row">
                	<label><?php esc_html_e("Login button's lightbox", 'pc_ml') ?></label>
               		<select name="pc_sc_login_lb" id="pc_sc_login_lb" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." autocomplete="off">
						<option value=""><?php esc_html_e('As default', 'pc_ml') ?></option>
						<option value="none"><?php esc_html_e('No login button', 'pc_ml') ?></option>
						<?php 
						foreach($lb_instances as $lb_id => $lb_name) {
							echo '<option value="'. esc_attr($lb_id) .'">'. esc_html($lb_name) .'</option>';	
						}
                        ?>
                	</select>
                </li>
                <li class="pc_scw_field pc_scw_field pc_scw_wb_row">
                	<label><?php esc_html_e("Registration button's lightbox", 'pc_ml') ?></label>
               		<select name="pc_sc_registr_lb" id="pc_sc_registr_lb" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." autocomplete="off">
						<option value=""><?php esc_html_e('As default', 'pc_ml') ?></option>
						<option value="none"><?php esc_html_e('No registration button', 'pc_ml') ?></option>
						<?php 
						foreach($lb_instances as $lb_id => $lb_name) {
							echo '<option value="'. absint($lb_id) .'">'. esc_html($lb_name) .'</option>';	
						}
                        ?>
                	</select>
                </li>
                
                <li class="pc_scw_field pc_scw_field">
                	<input type="button" id="pg-pvt-content-submit" class="button-primary" value="<?php esc_attr_e('Insert', 'pc_ml') ?>" name="submit"  />
                </li>
            </ul>
        </div>
        <?php endif; ?>
        
        
        <div id="pc_sc_reg" class="pc_scw_block pc_scw_block">
            <ul>
            	<li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Which form?', 'pc_ml') ?></label>
                    <select name="pc_sc_rf_id" id="pc_sc_rf_id" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." autocomplete="off">
					  <?php 
                      $reg_forms = get_terms(array(
                        'taxonomy'   => 'pc_reg_form',
                        'hide_empty' => 0,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                      ));
    
                      foreach($reg_forms as $rf) {
                          echo '<option value="'. absint($rf->term_id) .'">'. esc_html($rf->name) .'</option>';
                      }
                      ?>
                    </select>
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Layout', 'pc_ml') ?></label>
                    <select name="pc_sc_rf_layout" id="pc_sc_rf_layout" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." autocomplete="off">
                        <option value="" selected="selected"><?php esc_html_e('Default one', 'pc_ml') ?></option>
                        <option value="one_col"><?php esc_html_e('Single column', 'pc_ml') ?></option>
                        <option value="fluid"><?php esc_html_e('Fluid (multi column)', 'pc_ml') ?></option>
                    </select>
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Custom categories assignment (ignored if field is in form)', 'pc_ml') ?></label>
                    <select name="pc_sc_rf_cat" id="pc_sc_rf_cat" multiple="multiple" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select categories', 'pc_ml') ?> .." autocomplete="off">
					  <?php
                      $ucats = get_terms( array(
                        'taxonomy'   => 'pg_user_categories',
                        'orderby'    => 'name',
                        'hide_empty' => 0,
                      ));
                          
                      foreach($ucats as $ucat) {
                        echo '<option value="'. absint($ucat->term_id) .'">'. esc_html($ucat->name) .'</option>';		
                      }	
                      ?>
                    </select>
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Custom redirect (use a valid URL or "refresh" keyword)', 'pc_ml') ?></label>
                    <input type="text" name="pc_sc_rf_redirect" id="pc_sc_rf_redirect" value="" autocomplete="off" />
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Form alignment', 'pc_ml') ?></label>
                    <select name="pc_sc_rf_align" id="pc_sc_rf_align" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." autocomplete="off">
                        <option value="center" selected="selected"><?php esc_html_e('Center', 'pc_ml') ?></option>
                        <option value="left"><?php esc_html_e('Left', 'pc_ml') ?></option>
                        <option value="right"><?php esc_html_e('Right', 'pc_ml') ?></option>
                    </select>
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<input type="button" id="pg-regform-submit" class="button-primary" value="<?php esc_attr_e('Insert Form', 'pc_ml') ?>" name="submit" />
                </li>
       		</ul>
        </div> 
        
        
        <div id="pc_login" class="pc_scw_block pc_scw_block">
            <ul>
            	<li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Custom redirect (use a valid URL or "refresh" keyword)', 'pc_ml') ?></label>
                    <input type="text" name="pc_sc_lf_redirect" id="pc_sc_lf_redirect" value="" autocomplete="off" />
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Form alignment', 'pc_ml') ?></label>
                    <select name="pc_sc_lf_align" id="pc_sc_lf_align" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." autocomplete="off">
                        <option value="center" selected="selected"><?php esc_html_e('Center', 'pc_ml') ?></option>
                        <option value="left"><?php esc_html_e('Left', 'pc_ml') ?></option>
                        <option value="right"><?php esc_html_e('Right', 'pc_ml') ?></option>
                    </select>
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<input type="button" id="pg-loginform-submit" class="button-primary" value="<?php esc_attr_e('Insert Form', 'pc_ml') ?>" name="submit" />
                </li>
       		</ul>
        </div>  
        
        
        <div id="pc_logout" class="pc_scw_block pc_scw_block">
            <ul>
            	<li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Custom redirect (use a valid URL)', 'pc_ml') ?></label>
                    <input type="text" name="pc_sc_lb_redirect" id="pc_sc_lb_redirect" value="" autocomplete="off" />
                    <p><?php 
                        /* translators: 1: html code, 2: html code, 3: html code, 4: html code. */
                        echo sprintf(esc_html__('%1$sNOTE:%2$s is possible to directly logout users adding %3$s?pc_logout%4$s to any site URL', 'pc_ml'), '<strong>', '</strong>', '<em>', '</em>') . '.<br/>'. esc_html__('Example', 'pc_ml') .': <em>http://www.mysite.com/?pc_logout</em>' 
                    ?></p>
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<input type="button" id="pg-logoutbox-submit" class="button-primary" value="<?php esc_attr_e('Insert Button', 'pc_ml') ?>" name="submit" />
                </li>
       		</ul>
        </div>   


		<div id="pc_user_del" class="pc_scw_block pc_scw_block">
            <ul>
            	<li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Form alignment', 'pc_ml') ?></label>
                    <select name="pc_sc_udel_align" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." autocomplete="off">
                        <option value="center" selected="selected"><?php esc_html_e('Center', 'pc_ml') ?></option>
                        <option value="left"><?php esc_html_e('Left', 'pc_ml') ?></option>
                        <option value="right"><?php esc_html_e('Right', 'pc_ml') ?></option>
                    </select>
                </li>
            	<li class="pc_scw_field pc_scw_field">
                	<label><?php esc_html_e('Custom redirect (use a valid URL)', 'pc_ml') ?></label>
                    <input type="text" name="pc_sc_udel_redirect" value="" autocomplete="off" />
                </li>
                <li class="pc_scw_field pc_scw_field">
                	<input type="button" id="pg-userdel-submit" class="button-primary" value="<?php esc_attr_e('Insert Box', 'pc_ml') ?>" name="submit" />
                </li>
       		</ul>
        </div>   

        
        <?php
        // additional tabs
        foreach($add_tabs as $tab_id => $tab) {
            echo '<div id="'. esc_attr($tab_id) .'" class="pc_scw_block pc_scw_block">'. pc_static::wp_kses_ext($tab['contents']) .'</div>';	
        }
        ?>
    </div>    
    
    <?php
	endif;
	return true;
}, 1);
