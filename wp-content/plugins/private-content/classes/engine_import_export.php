<?php
// CLASS MANAGING IMPORT / EXPORT ENGINE FUNCTIONS
if(!defined('ABSPATH')) {exit;}


class pvtcont_engine_import_export {
    public $engine_ei_structure = array(); // (array) the export/import structure 
    public $subjs_w_override_confirm = array(); // (array) subject IDs requiring an existing-index override confirmation  
    
    
    public function __construct() {
        
        // PC-FILTER - suite engine export - allow extra subjects to be injected. Passes the structure array(plugin-name => array(subj_id => subj_name))
        $engine_ei_structure = array(
            'PrivateContent Plugin' => array(
                'pc_settings' => esc_html__('Settings', 'pc_ml'),
                'pc_reg_form' => esc_html__('Registration forms', 'pc_ml'),
            ),
        );
        $this->engine_ei_structure = apply_filters('pc_engine_ie_structure', $engine_ei_structure);
        
        
        // PC-FILTER - suite engine export - defining which subjects requires an existing-index override confirmation
        $subjs_w_override_confirm = array('pc_reg_form');
        $this->subjs_w_override_confirm = apply_filters('pc_engine_ie_w_override_confirm', $subjs_w_override_confirm);
    }
    
    
    
    /* 
     * Getting the subjects list, without group division 
     * @return (mixed) the subjects associative array, or the targeted subject name (or if doesn't exist, false)
     */
    public function get_subjs($subj = false) {
        $subjs = array();
        
        foreach($this->engine_ei_structure as $group_name => $group_subjs) {
            $subjs = array_merge($subjs, $group_subjs);   
        }
        
        if($subj) {
            return (isset($subjs[$subj])) ? $subjs[$subj] : false;    
        }
        return $subjs;
    }
    
    
    
    
    /* Reset the import report data array */
    private function reset_import_report() {
        foreach(array_keys($this->import_report) as $rep_key) {
            $this->import_report = array();
        }
    }
    

    
    
