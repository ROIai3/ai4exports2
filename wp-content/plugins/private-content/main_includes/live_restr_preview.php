<?php
// TOPBAR MENU ELEMENT TO MANAGE THE LIVE RESTRICTIONS PREVIEW FOR WP USERS
if(!defined('ABSPATH')) {exit;}


add_action('wp_enqueue_scripts', function() {
    if(is_admin() || !PVTCONT_WP_USER_PASS || isset($GLOBALS['pc_user_id'])) {
        return false;
    }
    
    wp_enqueue_style('pc-front-wp-topbar', PC_URL .'/css/front_wp_topbar.css', 250, PC_VERS);
    wp_enqueue_style('lcwp-lc-select', PC_URL .'/js/lc-select/themes/lcwp_prefixed.css', 230, PC_VERS);
    
    add_action('admin_bar_menu', 'pvtcont_live_restr_topbar_switch_registr', 501);
    pvtcont_live_restr_topbar_switch_footer_code();
}, 9000);



function pvtcont_live_restr_topbar_switch_registr() {
	global $wp_admin_bar;
    
    if(!is_admin_bar_showing() || !is_object($wp_admin_bar)) {
        return false;   
    }
    
    $user_has_pc_restr_preview = get_user_meta(get_current_user_id(), 'pc_restr_preview_config', true);
    
    if(!empty($user_has_pc_restr_preview)) {
        $txt = esc_html__('preview ON', 'pc_ml');
        $class = 'pc_restr_preview_on';
    }
    else {
        $txt = esc_html__('preview OFF', 'pc_ml');
        $class = 'pc_restr_preview_off';
    }
    
    $wp_admin_bar->add_menu(array( 
        'id'    => 'pc_restr_preview', 
        'title' => '<span alt="'. esc_attr__('PrivateContent restrictions preview', 'pc_ml') .'"><span id="ab-updates">'. $txt .'</span></span>', 
        'href'  => '#',
        'meta'  => array('class' => $class)
    ));
    
    
    $unlogged_sel = (!empty($user_has_pc_restr_preview) && in_array('unlogged', (array)$user_has_pc_restr_preview)) ? 'selected="selected"' : '';
    $opts = pc_static::user_cat_dd_opts($user_has_pc_restr_preview, false);
    
    if(strpos($opts, '<optgroup') === false) {
        $opts = '<option value="unlogged" '. $unlogged_sel .'>'. esc_html__('Unlogged user', 'pc_ml') .'</option>' . $opts;  
    } else {
        $opts = '
        <optgroup label="'. esc_attr__('Extra', 'pc_ml') .'">
            <option value="unlogged" '. $unlogged_sel .'>'. esc_html__('Unlogged user', 'pc_ml') .'</option>
        </optgroup>' . $opts;
    }
        
    $form = '
    <form id="pc_restr_preview_form">
        <label>'. esc_html__('View website pages like a user owning these categories', 'pc_ml') .'</label><i></i>
        <fieldset>
            <select name="pc_restr_preview" autocomplete="off" data-placeholder="'. esc_attr__('leave empty to bypass any restriction', 'pc_ml') .' .." multiple> 
                '. $opts .'
            </select>
        </fieldset>
        <input type="submit" value="&rsaquo;" />
    </form>';
    
    $wp_admin_bar->add_menu(array(
        'id'     => 'pc_restr_preview_form',
        'parent' => 'pc_restr_preview',
        'title'  => $form,
        'href'   => '',
    ));
}




