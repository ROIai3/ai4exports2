<?php 
if(!defined('ABSPATH')) {exit;}


// redirects - custom field allowing custom URL insertion
function pvtcont_redirect_custom_field($field_id, $field, $value, $all_vals) {
	
	// WP pages list
	$pages = pc_static::get_pages(); 
	
	// label and note
	switch($field_id) {
		case 'pg_redirect_page' : 
			$label = esc_html__("Main redirect target for restricted pages", 'pc_ml'); 
			$note = esc_html__('Unlogged users trying to reach restricted page will be moved there', 'pc_ml'); 
			break;
			
		case 'pg_blocked_users_redirect' : 
			$label = esc_html__("Redirect target for blocked users", 'pc_ml'); 
			$note = esc_html__('Logged users not matching specific restrictions will be moved there (eg. logged user does not own the right category to acces a page)', 'pc_ml'); 
			break;
		
		case 'pg_logged_user_redirect' : 
			$label = esc_html__("Redirect page after user's login", 'pc_ml'); 
			$note = esc_html__("This value can be overwritten in user categories or through shortcode", 'pc_ml'); 
			break;
			
		case 'pg_logout_user_redirect' : 
			$label = esc_html__("Redirect page after user's logout", 'pc_ml'); 
			$note = esc_html__("By default users will be moved there after the logout", 'pc_ml'); 
			break;	
		
	}
	
	
	// main redirect - if no value - select first page
	if($field_id == 'pg_redirect_page' && !$value) {
		$value = key($pages);	
	}
	
	// options
	$opts = array(
		'' 			=> (in_array($field_id, array('pg_logged_user_redirect', 'pg_logout_user_redirect'))) ? 
                        '- '. esc_html__('Refresh the page', 'pc_ml') : '- '. esc_html__('Do not redirect users', 'pc_ml'), 
                        
		'use_main' 	=> '- '. esc_html__('Use main redirect target', 'pc_ml'),
		'custom' 	=> '- '. esc_html__('Custom redirect', 'pc_ml')
	) + $pages;
	
	// specific cases
	if($field_id == 'pg_redirect_page' || $field_id == 'pg_blocked_users_redirect') {unset($opts['']);}
	if($field_id != 'pg_blocked_users_redirect') {unset($opts['use_main']);}
	
	// custom text visibility
	$ct_vis = ($value == 'custom' || ($field_id == 'pg_redirect_page' && !$value)) ? '' : 'pc_displaynone';
	
	// custom text value
	$ct_val = isset($all_vals[$field_id .'_custom']) ? esc_attr($all_vals[$field_id .'_custom']) : '';
	
	
	// build code
	echo '
	<tr class="'. esc_attr($field_id) .'">
		<td class="lcwp_sf_label" rowspan="2"><label>'. wp_kses_post($label) .'</label></td>
		<td class="lcwp_sf_field">
			<select name="'. esc_attr($field_id) .'" class="lcwp_sf_select pc_redirect_cf_select" autocomplete="off">';
			
				foreach($opts as $id => $name) {
					echo '<option value="'. esc_attr($id) .'" '. selected($value, $id, false) .'>'. esc_html($name) .'</option>';	
				}
	echo '
			</select>   
		</td>
		<td><p class="lcwp_sf_note">'. wp_kses_post($note) .'</p></td>
	</tr>
	<tr>
		<td colspan="2" class="pc_redirect_cfw '. esc_attr($field_id) .'_cfw '. esc_attr($ct_vis) .'">
			<input type="text" name="'. esc_attr($field_id) .'_custom" value="'. esc_attr($ct_val) .'" placeholder="'. esc_attr__('Custom redirect URL', 'pc_ml') .'" />
		</td>
	</tr>';
	
    if(!isset($GLOBALS['pvtcont_redirect_custom_field_js_printed'])) {
        $GLOBALS['pvtcont_redirect_custom_field_js_printed'] = true;
        
        $inline_js = '
        (function($) { 
            "use strict";

            $(document).on(`change`, `.pc_redirect_cf_select`, function(e) {
                var $subj = $(`.`+ $(this).attr(`name`) +`_cfw`);

                if($(this).val() == `custom`) {
                    $subj.removeClass(`pc_displaynone`);	
                } else {
                    $subj.addClass(`pc_displaynone`);
                }
            });
        })(jQuery);';
        wp_add_inline_script('lcwp_magpop', $inline_js);
        
    }
}





