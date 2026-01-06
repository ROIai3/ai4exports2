<?php
// TOOLSET TO CREATE AND MANAGE FIELDS/FORMS
if(!defined('ABSPATH')) {exit;}


class pc_form {
	public $fields = array(); // (array) registered fields array 
	public $form_pages = 1; // (int) contains form pages number (1 = no pages)
	public $mail_is_required = false; // (bool) flag for required mail 
	
    public $form_term_id = false; // (int)optional property useful for extra operations
	public $errors = ''; // (string) form validation errors (HTML code)
	public $form_data = array(); // (array) array containing form's data (associative array(field_name => value))
	
	
	// field indexes array already used and must not be overrided with add-ons
	// useful to perform add-on validations - extended on construct with registered fields
	public $forbidden_indexes = array(
		'id', 
		'name', 
		'surname', 
		'username', 
		'psw', 
		'pc_cat', 
		'categories',
		'email', 
		'tel',
		'check_psw', 
		'insert_date',  
		'page_id', 
		'disable_pvt_page', 
		'status', 	
		'wp_user_id', 
		'last_access',
		'pc_disclaimer', 
		'pc_hnpt_1',
		'pc_hnpt_2',
		'pc_hnpt_3',
		'pc_reg_btn'
	);
	
	
	// fields ID array to discard when using wizards (eg. to hide username from reg form wizad's dropdown)
	public $no_wizard_indexes = array();
	
	
	/* INIT - setup plugin fields and whether mail is required 
	 * @param (array) $args = utility array, used to setup differently fields. Possible indexes
	 *	- use_custom_cat_name = whether to use custom category name
	 *	- strip_no_reg_cats = whether to remove categories not allowed on registration
	 *
	 */
	public function __construct($args = array()) {
        
		// check if WP user sync is required - otherwise // PC-FILTER - allows add-ons to require e-mail (by default is false) - acts only if PC doesn't require it
		$this->mail_is_required = (
            (get_option('pg_wp_user_sync') && get_option('pg_require_wps_registration')) ||
            (!get_option('pg_allow_duplicated_mails') && get_option('pg_onlymail_registr'))
        ) ? 
            true : apply_filters('pc_set_mail_required', false);
		
        
		///////////////////////
		$fist_last_name = get_option('pg_use_first_last_name');
		
        $custom_cat_name = (isset($args['use_custom_cat_name']) || isset($GLOBALS['pvtcont_custom_cat_name'])) ? trim(get_option('pg_reg_cat_label', '')) : '';
		$cat_placeh_opt = (!is_admin() && !get_option('pg_reg_multiple_cats') && !empty(get_option('pg_reg_cat_placeh'))) ? array('' => get_option('pg_reg_cat_placeh')) : array();
        $group = esc_html__('Core fields', 'pc_ml');
        
		$fields = array(
			'name' => array(
				'label' 	=> ($fist_last_name) ? esc_html__('First name', 'pc_ml') : esc_html__('Name', 'pc_ml'),
				'type' 		=> 'text',
				'subtype' 	=> '',
				'maxlen' 	=> 150,
				'opt'		=> '',
				'placeh'	=> get_option('pg_name_placeh'),
				'icon'		=> get_option('pg_name_icon'), // FontAwesome icon class
                'group'     => $group,
                'helper'    => '',
				'note' 		=> ($fist_last_name) ? esc_html__('User first name', 'pc_ml') : esc_html__('User name', 'pc_ml')
			),
			'surname' => array(
				'label' 	=> ($fist_last_name) ? esc_html__('Last name', 'pc_ml') : esc_html__('Surname', 'pc_ml'),
				'type' 		=> 'text',
				'subtype' 	=> '',
				'maxlen' 	=> 150,
				'opt'		=> '',
				'placeh'	=> get_option('pg_surname_placeh'),
				'icon'		=> get_option('pg_surname_icon'),
                'group'     => $group,
                'helper'    => '',
				'note' 		=> ($fist_last_name) ? esc_html__('User last name', 'pc_ml') : esc_html__('User name', 'pc_ml')
			),
			'username' => array(
				'label' 	=> esc_html__('Username', 'pc_ml'),
				'type' 		=> 'text',
				'subtype' 	=> '',
				'maxlen' 	=> 150,
				'opt'		=> '',
				'placeh'	=> get_option('pg_username_placeh'),
				'icon'		=> get_option('pg_username_icon'),
                'group'     => $group,
                'helper'    => '',
				'note' 		=> esc_html__('Username used for the login', 'pc_ml'),
				'sys_req' 	=> true,
			),
			'psw' => array(
				'label' 	=> esc_html__('Password', 'pc_ml'),
				'type' 		=> 'password',
				'subtype' 	=> '',
				'minlen' 	=> get_option('pg_psw_min_length', 4),
				'maxlen' 	=> 50,
				'opt'		=> '',
				'placeh'	=> get_option('pg_psw_placeh'),
				'icon'		=> get_option('pg_psw_icon'),
                'group'     => $group,
                'helper'    => (get_option('pg_show_psw_helper')) ? $this->psw_requiremens() : '',
				'note' 		=> esc_html__('Password used for the login', 'pc_ml'),
				'sys_req' 	=> true
			),
			'categories' => array(
				'label' 	=> (empty($custom_cat_name)) ? esc_html__('Category', 'pc_ml') : $custom_cat_name,
				'type' 		=> 'select',
				'subtype' 	=> '',
				'maxlen' 	=> 20,
				'opt'		=> (isset($args['strip_no_reg_cats'])) ? $cat_placeh_opt + pc_static::user_cats(true) : $cat_placeh_opt + pc_static::user_cats(),
				'placeh'	=> get_option('pg_categories_placeh'),	
				'icon'		=> get_option('pg_categories_icon'),
                'group'     => $group,
                'helper'    => '',
				'note' 		=> 'PrivateContent '. esc_html__('Categories', 'pc_ml'),
				'multiple'	=> (get_option('pg_reg_multiple_cats')) ? true : false,		
                'def_choice'=> array(),
				'sys_req' 	=> true
			),
			'email' => array(
				'label' 	=> esc_html__('E-Mail', 'pc_ml'),
				'type' 		=> 'text',
				'subtype' 	=> 'email',
				'maxlen' 	=> 255,
				'opt'		=> '',
				'placeh'	=> get_option('pg_email_placeh'),
				'icon'		=> get_option('pg_email_icon'),
				'note' 		=> esc_html__('User E-mail', 'pc_ml'),
                'group'     => $group,
                'helper'    => '',
				'sys_req' 	=> $this->mail_is_required 
			),  
			'tel' => array(
				'label' 	=> esc_html__('Telephone', 'pc_ml'),
				'type' 		=> 'text',
				'subtype' 	=> '',
				'maxlen' 	=> 20,
				'opt'		=> '',
				'placeh'	=> get_option('pg_tel_placeh'),
				'icon'		=> get_option('pg_tel_icon'),
                'group'     => $group,
                'helper'    => '',
				'note' 		=> esc_html__('User Telephone', 'pc_ml')
			),
			'pc_disclaimer' => array(
				'label' 	=> esc_html__("Disclaimer", 'pc_ml'),
				'type' 		=> 'single_checkbox',
				'subtype' 	=> '',
				'maxlen' 	=> 1,
				'opt'		=> '1',
				'check_txt'	=> strip_tags((string)get_option('pg_disclaimer_txt'), '<br><a><strong><em>'),
				'disclaimer'=> true,
                'group'     => $group,
                'helper'    => '',
				'note' 		=> esc_html__('Registration disclaimer', 'pc_ml'),
				'sys_req' 	=> true
			)
		);	
	
		// PC-FILTER - add fields to the usable ones - passes default fields structure
		$this->fields = apply_filters('pc_form_fields_filter', $fields);
		
		// PC-FILTER - allow forbidden fields extension - useful to report custom field indexes usage that must not be overrided - passes default forbidden + pc-registered fields array
		$this->forbidden_indexes = apply_filters('pc_forbidden_field_indexes', array_unique(array_merge($this->forbidden_indexes, array_keys($this->fields))));
		
		// PC-FILTER - fields ID array to discard when using wizards (eg. to hide username from reg form wizad's dropdown)
		$this->no_wizard_indexes = array_unique( (array)apply_filters('pc_no_wizard_indexes', array('username', 'psw', 'pc_disclaimer')) );
		
		return true;
	}
	
    

