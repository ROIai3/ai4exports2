<?php
if(!defined('ABSPATH')) {exit;}


include_once(PC_DIR .'/classes/pc_form_framework.php');
$form_fw = new pc_form;	


$user_cats = array();
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
   
<form method="post" class="form-wrap pc_csv_import_form" enctype="multipart/form-data">

    <div class="pc_imp_exp_box" data-import-step="1">
        <h4><?php esc_html_e('Step 1 - File Upload', 'pc_ml') ?></h4>

        <fieldset>
            <table class="widefat pc_imp_exp_table">
                <tbody>
                    <tr>
                        <td>
                            <?php esc_html_e("Select the CSV file cotaining users data", 'pc_ml'); ?><br/>
                            <small>(<?php esc_html_e('max files size', 'pc_ml') ?> <?php echo esc_html(pc_static::human_filesize(wp_max_upload_size(), 0)) ?>)</small>
                        </td>
                        <td>
                            <input type="file" name="pc_csv_import_csv" accept=".csv" required />
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e("Categories assigned to imported users", 'pc_ml'); ?></td>
                        <td>
                            <select name="pc_csv_imp_user_cats[]" class="pc_lc_select" data-placeholder="<?php esc_attr_e('Select categories', 'pc_ml') ?> .." multiple="multiple" autocomplete="off">
                                <?php
                                foreach($user_cats as $key => $label) {
                                    echo '<option value="'. esc_attr($key) .'" '. esc_html($sel) .'>'. esc_html($label) .'</option>'; 
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e("Enable imported users reserved page?", 'pc_ml') ?></td>
                        <td>
                            <input type="checkbox" name="pc_csv_imp_have_pvt_pag" value="1" class="pc_lc_switch" autocomplete="off" checked />
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e("Ignore first CSV row?", 'pc_ml') ?> <em>(<?php esc_html_e("to skip headings", 'pc_ml') ?>)</em></td>
                        <td>
                            <input type="checkbox" name="pc_csv_imp_ignore_first_row" value="1" class="pc_lc_switch" autocomplete="off" />
                        </td>
                    </tr>
                    <tr class="<?php if(!apply_filters('pc_csv_imp_show_create_new_psw_f', false)) {echo 'pc_displaynone';} ?>">
                        <td><?php esc_html_e("Automatically create a new password for imported users?", 'pc_ml') ?></td>
                        <td>
                            <input type="checkbox" name="pc_csv_imp_create_new_psw" value="1" class="pc_lc_switch" autocomplete="off" />
                        </td>
                    </tr>
                    
                    <?php
                    // PC-ACTION - allow custom fields for CSV import form - HTML structure must comply
                    do_action('pc_csv_import_form_fields');
                    ?>
                </tbody>
            </table>

            <br/>
            <input type="hidden" name="pc_nonce" value="<?php echo esc_attr(wp_create_nonce('lcwp_nonce')) ?>" />
            <input type="submit" name="pc_csv_import_upload" value="<?php esc_attr_e('Upload', 'pc_ml') ?>" class="button-primary" /> 
        </fieldset>
    </div>


    <div class="pc_imp_exp_box pc_displaynone" data-import-step="2">
        <h4><?php esc_html_e('Step 2 - Data Mapping', 'pc_ml') ?></h4>

        <fieldset>
            <table class="widefat pc_imp_exp_table">
                <tbody>
                    <tr>
                        <td class="pc_csv_imp_data_map_wrap"></td>
                    </tr>
                </tbody>
            </table>

            <br/>
            <input type="hidden" name="pc_nonce" value="<?php echo esc_attr(wp_create_nonce('lcwp_nonce')) ?>" />

            <input type="button" name="pc_csv_import_back" value="<?php esc_attr_e('Back to upload', 'pc_ml') ?>" class="button-secondary alignright" />
            <input type="submit" name="pc_csv_do_import" value="<?php esc_attr_e('Import', 'pc_ml') ?>" class="button-primary" /> 
        </fieldset>
    </div>
</form>
    

<?php
$inline_js = '
(function($) { 
    "use strict"; 
    
    const $form = $(`.pc_csv_import_form`);
    
    let is_acting = false,
        import_done = false;
    
		
    $form.on(`submit`, function(e) {
        e.preventDefault();
        ($(`.pc_imp_exp_box[data-import-step="1"]`).hasClass(`pc_displaynone`)) ? step_2() : step_1();
        return false;
    });
    
    
    // step 1 - CSV file upload
    const step_1 = async function() {
        const $fieldset = $form.find(`.pc_imp_exp_box[data-import-step="1"] fieldset`);
        if(is_acting) {
            return false;
        }
        
        if(!$(`select[name="pc_csv_imp_user_cats[]"]`).val() || !$(`select[name="pc_csv_imp_user_cats[]"]`).val().length) {
            $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>'. esc_html__('Please select at least one user category to assign', 'pc_ml') .'</p></div>`);
            
            scroll_to_notices_wrap();
            return false;   
        }
        is_acting = true;
        
        let formData = new FormData($form[0]);
        formData.append(`action`, `pvtcont_csv_import_csv_upload`);

        $(`.pc_imp_exp_notices`).empty();
        $fieldset[0].disabled = true;
        
        await fetch(ajaxurl, {
            method: `POST`,
            body: formData
        })
        .then((response) => response.json())
        .then((result) => {
            
            if(result.status == `success`) {
                $(`.pc_imp_exp_box[data-import-step="1"]`).addClass(`pc_displaynone`);
                $(`.pc_imp_exp_box[data-import-step="2"]`).removeClass(`pc_displaynone`);
                
                $(`.pc_csv_imp_data_map_wrap`).html(result.data_map_table);
                
                pc_ie_lc_select();
                pc_ie_lc_switch();
            }
            else {
                $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>`+ result.message +`</p></div>`);
                scroll_to_notices_wrap();
            }
        })
        .catch((e) => {
            if(e.status) {
                console.error(e);
                
                $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>Error performing the operation</p></div>`);
                scroll_to_notices_wrap();
            }
        })
        .finally(() => {
            $fieldset[0].disabled = false;
            is_acting = false;
        });
    };	 
    

    // step 2 - proceed
    const step_2 = async function() {
        const $fieldset = $form.find(`.pc_imp_exp_box[data-import-step="2"] fieldset`);
        
        if(is_acting) {
            return false;   
        }
        $(`.pc_imp_exp_notices`).empty();
        
        
        // check mandatory and doubled assignments
        const email_required = '. (($form_fw->mail_is_required) ? 'true' : 'false') .',
              psw_required = ($(`input[name="pc_csv_imp_create_new_psw"]`)[0].checked) ? false : true; 
        
        let assigned = [],
            abort = false;
        
        $(`.pc_csv_imp_data_map_wrap select[name="pc_csv_imp_dest_field[]"]`).each(function() {
            const val = $(this).val();
            
            if(!val) {
                return;   
            }
            if(assigned.includes(val)) {
                const label = $(this).find(`option[value="`+ val +`"]`).text();
                
                $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p><strong>${ label }</strong> - '. esc_html__('another column has been already associated with this field', 'pc_ml') .'</p></div>`);
                scroll_to_notices_wrap();
                
                abort = true;
                return false;
            }
            
            assigned.push(val);
        });
        
        if(abort) {
            return false;   
        }
        if(!assigned.includes(`username`)) {
            $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>'. esc_html__('Username field assignment is mandatory', 'pc_ml') .'</p></div>`);
            
            scroll_to_notices_wrap();
            return false;
        }
        if(email_required && !assigned.includes(`email`)) {
            $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>'. esc_html__('E-mail field assignment is mandatory', 'pc_ml') .'</p></div>`);
            
            scroll_to_notices_wrap();
            return false;
        }
        if(psw_required && !assigned.includes(`psw`)) {
            $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>'. esc_html__('Password field assignment is mandatory', 'pc_ml') .'</p></div>`);
            
            scroll_to_notices_wrap();
            return false;
        }
        
        
        if($fieldset.find(`select`).length && !confirm(`'. esc_html__("Did you double check the import setup? Proceed?", 'pc_ml') .'`)) {
            return false;
        }
        is_acting = true;
        
        let formData = new FormData($form[0]);
        formData.append(`action`, `pvtcont_csv_import`);

        $fieldset[0].disabled = true;
        
        await fetch(ajaxurl, {
            method: `POST`,
            body: formData
        })
        .then((response) => response.json())
        .then((result) => {
            
            if(result.status == `success`) {
                $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_success pc_import_report">`+ result.report +`</div>`);
            }
            else {
                $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>`+ result.message +`</p></div>`);
            }
            
            if(result.back_to_upload) {
                setTimeout(() => {
                    import_done = true;
                    $(`input[name="pc_csv_import_back"]`).trigger(`click`);
                    import_done = false;
                }, 50);
            }
        })
        .catch((e) => {
            console.error(e);
            $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_error"><p>Error performing the operation</p></div>`);
        })
        .finally(() => {
            $fieldset[0].disabled = false;
            is_acting = false;
            
            scroll_to_notices_wrap();
        });
    };	
    
    
    
    // back to step 1?
    $(document).on(`click`, `input[name="pc_csv_import_back"]`, function() {
        if(is_acting) {
            return false;   
        }
        if(!import_done && !confirm(`'. esc_html__("Do you really want to go back to the upload form?", 'pc_ml') .'`)) {
            return false;   
        }
        
        $(`.pc_imp_exp_box[data-import-step="1"]`).removeClass(`pc_displaynone`);
        $(`.pc_imp_exp_box[data-import-step="2"]`).addClass(`pc_displaynone`);

        if(!import_done) {
            $(`.pc_imp_exp_notices`).empty();
        }
        
        $(`.pc_csv_imp_data_map_wrap`).empty();
        scroll_to_notices_wrap();
    });
    
    
    
    // scroll back to notices wrap
    const scroll_to_notices_wrap = function() {
        window.scroll({
            top: $(`.pc_imp_exp_notices`)[0].offsetTop - 30, 
            behavior:`smooth`
        });
    };
    
})(jQuery);';
wp_add_inline_script('lcwp_magpop', $inline_js);