// targeted users allowed to use the plugin
function pvtcont_users_tup_cb($field_id, $field, $value, $all_vals) {
    ?>
    <tr class="pc_<?php echo esc_attr($field_id) ?>">
        <td class="lcwp_sf_label"><label><?php esc_html_e('Targeted users able to use the plugin', 'pc_ml') ?><br/><small class="lcwp_sf_note"><?php esc_html_e('NB: administrators are always included', 'pc_ml') ?></small></label></td>
		<td class="lcwp_sf_field" colspan="2">
            
            <?php 
            $the_val = (isset($all_vals['pg_users_tup'])) ? $all_vals['pg_users_tup'] : array();
            echo pc_static::wp_kses_ext(pc_wpuc_static::autocomplete_users_search_n_pick('pg_users_tup', $the_val));
            ?>
            <input type="hidden" name="<?php echo esc_attr($field_id) ?>" /> <!-- js vis trick -->
		</td>
	</tr>
    <?php
}

    
    





// WP user sync - buttons
//// perform sync
function pvtcont_do_wp_sync($field_id, $field, $value, $all_vals) {
	if($field['hide']) {return false;}
	?>
	<tr>
        <td colspan="2">
        	<input type="button" id="pc_do_wp_sync" class="button-secondary" value="<?php esc_attr_e('Sync users', 'pc_ml') ?>" />
        	<span class="pc_gwps_result"></span>
        </td>
        <td><span class="lcwp_sf_note"><strong><?php esc_html_e('Only users with unique username and e-mail will be synced', 'pc_ml') ?></strong></span></td>
    </tr>
    <?php	
}

//// sync existing users - only if $_GET['wps_existing_sync'] isset
function pvtcont_wps_matches_sync($field_id, $field, $value, $all_vals) {
	if($field['hide'] || !isset($_GET['wps_existing_sync'])) {return false;}
	?>
	<tr>
        <td colspan="2">
        	<input type="button" id="pc_wps_matches_sync" class="button-secondary" value="<?php esc_attr_e('Search existing matches and sync', 'pc_ml') ?>" />
        	<span class="pc_gwps_result"></span>
        </td>
        <td><span class="lcwp_sf_note"><strong><?php esc_html_e('Search matches between existing PrivateContent and WP users, and sync them', 'pc_ml') ?></strong></span></td>
    </tr>
    <?php	
}

//// unsync
function pvtcont_clean_wp_sync($field_id, $field, $value, $all_vals) {
	if($field['hide']) {return false;}
	?>
	<tr>
        <td colspan="2">
        	<input type="button" id="pc_clean_wp_sync" class="button-secondary" value="<?php esc_attr_e('Clear sync', 'pc_ml') ?>" />
        	<span class="pc_gwps_result"></span>
        </td>
        <td><span class="lcwp_sf_note"><strong><?php esc_html_e('Detach previous sync and delete related WP users', 'pc_ml') ?></strong></span></td>
    </tr>
    <?php	
}




// registration form builder
function pvtcont_sc_reg_form_builder($field_id, $field, $value, $all_vals) {
?>
    <table id="pc_reg_form_builder_cmd_wrap" class="widefat">
        <tbody>
            <tr>
                <td>
                    <input type="text" name="pg_new_reg_form_name" id="pc_new_reg_form_name" placeholder="<?php esc_attr_e("New form's name", 'pc_ml') ?>" maxlength="150" autocomplete="off" />
                </td>
                <td>
                    <input type="button" value="<?php esc_attr_e('Add', 'pc_ml') ?>" id="pc_reg_form_add" class="button-secondary" />
                </td>
                <td>
                    <select name="pg_form_builder_dd" class="lcwp_sf_select pc_form_builder_dd" data-placeholder="<?php esc_attr_e('Select a form to edit', 'pc_ml') ?> .." autocomplete="off">
                        <?php 
                        $a = 0;
                        $reg_forms = get_terms(array(
                            'taxonomy'   => 'pc_reg_form',
                            'hide_empty' => 0,
                            'orderby'    => 'name',
                            'order'      => 'ASC',
                        ));
                                                                       
                        foreach($reg_forms as $rf) {
                           $sel = (!$a) ? 'selected="selected"' : '';
                            
                           echo '<option value="'. absint($rf->term_id) .'" '. esc_html($sel) .'>'. absint($rf->term_id) .' - '. esc_html($rf->name) .'</option>';
                           $a++;
                        }
                        ?>
                    </select>
                </td>
                <td id="pc_reg_form_cmd">
                    <input type="button" value="<?php esc_attr_e('Save', 'pc_ml') ?>" class="pc_reg_form_save button-primary" />
                    <input type="button" value="<?php esc_attr_e('Delete', 'pc_ml') ?>" id="pc_reg_form_del" class="button-secondary" />
                </td>
            </tr> 	
        </tbody>
    </table>

    <i id="pc_reg_form_loader"></i>
    <br class="clear" /> 
    
    <div id="pc_reg_form_builder"></div>	
<?php	
}