function pvtcont_live_restr_topbar_switch_footer_code() {
    $inline_js = '
    (function($) {
        "use strict";
        
        // show form NOT on hover
        $(document).on(`click`, `#wp-admin-bar-pc_restr_preview`, function(e) {
            if($(e.target).is(`#wp-admin-bar-pc_restr_preview .ab-sub-wrapper`) || $(e.target).parents(`#wp-admin-bar-pc_restr_preview .ab-sub-wrapper`).length) {
                return false;   
            }
            
            $(this).toggleClass(`pcrp_shown`);
        });
        
        
        
        // hide visible dropdown if page is scrolled
        $(window).on("scroll", function() {
            const $subj = $(`#lc-select-dd[data-fname="pc_restr_preview"]`);
            
            if($subj.length) {
                $subj.removeClass(`lcslt-shown`);
                $(`#pc_restr_preview_form .lcslt_dd-open`).removeClass(`lcslt_dd-open`);
            }
        });
        
        
        // setup dropdown
        $(document).ready(function() {
            if(!$(`select[name="pc_restr_preview"]`).length) {
                return false;     
            }
            if(typeof(lc_select) == `undefined`) {
                console.error(`pvtContent: LC select script not found`);
                return false;  
            }

            new lc_select(`select[name="pc_restr_preview"]`, {
                wrap_width      : `100%`,
                addit_classes   : [`lcslt-lcwp`],
                pre_placeh_opt  : true, 
                labels : [ 
                    pc_vars.lcslt_search,
                    pc_vars.lcslt_add_opt,
                    pc_vars.lcslt_select_opts +` ..`,
                    `.. `+ pc_vars.lcslt_no_match +` ..`,
                ],
            });
        });
        
        
        
        // unlogged option must be alone
        $(document).on("change", `select[name="pc_restr_preview"]`, function() {
            let val = ($(this).val() !== null && typeof($(this).val()) == "object") ? $(this).val() : [],
                unlogged_chosen = false;

            // if UNLOGGED is selected, discard the rest
            if($.inArray("unlogged", val) !== -1) {
                $(this).find("option").prop("selected", false);
                $(this).find(`option[value="unlogged"]`).prop("selected", true);

                const resyncEvent = new Event("lc-select-refresh");
                this.dispatchEvent(resyncEvent);
            }
        });
        
        
        
        // sumit form on bnt click
        $(document).on(`click`, `#pc_restr_preview_form input[type="submit"]`, function(e) {
            $(`#pc_restr_preview_form`).submit();
        });
        
        
        // save preferences
        let form_is_acting = false;
        $(document).on(`submit`, `#pc_restr_preview_form`, async function(e) {
            e.preventDefault();
            
            const $form     = $(this),
                  redirect  = $form.data(`pc_redirect`),
                  val       = $form.find(`select[name="pc_restr_preview"]`).val();	

            if(form_is_acting) {
                return false;    
            }
            form_is_acting = true;	

            $form.prop(`disabled`, `disabled`);
            $(`.lcslt-f-pc_restr_preview`).addClass(`lcslt-disabled`);
            
            let f_data = new FormData();
            f_data.append(`action`, `pvtcont_set_live_restr_preview`);
            f_data.append(`to_emulate`, JSON.stringify(val));
            f_data.append(`pc_nonce`, `'. esc_js(wp_create_nonce('lcwp_ajax')) .'`);

            await fetch(
                pc_vars.ajax_url,
                {
                    method      : `POST`,
                    credentials : `same-origin`,
                    keepalive   : false,
                    body        : f_data,
                }
            )
            .then(async response => {
                if(!response.ok) {return Promise.reject(response);}
                const resp = (await response.text());

                // success
                if(resp == `success`) {
                    window.location.replace(window.pc_set_pcac_param(false, true));
                }
                else {
                    alert(resp);
                }
            })
            .catch(e => {
                if(e.status) {
                    console.error(e);
                    alert(pc_vars.ajax_failed_mess);
                }
                return false;
            })
            .finally(() => {
                $form.removeAttr(`disabled`);
                $(`.lcslt-f-pc_restr_preview`).removeClass(`lcslt-disabled`);
                form_is_acting = false;
            });
            
            return false;
        });';
        
        
        // PC-FILTER - eventually output codes for WP live restriction preview JS 
        $inline_js = apply_filters('pc_live_restr_topbar_js', $inline_js);
    
    $inline_js .= '
    })(jQuery);';
    wp_add_inline_script('pc_frontend', $inline_js);
}