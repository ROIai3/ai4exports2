<?php
if(!defined('ABSPATH')) {exit;}


$user_statuses = array(
    'all'   => esc_html__('Any status', 'pc_ml'),
    '1'     => esc_html__('Active users', 'pc_ml'),
    '2'     => esc_html__('Disabled users', 'pc_ml'),
    '3'     => esc_html__('Pending users', 'pc_ml'),
);



$user_cats = array();
if(PVTCONT_CURR_USER_MANAGEABLE_CATS == 'any') {
    $user_cats["all"] = esc_html__('All categories', 'pc_ml');
}
$ucats = get_terms(array(
    'taxonomy'   => 'pg_user_categories',
    'orderby'    => 'name',
    'hide_empty' => 0,
));

foreach($ucats as $ucat) {
    if(!get_option('pg_tu_can_edit_user_cats') && PVTCONT_CURR_USER_MANAGEABLE_CATS != 'any' && !in_array($ucat->term_id, (array)PVTCONT_CURR_USER_MANAGEABLE_CATS)) {
        continue;    
    }
    $user_cats[ (int)$ucat->term_id ] = esc_html($ucat->name);
}



// PC-FILTER - if add-ons want to specify that some data cannot be exported. Pass subject name
$cannot_be_exported = apply_filters('pc_cannot_pvtc_export', array());
$cbe_notice = '';

if(!empty($cannot_be_exported)) {
    $cbe_notice = '<div class="pc_warn pc_info"><p>'. esc_html__('Please note these data linked to privateContent users cannot be exported', 'pc_ml') .': '. implode(', ', $cannot_be_exported) .'</p></div>';
}



// validation indexes
$indexes = array();
$indexes[] = array('index'=>'pc_nonce', 'label'=>'nonce', 'required'=>true);
$indexes[] = array('index'=>'pc_pvtc_exp_include_pvtpag', 'label'=>'export also reserved page contents');

if(isset($_POST['pc_targeted_export_params'])) {
    $indexes[] = array('index'=>'pc_targeted_export_params', 'label'=>esc_html__('Advanced users search - export parameters', 'pc_ml'), 'required'=>true);
}
else {
    $indexes[] = array('index'=>'pc_pvtc_exp_user_statuses', 'label'=>esc_html__('Users type', 'pc_ml'), 'allowed'=>array_keys($user_statuses), 'required'=>true);
    $indexes[] = array('index'=>'pc_pvtc_exp_user_cats', 'label'=>esc_html__('User categories', 'pc_ml'), 'allowed'=>array_keys($user_cats), 'required'=>true);
}

// PC-FILTER - allow CSV import form validation array management. Array elements must comply with Simple form validator class
$indexes = apply_filters('pc_pvtc_export_form_validation', $indexes);


if(isset($_POST['pc_pvtc_export'])) {
    $validator = new simple_fv('pc_ml');
    $validator->formHandle($indexes);
    
    $fdata = $validator->form_val;
    
    if(!isset($fdata['pc_targeted_export_params'])) {
        if(!is_array($fdata['pc_pvtc_exp_user_statuses']) || in_array('all', $fdata['pc_pvtc_exp_user_statuses'])) {
            $fdata['pc_pvtc_exp_user_statuses'] = array('all');   
        }    
        if(!is_array($fdata['pc_pvtc_exp_user_cats']) || in_array('all', $fdata['pc_pvtc_exp_user_cats'])) {
            $fdata['pc_pvtc_exp_user_cats'] = array('all');   
        }
    }
    
    $error = $validator->getErrors();
    if(empty($error) && (!pc_static::verify_nonce($_POST['pc_nonce'], 'lcwp_nonce') || !pc_wpuc_static::current_wp_user_can_edit_pc_user('some'))) {
        $error = 'Cheating?';
    }
    if(!empty($error)) {
        echo '<div class="notice notice-error"><p>'. wp_kses_post($error) .'</p></div>';
    }
    
    
    // perform export
    else {
        $uie = new pvtcont_users_import_export;
        $export_data = $uie->pvtc_export($fdata);
        
        if($export_data['status'] == 'error') {
            echo '<div class="notice notice-error"><p>'. wp_kses_post($export_data['message']) .'</p></div>';
        }
        else {
            $filename = 'pvtc-to-pvtc_from_'. strtolower(sanitize_title(get_site_url())) .' -'. wp_date('Y-m-d-H:i:s') .'.json';
            
            $inline_js = '
            (function() { 
                "use strict";
                
                const blob = new Blob([`'. pc_static::wp_kses_ext(str_replace('`', '\`', $export_data['filedata'])) .'`], {type: `application/json; charset=utf-8`}),
                      downloadUrl = URL.createObjectURL(blob),
                      a = document.createElement("a");
                
                a.href = downloadUrl;
                a.download = `'. esc_js($filename) .'`;
                document.body.appendChild(a);
                a.click();
            })();';
            wp_add_inline_script('lcwp_magpop', $inline_js);
        }
    }
    
}
else {
    $fdata = array();
    foreach($indexes as $i) {
        $fdata[ $i['index'] ] = null; 
    }
    
    $fdata['pc_pvtc_exp_user_statuses'] = array('all');
    $fdata['pc_pvtc_exp_user_cats'] = array('all');
}
?>