// Fixed fields - placeholder and icons
function pvtcont_fix_field_detail($field_id, $field, $value, $all_vals) {
	include_once(PC_DIR .'/classes/pc_form_framework.php');
	
	$f_fw = new pc_form;
	$fields = $f_fw->fields;
	$fname = $field['cb_subj'];
	
	switch($fname) {
		case 'name' 		: $label 	= esc_html__("Name's icon and placeholder text", 'pc_ml'); break;
		case 'surname' 		: $label 	= esc_html__("Surname's icon and placeholder text", 'pc_ml'); break;
		case 'username' 	: $label 	= esc_html__("Username's icon and placeholder text", 'pc_ml'); break;
		case 'psw' 			: $label 	= esc_html__("Password's icon and placeholder text", 'pc_ml'); break;
		case 'repeat_psw' 	: $label 	= esc_html__("Repeat password's icon and placeholder text", 'pc_ml'); break;
		case 'categories' 	: $label 	= esc_html__("Category's icon and placeholder text", 'pc_ml'); break;
		case 'email' 		: $label 	= esc_html__("E-mail's icon and placeholder text", 'pc_ml'); break;
		case 'tel' 			: $label 	= esc_html__("Telephone's icon and placeholder text", 'pc_ml'); break;	
	}
	
	
	$icon = get_option('pg_'. $fname .'_icon');
    $shown_icon = $icon;
    
	$plac = get_option('pg_'. $fname .'_placeh', ($fname == 'repeat_psw') ? 'Repeat password' : $fields[$fname]['placeh']);
    
    // special case for psw field with revealer
    if(isset($all_vals['pg_single_psw_f_w_reveal']) && $all_vals['pg_single_psw_f_w_reveal'] && $fname == 'psw') {
        $shown_icon = 'far fa-eye';    
        $noclick_css = 'style="pointer-events: none; filter: contrast(0.8);"';    
    } else {
        $noclick_css = '';        
    }
	?>
    <tr class="pc_<?php echo esc_attr($field_id) ?>">
		<td class="lcwp_sf_label">
        	<label><?php echo esc_html($label) ?></label>
        </td>
		<td class="lcwp_sf_field">
			
            <div class="pc_field_icon_trigger" <?php echo esc_html($noclick_css) ?>>
                <i class="<?php echo esc_attr(pc_static::fontawesome_v4_retrocomp($shown_icon)) ?>" title="<?php esc_attr_e('set icon', 'pc_ml') ?>"></i>
                <input type="hidden" name="pg_<?php echo esc_attr($fname) ?>_icon" value="<?php echo esc_attr(pc_static::fontawesome_v4_retrocomp($icon)) ?>" autocomplete="off" /> 
            </div>
            
            <input type="text" name="pg_<?php echo esc_attr($fname) ?>_placeh" value="<?php echo esc_attr($plac) ?>" class="pc_cust_field_placeh" />  
		</td>
	</tr>
    <?php
}



// Buttons icon
function pvtcont_btns_icon($field_id, $field, $value, $all_vals) {
	$btn = $field['cb_subj'];
	$opt_name = 'pg_'. $btn .'_btn_icon';
	
	switch($btn) {
		case 'register' : $label 	= esc_html__("Registration form - button's icon", 'pc_ml'); break;
		case 'login' 	: $label 	= esc_html__("Login form - button's icon", 'pc_ml'); break;
		case 'logout' 	: $label 	= esc_html__("Logout button's icon", 'pc_ml'); break;	
		case 'user_del' : $label 	= esc_html__("User deletion's icon", 'pc_ml'); break;	
	}
	
	$icon = get_option($opt_name);
	?>
    <tr class="pc_<?php echo esc_attr($field_id) ?>">
		<td class="lcwp_sf_label">
        	<label><?php echo esc_html($label) ?></label>
        </td>
		<td class="lcwp_sf_field">
			
            <div class="pc_field_icon_trigger">
                <i class="<?php echo esc_attr(pc_static::fontawesome_v4_retrocomp($icon)) ?>" title="<?php esc_attr_e('set icon', 'pc_ml') ?>"></i>
                <input type="hidden" name="<?php echo esc_attr($opt_name) ?>" value="<?php echo esc_attr(pc_static::fontawesome_v4_retrocomp($icon)) ?>" autocomplete="off" /> 
            </div>
		</td>
	</tr>
    <?php
}



