<?php
if(!defined('ABSPATH')) {exit;}
dike_lc('lcweb', PC_DIKE_SLUG, true); /* NFPCF */


global $pc_users;
include_once(PC_DIR .'/classes/simple_form_validator.php');
include_once(PC_DIR .'/classes/users_import_export.php');

$base_hub_url = admin_url('admin.php?page=pc_import_export');
$page_title = '<h1 class="pc_page_title">PrivateContent '. esc_html__('Import / Export Hub', 'pc_ml') .'</h1>';
$back_to_main_icon = '<a href="'. $base_hub_url .'" class="page-title-action" title="'. esc_attr__('back to main import/export page', 'pc_ml') .'"><i class="dashicons dashicons-undo"></i></a>';


echo '
<div class="wrap pc_form pc_imp_exp_wrap">';

    if(isset($_GET['pvtc_export'])) {
        echo wp_kses_post(str_replace('</h1>', ' - '. esc_html__('Export for PrivateContent', 'pc_ml') . $back_to_main_icon .'</h1>', $page_title));
        include_once('pvtc_export.php');
    }
    elseif(isset($_GET['csv_export'])) {
        echo wp_kses_post(str_replace('</h1>', ' - '. esc_html__('CSV / EXCEL Export', 'pc_ml') . $back_to_main_icon .'</h1>', $page_title));
        include_once('csv_export.php');
    }
    elseif(isset($_GET['pvtc_import'])) {
        echo wp_kses_post(str_replace('</h1>', ' - '. esc_html__('Import from PrivateContent', 'pc_ml') . $back_to_main_icon .'</h1>', $page_title));
        include_once('pvtc_import.php');
    }
    elseif(isset($_GET['csv_import'])) {
        echo wp_kses_post(str_replace('</h1>', ' - '. esc_html__('Import from CSV', 'pc_ml') . $back_to_main_icon .'</h1>', $page_title));
        include_once('csv_import.php');
    }
    elseif(isset($_GET['wp_import']) && $pc_users->wp_user_sync) {
        echo wp_kses_post(str_replace('</h1>', ' - '. esc_html__('Import from WordPress', 'pc_ml') . $back_to_main_icon .'</h1>', $page_title));
        include_once('wp_import.php');
    }
    elseif(isset($_GET['engine_export']) && current_user_can('manage_options')) {
        echo wp_kses_post(str_replace('</h1>', ' - '. esc_html__('Export suite setup', 'pc_ml') . $back_to_main_icon .'</h1>', $page_title));
        include_once('engine_export.php');
    }
    elseif(isset($_GET['engine_import']) && current_user_can('manage_options')) {
        echo wp_kses_post(str_replace('</h1>', ' - '. esc_html__('Import suite setup', 'pc_ml') . $back_to_main_icon .'</h1>', $page_title));
        include_once('engine_import.php');
    }
    
    else {    
        echo wp_kses_post($page_title) .'
        
        <div class="pc_imp_exp_notices"></div>
        
        <div class="pc_imp_exp_box">
            <h4>'. esc_html__('What to do?', 'pc_ml') .'</h4>
            
            <div class="pc_imp_exp_hub_optslist">
                <a href="'. esc_attr(add_query_arg('pvtc_export', '', $base_hub_url)) .'">
                    <img src="'. esc_attr(PC_URL) .'/img/pc_exp_icon.svg" alt="pvtcontent export" />
                    <span>
                        <strong>'. esc_html__('Export for PrivateContent', 'pc_ml') .'</strong>
                        <em>'. esc_html__('Moving users from a PrivateContent installation to another', 'pc_ml') .'</em>
                    </span>
                </a>
                
                <a href="'. esc_attr(add_query_arg('csv_export', '', $base_hub_url)) .'">
                    <img src="'. esc_attr(PC_URL) .'/img/csv_exp_icon.svg" alt="csv export" />
                    <span>
                        <strong>'. esc_html__('CSV / EXCEL Export', 'pc_ml') .'</strong>
                        <em>'. esc_html__('Getting users data to be used in third-party systems', 'pc_ml') .'</em>
                    </span>
                </a>
                
                <a href="'. esc_attr(add_query_arg('pvtc_import', '', $base_hub_url)) .'">
                    <img src="'. esc_attr(PC_URL) .'/img/pc_imp_icon.svg" alt="pvtcontent import" />
                    <span>
                        <strong>'. esc_html__('Import from PrivateContent', 'pc_ml') .'</strong>
                        <em>'. esc_html__('Importing users from another PrivateContent installation', 'pc_ml') .'</em>
                    </span>
                </a>
                
                <a href="'. esc_attr(add_query_arg('csv_import', '', $base_hub_url)) .'">
                    <img src="'. esc_attr(PC_URL) .'/img/csv_imp_icon.svg" alt="csv import" />
                    <span>
                        <strong>'. esc_html__('Import from CSV file', 'pc_ml') .'</strong>
                        <em>'. esc_html__('Importing users from a CSV file generated on third-party systems', 'pc_ml') .'</em>
                    </span>
                </a>';
                
                if($pc_users->wp_user_sync) {
                    echo '
                    <a href="'. esc_url(add_query_arg('wp_import', '', $base_hub_url)) .'">
                        <img src="'. esc_attr(PC_URL) .'/img/wp_imp_icon.svg" alt="WordPress import" />
                        <span>
                            <strong>'. esc_html__('Import from WordPress', 'pc_ml') .'</strong>
                            <em>'. esc_html__('Turning WordPress users into PrivateContent ones', 'pc_ml') .'</em>
                        </span>
                    </a>';
                }
        
            echo '
            </div>';
            
            if(current_user_can('manage_options')) {
                echo '
                <hr/>

                <div class="pc_imp_exp_hub_optslist">
                    <a href="'. esc_attr(add_query_arg('engine_export', '', $base_hub_url)) .'">
                        <img src="'. esc_attr(PC_URL) .'/img/engine_exp_icon.svg" alt="engine export" />
                        <span>
                            <strong>'. esc_html__('Export PrivateContent suite setup', 'pc_ml') .'</strong>
                            <em>'. esc_html__('Selectively export PrivateContent suite settings, forms, fields, etc.', 'pc_ml') .'</em>
                        </span>
                    </a>

                    <a href="'. esc_attr(add_query_arg('engine_import', '', $base_hub_url)) .'">
                        <img src="'. esc_attr(PC_URL) .'/img/engine_imp_icon.svg" alt="engine import" />
                        <span>
                            <strong>'. esc_html__('Import PrivateContent suite setup', 'pc_ml') .'</strong>
                            <em>'. esc_html__('Selectively import PrivateContent suite settings, forms, fields, etc.', 'pc_ml') .'</em>
                        </span>
                    </a>    
                </div>';
            }
        
        echo '
        </div>';
    }
    
echo '
</div>';


$inline_js = '
(function($) { 
    "use strict"; 
    
    // lc switch
    window.pc_ie_lc_switch = function() {
        lc_switch(`.pc_imp_exp_wrap .pc_lc_switch`, {
            on_txt      : "'. esc_js(strtoupper(esc_attr__('yes', 'pc_ml'))) .'",
            off_txt     : "'. esc_js(strtoupper(esc_attr__('no', 'pc_ml'))) .'",   
        });
    };

    // lc_select
    window.pc_ie_lc_select = function() {
        new lc_select(`.pc_imp_exp_wrap .pc_lc_select`, {
            wrap_width : `100%`,
            addit_classes : [`lcslt-lcwp`],
        });
    };
    
    
    $(document).ready(function() {
        pc_ie_lc_select();
        pc_ie_lc_switch();
    });
})(jQuery);';
wp_add_inline_script('lcwp_magpop', $inline_js);