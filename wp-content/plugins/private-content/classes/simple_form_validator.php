<?php
/**
 * Simple Form Validator - PHP class to speed up the boring process of form validation
 * NB: since long time, it is coded to be used in WordPress
 * 
 * @author Luca Montanari
 * @copyright 2011-2025 Luca Montanari - https://lcweb.it
 * @version 1.2.4 - 29/03/2025
 */

if(!defined('ABSPATH')) {exit;}



if(!class_exists('simple_fv')) {
class simple_fv {
	  
	private $errors = array( // (array) errors container - array with error types indexes
		'required' 			=> array(),
		
		'wrong_int'			=> array(),
		'wrong_float'		=> array(),
		'wrong_mail'		=> array(),
		'wrong_date'		=> array(),
		'wrong_time'		=> array(),
		'wrong_url'			=> array(),
		'wrong_hex'			=> array(),
		'wrong_ip'			=> array(),
		'wrong_zip'			=> array(),
		'wrong_tel'			=> array(),
		
		'wrong_type'		=> array(),
		'allowed' 			=> array(),
		'forbidden' 		=> array(),
		'not_equal' 		=> array(),
		'size' 				=> array()
	);
	
    
    private $ml_key = ''; // (string) localization key
	public $form_val = array(); // (array) form field values container (index => val)
	public $custom_error = array(); // (array) custom error container (subject => error message)
	
    
    
    public function __construct($ml_key = '') {
        $this->ml_key = $ml_key;
    }
    
    
    
    
    /* Creating an escaping function similar to wp_kses_post but allowing forms, <style> and <script> */
    public static function wp_kses_ext($content) {
        if(empty($content)) {
            return $content;   
        }
        
        $allowed_tags = wp_kses_allowed_html('post');

        $allowed_tags['style'] = array(
            'type' => array()
        );
        $allowed_tags['script'] = array(
            'type' => array(),
            'src'  => array(),
            'async' => array(),
            'defer' => array(),
        );

        $allowed_tags['form'] = array(
            'action' => array(),
            'method' => array(),
            'enctype' => array(),
            'disabled' => array(),
            'readonly' => array(),
        );
        $allowed_tags['input'] = array(
            'type' => array(),
            'name' => array(),
            'value' => array(),
            'placeholder' => array(),
            'checked' => array(),
            'disabled' => array(),
            'autocomplete'=> array(),
            'readonly' => array(),
            'size' => array(),
        );
        $allowed_tags['select'] = array(
            'name' => array(),
            'multiple' => array(),
            'autocomplete'=> array(),
            'readonly' => array(),
            'size' => array(),
        );
        $allowed_tags['option'] = array(
            'value' => array(),
            'selected' => array(),
            'disabled' => array(),
        );
        $allowed_tags['textarea'] = array(
            'name' => array(),
            'placeholder' => array(),
            'rows' => array(),
            'cols' => array(),
            'autocomplete'=> array(),
            'readonly' => array(),
            'size' => array(),
            'disabled' => array(),
        );
        $allowed_tags['button'] = array(
            'type' => array(),
            'name' => array(),
            'value' => array(),
            'autocomplete'=> array(),
            'size' => array(),
            'disabled' => array(),
        );
        $allowed_tags['label'] = array(
            'for' => array(),
        );

        foreach ($allowed_tags as $tag => $attributes) {
            $allowed_tags[$tag]['class'] = array();
            $allowed_tags[$tag]['id'] = array();
            $allowed_tags[$tag]['data-*'] = true;
        }
        
        $sanitized = wp_kses(
            htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 
            $allowed_tags
        );
        return htmlspecialchars_decode($sanitized, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
    }
    
    
    
    
	/* HANDLED DATA SANITIZED (acts recursively) */
	public static function sanitize_val($data) {
        if(!is_array($data)) {
            return ($data != wp_strip_all_tags($data)) ? self::wp_kses_ext($data) : trim(_sanitize_text_fields($data, true));
        }
        
        $sanitized = array();
        foreach($data as $key => $val) {
            if(is_array($val)) {
                $sanitized[$key] = self::sanitize_val($val);   
            }
            else {
                $sanitized[$key] = ($val != wp_strip_all_tags($val)) ? self::wp_kses_ext($val) : trim(_sanitize_text_fields($val, true));
            }
        }
        
        return $sanitized;
	}
        
	
	
    
	/* 
     * GET THE INDEX VALUE - search between $_POST, $_GET, $_REQUEST
	 * @param $index = index to search
	 */
	private function getIndexVal($index) {
		if(isset($_POST[$index])) {
            $index_val = $_POST[$index];
        }
		elseif(isset($_GET[$index])) {
            $index_val = $_GET[$index];
        }
		elseif(isset($_REQUEST[$index])) {
            $index_val = $_REQUEST[$index];
        }
		elseif(isset($_FILES[$index])) {
            $index_val = $index; // return index - will get the val during validation
        }
		else {
            $index_val = false;
        }
        
        return (isset($_FILES[$index])) ? $index_val : self::sanitize_val($index_val);
	}
	
    
    
    
    /* 
     * RETURNS ALL FIELD INDEX VALUES (associative array) without performing any validation
	 * @param (array) $indexs = array of form field indexes
     * @param (bool) $use_validation_structure = whether to elaborate using the validation array structure
	 */
	public function getAllIndexVals($indexes, $use_validation_structure = false) {
		$vals = array();
        
        foreach($indexes as $index) {
            if($use_validation_structure) {
                $index = $index['index'];    
            }
            $vals[$index] = $this->getIndexVal($index);
        }
        
        return $vals;
	}
    
    
    
    
    /* 
     * INDEX RESULT - given an array of index value validation results - return the overall response (useful for arrays)
	 */
	private function index_final_result($results) {
		$gr = true;
		foreach($results as $result) {
			if(!$result) {
                $gr = false; 
                break;
            }
		}
		return $gr;
	}
    
    
    
	
	/* 
     * HANDLE THE INDEXES ARRAY and performs the validations
	 * @param $indexes_array = array of associatives array containing the validation params
	 * @param $hide_err = hide the errors? (true/false)
     *
     * @return (array) associative array(field_index => validation response (bool))
	 */ 
	public function formHandle($indexes_array, $hide_err = false) {
        $results = array();
        
    	foreach($indexes_array as $index_val) {
			$index_results = array();
			
			$index = $index_val['index'];
			$label = ($hide_err) ? '' : $index_val['label'];
            unset($index_val['index'], $index_val['label']);
            
            
			// get the index value
			$passed_data = $this->getIndexVal($index);
			$this->form_val[$index] = $passed_data;
            
			foreach($index_val as $key => $val) {
				$validate_type = $key;
				$validate_val = $val;

				if(is_array($passed_data)) {
					foreach($passed_data as $arr_key => $single_passed_data) {
                        
                        // array counting validation
                        if($key == 'min_array' || $key == 'max_array') {
                            $index_results[$index] = $this->validate($validate_type, $validate_val, $passed_data, $label, $hide_err);
                        }
                        else {
                            $index_results[$index] = $this->validate($validate_type, $validate_val, $single_passed_data, $label, $hide_err);
                        }
					}
				}
				else {
					$index_results[$index] = $this->validate($validate_type, $validate_val, $passed_data, $label, $hide_err);
                }
			}
            
			$results[] = $this->index_final_result($index_results);
		}
		
		return $results;
    }
	
	
    
    
	/* OR CONDITION 
	 * @param label = error's subject
	 * @param error_txt = the error's text
	 * @param fields = array of associatives array containing the validation params
	 */
	public function or_cond($label, $error_txt, $fields) {
		$results = $this->formHandle($fields, true); 

		$final = false;
		foreach($results as $result) {
			if($result) {
                $final = true; 
                break;
            }	
		}
		
		if(!$final) {
            $this->custom_error[$label] = $error_txt;
        }
		return $final;		
	}

	
    
	
	/* 
     * VALIDATE FIELD VALUE
	 * @param $type = validation type
	 * @param $val = value to validate
     * @param $index_val = index value to validate
	 * @param $label = field label
	 * @param $test = validate without saving in errors array (for OR condition)
     *
     * @return (bool)
	 */
	private function validate($type, $val, $index_val, $label, $test = false) {
		
		// required
		if($type == 'required' && ($index_val === false || trim($index_val) === '')) {
            if(!$test) {
                $this->errors['required'][] = $label;
            }
            return false;
		}
		
		
		// standard types
		if($type == 'type' && !empty($index_val)) {
			if($val == 'int') {
				if(substr($index_val, 0, 1) == '0' && strlen($index_val) > 1) {
                    $index_val = substr($index_val, 1);
                }

				if(!filter_var($index_val, FILTER_VALIDATE_INT) && $index_val != '0') {
					if(!$test) {
                        $this->errors['wrong_int'][] = $label;
                    }
					return false;
                }
			}
			
			elseif($val == 'float' && !empty($index_val)) {
				$index_val = str_replace(",", ".", $index_val);
                
                if($index_val != '0.00' && !filter_var($index_val, FILTER_VALIDATE_FLOAT)) {
                    if(!$test) {
                        $this->errors['wrong_float'][] = $label;
                    }
                    return false;
                }
			}
			
			elseif($val == 'negative_int' && !empty($index_val)) {
				if(substr($index_val, 0, 1) == "-") {
                    $index_val = substr($index_val, 1);
                }
				if(substr($index_val,0,1) == '0' && strlen($index_val) > 1) {
                    $index_val = substr($index_val,1);
                }
				
				if(!filter_var($index_val, FILTER_VALIDATE_INT) && $index_val != '0') {
					if(!$test) {
                        $this->errors['wrong_int'][] = $label;
                    }
					return false;
				}	
			}
			
			elseif($val == 'email' && !empty($index_val)) {
				if(!filter_var($index_val, FILTER_VALIDATE_EMAIL)) {
					if(!$test) {
                        $this->errors['wrong_mail'][] = $label;
                    }
					return false;
				}
			}	
			
			elseif($val == 'eu_date' && !empty($index_val)) { //dd/mm/yyyy
				$date = preg_split( '/[-\.\/ ]/', trim($index_val));
				
				$not_int = true;
				foreach($date as $date_part) {
					if(preg_match('/[\D]/', $date_part)) {
                        $not_int = false; 
                        break;
                    }	
				}
				
				if(!$not_int || count($date) != 3 || !checkdate($date[1], $date[0], $date[2])) {
					if(!$test) {
                        $this->errors['wrong_date'][] = $label;
                    }
					return false;
                }
			}
			
			elseif($val == 'us_date' && !empty($index_val)) { // mm/dd/yyyy
				$date = preg_split( '/[-\.\/ ]/', trim($index_val));
				
				$not_int = true;
				foreach($date as $date_part) {
					if(preg_match('/[\D]/', $date_part)) {
                        $not_int = false; 
                        break;
                    }	
				}
				
				if(!$not_int || count($date) != 3 || !checkdate($date[0], $date[1], $date[2])) {
					if(!$test) {
                        $this->errors['wrong_date'][] = $label;
                    }
					return false;
				}
			}
			
			elseif($val == 'iso_date' && !empty($index_val)) { // yyyy/mm/dd
				$date = preg_split( '/[-\.\/ ]/', trim($index_val));
				
				$not_int = true;
				foreach($date as $date_part) {
					if(preg_match('/[\D]/', $date_part)) {
                        $not_int = false; 
                        break;
                    }	
				}
				
				if(!$not_int || count($date) != 3 || !checkdate($date[1], $date[2], $date[0])) {
					if(!$test) {
                        $this->errors['wrong_date'][] = $label;
                    }
					return false;
				}
			}
			
			elseif(in_array($val, array('12h_time', '24h_time')) && !empty($index_val)) {
				$arr = explode(':', $index_val);
                if(count($arr) != 2 || !is_numeric(trim($arr[0])) || !is_numeric(trim($arr[1]))) {
                    $this->errors['wrong_time'][] = $label;
                    return false;
                }
                
                $hour  = (int)trim($arr[0]);
				$mins  = (int)trim($arr[1]);
                $max_h = ($val == '12h_time') ? 12 : 24;
                
				if($hour < 0 || $hour >= $max_h || $mins < 0 || $mins >= 60) {
					if(!$test) {
                        $this->errors['wrong_time'][] = $label;
                    }
					return false;
				}
			}
			
			elseif($val == 'url' && !empty($index_val)) {
				if(!filter_var($index_val, FILTER_VALIDATE_URL)) {
					if(!$test) {
                        $this->errors['wrong_url'][] = $label;
                    }
					return false;
				}	
			}
			
			elseif($val == 'hex' && !empty($index_val)) {
				$pattern_3chars = '/^#[a-f0-9]{3}$/i';
				$pattern_6chars = '/^#[a-f0-9]{6}$/i';
                
				if(!preg_match($pattern_3chars, $index_val) && !preg_match($pattern_6chars, $index_val)) {
					if(!$test) {
                        $this->errors['wrong_hex'][] = $label;
                    }
					return false;
				}
			}	
			
			elseif($val == 'ipv4' && !empty($index_val)) {
				$pattern = '/^(?:(?:25[0-5]|2[0-4]\d|(?:(?:1\d)?|[1-9]?)\d)\.){3}(?:25[0-5]|2[0-4]\d|(?:(?:1\d)?|[1-9]?)\d)$/';
				if(!preg_match($pattern, $index_val)) {
					if(!$test) {
                        $this->errors['wrong_ip'][] = $label;
                    }
					return false;
				}
			}	
			
			elseif($val == 'us_zipcode' && !empty($index_val)) {
				$pattern = '/(^\d{5}$)|(^\d{5}-\d{4}$)/';
				if(!preg_match($pattern, $index_val)) {
					if(!$test) {
                        $this->errors['wrong_zip'][] = $label;
                    }
					return false;
				}
			}		
			
			
			// eg. (541) 754-3010
			elseif($val == 'us_tel' && !empty($index_val)) {
				$pattern = '/^\(?(\d{3})\)?[-\. ]?(\d{3})[-\. ]?(\d{4})$/';
				if(!preg_match($pattern, $index_val)) {
					if(!$test) {
                        $this->errors['wrong_tel'][] = $label;
                    }
					return false;
				}
			}	
		}
		
        
		// preg_match
		elseif($type == 'preg_match' && !empty($index_val)) {
			if(!preg_match($val, $index_val)) {
				if(!$test) {
                    $this->errors['wrong_type'][] = $label;
                }
				return false;
			}
		}
		
		
		// min val
		elseif($type == 'min_val' && !empty($index_val)) {
			if((float)$index_val < (float)$val) {
				if(!$test) {
					$this->custom_error[$label] = esc_html__('minimum value is', $this->ml_key).' '.$val;
				}
				return false;
			}
		}
		
		
		// max val
		elseif($type == 'max_val' && !empty($index_val)) {
			if((float)$index_val > (float)$val) {
				if(!$test) {
					$this->custom_error[$label] = esc_html__('maximum value is', $this->ml_key).' '.$val;
				}
				return false;
			}
		}
		
		
		// min lenght
		elseif($type == 'min_len') {
			if(strlen($index_val) < (int)$val) {
				if(!$test) {
					$this->custom_error[$label] = esc_html__('must be at least of', $this->ml_key) .' '.$val.' '. esc_html__('characters', $this->ml_key);
				}
				return false;
			}
		}
			
		
		// max lenght
		elseif($type == 'max_len') {
			if(strlen($index_val) > (int)$val) {
				if(!$test) {
					$this->custom_error[$label] = esc_html__('maximum', $this->ml_key).' '.$val.' '.esc_html__('characters allowed', $this->ml_key);
				}
				return false;
			}
		}
		
		
		// lenght obbligatoria
		elseif($type == 'right_len') {
			if(strlen($index_val) != (int)$val) {
				if(!$test) {
					$this->custom_error[$label] = esc_html__('must be long', $this->ml_key).' '.$val.' '.esc_html__('characters', $this->ml_key);
				}
				return false;
			}
		}
		
		
		// allowed
		if($type == 'allowed' && !empty($index_val)) {
			if(!in_array($index_val, $val)) {
				if(!$test) {
                    $this->errors['allowed'][] = $label;
                }
				return false;
			}
		}	
		
		
		// forbidden
		if($type == 'forbidden' && !empty($index_val)) {
			if(in_array($index_val, $val)) {
				if(!$test) {
                    $this->errors['forbidden'][] = $label;
                }
				return false;
			}
		}
			
			
		// equal to other field
		if($type == 'equal' && !empty($index_val)) {
			$equal_val = $this->getIndexVal($val);	
			if($index_val != $equal_val) {
				if(!$test) {
                    $this->errors['not_equal'][] = $label;
                }
				return false;
			}
		}
		
		
		// min array count
		if($type == 'min_array') {
			if(!is_array($index_val) || count($index_val) < $val) {
				if(!$test) {
					$this->custom_error[$label] = esc_html__('Select', $this->ml_key).' '.$val.' '.esc_html__('options', $this->ml_key);
				}
				return false;	
			}
		}
		
		
		// max array count
		if($type == 'max_array' && is_array($index_val)) {
			if(count($index_val) > $val) {
				if(!$test) {
					$this->custom_error[$label] = esc_html__('Maximum', $this->ml_key).' '.$val.' '.esc_html__('options allowed', $this->ml_key);
				}
				return false;	
			}
		}
		
		
		///////////////////////////////////////////////////
		
        
		// upload required
		if($type == 'ul_required' && $val == true) {
			if(absint($_FILES[$index_val]['error']) > 0) {
				if(!$test) {
                    $this->errors['required'][] = $label;
                }
				return false;
			}
		}
		
		// min filesize
		if($type == 'min_filesize' && absint($_FILES[$index_val]['error']) <= 0) {
			$filesize = absint($_FILES[$index_val]["size"]) / 1024;
			if($filesize < $val) {
				if(!$test) {
                    $this->errors['size'][] = $label;
                }
				return false;
			}
		}
		
		// max filesize
		if($type == 'max_filesize' && absint($_FILES[$index_val]['error']) <= 0) {
			$filesize = absint($_FILES[$index_val]["size"]) / 1024;
			if($filesize > $val) {
				if(!$test) {
                    $this->errors['size'][] = $label;
                }
				return false;
			}	
		}
		
		// file mimetype
		if($type == 'mime_type' && absint($_FILES[$index_val]['error']) <= 0) {
            $file_type = wp_check_filetype(basename($_FILES[$index_val]['name']));

			if(!is_array($file_type) || !in_array($file_type['type'], $val)) {
				if(!$test) {
                    $this->errors['wrong_type'][] = $label;
                }
				return false;
			}
		}
        
        
        return true;
	}
    
    
    
	
	/* 
     * ERROR TRANSLITTERATION 
     */
	private function errorTranslate($type, $label_array) {
		$message = ' - ';
		$plural = (count($label_array) > 1) ? true : false;
        
		switch($type) {
			case 'required' : 
				$message .= ($plural) ? esc_html__('are required', $this->ml_key) : esc_html__('is required', $this->ml_key);
				break;
			
			case 'wrong_int' : 
				$message .= ($plural) ? esc_html__('are not valid integers', $this->ml_key) : esc_html__('is not a valid integer', $this->ml_key);
				break;
				
			case 'wrong_float' : 
				$message .= ($plural) ? esc_html__('are not valid floating numbers', $this->ml_key) : esc_html__('is not a valid floating number', $this->ml_key);
				break;	
				
			case 'wrong_mail' : 
				$message .= ($plural) ? esc_html__('are not valid e-mail addresses', $this->ml_key) : esc_html__('is not a valid e-mail address', $this->ml_key);
				break;		
				
			case 'wrong_date' : 
				$message .= ($plural) ? esc_html__('are not valid dates', $this->ml_key) : esc_html__('is not a valid date', $this->ml_key);
				break;		
				
			case 'wrong_time' : 
				$message .= ($plural) ? esc_html__('are not valid times', $this->ml_key) : esc_html__('is not a valid time', $this->ml_key);
				break;
				
			case 'wrong_url' : 
				$message .= ($plural) ? esc_html__('are not valid urls', $this->ml_key) : esc_html__('is not a valid url', $this->ml_key);
				break;	
				
			case 'wrong_hex' : 
				$message .= ($plural) ? esc_html__('are not valid hexadecimal colors', $this->ml_key) : esc_html__('is not a valid hexadecimal color', $this->ml_key);
				break;	
			
			case 'wrong_ip' : 
				$message .= ($plural) ? esc_html__('are not valid IP addresses', $this->ml_key) : esc_html__('is not a valid IP address', $this->ml_key);
				break;	
				
			case 'wrong_zip' : 
				$message .= ($plural) ? esc_html__('are not valid ZIP codes', $this->ml_key) : esc_html__('is not a valid ZIP code', $this->ml_key);
				break;
				
			case 'wrong_tel' : 
				$message .= ($plural) ? esc_html__('are not valid telephone numbers', $this->ml_key) : esc_html__('is not a valid telephone number', $this->ml_key);
				break;		
					
			case 'wrong_type' : 
                $message .= esc_html__('invalid data inserted', $this->ml_key);
				break;	
				
			case 'allowed' : 
				$message .= ($plural) ? esc_html__('values are not between the allowed', $this->ml_key) : esc_html__('value is not between the allowed', $this->ml_key);
				break;
				
			case 'forbidden' : 
				$message .= ($plural) ? esc_html__('values are between the forbidden', $this->ml_key) : esc_html__('values is between the forbidden', $this->ml_key);
				break;
				
			case 'not_equal' : 
                $message .= esc_html__("the value doesn't match", $this->ml_key); 
				break;
				
			case 'size' : 
                $message .= esc_html__('file size is wrong', $this->ml_key);
				break;						
		}	
		
		return $message;
	}
	
	
    
    
	/* ERROR CREATOR */
	public function getErrors($type = 'string') {
		$errors_array = $this->errors;
		
		// validator errors
		foreach($errors_array as $err_type => $labels) {
			$labels = array_unique($labels);
			if(implode(', ', $labels) != '') {
				$errors[] = implode(', ', $labels) . $this->errorTranslate($err_type, $labels);	
			}
		}
		
		// custom message
		foreach($this->custom_error as $subj => $txt) {
			$errors[] = $subj . ' - ' . $txt;		
		}
		
		if(isset($errors)) {
			if($type == 'string') {
                return implode(' <br/> ', $errors);
            } 
			else {
                return $errors;
            }
		}
		else {
			return false;	
		}
	}
}
} // if class_exists() end 