    /* 
     * Export JSON creation
     * @return (array) (
            status  => succcess|error, 
            message => (if error),
            filedata=> (if success)
        )
     */
    public function export($subjects) {
        global $pc_users;
        
        $json = array(
            'main' => array(
                'pc_ver' => PC_VERS,
                'export_date' => gmdate('Y-m-d H:i:s'),
            ),
            'subjs' => array(),
        );
        
        
        foreach($subjects as $subj_id) {
            $val = '';
            
            // settings export
            if($subj_id == 'pc_settings') {
                include_once(PC_DIR .'/settings/settings_engine.php'); 
                include_once(PC_DIR .'/settings/structure.php');
                
                lcwp_settings_engine::$prod_acronym = 'lcpc';
                $engine = new lcwp_settings_engine('pc_settings', $GLOBALS['pc_settings_tabs'], $GLOBALS['pc_settings_structure']);
                $val = pc_static::stringify_for_json($engine->get_export_json());
            }
            
            
            // registration forms
            elseif($subj_id == 'pc_reg_form') {
                $forms_to_export = array();
                $reg_forms = get_terms(array(
                    'taxonomy'   => 'pc_reg_form',
                    'orderby'    => 'name',
                    'hide_empty' => 0,
                ));
                
                foreach($reg_forms as $reg_form) {
                    $forms_to_export[ $reg_form->term_id ] = array(
                        'name' => $reg_form->name,
                        'slug' => $reg_form->slug,
                        'data' => pc_static::stringify_for_json($reg_form->description),
                        'metas'=> array()
                    );
                    
                    $forms_to_export[ $reg_form->term_id ]['metas'] = pc_static::fix_wp_get_all_meta(get_term_meta($reg_form->term_id), true);
                }
                
                $val = $forms_to_export;
            }
            
            
            else {
                // PC-FILTER - allow extra modules to export their data through the engine export/import
                $val = apply_filters('pc_engine_export_subj', '', $subj_id);
            }
              
            /////////////////////
            if(!empty($val)) {
                $json['subjs'][$subj_id] = $val;   
            }
        }
        
        
        // PC-FILTER - one last filter to manipulate the engine export data
        $json['subjs'] = apply_filters('pc_engine_export_data', $json['subjs']);
        
        return array(
            'status'    => 'success',
            'filedata'  => json_encode($json)   
        );
    }
    
    
    
    
    /* 
     * Validate the uploaded JSON file containing the import data. To be used in AJAX call, handles $_FILES['pc_engine_import_json']
     * @return (array|string) the file contents array or the error message 
     */
    public function validate_import_file() {
        if(!isset($_FILES['pc_engine_import_json']) || $_FILES['pc_engine_import_json']['error'] !== UPLOAD_ERR_OK) {
            return 'Missing uploaded file';
        }
        if($_FILES['pc_engine_import_json']['type'] !== 'application/json') {
            return esc_html__('Wrong file type', 'pc_ml');
        }
        $contents = json_decode(file_get_contents(
            sanitize_text_field($_FILES['pc_engine_import_json']['tmp_name'])
        ), true);


        if(!is_array($contents) || !isset($contents['main']) || !isset($contents['subjs'])) {
            return esc_html__('Wrong or corrupted JSON data', 'pc_ml');
        }
        if(empty($contents['subjs']) || !is_array($contents['subjs'])) {
            return esc_html__('No import subjects found', 'pc_ml');
        }
        
        $avail_subjs = $this->get_subjs();
        $possible_choices = array();
        foreach($contents['subjs'] as $subj_id => $subj_data) {
            if(isset($avail_subjs[$subj_id])) {
                $possible_choices[] = $subj_id;
            }
        }
        
        if(empty($possible_choices)) {
            return esc_html__('No import subjects found', 'pc_ml');
        }
        return $contents;
    }
    
    
    
    
    /*
     * Handling the JSON file data array, the subjects to import and the ones with override permission, perform the import
     * @return (array) 
            (status => success, report => (string)) OR
            (status => error, message => (string)) 
     */
    public function import($contents, $involved_subjs, $can_override = array()) {
        $reports = array();
        
        foreach($involved_subjs as $subj_id) {
            $override_data = (in_array($subj_id, $can_override)) ? true : false;
            
            // PC-FILTER - allow extra data manipulation for a specific engine subject import
            $subj_data = apply_filters('pc_engine_import_'. $subj_id .'_data', $contents['subjs'][$subj_id], $involved_subjs, $can_override);
            
            
            // settings export
            if($subj_id == 'pc_settings') {
                include_once(PC_DIR .'/settings/settings_engine.php');     
                $pc_settings_baseurl = admin_url('admin.php?page=pc_settings');
                
                $final_url = add_query_arg(array(
                        'lcwp_sf_import'        => urlencode(lcwp_settings_engine::get_final_export_string($subj_data)),
                        'lcwp_sf_is_importing'  => '',
                        'pc_settings_nonce'     => wp_create_nonce('lcwp')
                    ),
                    $pc_settings_baseurl
                );
                
                $reports[$subj_id] = esc_html__('To complete the import, click on', 'pc_ml') .' <strong><a href="'. esc_attr($final_url) .'" target="_blank">'. esc_html__('this link', 'pc_ml') .' &raquo;</a></strong>';
            }
            
            // registration forms
            elseif($subj_id == 'pc_reg_form') {
                $import_report = array(
                    'added'     => array(),
                    'overridden'=> array(),
                    'ignored'   => array(),
                    'errors'    => array()
                );
                
                foreach($subj_data as $orig_term_id => $term_data) {
                    $existing_one = get_term_by('slug', $term_data['slug'], 'pc_reg_form');
                    
                    // does it exists?
                    if($existing_one) {
                        $term_id = $existing_one->term_id;
                        
                        if($override_data) {
                            wp_update_term($term_id, 'pc_reg_form', array(
                                'name' => $term_data['name'],
                                'slug' => $term_data['slug'],
                                'description' => $term_data['data']
                            ));
                            $import_report['overridden'][$term_id] = $term_data['name'];
                        }
                        else {
                            $import_report['ignored'][$term_id] = $existing_one->name;
                            continue;
                        }
                    }
                    
                    // create new one
                    else {
                        $args = array(
                            'cat_name' => $term_data['name'], 
                            'category_description' => $term_data['data'], 
                            'category_nicename' => $term_data['slug'],
                            'taxonomy' => 'pc_reg_form',
                        );
                        $term_id = wp_insert_category($args);

                        if(is_wp_error($term_id)) {
                            $import_report['errors'][$orig_term_id] = esc_html__('Error importing the registration form', 'pc_ml') .' "'. $term_data['name'] .'": '. $term_id->get_error_message();
                            continue;
                        }
                        
                        $import_report['added'][$term_id] = $term_data['name'];
                    }
                    
                    // update metas
                    if(is_array($term_data['metas'])) {
                        foreach($term_data['metas'] as $meta_key => $meta_val) {
                            update_term_meta($term_id, $meta_key, maybe_unserialize($meta_val)); 
                        }
                    }
                }
                
                
                // compose the human report
                $html_report = '
                <ul>';
                
                foreach($import_report as $report_subj => $rsd) {
                    if(empty($rsd)) {
                        continue;   
                    }
                    
                    switch($report_subj) {
                        case 'added' : $pre_txt = esc_html__('Registration forms added', 'pc_ml'); break;
                        case 'overridden' : $pre_txt = esc_html__('Registration forms updated', 'pc_ml'); break;
                        case 'ignored' : $pre_txt = esc_html__('Duplicate registration forms ignored', 'pc_ml'); break;
                        case 'errors' : $pre_txt = esc_html__('Errors', 'pc_ml'); break;
                    }
                    $html_report .= '
                    <li class="pc_import_report">
                        <strong>'. count($rsd) .' '. $pre_txt .':</strong>
                        <ul>
                            <li>'. implode('</li><li>', $rsd) .'</li>
                        </ul>
                    </li>';
                }
                
                $reports[$subj_id] = $html_report . '
                </ul>';
            }
           
            
            else {
                // PC-FILTER - allow extra modules to import their data through the engine export/import - must return an HTML code to use for report
                $reports[$subj_id] = apply_filters('pc_engine_import_subj', '', $subj_id, $subj_data, $override_data);
            }
        }
        
        return array(
            'status' => 'success',
            'report' => $reports,
            'back_to_upload' => false,
        );
    }
    
}
