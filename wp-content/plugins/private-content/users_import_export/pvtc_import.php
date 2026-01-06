<?php if(!defined('ABSPATH')) {exit;} ?>
<div class="pc_imp_exp_notices"></div>
   
<form method="post" class="form-wrap pc_pvtc_import_form" enctype="multipart/form-data">

    <div class="pc_imp_exp_box" data-import-step="1">
        <h4><?php esc_html_e('Step 1 - File Upload', 'pc_ml') ?></h4>

        <fieldset>
            <table class="widefat pc_imp_exp_table">
                <tbody>
                    <tr>
                        <td>
                            <?php esc_html_e("Select the JSON file cotaining users data", 'pc_ml'); ?><br/>
                            <small>(<?php esc_html_e('max files size', 'pc_ml') ?> <?php echo esc_html(pc_static::human_filesize(wp_max_upload_size(), 0)) ?>)</small>
                        </td>
                        <td>
                            <input type="file" name="pc_pvtc_import_json" accept=".json" required />
                        </td>
                    </tr>
                    
                    <?php
                    // PC-ACTION - allow custom fields for pvtc import form - HTML structure must comply
                    do_action('pc_pvtc_import_form_fields');
                    ?>
                </tbody>
            </table>

            <br/>
            <input type="hidden" name="pc_nonce" value="<?php echo esc_attr(wp_create_nonce('lcwp_nonce')) ?>" />
            <input type="submit" name="pc_pvtc_import_upload" value="<?php esc_attr_e('Upload', 'pc_ml') ?>" class="button-primary" /> 
        </fieldset>
    </div>


    <div class="pc_imp_exp_box pc_displaynone" data-import-step="2">
        <h4><?php esc_html_e('Step 2 - Data Matching', 'pc_ml') ?></h4>

        <fieldset>
            <table class="widefat pc_imp_exp_table">
                <tbody>
                    <tr>
                        <td class="pc_pvtc_imp_cat_assign_wrap"></td>
                    </tr>
                    <tr>
                        <td class="pc_pvtc_imp_exist_users_wrap"></td>
                    </tr>
                </tbody>
            </table>

            <br/>
            <input type="hidden" name="pc_nonce" value="<?php echo esc_attr(wp_create_nonce('lcwp_nonce')) ?>" />

            <input type="button" name="pc_pvtc_import_back" value="<?php esc_attr_e('Back to upload', 'pc_ml') ?>" class="button-secondary alignright" />
            <input type="submit" name="pc_pvtc_do_import" value="<?php esc_attr_e('Import', 'pc_ml') ?>" class="button-primary" /> 
        </fieldset>
    </div>
</form>
    

<?php
$inline_js = '
(function($) { 
    "use strict"; 
    
    const $form = $(`.pc_pvtc_import_form`);
    
    let is_acting = false,
        import_done = false;
    
		
    $form.on(`submit`, function(e) {
        e.preventDefault();
        ($(`.pc_imp_exp_box[data-import-step="1"]`).hasClass(`pc_displaynone`)) ? step_2() : step_1();
        return false;
    });
    
    
    // step 1 - JSON file upload
    const step_1 = async function() {
        const $fieldset = $form.find(`.pc_imp_exp_box[data-import-step="1"] fieldset`);

        if(is_acting) {
            return false;
        }
        is_acting = true;
        
        let formData = new FormData($form[0]);
        formData.append(`action`, `pvtcont_pvtc_import_json_upload`);

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
                
                $(`.pc_pvtc_imp_cat_assign_wrap`).html(result.cat_assign_table);
                $(`.pc_pvtc_imp_exist_users_wrap`).html(result.exist_user_table);
                
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
        
        // be sure there`s something to import
        if($(`select[name="pc_pvtc_import_exist_user_action[]"]`).length) {
            let to_skip = 0;
            $(`select[name="pc_pvtc_import_exist_user_action[]"]`).each(function() {
                if($(this).val() == `discard`) {
                    to_skip++;   
                }
            });
            
            if(parseInt($(`.pc_pvtc_imp_exist_users_wrap table`).data(`users-to-import`), 10) <= to_skip) {
                $(`.pc_imp_exp_notices`).html(`<div class="pc_warn pc_warning"><p>'. esc_attr__("No user to import, all have been discarded", 'pc_ml') .'</p></div>`);
                scroll_to_notices_wrap();
                return false;
            }
        }
        
        
        if($fieldset.find(`select`).length && !confirm(`'. esc_attr__("Did you double check the import setup? Proceed?", 'pc_ml') .'`)) {
            return false;
        }
        is_acting = true;
        
        let formData = new FormData($form[0]);
        formData.append(`action`, `pvtcont_pvtc_import`);

        $(`.pc_imp_exp_notices`).empty();
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
                    $(`input[name="pc_pvtc_import_back"]`).trigger(`click`);
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
    $(document).on(`click`, `input[name="pc_pvtc_import_back"]`, function() {
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
        
        $(`.pc_pvtc_imp_cat_assign_wrap, .pc_pvtc_imp_exist_users_wrap`).empty();
        scroll_to_notices_wrap();
    });
    
    
    
    // bulk set existing users action to "discard" or "override"
    $(document).on(`click`, `[data-bulk-act]`, function() {
        const action = $(this).data(`bulk-act`);
        
        if(action == `discard`) {
            if(!confirm(`'. esc_html__("Do you really want to discard all corresponding users?", 'pc_ml') .'`)) {
                return false;
            }
        }
        else if(action == `override`) {
            if(!confirm(`'. esc_attr__("Do you really want to overwrite all corresponding users?", 'pc_ml') .'`)) {
                return false;
            }  
        }
        
        $(`select[name="pc_pvtc_import_exist_user_action[]"] option`).each(function() {
            this.selected = ($(this).attr(`value`) == action) ? true : false;     
        });
        
        $(`select[name="pc_pvtc_import_exist_user_action[]"]`).each(function() {
            const resyncEvent = new Event(`lc-select-refresh`);
            this.dispatchEvent(resyncEvent);
        });
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