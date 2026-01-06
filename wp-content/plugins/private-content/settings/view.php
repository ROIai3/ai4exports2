<?php 
if(!defined('ABSPATH')) {exit;}
dike_lc('lcweb', PC_DIKE_SLUG, true); /* NFPCF */

include_once(PC_DIR .'/classes/simple_form_validator.php');
include_once(PC_DIR .'/settings/settings_engine.php'); 
include_once(PC_DIR .'/settings/field_options.php'); 
include_once(PC_DIR .'/settings/custom_fields.php');
include_once(PC_DIR .'/settings/structure.php'); 
include_once(PC_DIR .'/settings/nfpcf.php');

global $pc_wp_user;
$ml_key = 'pc_ml';

lcwp_settings_engine::$prod_acronym = 'lcpc';
lcwp_settings_engine::$css_prefix = 'pc_';
lcwp_settings_engine::$ml_key = 'pc_ml';


$engine = new lcwp_settings_engine('pc_settings', $GLOBALS['pc_settings_tabs'], $GLOBALS['pc_settings_structure']);
?>

<div class="wrap lcwp_settings_wrap">
    <div class="lcwp_settings_head">
        <h2 class="lcwp_settings_page_title"><?php esc_html_e('PrivateContent Settings', 'pc_ml') ?></h2>  
        
        <div class="lcwp_settings_head_cmds">
            <?php echo lcwp_settings_engine::wp_kses_ext($engine->import_export_btns()) ?>
            <form class="lcwp_sf_search_wrap">
                <i class="dashicons dashicons-no-alt"></i>
                <input type="text" name="lcwp_sf_search" value="" placeholder="<?php esc_attr_e('search fields', 'pc_ml') ?> .." /> 
            </form>
        </div>
    </div>

	<?php
    // get fetched data and allow customizations
    if($engine->form_submitted()) {
        $fdata = $engine->form_data;
        $errors = (!empty($engine->errors)) ? $engine->errors : array();
        
        
        
        /////////////////////////////////////////////////////////
        
    
        
        // validate custom redirects
        if($fdata['pg_redirect_page'] == 'custom' && empty($fdata['pg_redirect_page_custom'])) {
            $errors[ esc_html__('Restriction redirect target / Custom URL', 'pc_ml') ] = esc_html__('Insert a valid URL', 'pc_ml'); 	
        }
        if($fdata['pg_logged_user_redirect'] == 'custom' && empty($fdata['pg_logged_user_redirect_custom'])) {
            $errors[ esc_html__('Logged In Users Redirect / Custom URL', 'pc_ml') ] = esc_html__('Insert a valid URL', 'pc_ml'); 	
        }
        if($fdata['pg_logout_user_redirect'] == 'custom' && empty($fdata['pg_logout_user_redirect_custom'])) {
            $errors[ esc_html__('Logged In Users Redirect / Custom URL', 'pc_ml') ] = esc_html__('Insert a valid URL', 'pc_ml'); 	
        }
        
        
        
		// Google Analytics - validate tracking code
		if(!empty($fdata['pg_analytics_id'])) {
			if(!preg_match('/^ua-\d{4,9}-\d{1,4}$/i', $fdata['pg_analytics_id'])) {
				$errors[ esc_html__('Google Analytics', 'pc_ml') ] = esc_html__('Tracking ID format is wrong', 'pc_ml'); 	
			}
		}
		
		
        
        // Manage user pvt pages management capabilities on settings save
        if(isset($fdata['pg_min_role'])) {     
            pc_wpuc_static::upp_manag_wp_roles_setup($fdata['pg_min_role']);
            
            // PC categories custom capability setup
            $include_pc_admin_role = (isset($fdata['pg_any_pc_admin_cmu']) && $fdata['pg_any_pc_admin_cmu']) ? true : false;
            pc_wpuc_static::pc_cats_manag_cap_setup($fdata['pg_min_role'], $include_pc_admin_role);
        }
        
        //PC Admin (WP role) capabilities setup
        if(isset($fdata['pg_pc_admin_role_add_caps'])) {
            pc_wpuc_static::man_pc_admin_role_caps( (array)$fdata['pg_pc_admin_role_add_caps'] );
        }
        
        
        
		// WP registration to PC - where enabled, require caregories
		if(!empty($fdata['wp_to_pc_sync_on_register']) && empty($fdata['wp_to_pc_sync_on_register_cats'])) {
			$errors[ esc_html__('WordPress to PrivateContent - Registration Sync', 'pc_ml') ] = esc_html__('Select at least one category', 'pc_ml'); 
		}
		
		
        
		// lightbox instances - validate and save /* NFPCF */
		if(isset($_POST['pc_lb_id'])) {
			$tot_lb = count($_POST['pc_lb_id']);
			$GLOBALS['pvtcont_lb_data'] = array(); // utility array to keep changes also with errors 
			
			// check fields consistency
			if(
				(!is_array($_POST['pc_lb_note']) || count($_POST['pc_lb_note']) != $tot_lb) ||
				(!is_array($_POST['pc_lb_contents']) || count($_POST['pc_lb_contents']) != $tot_lb)
			) {
				$errors[ esc_html__('Lightbox instances', 'pc_ml') ] = esc_html__('Missing fields', 'pc_ml'); 		
			}
			
			else {
				// every field must be filled
				$a = 0;
                $lb_ids = array_map('sanitize_text_field', (array)$_POST['pc_lb_id']);
                
				foreach($lb_ids as $lb_id) {
					$note 		= stripslashes(pc_static::sanitize_val($_POST['pc_lb_note'][$a]));
					$contents 	= stripslashes(pc_static::sanitize_val($_POST['pc_lb_contents'][$a]));
					
					$GLOBALS['pvtcont_lb_data'][$lb_id] = array(
						'note' 		=> $note,
						'contents'	=> $contents
					);
					
					if(empty($note) || empty($contents)) {
						$errors[ esc_html__('Lightbox instances', 'pc_ml') ] = esc_html__('Every field must be filled', 'pc_ml'); 	
					}
					else {
						wp_update_term($lb_id, 'pc_lightboxes', array(
						  'name' 		=> $note,
						  'description'	=> base64_encode($contents)
						));
						
						// sync with WPML
						if(function_exists('icl_register_string')) {
							icl_register_string('PrivateContent Lightboxes', 'Lightbox #'.$lb_id, $contents);
						}
						
						// sync with Polylang
						if(function_exists('pll_register_string')) {
							pll_register_string('PrivateContent Lightboxes', $contents, 'Lightbox #'.$lb_id, true);
						}
					}
					
					$a++;
				}
			}
		}
		
        
		
        // custom restrictions - check data existence in arrays
        if(!empty($fdata['pg_cr_url'])) {
            for($a=0; $a < count($fdata['pg_cr_url']); $a++) {
                if(empty($fdata['pg_cr_url'][$a]) || !isset($fdata['pg_cr_allow'][$a]) || empty($fdata['pg_cr_allow'][$a])) {
                    
                    $errors[ esc_html__('Custom Restrictions', 'pc_ml') ] = esc_html__('Each restriction must have an "allow" value', 'pc_ml'); 	
                    break;	
                }
            }
        }
        
        
        
        /////////////////////////////////////////////////////////
        
    
        
        // PC-FILTER - manipulate setting errors - passes errors array and form values - error subject as index + error text as val
        $errors = apply_filters('pc_setting_errors', $errors, $fdata);	
        
        
        // save or print error
        if(empty($errors)) {
            
            // apply custom WPS roles
            if(isset($fdata['pg_wp_user_sync'])) {
                $old_roles = $pc_wp_user->get_wps_custom_roles();
                $new_roles = array_unique( array_merge(array('pvtcontent'), (array)$fdata['pg_custom_wps_roles']));

                sort($old_roles); sort($new_roles);

                if($old_roles !== $new_roles) {
					$pc_wp_user->wps_roles = $new_roles;
                    $pc_wp_user->set_wps_custom_roles();
                    
                    $fdata['pg_custom_wps_roles'] = $new_roles;
                }	
            }
            
            // PC-FILTER - allow data manipulation (or custom actions) before settings save - passes form values
            $engine->form_data = apply_filters('pc_before_save_settings', $fdata); 
            
            // save
            $engine->save_data();
            
            
            
            // create custom style css file
            if(!get_option('pg_inline_css') && !isset($_POST['pg_inline_css'])) {
                if(!pc_static::create_custom_style()) {
                    echo '<div class="updated"><p>'. esc_html__('An error occurred during dynamic CSS creation. Code will be used inline anyway', 'pc_ml') .'</p></div>';
                }
                else {
                    delete_option('pg_inline_css');
                }
            }
			
            $engine->successful_save_redirect();
        }
        
        // compose and return errors
        else {
            echo wp_kses_post($engine->get_error_message_html($errors));	
        }
    }
	
	
	// if successfully saved
	echo lcwp_settings_engine::wp_kses_ext($engine->get_success_message_html());
	
	// print form code
    echo lcwp_settings_engine::wp_kses_ext($engine->get_code());
    ?>