// Lightbox Instances
function pvtcont_lightbox_instances($field_id, $field, $value, $all_vals) {
	?>
    <ul id="pc_lb_inst">
		<?php 
		$lb_instances = get_terms(array(
            'taxonomy'   => 'pc_lightboxes',
            'hide_empty' => 0,
            'order'      => 'ASC',
        ));
		 
		if(empty($lb_instances)) : ?>
            <em><?php esc_html_e('no existing instances', 'pc_ml') ?> ..</em>
			
        <?php else : 
            $a = 0;
            foreach($lb_instances as $inst) : 
			
				if(isset($GLOBALS['pvtcont_lb_data']) && isset($GLOBALS['pvtcont_lb_data'][$inst->term_id])) {
					$note 		= $GLOBALS['pvtcont_lb_data'][$inst->term_id]['note'];	
					$contents 	= $GLOBALS['pvtcont_lb_data'][$inst->term_id]['contents'];	
				}
				else {
					$note 		= ($inst->name == '|||pclbft|||') ? '' : $inst->name;	
					$contents 	= base64_decode($inst->description);	
				}
				?>	
                
                <li>
                    <aside>
                        <span class="pc_del_field dashicons dashicons-no-alt" rel="<?php echo absint($inst->term_id) ?>" title="<?php esc_attr_e('remove restriction', 'pc_ml') ?>"></span>
                        <input type="hidden" name="pc_lb_id[]" value="<?php echo absint($inst->term_id) ?>" />
                    </aside>    
                    <div class="pc_lb_inst_toprow">
                        <table>
                          <tr>
                            <td>
                                <input type="text" name="pc_lb_note[]" class="pc_lb_note" value="<?php echo esc_attr($note) ?>" placeholder="<?php esc_attr_e("Lightbox title (required)", 'pc_ml') ?>" maxlength="250" autocomplete="off" />
                            </td>
                            <td>
                                <span><strong><?php esc_html_e('Trigger class', 'pc_ml') ?>:</strong> <em>pc_lb_trig_<?php echo absint($inst->term_id) ?></em></span>
                                <strong>|</strong>
                                <span><strong><?php esc_html_e('Lightbox class', 'pc_ml') ?>:</strong> <em>pc_lb_<?php echo absint($inst->term_id) ?></em></span>
                            </td>
                          </tr>
                        </table>
                    </div>                
                    <div class="pclb_editow_wrap">
                        <?php 
						$args = array('textarea_rows'=> 2);
						wp_editor($contents, 'pclb_'.$inst->term_id, $args); 
						?>
                    </div>
                </li>
            <?php endforeach;
         endif; ?>
    </ul>
    
    <?php 
    // force textarea name to be array
    $inline_js = '
    (function($) { 
        "use strict";
        
        $(document).ready(function(e) {
            setTimeout(function() {
                $(".pclb_editow_wrap textarea.wp-editor-area").attr("name", "pc_lb_contents[]");
            }, 500);
        });
    })(jQuery);';
    wp_add_inline_script('lc-select', $inline_js);
}




// Settings -> URL-based custom restrictions - HTML template
function pvtcont_cr_template($url = '', $allow = false, $block = false) {
	$code = '<li>';
	
	$code .= '
	<aside>
		<span class="pc_del_field dashicons dashicons-no-alt" title="'. esc_attr__('remove restriction', 'pc_ml') .'"></span>
		<span class="pc_move_field dashicons dashicons-move" title="'. esc_attr__('sort restriction', 'pc_ml') .'"></span>
	</aside>';
	
	$code .= '
	<div class="pg_cr_url_fwrap">
		<input type="text" name="pg_cr_url[]" value="'. $url .'" placeholder="'. esc_attr__("URL to restrict. Supports also regular expressions", 'pc_ml') .'" autocomplete="off" />
	</div>';
		
	$code .= '
	<div class="pg_cr_allow_fwrap">
		<label>'. esc_html__('Which user categories can access?', 'pc_ml') .'</label>
		<select name="pg_cr_allow[][]" class="lcwp_sf_select pg_cr_allow" multiple="multiple" autocomplete="off">
			'. pc_static::user_cat_dd_opts($allow) .'
		</select>
	</div>';
	
	
	$code .= '
	<div class="pg_cr_allow_fwrap">
		<label>'. esc_html__('Among them - want to block someone?', 'pc_ml') .'</label>
		<select name="pg_cr_block[][]" class="lcwp_sf_select pg_cr_block" multiple="multiple" autocomplete="off">
			'. pc_static::user_cat_dd_opts($block, false) .'
		</select>
	</div>';	
			
			
	return $code . '</li>';
}