<div class="pc_imp_exp_notices"><?php echo wp_kses_post($cbe_notice) ?></div>

<div class="pc_imp_exp_box">
    <h4><?php esc_html_e('Export configuration', 'pc_ml') ?></h4>
    
    <form method="post" class="form-wrap" action="<?php echo esc_attr(pc_static::curr_url()) ?>">
        <table class="widefat pc_imp_exp_table">
            <tbody>
                
                <?php if(!isset($_GET['pc_targeted_export']) || !isset($_GET['pc_texp_label']) || !isset($_GET['pc_texp_conds'])) : ?>
                    <tr>
                        <td><?php esc_html_e("Users status", 'pc_ml'); ?></td>
                        <td>
                            <select name="pc_pvtc_exp_user_statuses[]" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select an option', 'pc_ml') ?> .." multiple="multiple" autocomplete="off">
                                <?php
                                foreach($user_statuses as $key => $label) {
                                    $sel = (is_array($fdata['pc_pvtc_exp_user_statuses']) && in_array($key, $fdata['pc_pvtc_exp_user_statuses'])) ? 'selected="selected"' : '';
                                    echo '<option value="'. esc_attr($key) .'" '. esc_html($sel) .'>'. esc_html($label) .'</option>'; 
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e("User categories", 'pc_ml'); ?></td>
                        <td>
                            <select name="pc_pvtc_exp_user_cats[]" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select categories', 'pc_ml') ?> .." multiple="multiple" autocomplete="off">
                                <?php
                                foreach($user_cats as $key => $label) {
                                    $sel = (is_array($fdata['pc_pvtc_exp_user_cats']) && in_array($key, $fdata['pc_pvtc_exp_user_cats'])) ? 'selected="selected"' : '';
                                    echo '<option value="'. esc_attr($key) .'" '. esc_html($sel) .'>'. esc_html($label) .'</option>'; 
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                <?php else : ?>
                    <tr>
                        <td><?php echo pc_static::wp_kses_ext(stripslashes(pc_static::sanitize_val($_GET['pc_texp_label']))) ?></td>
                        <td>
                            <?php echo pc_static::wp_kses_ext(stripslashes(pc_static::sanitize_val($_GET['pc_texp_conds']))) ?>
                            <input type="hidden" name="pc_targeted_export_params" value="<?php echo esc_attr(pc_static::sanitize_val($_GET['pc_targeted_export'])) ?>" />
                        </td>
                    </tr>
                <?php endif; ?>
                

                <tr>
                    <td><?php esc_html_e("Include user reserved page contents?", 'pc_ml') ?></td>
                    <td>
                        <input type="checkbox" name="pc_pvtc_exp_include_pvtpag" value="1" <?php if(isset($_POST['pc_pvtc_exp_include_pvtpag'])) {echo 'checked="checked"';} ?> class="pc_lc_switch" autocomplete="off" />
                    </td>
                </tr>

                <?php
                // PC-ACTION - add fields in CSV export form - must comply with table code
                do_action('pc_pvtc_export_form');
                ?>
                
            </tbody>
        </table>

        <br/>
        <input type="hidden" name="pc_nonce" value="<?php echo esc_attr(wp_create_nonce('lcwp_nonce')) ?>" /> 
        <input type="submit" name="pc_pvtc_export" value="<?php esc_attr_e('Export', 'pc_ml') ?>" class="button-primary" />  
    </form>
</div>


<?php
$inline_js = '
(function($) { 
    "use strict"; 
    
    $(document).on(`change`, `select[name="pc_pvtc_exp_user_cats[]"], select[name="pc_pvtc_exp_user_statuses[]"]`, function() {
        let vals = ($(this).val() !== null && typeof($(this).val()) == "object") ? $(this).val() : [];
        
        // if ALL is selected, discard the rest
        if(vals.includes(`all`)) {
            $(this).find("option").prop("selected", false);
            $(this).find(`option[value="all"]`).prop("selected", true);

            const resyncEvent = new Event("lc-select-refresh");
            this.dispatchEvent(resyncEvent);
        }

        const resyncEvent = new Event(`lc-select-refresh`);
        this.dispatchEvent(resyncEvent);
    });
})(jQuery);';
wp_add_inline_script('lcwp_magpop', $inline_js);