	/* 
	 * Returns a tweaked version of fields array, grouped by "group" key. 
     * Fields not belonging to a group will be appended in the "Ungrouped" array index
     *
     * @param (bool) $alphabet_sort - whether to sort fields by they slugs A-Z
     * @param (false|array) $custom_fields_array - whether to pass a custom fields selection
	 * @since 8.3.3
	 */
	public function get_fields_grouped($alphabet_sort = true, $custom_fields_array = false) {
        $to_return = array();
        $ungrouped = array();
        
        $to_manage = (empty($custom_fields_array)) ? $this->fields : $custom_fields_array;
        if($alphabet_sort) {
            ksort($to_manage, SORT_NATURAL);    
        }
        
        foreach($to_manage as $f_id => $f_data) {
            if(!isset($f_data['group']) || empty($f_data['group'])) {
                $ungrouped[$f_id] = $f_data;    
            }
            
            else {
                $group = $f_data['group'];
                
                if(!isset($to_return[$group])) {
                    $to_return[$group] = array();        
                }
                
                $to_return[$group][$f_id] = $f_data;
            }
        }
        
        
        // core is first
        $core_key = esc_html__('Core fields', 'pc_ml');
        if(isset($to_return[$core_key])) {
            $to_prepend = $to_return[$core_key];
            unset($to_return[$core_key]);

            $to_return = array($core_key => $to_prepend) + $to_return;
        }
        
        // not is last
        if(!empty($ungrouped)) {
            $to_return[ esc_html__('Ungrouped', 'pc_ml') ] = $ungrouped;    
        }
        
        return $to_return;
	}
    

    
	/* 
	 * Retrieves a field from plugin ones 
	 * @since 5.0
	 */
	public function get_field($field_name) {
		return (isset($this->fields[$field_name])) ? $this->fields[$field_name] : false;
	}
	
    
    
	/* 
	 * Retrieves field name from plugin ones 
	 * @since 5.0
	 */
	public function get_field_name($field) {
		return (isset($this->fields[$field])) ? $this->fields[$field]['label'] : false;
	}
	
	
    