// preset styles preview and setter 
function pvtcont_preset_styles($field_id, $field, $value, $all_vals) {

	echo '
	<table id="pc_preset_styles_cmd_wrap" class="widefat lcwp_settings_table">
		<tr class="pc_'. esc_attr($field_id) .'">
			<td class="lcwp_sf_label"><label>'. esc_html__('Setup a preset style?', 'pc_ml') .'</label></td>
			<td class="lcwp_sf_field">
				<select name="'. esc_attr($field_id) .'" id="pc_pred_styles" class="lcwp_sf_select mg_pred_styles_cf_select" autocomplete="off">
					<option value="">('. esc_html__('choose an option to preview', 'pc_ml') .')</option>';
				
					foreach(pc_preset_style_names() as $id => $name) {
						echo '<option value="'. esc_attr($id) .'">'. esc_html($name) .'</option>';	
					}
		  echo '
				</select>   
			</td>
			<td style="width: 50px;">
				<input name="mg_set_style" id="pc_set_style" value="'. esc_attr__('Set', 'pc_ml') .'" class="button-secondary" type="button" />
			</td>
			<td><p class="lcwp_sf_note">'. esc_html__('Overrides styling options and applies preset styles', 'pc_ml') .'. '. /* translators: 1: HTML code, 2: HTML code. */ sprintf(esc_html__('Once applied, %1$spage will be reloaded%2$s showing updated options', 'pc_ml'), '<strong>', '</strong>') .'</p></td>
		</tr>
		<tr class="pc_displaynone">
			<td class="lcwp_sf_label"><label>'. esc_html__('Preview', 'pc_ml') .'</label></td>
			<td colspan="3" id="pc_preset_styles_preview"></td>
		</tr>
	</table>';
	
	
    $inline_js = '
    (function($) { 
        "use strict";    
        
        // predefined style - preview toggle
        $(document).on("change", `#pc_pred_styles`, function() {
            const sel = $(`#pc_pred_styles`).val();

            if(!sel) {
                $(`#pc_preset_styles_cmd_wrap tr`).last().hide();
                $(`#pc_preset_styles_preview`).empty();	
            }
            else {
                $(`#pc_preset_styles_cmd_wrap tr`).last().show();

                const img_url = `'. esc_js(PC_URL) .'/img/preset_styles_demo/`+ sel +`.jpg`;
                $(`#pc_preset_styles_preview`).html(`<img src="`+ img_url +`" />`);		
            }
        });


        // set predefined style 
        $(document).on(`click`, `#pc_set_style`, function() {
            const sel_style = $(`#pc_pred_styles`).val();
            
            if(!sel_style) {
                return false;
            }

            if(!confirm(`'. esc_attr__('This will overwrite your current settings reloading the page, continue?', 'pc_ml') .'`)) {
                return false;
            } 
            $(this).replaceWith(`<div class="pc_spinner pc_spinner_inline"></div>`);

            var data = {
                action     : `pvtcont_set_predefined_style`,
                style      : sel_style,
                lcwp_nonce : `'. esc_js(wp_create_nonce('lcwp_nonce')) .'`
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) == `success`) {
                    lc_wp_popup_message(`success`, "'. esc_attr__('Style successfully applied!','pc_ml') .'");	

                    setTimeout(function() {
                        window.location.reload();	
                    }, 1500);
                }
                else {
                    lc_wp_popup_message(`error`, response);	
                }
            })
            .fail(function(e) {
                console.error(e);
                lc_wp_popup_message(`error`, "Error applying preset style");	
            });	
        });
    })(jQuery);';
    wp_add_inline_script('lc-select', $inline_js);
}




// URL-based restrictions
function pvtcont_url_base_restr_field($field_id, $field, $value, $all_vals) {
	?>
    <ul id="pc_cr_list">
		<?php if(empty($all_vals['pg_cr_url'])) : ?>
            <em><?php esc_html_e('no custom restrictions added', 'pc_ml') ?> ..</em>
        <?php else : 
    
            $a = 0;
            foreach($all_vals['pg_cr_url'] as $pg_cr_url) {
                $allow = (is_array($all_vals['pg_cr_allow']) && isset($all_vals['pg_cr_allow'][$a])) ? (array)$all_vals['pg_cr_allow'][$a] : array(); 
                $block = (is_array($all_vals['pg_cr_block']) && isset($all_vals['pg_cr_block'][$a])) ? (array)$all_vals['pg_cr_block'][$a] : array(); 

                echo pc_static::wp_kses_ext(pvtcont_cr_template($pg_cr_url, $allow, $block));
                $a++;	
            }
        
         endif; ?>
    </ul>
    <?php	
}



