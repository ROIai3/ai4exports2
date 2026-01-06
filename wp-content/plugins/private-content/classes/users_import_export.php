<?php
// CLASS MANAGING ALL VARIOUS IMPORT / EXPORT USERS FUNCTIONS
if(!defined('ABSPATH')) {exit;}


class pvtcont_users_import_export extends pc_users {

    public $import_report = array(
        'new_cats'          => array(),
        'new_users'         => array(),
        'overridden_users'  => array(),
        'discarded_users'   => array(),
        'user_errors'       => array(),
    );
    
    /* Reset the import report data array */
    private function reset_import_report() {
        foreach(array_keys($this->import_report) as $rep_key) {
            $this->import_report = array();
        }
    }
    

    
    
    /* 
     * JSON creation for pvtcontent-to-pvtcontent export
     * @param (array) $fdata - data fetched from the export form
     * @return (array) (
            status  => succcess|error, 
            message => (if error),
            filedata=> (if success)
        )
     */
    public function pvtc_export($fdata) {
        global $pc_users;
        
        // users query
        $query_args = array(
            'limit' => -1,
        );

        
        // standard export
        if(!isset($fdata['pc_targeted_export_params'])) {
            if(is_array($fdata['pc_pvtc_exp_user_statuses']) && !in_array('all', $fdata['pc_pvtc_exp_user_statuses'])) {
                $query_args['search'] = array(
                    array(
                        array('key' => 'status', 'val' => $fdata['pc_pvtc_exp_user_statuses'], 'operator' => 'IN')
                    )
                );    
            }

            // in case of specific cats fetching
            if(is_array($fdata['pc_pvtc_exp_user_cats']) && !in_array('all', $fdata['pc_pvtc_exp_user_cats'])) {
                $query_args['categories'] = $fdata['pc_pvtc_exp_user_cats'];
            }
        }

        // advanced search export
        else {
            $as_params = unserialize(base64_decode($fdata['pc_targeted_export_params']));
            
            $query_args['status'] = (int)$as_params['status'];
            $query_args['search'] = (array)$as_params['search'];
        }
        
        
        // PC-FILTER - filter pc-to-pc export get_users() query parameters
        $query_args = apply_filters('pc_pvtc_export_users_query', $query_args, $fdata);
        $users = $this->get_users($query_args); 
        
        if(empty($users)) {
            return array(
                'status' => 'error',
                'message'=> esc_html__('No matching users found', 'pc_ml')    
            );
        }
        

        // users data
        $involved_cat_ids = array();
        
        $udata_to_export = array();
        foreach($users as $u) {
            $ute = array(
                'metas' => array(),
            );
            
            foreach($u as $key => $udata) {
                if($key == 'id') {
                    continue;   
                }
                if($key == 'categories') {
                    if(is_array($udata)) {
                        foreach($udata as $cat_id) {
                            if(!in_array($cat_id, $involved_cat_ids)) {
                                $involved_cat_ids[] = $cat_id;   
                            } 
                        }
                    }
                }
                
                
                $udata = pc_static::stringify_for_json($udata);

                if(in_array($key, $this->fixed_fields)) {
                    $ute[$key] = $udata;   
                }
                else {
                    
                    // PC-FILTER - pvtcont-to-pvtcont export - user meta export filter - return false to skip
                    $um_export = apply_filters('pc_pvtc_export_user_meta', true, $key, $udata);
                    if($um_export) {
                        $ute['metas'][$key] = $udata;
                    }
                }
                
                if($key == 'page_id') {
                    if($fdata['pc_pvtc_exp_include_pvtpag']) {
                        $ute['pvt_page']['metas'] = pc_static::fix_wp_get_all_meta(get_post_meta((int)$udata), true);
                        $ute['pvt_page']['contents'] = get_post_field('post_content', (int)$udata, 'db');

                        foreach($ute['pvt_page'] as $ute_key => $ute_val) {
                            $ute['pvt_page'][$ute_key] = pc_static::stringify_for_json($ute_val);
                        }
                    }
                    
                    unset($ute[$key]); // delete the WP page ID key since it's useless for the import
                }
                
                elseif($key == 'wp_user_id' && !empty($udata) && $this->wp_user_sync) {
                    $ute['wp_user_id'] = pc_static::fix_wp_get_all_meta(get_user_meta((int)$udata), true); // only user metas - essential data already created by PvtContent on sync
                }
            }
            
            
            // PC-FILTER - allow pvtcontent export data array management
            $ute = apply_filters('pc_pvtc_export_user_data_array', $ute, $u['id']);
            
            $udata_to_export[ $u['id'] ] = $ute;
        }
        
        
        // user categories to export
        $cats_to_export = array();
        $ucats = get_terms(array(
            'taxonomy'   => 'pg_user_categories',
            'orderby'    => 'name',
            'hide_empty' => 0,
        ));
        
        foreach($ucats as $ucat) {
            if(!in_array($ucat->term_id, $involved_cat_ids)) {
                continue;   
            }
            
            $cats_to_export[$ucat->term_id] = array(
                'name' => $ucat->name,
                'slug' => $ucat->slug,
                'descr'=> $ucat->description,
                'no_pc_registr' => pc_static::retrocomp_get_term_meta($ucat->term_id, 'pg_ucat_no_registration', "pg_ucat_".$ucat->term_id."_no_registration"),
            );
        }
        
        
        $json = array(
            'main' => array(
                'pc_ver'        => PC_VERS,
                'export_date'   => gmdate('Y-m-d H:i:s'),
                'wp_user_sync'  => $this->wp_user_sync,
                'pc_categories' => $cats_to_export,
            ),
            'users' => $udata_to_export,
        );
        
        return array(
            'status'    => 'success',
            'filedata'  => json_encode($json)   
        );
    }
    
    
    
    
    /* 
     * Generic users data export (CSV / XSLX)
     * @param (array) $fdata - data fetched from the export form
     * @return (array) (
            status  => succcess|error, 
            message => (if error),
            filedata=> (if success)
        )
     */
    public function csv_export($fdata) {
        require_once(PC_DIR .'/classes/pc_form_framework.php');
        $f_fw = new pc_form;
        
        // users query
        $query_args = array(
            'limit' => -1,
        );
        
        // what to export - associative array index => label used also for file column headings
        $to_get = array(
            'id' => 'User ID',
            'status' => 'Status'
        );
        foreach($f_fw->fields as $key => $data) {
            if(!in_array($key, array('psw', 'pc_disclaimer'))) {
                $to_get[$key] = $data['label'];	
            }
        }

        $to_get = array_merge(
            $to_get,
            array(
                'insert_date' 	=> esc_html__('Registered on', 'pc_ml'),
                'last_access' 	=> esc_html__('Last access', 'pc_ml'),		
            )
        ); 
        
        // PC-FILTER - filter CSV export field_id => field_name array 
        $to_get = apply_filters('pc_csv_export_fields_to_get', $to_get, $fdata);
        
        
        // standard export
        if(!isset($fdata['pc_targeted_export_params'])) {
            if(is_array($fdata['pc_csv_exp_user_statuses']) && !in_array('all', $fdata['pc_csv_exp_user_statuses'])) {
                $query_args['search'] = array(
                    array(
                        array('key' => 'status', 'val' => $fdata['pc_csv_exp_user_statuses'], 'operator' => 'IN')
                    )
                );    
            }

            // in case of specific cats fetching
            if(is_array($fdata['pc_csv_exp_user_cats']) && !in_array('all', $fdata['pc_csv_exp_user_cats'])) {
                $query_args['categories'] = $fdata['pc_csv_exp_user_cats'];
            }
        }
        
        // advanced search export
        else {
            $as_params = unserialize(base64_decode($fdata['pc_targeted_export_params']));
            
            $query_args['status'] = (int)$as_params['status'];
            $query_args['search'] = (array)$as_params['search'];
        }
        
        
        // PC-FILTER - filter CSV export get_users() query parameters
        $query_args = apply_filters('pc_csv_export_users_query', $query_args, $fdata);
        $users = $this->get_users($query_args); 
        
        if(empty($users)) {
            return array(
                'status' => 'error',
                'message'=> esc_html__('No matching users found', 'pc_ml')    
            );
        }
        

        if($fdata['pc_csv_exp_export_type'] == 'csv') {		
            $fh = fopen('php://temp/maxmemory:'. (25*1024*1024), 'r+');

            // headings
            fputcsv($fh, $to_get);	  

            //data
            foreach($users as $user) {
                $sanitized = array();

                foreach($to_get as $key => $label) {
                    $val = (isset($user[$key])) ? $user[$key] : '';
                    $val = $this->data_to_human($key, $val, true, true);
                    
                    if($val === '&#10003;') {
                        $val = 'yes';
                    }   
                    $sanitized[] = $val;
                }
                fputcsv($fh, $sanitized);
            }

            rewind($fh);
            $filedata = stream_get_contents($fh);
            fclose($fh);
            
            return array(
                'status'    => 'success',
                'filedata'  => base64_encode($filedata)    
            );
        }
        

        // EXCEL
        else {
            require_once(PC_DIR .'/classes/PhpXlsxGenerator.php');
            
            $excel_data = array(
                array_values($to_get)
            ); 
            
            foreach($users as $user) {
                $user_data = array();
                
                foreach($to_get as $key => $label) {
                    $val = (isset($user[$key])) ? $user[$key] : '';
                    $val = $this->data_to_human($key, $val, true, true);
                    
                    if($val === '&#10003;') {
                        $val = 'yes';
                    }
                    
                    $user_data[] = $val; 
                }
                
                $excel_data[] = $user_data; 
            }
            
            
            $xslx_generator = new PhpXlsxGenerator;
            $xslx_generator->addSheet($excel_data);
            $filedata = $xslx_generator->__toString();
            
            return array(
                'status'    => 'success',
                'filedata'  => base64_encode($filedata)    
            );
        }
    }
    
    
    
    
    /* 
     * Validate the uploaded JSON file for Pvtc-to-pvtc import. To be used in AJAX call, handles $_FILES['pc_pvtc_import_json']
     * @return (array|string) the file contents array or the error message 
     */
    public function validate_pvtc_import_file() {
        if(
            !isset($_FILES['pc_pvtc_import_json']) || 
            !isset($_FILES['pc_pvtc_import_json']['error']) || 
            !isset($_FILES['pc_pvtc_import_json']['tmp_name']) || 
            $_FILES['pc_pvtc_import_json']['error'] !== UPLOAD_ERR_OK
        ) {
            return 'Missing uploaded file';
        }
        if(!isset($_FILES['pc_pvtc_import_json']['type']) || $_FILES['pc_pvtc_import_json']['type'] !== 'application/json') {
            return esc_html__('Wrong file type', 'pc_ml');
        }
        $contents = json_decode(file_get_contents(sanitize_text_field($_FILES['pc_pvtc_import_json']['tmp_name'])), true);


        if(!is_array($contents) || !isset($contents['main']) || !isset($contents['main']['pc_categories']) || !isset($contents['users'])) {
            return esc_html__('Wrong or corrupted JSON data', 'pc_ml');
        }
        if(empty($contents['users'])) {
            return esc_html__('No users data found', 'pc_ml');
        }
        
        
        // categories indexes validation
        $mandatory_indexes = array('name', 'slug', 'descr', 'no_pc_registr');
        $indexes_not_empty = array('name', 'slug');
        foreach($contents['main']['pc_categories'] as $cat_id => $cat_data) {
            
            foreach($mandatory_indexes as $mi) {
                if(!isset($cat_data[$mi])) {
                    return 'Category "'. $cat_id .'" is missing the "'. $mi .'" index';
                }
            }
            foreach($indexes_not_empty as $ine) {
                if(empty($cat_data[$ine])) {
                    return 'Category "'. $cat_id .'" has empty "'. $ine .'" index';
                }
            }
        }
        
        
        // user fields categories validation
        $mandatory_indexes = array_merge($this->fixed_fields, array('metas'));
        $indexes_not_empty = array('insert_date','username','psw','categories','status');
        foreach($contents['users'] as $user_id => $udata) {
            
            foreach($mandatory_indexes as $mi) {
                if(!in_array($mi, array('id', 'page_id')) && !isset($udata[$mi])) {
                    return 'User "'. $user_id .'" is missing the "'. $mi .'" index';
                }
            }
            foreach($indexes_not_empty as $ine) {
                if(empty($udata[$ine])) {
                    return 'User "'. $user_id .'" has empty "'. $ine .'" index';
                }
            }
        }
        
        return $contents;
    }
    
    
    
    
    /*
     * Handling the JSON file data array and the eventual fetched field values, performs the pvtc-to-pvtc import
     * @return (array) 
            (status => success, report => (string)) OR
            (status => error, message => (string)) 
     */
    public function pvtc_import($contents, $fdata) {
        global $wpdb, $pc_meta, $pc_wp_user;
        
        $user_cats_assign = array();
        $this->reset_import_report();
        
        // as first create categories for the assignment
        foreach($fdata['pc_pvtc_import_ocat_id'] as $key => $orig_cat_id) {
            $orig_cat_data = $contents['main']['pc_categories'][$orig_cat_id];
            $dest_cat_id = $fdata['pc_pvtc_import_dcat_id'][$key];
            
            if($dest_cat_id == 'new') {
                $nc_args = array(
                    'cat_name' => $orig_cat_data['name'], 
                    'category_description' => $orig_cat_data['descr'], 
                    'category_nicename' => $orig_cat_data['slug'],
                    'taxonomy' => 'pg_user_categories',
                );
                $dest_cat_id = wp_insert_category($nc_args);
                
                if(is_wp_error($dest_cat_id)) {
                    return array(
                        'status' => 'error',
                        'message' => esc_html__('Error creating a new user category', 'pc_ml') .': '. $new_cat_id->get_error_message(),
                        'back_to_upload' => false,
                    );
                }
                
                if($orig_cat_data['no_pc_registr']) {
                    update_term_meta($dest_cat_id, 'pg_ucat_no_registration', 1); 
                }
                $this->import_report['new_cats'][$dest_cat_id] = '<a href="'. admin_url('term.php?taxonomy=pg_user_categories&tag_ID='. $dest_cat_id) .'" title="'. esc_attr__('go to category page', 'pc_ml') .'" target="_blank">'. $orig_cat_data['name'] .'</a>';
            }
            $user_cats_assign[ $orig_cat_id ] = $dest_cat_id;
        }
        
        
        // create an array for existing users actions
        $exist_user_actions = array();
        if(is_array($fdata['pc_pvtc_import_orig_uid'])) {
            foreach($fdata['pc_pvtc_import_orig_uid'] as $key => $orig_uid) {
                $exist_user_actions[ (int)$orig_uid ] = array(
                    'target_uid'    => (int)$fdata['pc_pvtc_import_exist_uid'][$key],
                    'action'        => $fdata['pc_pvtc_import_exist_user_action'][$key]
                );   
            }
        }
        
        // import users
        foreach($contents['users'] as $orig_uid => $orig_udata) {
            $orig_username = $orig_udata['username'];
            
            // PC-FILTER - allow single user data management before pvtcontent-to-pvtcontet user import
            $orig_udata = apply_filters('pc_pvtc_pre_import_user_data', $orig_udata, $exist_user_actions);
            
            // new categories assignment
            $orig_udata['categories'] = maybe_unserialize($orig_udata['categories']);
            $new_cats = array();
            
            foreach($orig_udata['categories'] as $ou_cat_id) {
                if(isset($user_cats_assign[$ou_cat_id])) {
                    $new_cats[] = (int)$user_cats_assign[$ou_cat_id];
                }
            }
            if(empty($new_cats)) {
                $this->import_report['user_errors'][$orig_username] = esc_html__('does not have assigned categories', 'pc_ml');
            }
            
            // existing user?
            if(isset($exist_user_actions[ (int)$orig_uid ])) {
                if($exist_user_actions[ (int)$orig_uid ]['action'] == 'discard') {
                    $this->import_report['discarded_users'][$orig_uid] = $orig_username;
                    continue;
                }
                
                ### overwrite existing user data ###
                elseif($exist_user_actions[ (int)$orig_uid ]['action'] == 'override') {
                    $existing_uid = (int)$exist_user_actions[ (int)$orig_uid ]['target_uid'];
                    
                    $eui_response = $this->pvtc_import_user_override($existing_uid, $orig_udata, $new_cats);
                    if($eui_response !== true) {
                        $this->import_report['user_errors'][$orig_username] = $eui_response;
                    }
                    else {
                        $this->import_report['overridden_users'][$orig_uid] = '<a href="'. admin_url('admin.php?page=pc_user_dashboard&user='. $existing_uid) .'" title="'. esc_attr__('go to the user dashboard', 'pc_ml') .'" target="_blank">'. $orig_username .'</a>';

                        // PC-ACTION - Pvtcontent-to-pvtContent user import, overriding users data - passes existing user ID, the data array of the user to import and the categories assignment array and the class reference (NB: form data can be caught through $_POST)
                        do_action('pc_pvtc_import_overridden_user', $existing_uid, $orig_udata, $new_cats, $fdata, $this);
                    }
                    continue;
                }
                
                else {
                    $this->import_report['user_errors'][$orig_username] = esc_html__('unknown action', 'pc_ml');
                    continue;
                }
            }
            
            
            ### create new user - start with basic to take advantage of basic systems and override ###
            $new_user_args = array(
                'username'  => $orig_username,
                'email'     => $orig_udata['email'],
                'psw'       => $orig_udata['psw'],
                'categories'=> $new_cats,
            );
            
            $allow_wp_sync_fail = (is_array($orig_udata['wp_user_id'])) ? false : true;
            $new_user_id = $this->insert_user($new_user_args, $orig_udata['status'], $allow_wp_sync_fail);
            
            if(!is_numeric($new_user_id)) {
                $this->import_report['user_errors'][$orig_username] = esc_html__('Error creating new user', 'pc_ml') .' - '. $this->validation_errors;
                continue;
            }
            
            $response = $this->pvtc_import_user_override($new_user_id, $orig_udata, $new_cats, true);
            $this->import_report['new_users'][$orig_uid] = '<a href="'. admin_url('admin.php?page=pc_user_dashboard&user='. $new_user_id) .'" title="'. esc_attr__('go to the user dashboard', 'pc_ml') .'" target="_blank">'. $orig_username .'</a>';
            
            // PC-ACTION - Pvtcontent-to-pvtContent new user import - passes new user ID, the data array of the user to import and the categories assignment array and the class reference (NB: form data can be caught through $_POST)
            do_action('pc_pvtc_import_new_user', $new_user_id, $orig_udata, $new_cats, $this);
        }
        
        return array(
            'status' => 'success',
            'report' => $this->human_report(),
            'back_to_upload' => true,
        );
    }
    
    
    
    
    /*
     * Handling the existing user ID and the to-import user data array, overrides the data
     * @return (true|string) true or the error string 
     */
    private function pvtc_import_user_override($existing_uid, $orig_udata, $new_cats, $after_new_creation = false) {
        global $wpdb, $pc_meta, $pc_wp_user;
        
        $tu_query_args = array('to_get' => array('page_id', 'wp_user_id'));
        $exist_udata = $this->get_user($existing_uid, $tu_query_args);
        
        // overwrite main data
        $query_arr = array();
        foreach($this->fixed_fields as $ff) {
            if(in_array($ff, array('id', 'wp_user_id'))) {
                continue;
            }
            
            if(isset($orig_udata[$ff])) {
                
                // categories must be saved as string for the mySQL query
                if($ff == 'categories') {
                    foreach($new_cats as $key => $val) {
                        $new_cats[$key] = (string)$val;  
                    }
                    $query_arr[$ff] = serialize($new_cats);
                }
                else {
                    $query_arr[$ff] = maybe_serialize($orig_udata[$ff]);
                }
            }
        }

        $result = $wpdb->update(PC_USERS_TABLE, $query_arr, array('id' => (int)$existing_uid));
        if(!empty($wpdb->last_error)) {
            return esc_html__('Error updating main user data', 'pc_ml') .' - '. $wpdb->last_error;
        }

        // overwrite metas
        if(is_array($orig_udata['metas'])) {
            foreach($orig_udata['metas'] as $meta_name => $meta_val) {
                $pc_meta->update_meta($existing_uid, $meta_name, maybe_unserialize($meta_val));   
            }
        }


        // overwrite reserved page?
        if(isset($orig_udata['pvt_page'])) {
            wp_update_post(array(
                'ID' => (int)$exist_udata['page_id'],
                'post_content' => $orig_udata['pvt_page']['contents']
            ));
            if(is_array($orig_udata['pvt_page']['metas'])) {
                foreach($orig_udata['pvt_page']['metas'] as $meta_name => $meta_val) {
                    update_post_meta((int)$exist_udata['page_id'], $meta_name, maybe_unserialize($meta_val));  
                }
            }
        }


        // WP user sync
        if($this->wp_user_sync) {
            
            // existing user has synced user and new one hasn't - delete the sync
            if(!empty($exist_udata['wp_user_id']) && !is_array($orig_udata['wp_user_id'])) {
                $pc_wp_user->detach_wp_user($existing_uid, true, $exist_udata['wp_user_id']);
            }
                
            // otherwise create/update
            elseif(is_array($orig_udata['wp_user_id'])) {
                $wp_sync_args = array(
                    'user_login'=> $orig_udata['username'],
                    'user_email'=> $orig_udata['email'],
                    'name'      => $orig_udata['name'],
                    'surname'   => $orig_udata['surname'],
                );
                
                if(!empty($exist_udata['wp_user_id'])) {
                    $wp_sync_args['ID'] = (int)$exist_udata['wp_user_id'];
                } else {
                    $wp_sync_args['user_pass'] = $orig_udata['psw'];
                }
                $wp_user_id = wp_insert_user($wp_sync_args);

                if(!is_wp_error($wp_user_id)) {
                    foreach($orig_udata['wp_user_id'] as $user_meta_name => $user_meta_val) {
                        update_user_meta($wp_user_id, $user_meta_name, maybe_unserialize($user_meta_val));
                    }

                    if(!empty($exist_udata['wp_user_id'])) {
                        $pc_wp_user->sync_psw_to_wp($orig_udata['psw'], $wp_user_id);
                    } 
                    else {
                        $pc_wp_user->sync_wp_user($orig_udata, $wp_user_id, true);
                    }
                }
                else {
                    return esc_html__('Error creating the WP mirror user', 'pc_ml') .': '. $wp_user_id->get_error_message();  
                }
            }
        }
        
        return true;
    }
    
    
    
    
    /* 
     * Validate the uploaded CSV file for the import. To be used in AJAX call, handles $_FILES['pc_csv_import_csv']
     * @return (array|string) the csv data array or the error message 
     */
    public function validate_csv_import_file() {
        include_once('pc_form_framework.php');
        
        if(
            !isset($_FILES['pc_csv_import_csv']) || 
            !isset($_FILES['pc_csv_import_csv']['error']) || 
            !isset($_FILES['pc_csv_import_csv']['tmp_name']) || 
            $_FILES['pc_csv_import_csv']['error'] !== UPLOAD_ERR_OK
        ) {
            return 'Missing uploaded file';
        }
        if(
            !isset($_FILES['pc_csv_import_csv']['type']) || 
            ($_FILES['pc_csv_import_csv']['type'] !== 'text/csv' && $_FILES['pc_csv_import_csv']['type'] != 'application/vnd.ms-excel' && $_FILES['pc_csv_import_csv']['type'] != 'text/x-csv')
        ) {
            return esc_html__('Wrong file type', 'pc_ml');
        }
        
        $f_delimiter = $this->get_csv_delimiter(sanitize_text_field($_FILES['pc_csv_import_csv']['tmp_name']));
        if(!$f_delimiter) {
            return esc_html__('Cannot detect the CSV data delimiter', 'pc_ml'); // also detects at least a row in the file
        }
        
        $ignore_first_row = (isset($_POST['pc_csv_imp_ignore_first_row']) && $_POST['pc_csv_imp_ignore_first_row']) ? true : false;
        $csv_data = $this->get_csv_data(sanitize_text_field($_FILES['pc_csv_import_csv']['tmp_name']));
        
        $auto_psw = (isset($_POST['pc_csv_imp_create_new_psw']) && $_POST['pc_csv_imp_create_new_psw']) ? true : false;
        $form_fw  = new pc_form;
        
        // validate the minimum cols number and eventually the e-mail existence
        if($form_fw->mail_is_required && !$auto_psw) {
            $min_cols = 3;
        }
        elseif($form_fw->mail_is_required || !$auto_psw) {
            $min_cols = 2;   
        }
        else {
            $min_cols = 1;   
        }
        
        $first_row_cols_num = 0;
        $a = 0;
        foreach($csv_data as $row_num => $row_data) {
            if($ignore_first_row && !$row_num) {
                continue;   
            }
            
            $real_row_num = $row_num + 1; 
            $not_empty_cols = 0;
            $has_email = false;
            
            foreach($row_data as $rd) {
                if(!empty($rd)) {
                    $not_empty_cols++;   
                }
                if(filter_var($rd, FILTER_VALIDATE_EMAIL)) {
                    $has_email = true;   
                }
            }
            
            if($not_empty_cols < $min_cols) {
                /* translators: 1: row number. */
                return sprintf( esc_html__("CSV row %s does not have the minimum values amount", 'pc_ml'), $real_row_num);   
            }
            if($form_fw->mail_is_required && !$has_email) {
                /* translators: 1: row number. */
                return sprintf( esc_html__("CSV row %s is missing the e-mail value", 'pc_ml'), $real_row_num);
            }
            
            if(!$a) {
                $first_row_cols_num = count($row_data);
            }
            else {
                if($first_row_cols_num != count($row_data)) {
                    /* translators: 1: row number. */
                    return sprintf( esc_html__("CSV row %s has a different values number compared to the first one", 'pc_ml'), $real_row_num);
                }
            }
            $a++;
        }
        
        return $csv_data;
    }
    
    
    
    
    /* 
     * Getting the CSV fields delimiter 
     * @return (string|false)
     */
    private function get_csv_delimiter($file_path) {
        $file_obj   = new \SplFileObject($file_path);
        
        $delimiters = array("|", ",", ";", "\t");
        $results    = [];
        $check_lines= 2;
        $counter    = 0;
        
        while($file_obj->valid() && $counter <= $check_lines) {
            $line = $file_obj->fgets();
            
            foreach($delimiters as $delimiter) {
                $fields = explode($delimiter, $line);
                $fields_count = count($fields);
                
                if($fields_count > 1) {
                    if(!empty($results[$delimiter])) {
                        $results[$delimiter] += $fields_count;
                    }else {
                        $results[$delimiter] = $fields_count;
                    }
                }
            }
            $counter++;
        }
        
        if(!empty($results)) {
            $results = array_keys($results, max($results));
            return $results[0];
        }
        return false;
    }
    
    
    
    
    /* 
     * Getting the CSV data in an associative array
     * @param (int) $start_row (from zero)
     * @param (int|false) $rows - how many rows to parse (false to get all)
     */
    private function get_csv_data($file_path, $start_row = 0, $rows = false) {
        $delimiter = $this->get_csv_delimiter($file_path);
        if(!$delimiter) {
            return array();
        }
         
        $handle = fopen($file_path, "r");
        $data   = array();
        
        $a = 0;
        $tot_rows = (!$rows) ? 0 : (int)$start_row + (int)$rows;
        while(($csv_data = fgetcsv($handle, $tot_rows, $delimiter)) !== false) {
            
            if($a >= (int)$start_row && (!$rows || count($data) <= (int)$rows)) {
                $data[$a] = array_map('trim', $csv_data);
            }
            $a++;
        }
        
        return $data;
    }
    
    
    
    
    /*
     * Handling the CSV file data array, the data assignment, the eventual fetched field values and a form framework instance, performs the import
     * @return (array) 
            (status => success, report => (string)) OR
            (status => error, message => (string)) 
     */
    public function csv_import($csv_data, $data_assign, $fdata, $f_fw) {
        global $wpdb, $pc_meta, $pc_wp_user;
        
        $this->reset_import_report();
        
        
        // import users
        foreach($csv_data as $row_num => $row_data) {
            $error_subj_key = esc_html__('row', 'pc_ml') .' '. $row_num;
            
            $new_user_args = array(
                'categories' => $fdata['pc_csv_imp_user_cats'],
                'disable_pvt_page' => ($fdata['pc_csv_imp_have_pvt_pag']) ? 0 : 1,
            );
            
            $abort = false;
            foreach($data_assign as $da_row_num => $assign_field) {
                if(!isset($row_data[ (int)$da_row_num ])) {
                    /* translators: 1: column number. */
                    $this->import_report['user_errors'][$error_subj_key] = sprintf( esc_html__("Column %s is missing", 'pc_ml'), $da_row_num);
                    $abort = true;   
                }
                
                $new_user_args[$assign_field] = 
                    preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', 
                        mb_convert_encoding($row_data[$da_row_num], "UTF-8", 
                            mb_detect_encoding($row_data[$da_row_num])
                        )
                    );
            }
            if($abort) {
                continue;   
            }
            
            
            // generate password?
            if($fdata['pc_csv_imp_create_new_psw']) {
                $new_user_args['psw'] = $f_fw->generate_psw();
            }
            
            // PC-FILTER - allow single user data management before CSV user import
            $new_user_args = apply_filters('pc_csv_pre_import_user_data', $new_user_args);
            
            $new_user_id = $this->insert_user($new_user_args, 1, $allow_wp_sync_fail = false);
            if(!is_numeric($new_user_id)) {
                $this->import_report['user_errors'][$error_subj_key] = esc_html__('Error creating new user', 'pc_ml') .' - '. $this->validation_errors;
                continue;
            }
            
            $this->import_report['new_users'][$row_num] = '<a href="'. admin_url('admin.php?page=pc_user_dashboard&user='. $new_user_id) .'" title="'. esc_attr__('go to the user dashboard', 'pc_ml') .'" target="_blank">'. $new_user_args['username'] .'</a>';
            
            // PC-ACTION - CSV new user import - passes new user ID, the password and the class reference (NB: form data can be caught through $_POST)
            do_action('pc_csv_import_new_user', $new_user_id, $new_user_args['psw'], $this);
        }
        