	/* 
	 * Adds HTML5 form validation attributes
	 * @since 6.0
	 *
	 * @param (array) $field = field data
	 * @param (bool) $is_required = true if field is required
	 *
	 * @return (string) attributes to be added
	 */
	protected function html5_validation_attr($field, $is_required) {
		$atts = array();
		
		// required
		if($is_required) {
            $atts[] = 'required="required"';
        }
		
		// text types
		if($field['type'] == 'text' && isset($field['subtype'])) {
			switch($field['subtype']) {
				
				case 'int' 		: 	
				case 'float' 	: 
					$atts[] = 'min="'. (float)$field['range_from'] .'" max="'. (float)$field['range_to'] .'" step="any"';
					break;		
				
				case 'zipcode' : // 00000
					$atts[] = 'pattern="[0-9]{5}"';
					break;
					
				case 'us_tel' : // 000-000-0000
					$atts[] = 'pattern="\d{3}[\-]\d{3}[\-]\d{4}"';
					break;			
			}
		}
		
		// regex - TODO?
				
		return implode(' ', $atts);	
	}
	
    
    
	
	/* FORM CODE GENERATOR 
	 * @since 5.0
	 *
	 * @param (array) $fields = multidimensional array containing included and required fields array('include'=>array, 'require'=>array)
	 * @param (string) $custom_fields = custom HTML code to add custom fields to the form - should be LI elements
	 * @param (int) $user_id = pvtContent user ID, to populate fields with its data 
	 *
	 * @return (string) form fields UL list
	 */
	public function form_code($fields, $custom_fields = false, $user_id = false) {
		$included = $fields['include'];
		$required = $fields['require'];
		
        $fullw_f_class = 'pc_fullw_field';
		$disclaimers = '';
		
		// check form pages count
		foreach($included as $field) {
			if($field == 'custom|||page') {
				$this->form_pages++;	
			}
		}
		$paginated_class = ($this->form_pages > 1) ? 'pc_paginated_form' : '';
		
		// check texts
		$txt_count = 0;
		$texts = (isset($fields['texts']) && is_array($fields['texts'])) ? $fields['texts'] : array(); 
		
		if(!is_array($included)) {
            return false;
        }
		
		// if is specified the user id get data to fill the field
		if($user_id) {
			include_once('users_manag.php');
			$user = new pc_users;
            
			$query = $user->get_users(array(
				'user_id' => $user_id,
				'to_get' => $included
			)); 
			$ud = $query[0];
		}
		else {
            $ud = false;
        }


		// build
		$form = '';
		$form_pag = 1;
		$printed_pag = 0; 
		
        
        // prepend pagination progress bar?
        if($this->form_pages > 1 && get_option('pg_forms_pags_progress')) {
            $form .= '
            <div class="pc_form_pag_progress">
                <i></i>';
            
            for($c=1; $c<=$this->form_pages; $c++) {
                $sel_class = ($c === 1) ? 'class="pc_fpp_current pc_fpp_active"' : '';
                $form .= '<span '. $sel_class .' title="'. esc_attr__('go to page', 'pc_ml') .' '. $c .'" data-pag="'. $c .'">'. $c .'</span>';    
            }
            
            $form .= '
            </div>';
        }
        
        
        // print fields
		foreach($included as $field) {
			if($field == 'custom|||page') {
				$form_pag++;
				continue;
			}
			
            //////////////////////////////
			// fieldset with page
			if($printed_pag < $form_pag) {
				$hidden_fs_class = '';
                
                if($printed_pag) {
					$form .= '</fieldset>';
					$hidden_fs_class = 'pc_hidden_fieldset';
				}
				
				$form .= '<fieldset class="pc_form_flist pc_f_pag_'. $form_pag .' '.$paginated_class.' '. $hidden_fs_class .'">';
				$printed_pag++;
			}
			/////////////////////////////
			
            
            $classes    = array();
            $atts       = array(
                'data-fname' => $field, 
            );
            
            
			// if is a text block
			if($field == 'custom|||text' || (strlen($field) > 13 && substr($field, 0, 16) == 'custom|||text|||')) {
				if(isset($texts[$txt_count])) {
                    $classes = array('pc_form_txt_block', $fullw_f_class, 'pc_ftb_'.$txt_count);
                    $classes = $this->apply_field_classes_filter($classes, $field, $fields, $user_id);
                    
                    // WPML/polylang translation
                    if(function_exists('icl_t')) {
                        $texts[$txt_count] = icl_t('PrivateContent Forms', 'Form #'. $this->form_term_id .' - text block #'. $txt_count, $texts[$txt_count]);
                    }
                    
					$form .= '
					<section class="'. $classes .'" '. $this->apply_field_atts_filter($atts, $field, $fields, $user_id) .'>
						'. do_shortcode($texts[$txt_count]) .'
					</section>';
					
					$txt_count++;
				}
			}
            
            // if is a separator block
			if(substr($field, 0, 15) == 'custom|||sep|||') {
				$classes = array('pc_disclaimer_f_sep', 'pc_fullw_field');
                $classes = $this->apply_field_classes_filter($classes, $field, $fields, $user_id);
                
                $form .= '<section class="'. $classes .'" '. $this->apply_field_atts_filter($atts, $field, $fields, $user_id) .'></section>';
			}
			
			// normal field
			else {
				$fdata = $this->get_field($field);		
				if(!$fdata) {
                    continue;    
                }
                    
                $classes[] = 'pc_f_'. esc_attr($field); 

                // required message
                $f_is_required = (in_array($field, $required) || (isset($fdata['sys_req']) && $fdata['sys_req'])) ? true : false;
                $req_f_html = ($f_is_required) ? ' <sup class="pc_req_field">&#10033;</sup>' : '';

                // html5 validation attributes
                $html5_is_req = (get_option('pg_no_html5_validation')) ? false : true;
                $html5_valid = ($html5_is_req) ? $this->html5_validation_attr($fdata, $f_is_required) : '';

                // field type class
                if($fdata['type'] == 'text' && isset($fdata['subtype']) && !empty($fdata['subtype'])) {
                    $classes[] = 'pc_'.$fdata['subtype'] .'_subtype';
                }
                if($fdata['type'] == 'select') {
                    $classes[] = (isset($fdata['multiple']) && $fdata['multiple']) ? 'pc_multiselect' : 'pc_singleselect';
                }
                if($fdata['type'] == 'single_checkbox' && (!isset($fdata['disclaimer']) || empty($fdata['disclaimer']))) {
                    $classes[] = 'pc_single_check';        
                }
                if(
                    (isset($fdata['icon']) && !empty($fdata['icon'])) ||
                    (get_option('pg_single_psw_f_w_reveal') && $field == 'psw')
                ) {
                    $classes[] = 'pc_field_w_icon';
                }
                $classes = array_merge(array('pc_form_field', 'pc_'. esc_attr($fdata['type']) .'_ftype'), $classes);


                ////////

                
                // helper?
                $helper = (isset($fdata['helper']) && !empty($fdata['helper'])) ? '<small class="pc_f_helper">'. strip_tags($fdata['helper'], '<a><br><strong></i><em>') .'</small>' : '';

                // placeholder field attr
                $placeh = (isset($fdata['placeh']) && !empty($fdata['placeh'])) ? 'placeholder="'.$fdata['placeh'].'"' : '';

                // special case for psw field with revealer
                $icon_label = $fdata['label'];

                if(get_option('pg_single_psw_f_w_reveal') && $field == 'psw') {
                    $fdata['icon'] = 'far fa-eye';    
                    $icon_label = esc_attr__('toggle password visibility', 'pc_ml');
                }

                
                // icon
                $icon = (in_array('pc_field_w_icon', $classes)) ? '<span class="pc_field_icon" title="'. esc_attr($icon_label) .'"><i class="'. esc_attr( pc_static::fontawesome_v4_retrocomp($fdata['icon'])) .'"></i></span>' : '';

                // predefined selection
                if(in_array($fdata['type'], array('checkbox', 'select'))) {
                    $sel_opts = array();

                    if(is_array($ud) && !empty($ud[$field])) {
                        $sel_opts = (array)$ud[$field];    
                    }
                    elseif(isset($fdata['def_choice'])) {
                        $sel_opts = (array)$fdata['def_choice'];    
                    }
                }

                
                // field value 
                $f_val = $val = ($ud) ? $ud[$field] : false;
                
                // PC-FILTER - extra control over form field values - passes current user value value, field id, form composition, user id,  user values related to the form and class instance
                $f_val = apply_filters('pc_form_field_val', $f_val, $field, $fields, $user_id, $ud, $this);
                
                
                ////////


                // text types
                if($fdata['type'] == 'text') {
                    $autocomplete_val = 'off'; //($field == 'username') ? 'new-password' : 'off';
                    $maxlen_attr = (!(int)$fdata['maxlen']) ? '' : 'maxlength="'. (int)$fdata['maxlen'] .'"';
                    
                    // specific text type
                    switch($fdata['subtype']) {
                        case 'email' 	: 
                            $subtype = 'email'; 
                            break;

                        case 'url'		: 
                            $subtype = 'url'; 
                            break;

                        case 'int' : 
                        case 'float' : 	
                        case 'zipcode' : 
                            $subtype = 'number'; 
                            break;

                        case 'eu_date' :
                        case 'us_date' :
                        case 'iso_date' :
                            $subtype = 'date';
                            break;
                            
                        case '12h_time' :
                        case '24h_time' :
                            $subtype = 'time';
                            break;

                        default : 
                            $subtype = 'text'; 
                            break;
                    }

                    // convert date format into ISO for HTML5 usage
                    if(in_array($fdata['subtype'], array('iso_date', 'eu_date', 'us_date'))) {
                        $f_val = self::human_to_iso_date($f_val, $fdata['subtype']);       
                    }
                    
                    // convert time format into HH:MM for HTML5 usage
                    if(in_array($fdata['subtype'], array('12h_time', '24h_time'))) {
                        $f_val = self::human_to_db_time($f_val, $fdata['subtype']); 
                    }

                    $form .= '
                    <section class="'. $this->apply_field_classes_filter($classes, $field, $fields, $user_id) .'" '. $this->apply_field_atts_filter($atts, $field, $fields, $user_id) .'>
                        <label>'. $fdata['label'] . $req_f_html . $helper .'</label>

                        <div class="pc_field_container">
                            '. $icon .'
                            <input type="'. $subtype .'" name="'. esc_attr($field) .'" value="'. esc_attr($f_val) .'" '. $maxlen_attr .' '. $html5_valid .' '. $placeh .' autocomplete="'. $autocomplete_val .'"  />
                            '. $helper .'
                        </div>
                    </section>';		
                }

                // password type
                elseif($fdata['type'] == 'password') {	
                    if(!isset($fdata['minlen'])) {
                        $fdata['minlen'] = 0;    
                    }
                    if(!isset($fdata['show_psw_val'])) {
                        $fdata['show_psw_val'] = false;    
                    }
                    
                    $val = ($fdata['show_psw_val']) ? esc_attr($f_val) : '';
                    $html_5_valid = ($html5_is_req) ? 'required="required"' : '';
                    $maxlen_attr = (!(int)$fdata['maxlen']) ? '' : 'maxlength="'. (int)$fdata['maxlen'] .'"';
                    
                    $f_atts = 'value="'. $val .'" minlength="'. (int)$fdata['minlen'] .'" '. $maxlen_attr .' autocapitalize="off" autocomplete="new-password" autocorrect="off" '.$html_5_valid;

                    // repeat psw specific data
                    // icon
                    $rp_icon = (get_option('pg_repeat_psw_icon')) ? '<span class="pc_field_icon" title="'. esc_attr__('Repeat password', 'pc_ml') .'"><i class=" '. esc_attr( pc_static::fontawesome_v4_retrocomp(get_option('pg_repeat_psw_icon')) ) .'"></i></span>' : '';

                    $rp_has_icon_class = ($rp_icon) ? 'pc_field_w_icon' : '';
                    ////

                    $sect_classes = $this->apply_field_classes_filter($classes, $field, $fields, $user_id); 
                    $sect_atts = $this->apply_field_atts_filter($atts, $field, $fields, $user_id); 

                    $form .= '
                    <section class="'. $sect_classes .'" '. $sect_atts .'>
                        <label>
                            '. $fdata['label'] . $req_f_html . $helper .'
                        </label>
                        <div class="pc_field_container">
                            '. $icon .'
                            <input type="'. $fdata['type'] .'" name="'. $field .'" '. $placeh .' '. $f_atts .' />
                            '. $helper .'
                        </div>
                    </section>';

                    if($field == 'psw' && !get_option('pg_single_psw_f_w_reveal')) {
                        $form .= '
                        <section class="'. $sect_classes .' pc_psw_confirm '. $rp_has_icon_class .'" '. $sect_atts .'>	
                            <label>'. esc_html__('Repeat password', 'pc_ml').' '.$req_f_html.'</label>
                            <div class="pc_field_container">
                                '. $rp_icon .'
                                <input type="'. $fdata['type'] .'" name="check_'. $field .'" '. $f_atts .' placeholder="'. esc_attr( get_option('pg_repeat_psw_placeh')).'" />
                            </div>
                        </section>';
                    }
                }

                // textarea
                elseif($fdata['type'] == 'textarea') {
                    $form .= '
                    <section class="'. $this->apply_field_classes_filter($classes, $field, $fields, $user_id) .'" '. $this->apply_field_atts_filter($atts, $field, $fields, $user_id) .'>
                        <label class="pc_textarea_label">'. $fdata['label'] . $req_f_html . $helper .'</label>
                        <div class="pc_field_container">
                            <textarea name="'. esc_attr($field) .'" class="pc_textarea" '. $html5_valid .' '. $placeh .' autocomplete="off">'. str_replace('<br />', '
', $f_val) .'</textarea>
                            '. $helper .'
                        </div>
                    </section>';		
                }

                // select
                elseif($fdata['type'] == 'select') {	
                    $multiple = (isset($fdata['multiple']) && $fdata['multiple']) ? 'multiple="multiple"' : '';
                    $multi_name = ($multiple) ? '[]' : '';
                    $placeh = (isset($fdata['placeh'])) ? 'data-placeholder="'. esc_attr($fdata['placeh']) .'"' : ''; 

                    $form .= '
                    <section class="'. $this->apply_field_classes_filter($classes, $field, $fields, $user_id) .'" '. $this->apply_field_atts_filter($atts, $field, $fields, $user_id) .'>
                        <label>'. $fdata['label'] . $req_f_html . $helper .'</label>
                        <div class="pc_field_container">
                            '. $icon .'
                            <select name="'. esc_attr($field.$multi_name) .'" '. $multiple .' '. $html5_valid .' '. $placeh .' autocomplete="off">';

                                foreach((array)$fdata['opt'] as $opt_key => $opt_val) { 
                                    
                                    // optgroup support
                                    if(is_array($opt_val)) {
                                        $form .= '
                                        <optgroup label="'. esc_attr($opt_key) .'">';  
                                        
                                        foreach($opt_val as $k => $v) {
                                            $sel = (in_array($k, (array)$f_val)) ? 'selected="selected"' : false;
                                            $form .= '<option value="'. esc_attr($k) .'" '. $sel .'>'. esc_html($v) .'</option>';        
                                        }
                                        
                                        $form .= '
                                        </optgroup>';
                                    }
                                    else {
                                        $sel = (in_array($opt_key, (array)$f_val)) ? 'selected="selected"' : false;
                                        $form .= '<option value="'. esc_attr($opt_key) .'" '. $sel .'>'. esc_html($opt_val) .'</option>';
                                    }
                                }        

                    $form .= '
                            </select>
                            '. $helper .'
                        </div>
                    </section>';			
                }

                // radio
                elseif($fdata['type'] == 'radio') {	
                    $form .= '
                    <section class="'. $this->apply_field_classes_filter($classes, $field, $fields, $user_id) .'" '. $this->apply_field_atts_filter($atts, $field, $fields, $user_id) .'>
                        <label class="pc_cb_block_label">'. $fdata['label'] . $req_f_html . $helper .'</label>
                        <div class="pc_check_wrap">';

                        foreach((array)$fdata['opt'] as $opt_val => $label) { 
                            $sel = (in_array($opt_val, (array)$f_val)) ? 'checked="checked"' : false;

                            $form .= 
                                '<input type="radio" name="'. esc_attr($field) .'" value="'. esc_attr($opt_val) .'" '. $sel .' autocomplete="off" />
                                <label class="pc_check_label">'. $label .'</label>'; 
                        }
                    $form .= '
                        </div>
                    </section>';
                }
                
                // checkbox
                elseif($fdata['type'] == 'checkbox') {	
                    $form .= '
                    <section class="'. $this->apply_field_classes_filter($classes, $field, $fields, $user_id) .'" '. $this->apply_field_atts_filter($atts, $field, $fields, $user_id) .'>
                        <label class="pc_cb_block_label">'. $fdata['label'] . $req_f_html . $helper .'</label>
                        <div class="pc_check_wrap">';

                        foreach((array)$fdata['opt'] as $opt_val => $label) { 
                            $sel = (in_array($opt_val, (array)$f_val)) ? 'checked="checked"' : false;

                            $form .= 
                                '<input type="checkbox" name="'. esc_attr($field) .'[]" value="'. esc_attr($opt_val) .'" '. $sel .' autocomplete="off" />
                                <label class="pc_check_label">'. $label .'</label>'; 
                        }
                    $form .= '
                        </div>
                    </section>';
                }

                // single-option checkbox
                elseif($fdata['type'] == 'single_checkbox') {	
                    $sel = ($f_val) ? 'checked="checked"' : '';

                    $f_classes = $this->apply_field_classes_filter($classes, $field, $fields, $user_id);
                    $f_atts = $this->apply_field_atts_filter($atts, $field, $fields, $user_id);

                    if(!isset($fdata['disclaimer']) || empty($fdata['disclaimer'])) {
                        $form .= '
                        <section class="'. $f_classes .'" '. $f_atts .'>
                            <input type="checkbox" name="'. esc_attr($field) .'" value="1" '. $sel .' '. $html5_valid .' autocomplete="off" />
                            <label>'. $fdata['check_txt'] .' '.$req_f_html.'</label>
                        </section>';
                    } 
                    else {
                        $disclaimers .= '
                        <section class="pc_disclaimer_f pc_fullw_field '. $f_classes .'" '. $f_atts .'>
                            <input type="checkbox" name="'. esc_attr($field) .'" value="1" '. $sel .' '. $html5_valid .' autocomplete="off" />
                            <div class="pc_disclaimer_ftxt">'. $fdata['check_txt'] .'</div>
                        </section>';
                    }
                }


                else {
                    $sect_classes = $this->apply_field_classes_filter($classes, $field, $fields, $user_id); 
                    $sect_atts = $this->apply_field_atts_filter($atts, $field, $fields, $user_id); 
                    
                    // PC-FILTER - allow custom field types and custom codes. Passes form structure, field type, field ID, field structure, whether the field is required, eventual user ID, extra classes to be applied, extra attributes to be applied and the class object
                    
                    // @since v5.1 - @updated v8.5.0
                    $form .= (string)apply_filters('pc_custom_field_type', '', $fdata['type'], $field, $fdata, $f_is_required, $user_id, $sect_classes, $sect_atts, $this);  
                }
			}
		}
		
		if($custom_fields) {
            $form .= $custom_fields;
        }
		
		if(!empty($disclaimers)) {
			$form .= '<section class="pc_disclaimer_f_sep '. $fullw_f_class .'"></section>' . $disclaimers;	
		}
		
        $form .= '</fieldset>';
        
        
        // PC-FILTER - allow extra javascript to be outputted right after forms closing. Requires <script></script>. Passes included/required fields array, user id and class instance
		// @since v8.0 
        $form .= (string)apply_filters('pc_inline_form_js', '', $fields, $user_id, $this);  
        
		return $form;
	}
	
    
    
    
    /* 
     * APPLY PC-FILTER TO FORM FIELD SECTION CLASSES AND RETURN CODE - Passes classes array, field id, included/required fields array, user id and form class instance
     * @since 8.0
     */
    private function apply_field_classes_filter($classes, $field_id, $form_fields, $user_id) {

        if(isset($this->fields[ $field_id ]['fullw_f']) && $this->fields[ $field_id ]['fullw_f']) {
            $classes[] = 'pc_fullw_field';   
        }
        
        $classes = (array)apply_filters('pc_field_classes', $classes, $field_id, $form_fields, $user_id, $this);
        return (empty($classes)) ? '' : esc_attr(implode(' ', $classes));
    }
    
    
    
