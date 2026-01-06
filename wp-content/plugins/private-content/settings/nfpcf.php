<?php 
if(!defined('ABSPATH')) {exit;}


if(ISPCF && isset($GLOBALS['pc_settings_structure'])) {
    global $pc_settings_structure;
    
    $nfpcf = array(
        'pg_logged_user_redirect_custom',
        'pg_blocked_users_redirect_custom',
        'pg_logged_user_redirect_custom',
        'pg_logout_user_redirect_custom',
        'pg_redirect_back_after_login',
        'pg_pvtpage_overriding_method',
        'pg_pvtpage_om_placeh_legend', // TH
        'pg_pvtpage_default_content',
        'pg_pvtpage_enable_preset',
        'pg_pvtpage_preset_pos', // TH
        'pg_pvtpage_preset_txt',
        'pg_pvtpage_wps_comments',
        'pc_chs_behavior',
        'pc_chs_lists_behavior',
        'pc_chs_cust_content', // TH
        'pg_warn_box_login',
        'pg_warn_box_registr',
        'pc_global_block_woo_sell',
        'pc_wph_message',
        'pg_def_lb_on_open',
        'pg_extend_cpt',
        'pg_extend_ct',
        'pg_ga4_id',
        'pg_ga4_api_secret', // TH
        'pg_gtm_id', // TH
        'pg_ga4_inject_js_code', // TH
        'pg_ga4_setup_note', // TH
        'pg_use_session_token',
        'pg_allowed_simult_sess',
        
        'pg_custom_wps_roles',
        'pg_lock_comments',
        'pg_hc_warning',
        
        'pg_default_nl_mex', // TH
        'pg_default_uca_mex', // TH
        'pg_default_hc_mex', // TH
        'pg_default_hcwp_mex', // TH
        'pg_login_ok_mex',
        'pg_default_pu_mex',
        'pg_default_du_mex',
        'pg_default_sr_mex',
        
        'pc_lb_note',
        'pc_lb_contents',
        
        'pg_lb_padding',
        'pg_lb_border_radius',
        'pg_lb_border_w',
        'pg_lb_max_w',
        'pg_lb_border_col',
        'pg_lb_overlay_col',
        'pg_lb_overlay_alpha',
        'pg_lb_bg',
        'pg_txt_col',
        
        'pg_cr_url',
        'pg_cr_allow',
        'pg_cr_block',
    );   
    
    $th = array(
        'pg_pvtpage_om_placeh_legend',
        'pg_pvtpage_overriding_method_helper',
        'pg_pvtpage_preset_pos',
        'pc_chs_cust_content',
        'pg_ga4_api_secret',
        'pg_gtm_id',
        'pg_ga4_inject_js_code',
        'pg_ga4_setup_note',
        'pg_default_nl_mex',
        'pg_default_uca_mex',
        'pg_default_hc_mex',
        'pg_default_hcwp_mex',
    ); 
    
    
    foreach($pc_settings_structure as $tab_id => $tab_sections) {
        foreach($tab_sections as $sect_id => $sect) {
        
            if(!isset($sect['fields'])) {
                continue;
            }
            foreach(array_keys($sect['fields']) as $fid) {
                if(in_array($fid, $th)) {
                    unset($pc_settings_structure[$tab_id][$sect_id]['fields'][$fid]);   
                }
            }
        }
    }
    
    
    if(isset($_POST['pc_settings'])) {
        foreach($nfpcf as $fid) {
            if(isset($_POST[$fid])) {
                unset($_POST[$fid]);
            }
        }
        
        $_POST['pg_antispam_sys'] = 'honeypot';
    }
    
    
    add_action('pc_settings_extra_code', function() use ($nfpcf, $th) {
        $selectors = array();
        foreach($nfpcf as $fid) {
            if(!in_array($fid, $th)) {
                $selectors[] = 'tr.pc_'. $fid;   
            }
        }
        
        
        $inline_js = '
        (function() { 
            "use strict";
            
            document.querySelectorAll(`'. esc_js(implode(', ', $selectors)) .'`).forEach(elem => {
                elem.classList.add(`pc_nfpcf`, `pc_nfpcf_w_btn`);
            });
            
            document.querySelectorAll(`#pc_new_reg_form_name, #pc_reg_form_add`).forEach(elem => {
                elem.classList.add(`pc_nfpcf`);
            });
            
            document.querySelectorAll(`select[name="pg_antispam_sys"] option:not([value="honeypot"]), #pc_new_reg_form_name, #pc_reg_form_add`).forEach(elem => {
                elem.setAttribute(`disabled`, `disabled`);
                
                if(elem.tagName.toLowerCase() === `option`) {
                    elem.innerText += ` (Premium only)`;     
                }
            });
            
            document.querySelectorAll(`select[name="pg_redirect_page"], select[name="pg_blocked_users_redirect"], select[name="pg_logged_user_redirect"]`).forEach(elem => {
                if(elem.querySelector(`option[value="custom"]`)) {
                    elem.querySelector(`option[value="custom"]`).setAttribute(`disabled`, `disabled`);
                }
            });
            
            
            document.querySelectorAll(`#pc_add_cr_trig, #pc_add_lb_trig, #pc_reg_form_del`).forEach(elem => {
                elem.remove();
            });
            window.nfpcf_inject_infobox(`#pc_lb_inst, #pc_cr_list`);
        })();';
        wp_add_inline_script('lcwp_magpop', $inline_js);
    });    
}