</div>




<?php
// FIELDS ICON WIZARD
echo lcwp_settings_engine::wp_kses_ext(pc_static::fa_icon_picker_code( esc_html__('no icon', 'pc_ml'), true));


$inline_js = '
(function($) { 
    "use strict";
    '. pc_static::fa_icon_picker_js('pc_field_icon_trigger') .'
})(jQuery);';
wp_add_inline_script('lc-select', $inline_js);




// SCRIPTS
$inline_js = '
(function($) { 
    "use strict";';
    
    // be sure user categories exist
    if(empty(pc_static::user_cats())) {
        $inline_js .= '
        alert(`'. esc_html__('Please create at least one user category to use the plugin', 'pc_ml') .'`);
        window.location.href = `'. esc_js(admin_url('edit-tags.php?taxonomy=pg_user_categories')) .'`;
        return false;';
    }
   
    $inline_js .= '
    $(document).ready(function($) {
        // codemirror - execute before tabbing
        wp.codeEditor.initialize($(`.lcwp_sf_code_editor`), lc_settings_css_codemirror_config);
        
        // unwrap unwanted warnings in lcwp_settings_head
        setTimeout(function() {
            $(`.lcwp_settings_head > *`).not(`.lcwp_settings_page_title, .lcwp_settings_head_cmds`).each(function() {
                $(`.lcwp_settings_wrap`).prepend( $(this).detach() ); 
            });
        }, 200);
        
        

        var rf_is_acting = false; // registration form builder flag 
        var li_is_acting = false; // lightbox instance flag  
        var wps_is_acting = false; // WP user sync flag 
        var pc_nonce = `'. esc_js(wp_create_nonce('lcwp_ajax')) .'`;

        const lc_select_refresh_evt = new Event(`lc-select-refresh`, {bubbles:true});


        // lightbox instance - add 
        $(document).on(`click`, `#pc_add_lb_trig`, function() {
            li_is_acting = true;

            var $parent = $(this).parents(`h3`);
            $parent.append(`<span class="pc_spinner pc_spinner_inline"></span>`);

            var data = {
                action: `pvtcont_add_lightbox`,
                pc_nonce: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) == `error`) {
                    lc_wp_popup_message(`error`, `'. esc_html__('Error creating lightbox term', 'pc_ml') .'`);
                }
                else {
                    if(!$(`#pc_lb_inst li`).length) {
                        $(`#pc_lb_inst li em`).remove();	
                    }

                    $(`#pc_lb_inst`).append(response);	
                }
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                }
            })
            .always(function() {
                $parent.find(`.pc_spinner`).remove();
                li_is_acting = false;
            });
        });


        // lightbox instance - remove
        $(document).on(`click`, `#pc_lb_inst li .pc_del_field`, function() {
            if(li_is_acting || !confirm(`'. esc_html__("Once deleted, you will not be able to use this trigger class. Continue?", 'pc_ml') .'`)) {
                return false;
            }

            var $li = $(this).parents(`li`);
            var lb_id = $(this).attr(`rel`);

            li_is_acting = true;
            $li.fadeTo(200, 0.5);

            var data = {
                action	: `pvtcont_del_lightbox`,
                lb_id	: $(this).attr(`rel`),
                pc_nonce: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) != `success`) {
                    lc_wp_popup_message(`error`, response);	
                    $li.fadeTo(200, 1);
                } else {
                    $li.remove();
                }
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                }
            })
            .always(function() {
                li_is_acting = false;
            });
        });


        /////////////////////////////////////////////////////////////////////


        // custom restrictions - add 
        $(document).on(`click`, `#pc_add_cr_trig`, function() {
            if($(`#pc_cr_list em`).length) {$(`#pc_cr_list`).empty();}

            $(`#pc_cr_list`).append(` '. lcwp_settings_engine::wp_kses_ext(pvtcont_cr_template()) .' `);
            window.lcwp_sf_live_select();
        });


        // custom restrictions - remove
        $(document).on(`click`, `#pc_cr_list li .pc_del_field`, function() {
            if(confirm(`'. esc_html__("Remove this restriction?", 'pc_ml') .'`)) {
                $(this).parents(`li`).slideUp(function() {
                    $(this).remove();
                });
            }
        });


        // custom restrictions - avoid empty "allow/block" dropdown
        $("form.form-wrap").submit(function(e) {
            $(`#pc_cr_list .pg_cr_allow`).each(function() {
                if($(this).val() == null) {
                    $(this).replaceWith(`<input type="hidden" name="pg_cr_allow[][]" value="" />`);	
                }
            });

            $(`#pc_cr_list .pg_cr_block`).each(function() {
                if($(this).val() == null) {
                    $(this).replaceWith(`<input type="hidden" name="pg_cr_block[][]" value="" />`);	
                }
            });
        });


        // custom restrictions - all/unlogged toggles
        $(document).on(`change`, `#pc_cr_list li select`, function() {
            var pc_sel = $(this).val();
            if(!pc_sel) {
                pc_sel = [];
            }

            // if ALL is selected, discard the rest
            if($.inArray("all", pc_sel) >= 0) {
                $(this).find(`option`).prop(`selected`, false);
                $(this).find(`.pc_all_field`).prop(`selected`, true);

                $(this)[0].dispatchEvent(lc_select_refresh_evt);
            }

            // if UNLOGGED is selected, discard the rest
            else if($.inArray("unlogged", pc_sel) >= 0) {
                $(this).find(`option`).prop(`selected`, false);
                $(this).find(`.pc_unl_field`).prop(`selected`, true);

                $(this)[0].dispatchEvent(lc_select_refresh_evt);
                var unlogged_chosen = true;
            }	
        });


        // custom restrictions - set allow/block dropdown indexes to allow multidimensional saving
        $("form.form-wrap").submit(function() {
            $(`#pc_cr_list > li`).each(function(i, v) {
                $(this).find(`.pg_cr_allow`).attr(`name`, `pg_cr_allow[`+ i +`][]`);
                $(this).find(`.pg_cr_block`).attr(`name`, `pg_cr_block[`+ i +`][]`);
            });

            return true;
        });


        // custom restrictions - sortable
        $("#pc_cr_list").sortable({ 
            handle: `.pc_move_field`,
            axis: "y" 
        });
        $("#pc_cr_list .pc_move_field").disableSelection();


        /////////////////////////////////////////////////////////////////////


        // registration form builder - add form
        $(document).on(`click`, `#pc_reg_form_add`, function() {
            var name = $.trim( $(`#pc_new_reg_form_name`).val());
            if(!name || rf_is_acting) {return false;}

            rf_is_acting = true;
            $(`#pc_reg_form_loader`).html(`<span class="pc_spinner" style="margin-bottom: 0;"></span>`);

            var data = {
                action: `pvtcont_add_reg_form`,
                form_name: name,
                pc_nonce: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.isNumeric(response)) {
                    $(`.pc_form_builder_dd option`).removeAttr(`selected`);
                    $(`.pc_form_builder_dd`).append(`<option value="`+ response +`" selected="selected">`+ response +` - `+ name +`</option>`);

                    $(`#pc_new_reg_form_name`).val(``);
                    $(`.pc_form_builder_dd`)[0].dispatchEvent(lc_select_refresh_evt);
                    
                    setTimeout(() => {
                        $(`.pc_form_builder_dd`).trigger(`change`);
                    }, 100);
                }
                else {
                    lc_wp_popup_message(`error`, response);	
                }
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                }
            })
            .always(function() {
                $(`#pc_reg_form_loader`).empty();
                rf_is_acting = false;
            });
        });


        // registration form builder - load builder
        $(document).on(`change`, `.pc_form_builder_dd`, function() {
            var val = $(this).val();
            if(!val) {
                $(`#pc_reg_form_cmd`).css(`visibility`, `hidden`);
                $(`#pc_reg_form_builder`).empty();
                return false;
            }

            if(rf_is_acting) {
                return false;
            }
            rf_is_acting = true;
            $(`#pc_reg_form_loader`).html(`<span class="pc_spinner" style="margin-bottom: 0;"></span>`);

            var data = {
                action: `pvtcont_reg_form_builder`,
                form_id: val,
                pc_nonce: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                $(`#pc_reg_form_cmd`).css(`visibility`, `visible`);
                $(`#pc_reg_form_builder`).html(response);
                
                window.lcwp_sf_live_check();
                window.lcwp_sf_live_select();

                /*** sort formbuilder rows ***/
                $( "#pc_reg_form_builder tbody" ).sortable({ 
                    handle: `.pc_move_field`,
                    axis: "y" 
                });
                $( "#pc_reg_form_builder tbody td .pc_move_field" ).disableSelection(); 
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                }
            })
            .always(function() {
                $(`#pc_reg_form_loader`).empty();
                rf_is_acting = false;
            });
        });
        // on start - load first form
        if($(`.pc_form_builder_dd option`).length) {
            $(`.pc_form_builder_dd`).trigger(`change`);	
        }


        // add field to builder
        $(document).on(`click`, `#pc_rf_add_field`, function() { 
            var f_val = $(`.pc_form_fields_dd`).val();
            var f_name = $(`.pc_form_fields_dd option[value="`+ f_val +`"]`).text();

            if(f_val != `custom|||text` && f_val != `custom|||page` && f_val != `custom|||sep` && $(`#pc_rf_builder_table tr[rel="`+ f_val +`"]`).length) {
                lc_wp_popup_message(`error`, `'. esc_html__('Field already in the form', 'pc_ml') .'`);
                return false;	
            }

            var required = (f_val == `categories`) ? `checked="checked"` : ``;
            var disabled = (f_val == `categories`) ? `disabled="disabled"` : ``; 

            if(f_val == `custom|||text`) {
                const d = new Date();
                let ct_uniq_id = f_val + `|||`+ d.getTime();            

                var code = 
                `<td colspan="2">`+
                    `<input type="hidden" name="pc_reg_form_field[]" value="`+ ct_uniq_id +`" class="pc_reg_form_builder_included" />`+
                    `<textarea name="pc_reg_form_texts[]" placeholder="'. esc_attr__('Supports HTML and shortcodes', 'pc_ml') .'"></textarea>`+
                `</td>`;
            }
            else if(f_val == `custom|||sep`) {
                const d = new Date();
                let ct_uniq_id = f_val + `|||`+ d.getTime();            

                var code = 
                `<td colspan="2" class="pc_separator_bar_td">`+
                    `<input type="hidden" name="pc_reg_form_field[]" value="`+ ct_uniq_id +`" class="pc_reg_form_builder_included" />`+
                    `<strong>'. esc_html__('SEPARATOR BAR', 'pc_ml') .'</strong>`+
                `</td>`;
            }
            else if(f_val == `custom|||page`) {
                var code = 
                `<td colspan="2" class="pc_paginator_td">`+
                    `<input type="hidden" name="pc_reg_form_field[]" value="`+ f_val +`" class="pc_reg_form_builder_included" />`+
                    `<strong>'. esc_html__('PAGINATOR', 'pc_ml') .'</strong>`+
                `</td>`;
            }
            else {
                var code = 
                `<td>`+
                    `<input type="hidden" name="pc_reg_form_field[]" value="`+ f_val +`" class="pc_reg_form_builder_included" />`+
                    f_name +
                `</td>`+
                `<td>`+
                    `<input type="checkbox" name="pc_reg_form_req[]" value="`+ f_val +`" `+required+` `+disabled+` class="lcwp_sf_check pc_reg_form_builder_required" autocomplete="off" />`+
                `</td>`;	
            }

            $(`#pc_rf_builder_table tbody`).append(
            `<tr rel="`+ f_val +`">`+
                `<td><span class="pc_del_field dashicons dashicons-no-alt" title="'. esc_attr__('remove field', 'pc_ml') .'"></span></td>`+
                `<td><span class="pc_move_field dashicons dashicons-move" title="'. esc_attr__('sort field', 'pc_ml') .'"></span></td>`+
                code +
            `</tr>`);

            window.lcwp_sf_live_check();
        });


        // delete form field
        $(document).on(`click`, `#pc_rf_builder_table .pc_del_field`, function() { 
            if(!rf_is_acting) {
                $(this).parents(`tr`).remove();
            }
        });


        // update form structure 
        $(document).on(`click`, `.pc_reg_form_save`, function() {
            if(rf_is_acting) {
                return false;
            }

            rf_is_acting = true;
            $(`#pc_reg_form_loader`).html(`<span class="pc_spinner" style="margin-bottom: 0;"></span>`);

            var form_id = $(`.pc_form_builder_dd`).val();
            var form_name = $(`#pc_rf_name`).val();

            // create fields + required array
            var included = [];
            var required = [];
            var texts 	= [];

            $(`#pc_rf_builder_table tbody tr`).each(function(i,v) {
                var f = $(this).find(`.pc_reg_form_builder_included`).val();
                included.push(f);

                if(f.substr(0, 13) == `custom|||text`) {
                    texts.push( $(this).find(`textarea`).val() );	
                }
                else {
                    if( $(this).find(`.pc_reg_form_builder_required`).is(`:checked`) ) {
                        required.push(f);	
                    }
                }
            });

            var data = {
                action: `pvtcont_update_reg_form`,
                form_id: form_id,
                form_name: form_name, 
                fields_included: included,
                fields_required: required,
                texts: texts,
                pc_nonce: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) == `success`) {
                    $(`.pc_form_builder_dd option[value=`+ form_id +`]`).html( form_id+` - `+form_name );
                    $(`.pc_form_builder_dd`)[0].dispatchEvent(lc_select_refresh_evt);

                    lc_wp_popup_message(`success`, `'. esc_html__('Form successfully saved!', 'pc_ml') .'`);
                }
                else {
                    lc_wp_popup_message(`error`, response);
                }
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                }
            })
            .always(function() {
                $(`#pc_reg_form_loader`).empty();
                rf_is_acting = false;
            });
        });


        // delete form - leaving one
        $(document).on(`click`, `#pc_reg_form_del`, function() {
            if($(`.pc_form_builder_dd option`).length == 1) {
                lc_wp_popup_message(`error`, `'. esc_html__('At least one form is required', 'pc_ml') .'`);
                return false;	
            }

            var form_id = $(`.pc_form_builder_dd`).val();
            if(!form_id) {return false;}

            if(confirm(`'. esc_html__('Delete this form? Related shortcodes will show the first one', 'pc_ml') .'`)) {
                rf_is_acting = true;
                $(`#pc_reg_form_loader`).html(`<span class="pc_spinner" style="margin-bottom: 0;"></span>`);

                var data = {
                    action: `pvtcont_del_reg_form`,
                    form_id: form_id,
                    pc_nonce: pc_nonce
                };
                $.post(ajaxurl, data, function(response) {
                    if($.trim(response) == `success`) {
                        $(`.pc_form_builder_dd option[value=`+ form_id +`]`).remove();
                        $(`.pc_form_builder_dd option`).first().attr(`selected`, `selected`);
                        
                        $(`.pc_form_builder_dd`)[0].dispatchEvent(lc_select_refresh_evt);	
                        setTimeout(() => {
                            $(`.pc_form_builder_dd`).trigger(`change`);
                        }, 100);
                        
                        $(`#pc_reg_form_del`).css(`background-color`, `#BB7071`).css(`color`, `#fff`);
                        setTimeout(function(){
                            $(`#pc_reg_form_del`).css(`background-color`, ``).css(`color`, ``);
                        }, 500);
                    }
                    else {
                        lc_wp_popup_message(`error`, response);
                    }
                })
                .fail(function(e) {
                    if(e.status) {
                        console.error(e);
                        lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                    }
                })
                .always(function() {
                    $(`#pc_reg_form_loader`).empty();
                    rf_is_acting = false;
                });
            }
        });



        ///////////////////////////////////////////////////



        // sync WP users sync
        $(document).on(`click`, `#pc_do_wp_sync`, function() {
            if(!wps_is_acting && confirm(`'. esc_html__('Mirror WordPress users will be created. Continue?', 'pc_ml') .'`)) {

                wps_is_acting = true;
                var $result_wrap = $(this).next(`span`);
                $result_wrap.html(`<span class="pc_spinner pc_spinner_inline"></span>`);

                var data = {
                    action: `pvtcont_wp_global_sync`,
                    pc_nonce: pc_nonce
                };
                $.post(ajaxurl, data, function(response) {
                    $result_wrap.html(response);
                })
                .fail(function(e) {
                    if(e.status) {
                        console.error(e);
                        lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                    }
                })
                .always(function() {
                    wps_is_acting = false;
                });
            }
        });

        // clean WP users sync
        $(document).on(`click`, `#pc_clean_wp_sync`, function() {
            if(!wps_is_acting && confirm(`'. esc_html__('WARNING: this will delete connected WordPress users and any related content will be lost. Continue?', 'pc_ml') .'`)) {

                wps_is_acting = true;
                var $result_wrap = $(this).next(`span`);
                $result_wrap.html(`<span class="pc_spinner"></span>`);

                var data = {
                    action: `pvtcont_wp_global_detach`,
                    pc_nonce: pc_nonce
                };
                $.post(ajaxurl, data, function(response) {
                    $result_wrap.html(response);
                })
                .fail(function(e) {
                    if(e.status) {
                        console.error(e);
                        lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                    }
                })
                .always(function() {
                    wps_is_acting = false;
                });
            }
        });

        // search existing matches and sync
        $(document).on(`click`, `#pc_wps_matches_sync`, function() {
            if(!wps_is_acting && confirm(`'. esc_html__('WARNING: this will turn matched WP userse into PrivateContent mirrors. Continue?', 'pc_ml') .'`)) {

                wps_is_acting = true;
                var $result_wrap = $(this).next(`span`);
                $result_wrap.html(`<span class="pc_spinner"></span>`);

                var data = {
                    action: `pvtcont_wps_search_and_sync_matches`,
                    pc_nonce: pc_nonce
                };
                $.post(ajaxurl, data, function(response) {
                    $result_wrap.html(response);
                })
                .fail(function(e) {
                    if(e.status) {
                        console.error(e);
                        lc_wp_popup_message(`error`, `'. esc_html__('Error performing the operation', 'pc_ml') .'`);
                    }
                })
                .always(function() {
                    wps_is_acting = false;
                });
            }
        });



        //////////////////////////////////////



        // messages styles preview
        const show_mess_preview_img = function() {
            const baseurl = `'. esc_js(PC_URL) .'/img/mess_styles/|||.jpg`,
            val = document.querySelector(`select[name="pg_messages_style"]`).value;

            document.getElementById(`pg_messages_style_preview`).setAttribute(`src`, baseurl.replace(`|||`, val));

            return true;
        };
        show_mess_preview_img();

        $(document).on(`change`, `select[name="pg_messages_style"]`, show_mess_preview_img);
    });';
    
    ob_start();
    require_once(PC_DIR .'/settings/mandatory_js.php');
    $inline_js .= ob_get_clean() .'
    
})(jQuery);';
wp_add_inline_script('lc-select', $inline_js);


    
// PC-ACTION - allow extra code printing in settings (for javascript/css)
do_action('pc_settings_extra_code');