<?php
if(!defined('ABSPATH')) {exit;}


global $pc_users;
if(!$pc_users->wp_user_sync) {
    wp_die('Please enable the WP user sync PrivateContent system to use this import');   
}


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

?>
<div class="pc_imp_exp_notices"></div>
    
<div class="pc_imp_exp_box">
    <h4><?php esc_html_e('Import configuration', 'pc_ml') ?></h4>
    
    <form method="post" class="form-wrap" target="">
        <fieldset>
            <table class="widefat pc_imp_exp_table">
                <tbody>
                    <tr>
                        <td><?php esc_html_e("WordPress user roles to target", 'pc_ml'); ?></td>
                        <td>
                            <select name="pc_wp_imp_roles[]" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select a role', 'pc_ml') ?> .." multiple="multiple" autocomplete="off">
                                <?php
                                foreach(get_editable_roles() as $role_id => $data) { 
                                    if(!in_array($role_id, array('administrator', 'pvtcontent', 'pvtcontent_admin', 'editor')) ) {
                                        echo '<option value="'. esc_attr($role_id) .'">'. esc_html($data['name']) .'</option>';
                                    }
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e("Default categories for imported users", 'pc_ml'); ?></td>
                        <td>
                            <select name="pc_wp_imp_cat[]" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select a category', 'pc_ml') ?> .." multiple="multiple" autocomplete="off">
                                <?php
                                foreach($user_cats as $key => $label) {
                                    echo '<option value="'. esc_attr($key) .'" '. esc_html($sel) .'>'. esc_html($label) .'</option>'; 
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <?php
                    // PC-ACTION - allow custom fields for pvtc import form - HTML structure must comply
                    do_action('wp_to_pc_bulk_sync_form_fields');
                    ?>
                </tbody>
            </table>
            
            <br/>
            <input type="hidden" name="pc_nonce" value="<?php echo esc_attr(wp_create_nonce('lcwp_ajax')) ?>" />
            <input type="submit" name="pc_wp_import" value="<?php esc_attr_e('Import', 'pc_ml') ?>" class="button-primary" /> 
        </fieldset>
    </form>
</div>


<?php
$inline_js = '
(function($) { 
    "use strict"; 
    
    let is_acting = false;
		
    // sync
    $(document).on(`submit`, `.pc_imp_exp_box form`, function(e) {
        e.preventDefault();
        
        const $fieldset = $(this).find(`fieldset`), 
              cats      = $(`[name="pc_wp_imp_cat[]"]`).serialize(),
              roles     = $(`[name="pc_wp_imp_roles[]"]`).serialize();

        if(is_acting) {
            return false;
        }
        if(!cats || !roles) {
            lc_wp_popup_message(`error`, `'. esc_html__('Please select at least a WordPress role and a target category', 'pc_ml') .'`);
            return false;
        }
        

        if(confirm(`'. esc_html__("Do you really want to turn every user belonging to these roles into a PrivateContent user? Will not be possible to have them back in future", 'pc_ml') .'`)) {
            is_acting = true;
            let data = `action=pvtcont_wp_to_pc_bulk_user_sync&`+ $(`.pc_imp_exp_box form`).serialize();
            
            $(`.pc_imp_exp_notices`).empty();
            $fieldset[0].disabled = true;

            $.post(ajaxurl, data, function(response) {
                $(`.pc_imp_exp_notices`).html(response);
            })
            .fail(function(e) {
                if(e.status) {
                    console.error(e);
                    $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>Error performing the operation</p></div>`);
                }
            })
            .always(function(e) {
                $fieldset[0].disabled = false;
                is_acting = false;
            });
        }
        
        return false;
    });	     
})(jQuery);';
wp_add_inline_script('lcwp_magpop', $inline_js);