    /* 
     * APPLY PC-FILTER TO FORM FIELD SECTION ATTRIBUTES AND RETURN CODE - Passes attributes associative array, field id, included/required fields array, user id and form class instance
     * @since 8.0
     */
    private function apply_field_atts_filter($atts, $field_id, $form_fields, $user_id) {
        $atts = (array)apply_filters('pc_field_atts', $atts, $field_id, $form_fields, $user_id, $this);  
        
        $code_arr = array();
        foreach($atts as $att_name => $att_val) {
            $code_arr[] = esc_attr($att_name) .'="'. esc_attr($att_val) .'"';     
        }
        return implode(' ', $code_arr);
    }
    

	
    
	/* FIELDS DATA AGGREGATOR - given an indexes array, scan $_GET and $_POST to store form data - if not found use false 
	 * @since 5.0 - @updated v8.0
	 *
     * @param (array) $fields = field indexes array
	 * @param (bool) $stripslashes = whether to use stripslashes to get true values after WP filters
     * @param (bool) $ignore_registered_fields - whether to skip fields indexes check against registered ones
     *
	 * @return (array) associative array (index => val)
	 */
	public function get_fields_data($fields, $stripslashes = true) {
		if(!is_array($fields)) {
            return false;
        }	
		
		$return = array();
		foreach($fields as $f) {
			if(isset($_POST[$f])) {
                $return[$f] = $_POST[$f];
            }
			elseif(isset($_GET[$f])) {
                $return[$f] = $_GET[$f];
            }
			else {
                $return[$f] = false;
            }
			
			$return[$f] = (is_string($return[$f]) && $stripslashes) ? wp_unslash($return[$f]) : map_deep($return[$f], 'wp_unslash');
			  
			// if is fetching password field - get also check psw
			if($f == 'psw' && !in_array('check_psw', $fields) && isset($_POST['check_psw'])) {
				$return['check_psw'] = (is_string($_POST['check_psw']) && $stripslashes) ? sanitize_text_field(wp_unslash($_POST['check_psw'])) : sanitize_text_field($_POST['check_psw']);
			}
		}
		
		return pc_static::sanitize_val($return);
	}


	
    