        return array(
            'status' => 'success',
            'report' => $this->human_report(),
            'back_to_upload' => true,
        );
    }

    
    
    
    
    /*
     * Handling the import process report array, returns the human-readable format
     */
    private function human_report() {
        $report = $this->import_report;
        $key_to_name = array(
            'new_cats'          => esc_html__('categories created', 'pc_ml'),
            'new_users'         => esc_html__('new users', 'pc_ml'),
            'overridden_users'  => esc_html__('overridden users', 'pc_ml'),
            'discarded_users'   => esc_html__('discarded users', 'pc_ml'),
            'existing_users'    => esc_html__('rows have the same username or e-mail of existing users', 'pc_ml'),
            'user_errors'       => esc_html__('errors', 'pc_ml') .' 🟠',
        );
        
        $html = '
        <h3>'. strtoupper(esc_html__('Import report', 'pc_ml')) .':</h3>
        <ul>';
        
        foreach($key_to_name as $key => $name) {
            if(!isset($report[$key]) || empty($report[$key])) {
                continue;   
            }
            $html .= '<li><strong>'. strtoupper(count($report[$key]) .' '. $key_to_name[$key]) .'</strong>';
            
            if($key != 'user_errors') {
                $html .= ': '. implode(', ', $report[$key]);
            }
            else {
                foreach($report[$key] as $username => $error) {
                    $first_part = (strpos($username, esc_html__('row', 'pc_ml')) !== false) ? $username : esc_html__('User', 'pc_ml') .' "'. $username .'"';
                    $html .= '<p><strong>'. $first_part .':</strong> '. $error .'</p>';
                }
            }
            
            $html .= '</li>';
        }
        
        return $html .'</ul>';
    } 
    
}
