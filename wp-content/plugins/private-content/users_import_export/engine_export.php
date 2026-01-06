<?php
if(!defined('ABSPATH')) {exit;}


include_once(PC_DIR .'/classes/engine_import_export.php');
$eie = new pvtcont_engine_import_export;

$notice = '';
$fdata = array(
    'pc_engine_exp_subjs' => array()
);


if(isset($_POST['pc_engine_export'])) {
    if(!isset($_POST['pc_engine_export_subj']) || empty($_POST['pc_engine_export_subj']) || !is_array($_POST['pc_engine_export_subj'])) {
        $notice = '<div class="pc_warn pc_error"><p>'. esc_html__('Please select at least one subject to export', 'pc_ml') .'</p></div>';
    }
    $fdata['pc_engine_exp_subjs'] = array_unique(pc_static::sanitize_val($_POST['pc_engine_export_subj']));
    
    if(!$notice) {
        $avail_subjs = $eie->get_subjs();
        
        foreach($fdata['pc_engine_exp_subjs'] as $chosen_subj) {
            if(!isset($avail_subjs[$chosen_subj])) {
                $notice = '<div class="pc_warn pc_error"><p>'. esc_html__('Unknown subject', 'pc_ml') .' "'. $chosen_subj .'"</p></div>';
                break;
            }
        }
    }
    
    // perform export
    if(!$notice) {
        $export_data = $eie->export($fdata['pc_engine_exp_subjs']);
        
        if($export_data['status'] == 'error') {
            $notice = '<div class="pc_warn pc_error"><p>'. $export_data['message'] .'</p></div>';
        }
        else {
            $filename = 'pc_engine_'. strtolower(sanitize_title($_SERVER['HTTP_HOST'])) .' -'. wp_date('Y-m-d-H:i:s') .'.json';
            
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
                
            $notice = '<div class="pc_warn pc_success"><p>'. esc_html__('Export successfully performed!', 'pc_ml') .'</p></div>';
        }
    }
}
?>

<div class="pc_imp_exp_notices"><?php echo wp_kses_post($notice) ?></div>

<div class="pc_imp_exp_box">
    <h4><?php esc_html_e('Export configuration', 'pc_ml') ?></h4>
    
    <form method="post" class="form-wrap" action="<?php echo esc_attr(pc_static::curr_url()) ?>">
        <table class="widefat pc_imp_exp_table pc_engine_ie_table">
            <tbody>
                <?php
                foreach($eie->engine_ei_structure as $prod_name => $opts) {
                    echo '
                    <tr>
                        <th>'. esc_html($prod_name) .'</th>
                        <td>
                            <ul class="pc_engine_ie_optslist">';
                    
                            foreach($opts as $opt_id => $opt_name) {
                                $checked = (in_array($opt_id, $fdata['pc_engine_exp_subjs'])) ? 'checked="checked"' : '';
                                
                                echo '
                                <li>
                                    <input type="checkbox" name="pc_engine_export_subj[]" value="'. esc_attr($opt_id) .'" class="pc_lc_switch" autocomplete="off" '. esc_html($checked) .' />
                                    <span>'. esc_html($opt_name) .'</span>
                                </li>';
                            }
                    
                    echo '
                            </ul>
                        </td>
                    </tr>';
                }
                ?>
            </tbody>
        </table>

        <br/>
        <input type="hidden" name="pc_nonce" value="<?php echo esc_attr(wp_create_nonce('lcwp_nonce')) ?>" /> 
        <input type="submit" name="pc_engine_export" value="<?php esc_attr_e('Export', 'pc_ml') ?>" class="button-primary" />  
    </form>
</div>