	/* SIMPLE-FORM-VALIDATOR - create array indexes
	 * @since 5.0 - @updated v8.0
	 *
	 * @param (array) $form_structure = multidimensional array containing included and required fields array('include'=>array, 'require'=>array)
	 * @param (array) $custom_valid = additional validation indexes in case of extra fields
     *
	 * @return (array) validator indexes
	 */
	public function generate_validator($form_structure, $custom_valid = array()) {
		$included = (array)$form_structure['include'];
		$required = (array)$form_structure['require'];
		
		// merge the two arrays to not have missing elements in included
		$included = array_merge($included, $required);
		if(empty($included)) {
            return array();
        }

		$indexes = array();
		$a = 0;
		foreach($included as $index) {
			$fval = $this->get_field($index);
			if(!$fval) {
				continue;	
			}

			$indexes[$a]['index'] = str_replace('.', '_', $index); // fix for dots in indexes
			$indexes[$a]['label'] = urldecode((string)$fval['label']);
			
			// required
			if(in_array($index, $required) || (isset($fval['sys_req']) && $fval['sys_req'])) {
				$indexes[$a]['required'] = true;
			}
			
			// min-length
			if($fval['type'] == 'password' || ($fval['type'] == 'text' && empty($fval['subtype']))) {
				if(isset($fval['minlen'])) {$indexes[$a]['min_len'] = $fval['minlen'];}
			}
			
			// maxlenght
			if($fval['type'] == 'text' && (empty($fval['subtype']) || $fval['subtype'] == 'int')) {
				$indexes[$a]['max_len'] = $fval['maxlen'];
			}
			
			// specific types
			if($fval['type'] == 'text' && !empty($fval['subtype'])) {
				$indexes[$a]['type'] = $fval['subtype'];
			}
	
			// allowed values
			if(($fval['type'] == 'select' || $fval['type'] == 'checkbox') && !empty($fval['opt'])) {
				$target_vals = array(); 
                
                // optgroup case
                if(is_array(reset($fval['opt']))) {
                    foreach($fval['opt'] as $group => $opts) {
                        $target_vals = array_merge($target_vals, array_keys($opts));    
                    }
                }
                else {
                    $target_vals = array_keys((array)$fval['opt']);       
                }
                
                $indexes[$a]['allowed'] = $target_vals;
			}
			
			// numeric value range
			if($fval['type'] == 'text' && in_array($fval['subtype'], array('int', 'float')) && isset($fval['range_from']) && $fval['range_from'] !== '') {
				$indexes[$a]['min_val'] = (float)$fval['range_from'];
				$indexes[$a]['max_val'] = (float)$fval['range_to'];
			}
			
			// regex validation
			if(in_array($fval['type'], array('text', 'textarea')) && isset($fval['regex']) && !empty($fval['regex'])) {
				$indexes[$a]['preg_match'] = $fval['regex'];			
			}
	
			////////////////////////////
			// password check validation
			if($index == 'psw' && !get_option('pg_single_psw_f_w_reveal')) {
				// add fields check
				$indexes[$a]['equal'] = 'check_psw';
				
				// check psw validation
				$a++;
				$indexes[$a]['index'] = 'check_psw';
				$indexes[$a]['label'] = esc_html__('Repeat password', 'pc_ml');
				$indexes[$a]['maxlen'] = $fval['maxlen'];
			}
	
			$a++;	
		}
		
		if(is_array($custom_valid)) {
			$indexes = array_merge($indexes, $custom_valid);	
		}
        return $indexes;
	}
	
	
	
    
	/* VALIDATE FORM DATA - using simple_form_validator
	 * @since 5.0
	 *
	 * @param (array) $indexes = validation structure built previously
	 * @param (array) $custom_errors = array containing html strings with custom errors
	 * @param (int) $user_id = utility value to perform database checks - contains a PC user ID
	 * @param (bool) $specific_checks = whether to perform categories and username unicity checks. Useful to avoid double checks on frontend insert/update
     * @param (int|false) $form_id = form term ID (where available), used for extra operations
     * @param (string|false) $form_taxonomy = form taxonomy (where available), used for extra operations
	 *
	 * @return (bool) true if form is valid, false otherwise (errors and data can be retrieved in related obj properties)
	 */
	public function validate_form($indexes, $custom_errors = array(), $user_id = false, $specific_checks = true, $form_id = false, $form_taxonomy = false) {
		include_once('simple_form_validator.php');
		global $wpdb;
        
		$validator = new simple_fv('pc_ml');	
        $form_vals = $validator->getAllIndexVals($indexes, true);

        // is a data-update form?
        if(!$user_id && isset($GLOBALS['pc_user_id'])) {
            $user_id = $GLOBALS['pc_user_id'];    
        }
        
        
        // PC-FILTER - allows extra control over form validation rules (validator engine) - passes validator indexes, fetched form values, form id, its taxonomy, user id and form class object 
        $indexes = (array)apply_filters('pc_form_validator_indexes', (array)$indexes, $form_vals, $form_id, $form_taxonomy, $user_id, $this);
        
		$validator->formHandle((array)$indexes);
		$fdata = $validator->form_val;
        
		// clean data and save options
		foreach($fdata as $key=>$val) {
			if(is_string($val)) {
				$fdata[$key] = stripslashes($val);
			} 
			elseif(is_array($val)) {
				$fdata[$key] = array();
				foreach($val as $arr_val) {$fdata[$key][] = stripslashes($arr_val);}
			}
		}
		
		/*** special validation cases ***/
		foreach($indexes as $field) {
		
			// password strength
			if($field['index'] == 'psw') {
				$psw_strength = $this->check_psw_strength($fdata['psw']);
				if($psw_strength !== true) {
					$validator->custom_error[esc_html__("Password strength", 'pc_ml')] = $psw_strength;
				}
			}
			
			// username unicity 
			if($specific_checks && $field['index'] == 'username') {
                if($user_id) {
                    $prepared = $wpdb->prepare(
                        "SELECT id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE username = %s AND status != 0 AND id != %d LIMIT 1", 
                        trim((string)$fdata['username']),
                        absint($user_id)
                    );
                }
                else {
                    $prepared = $wpdb->prepare(
                        "SELECT id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE username = %s AND status != 0 LIMIT 1",
                        trim((string)$fdata['username'])
                    );
                }
                
				$wpdb->query($prepared);
				if($wpdb->num_rows) {
					$validator->custom_error[esc_html__("Username", 'pc_ml')] =  esc_html__("Another user already has this username", 'pc_ml');	
				}
			}
			
			// categories
			if($specific_checks && $field['index'] == 'categories' && !empty($fdata['categories'])) {
				$cats = (isset($GLOBALS['pvtcont_ignore_no_reg_cats']) || isset($GLOBALS['pvtcont_adding_user_by_admin'])) ? pc_static::user_cats(false) : pc_static::user_cats(true);
                
				foreach((array)$fdata['categories'] as $f_cat) {
					if(!isset($cats[ absint($f_cat) ])) {
						$name = $this->fields['categories']['label']; 
						$validator->custom_error[$name] =  esc_html__("One or more chosen categories are wrong", 'pc_ml');	
						break;	
					}
				}
			}
		}
		
		// wrap up
		$this->form_data = $fdata;
		$errors = $validator->getErrors('array');
		
        if(!is_array($errors)) {
            $errors = array();
        }
		
		if(!empty($custom_errors)) {
			$errors = array_merge($errors, $custom_errors);
		}
		
		// PC-FILTER - add custom errors on form validation - passes errors array, form data and user id
        $errors = apply_filters('pc_form_valid_errors', $errors, $fdata, $user_id);
		
		
		// manage errors to be a string or a list for multiple errors
		if(empty($errors) || !is_array($errors)) {
			$this->errors = '';	
		}
		else {
			$errors = (count($errors) == 1) ? '<span>'. implode('', $errors) .'</span>' : '<ul><li>'. implode('</li><li>', $errors) .'</li></ul>';
		}
		
		$this->errors = $errors;
		return (empty($this->errors)) ? true : false;		
	}


	
    
	/* PASSWORD STRENGTH VALIDATOR - made to work with simple-form-validator errors 
	 * @since 5.0
	 *
	 * @return (bool/string) true if password is ok - otherwise string containing errors
	 */
	public function check_psw_strength($psw) {
		$options = get_option('pg_psw_strength', array());
		if(!is_array($options) || count($options) == 0) {return true;}
		
		// regex validation
		$new_error = array();
		foreach($options as $opt) {
			if($opt == 'chars_digits') {
				if(!preg_match("((?=.*\d)(?=.*[a-zA-Z]))", $psw)) {$new_error[] = esc_html__('characters and digits', 'pc_ml');}	
			}
			elseif($opt == 'use_uppercase') {
				if(!preg_match("(.*[A-Z])", $psw)) {$new_error[] = esc_html__('an uppercase character', 'pc_ml');}	
			}
			elseif($opt == 'use_symbols') {
				if(!preg_match("(.*[^A-Za-z0-9])", $psw)) {$new_error[] = esc_html__('a symbol', 'pc_ml');}	
			}
		}
		if(count($new_error)) {
            $imploded = str_replace(',', ' '. esc_html__('and', 'pc_ml'), implode(', ', $new_error));
			$regex_err = esc_html__('must contain at least', 'pc_ml') .' '. $imploded;	
		}
		
		return (!isset($regex_err)) ? true : $regex_err;
	}	
	
	
    
	
	/* 
	 * HONEYPOT antispam code generator
	 * @since 5.0
	 */
	public function honeypot_generator() {
		$rand = wp_rand(100000, 1000000) + wp_rand(100000, 1000000);
		
        $clean_domain = str_replace(array('http://', 'https://', 'http://www.', 'https://www.', 'www.'), '', strtolower(site_url()));
        $clean_domain = substr(preg_replace("/[^A-Za-z0-9]/", '', $clean_domain), 0, 6);
        $guessthis = strrev((base_convert($clean_domain, 36, 10) + $rand));
        
		return '
		<div class="pc_hnpt_code">
			<input type="text" name="pc_hnpt_1" value="" autocomplete="off" />
			<input type="text" name="pc_hnpt_2" value="'. esc_attr($rand) .'" autocomplete="off" required />
			<input type="text" name="pc_hnpt_3" value="'. esc_attr($guessthis) .'" autocomplete="off" required />
		</div>'; 
	}
	
	
    
    
	/* 
	 * HONEYPOT antispam validator
	 * @since 5.0
	 */
	public function honeypot_validaton() {
		// three fields must be valid
		if(!isset($_POST['pc_hnpt_1']) || !isset($_POST['pc_hnpt_2']) || !isset($_POST['pc_hnpt_3'])) {
            return false;
        }
		
		// first field must be empty
		if(!empty($_POST['pc_hnpt_1'])) {
            return false;
        }
		
        
        $rand = absint($_POST['pc_hnpt_2']);
            
        $clean_domain = str_replace(array('http://', 'https://', 'http://www.', 'https://www.', 'www.'), '', strtolower(site_url()));
        $clean_domain = substr(preg_replace("/[^A-Za-z0-9]/", '', $clean_domain), 0, 6);
        
        $guessthis = strrev((base_convert($clean_domain, 36, 10) + $rand));
		if($guessthis != sanitize_text_field(wp_unslash($_POST['pc_hnpt_3']))) {
            return false;
        }
		return true;
	}
    
    
    
    
    /* 
	 * Return a string describing password requirements
	 * @since 8.0
	 */
    public static function psw_requiremens() {
        $min_length = get_option('pg_psw_min_length', 4);
        $strength   = get_option('pg_psw_strength', array());
        
        /* translators: 1: min length. */
        $txt = sprintf(esc_html__("Min. %s chars long", 'pc_ml'), $min_length);
        
        if(!empty($strength)) {
            $opts = array();
            foreach($strength as $opt) {
                if($opt == 'chars_digits') {
                    $opts[] = esc_html__('characters and digits', 'pc_ml');
                }
                elseif($opt == 'use_uppercase') {
                    $opts[] = esc_html__('an uppercase character', 'pc_ml');
                }
                elseif($opt == 'use_symbols') {
                    $opts[] = esc_html__('a symbol', 'pc_ml');
                }        
            }
    
            $txt .= '. '. ucfirst(esc_html__('must contain at least', 'pc_ml')) .': '. implode(', ', $opts); 
        }
        
        return $txt;
    }
    
    
    
    
    /* 
     * Generates a password satisfying requirements 
     * @since 8.0
     */
    public function generate_psw() {
        $min_length = ((int)get_option('pg_psw_min_length', 10) < 10) ? 10 : (int)get_option('pg_psw_min_length', 10); 
        $new_psw = wp_generate_password($min_length, true);

        while($this->check_psw_strength($new_psw) !== true) {
            $new_psw = wp_generate_password($min_length, true);	
        }   
        
        return $new_psw; 
    }
    
    
    
    
    /* 
     * Given an EU or US date string, returns the ISO format to be used in the "date" field
     * @since 8.0
     *
     * @param (string) $input_format = eu_date or us_date 
     * @param (string) $separator = what is used to split date parts (auto by default)
     *
     * @return (string || false)
     */
    public static function human_to_iso_date($date, $input_format, $separator = 'auto') {
        if($separator == 'auto') {
            $separator = '/[-\.\/ ]/';
        }
        $date = preg_split($separator, trim($date));
        $date = array_map('trim', $date);
        
        if($input_format == 'eu_date') {
            return (count($date) != 3 || !checkdate($date[1], $date[0], $date[2])) ? false : $date[2].'-'.$date[1].'-'.$date[0];
        }
        elseif($input_format == 'us_date') {
            return (count($date) != 3 || !checkdate($date[0], $date[1], $date[2])) ? false : $date[2].'-'.$date[0].'-'.$date[1];  
        }
        elseif($input_format == 'iso_date') {
            return (count($date) != 3 || !checkdate($date[1], $date[2], $date[0])) ? false : $date[0].'-'.$date[1].'-'.$date[2];  
        }
        return false;
    }
    
    
    /* 
     * Given an ISO date string, returns EU/US format
     * @since 8.0
     *
     * @param (string) $output_format = eu_date, us_date or iso_date
     * @param (string) $separator = what is used to join date parts
     *
     * @return (string || false)
     */
    public static function iso_to_human_date($date, $output_format, $separator = '/') {
        $date = explode('-', trim($date));
        $date = array_map('trim', $date);
        
        if(count($date) != 3 || !checkdate($date[1], $date[2], $date[0])) {
            return false;    
        }
        
        if($output_format == 'iso_date') {
            return $date[0] . $separator . $date[1] . $separator . $date[2];
        }
        elseif($output_format == 'eu_date') {
            return $date[2] . $separator . $date[1] . $separator . $date[0];
        }
        elseif($output_format == 'us_date') {
            return $date[1] . $separator . $date[2] . $separator . $date[0];
        }
        return false;
    }
    
    
    
    
    /* 
     * Given a 24 or 12h time string, returns the 24h format to be used in the "time" field
     * @since 8.5
     *
     * @param (string) $input_format = eu_date or us_date 
     * @return (string || false)
     */
    public static function human_to_db_time($time, $input_format) {
        $time = strtolower($time);
        if(strpos($time, ':') === false) {
            return false;    
        }
        
        if($input_format == '24h_time' && strpos($time, 'am') === false && strpos($time, 'pm') === false) {
            return $time;
        }
        
        $am_pm = strtolower(substr($time, 0, -2));
        $time_arr = explode(':', substr($time, 0, -3));
        $time_arr = array_map('trim', $time_arr);
        
        if($am_pm == 'pm') {
            $time_arr[0] = (int)$time_arr[0] + 12;
        }
        return implode(':', $time_arr);
    }
    
    
    /* 
     * Given the database 24hrs time format, eventually turns it into 12h one
     * @since 8.5
     *
     * @param (string) $output_format = eu_date, us_date or iso_date
     * @return (string || false)
     */
    public static function db_to_human_time($time, $output_format) {
        if($output_format == '24h_time') {
            return $time;    
        }
        
        $time = explode(':', trim($time));
        $time = array_map('trim', $time);
        
        if(count($time) != 2 || $time[0] < 0 || $time[0] >= 24 || $time[1] < 0 || $time[1] >= 60) {
            return false;    
        }
        
        return wp_date("h:i A", strtotime(implode(':', $time) .":00 UTC"));
    }
}

