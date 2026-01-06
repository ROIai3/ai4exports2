<?php
// TOOLSET TO CREATE AND MANAGE USERS
if(!defined('ABSPATH')) {exit;}


class pc_users {
	// pvtContent fixed fields
	public $fixed_fields = array('id','insert_date','name','surname','username','psw','categories','email','tel','page_id','disable_pvt_page','status','wp_user_id','last_access'); 
	
	public $validation_errors = ''; // static resource for methods using validator - contains HTML code with errors
	public $wp_sync_error = ''; // static resource for WP sync errors printing - contains a string
	
	protected $user_id; // static resource storing currently managed user ID for sequential operations
	public $wp_user_sync; // flag to understand if wp-user-sync is enabled 
	
    public $wp_date_format; // (string)
	public $wp_time_format; // (string)
    public $wp_timezone; // (string)
	
    private $use_meta_join = false; // (bool) whether get_users() query uses meta table joins
    private $is_searching_metas = false;  // (bool) whether get_activities() query searches for a meta
    private $where_joins_assoc = array(); // (array) index=>meta_key association to reduce LEFT OUTER JOINs to the minimum and retrieve it in the ORDER instruction
    private $exists_joins_assoc = array(); // (array) index=>meta_key association to be used along with EXISTS condition, to reduce LEFT OUTER JOINs to the minimum
    private $multi_meta_s_join = array(); // (array) array or LEFT OUTER JOINs to be used in get_users() query
    
    private $prepared_vars = array( // WPDB prepare - store placeholders and values in precise order - guarda che mi tocca fare...
        'where' => array(),
        'limit' => array(),
    );
    
    
    
	// avoid to recall $wpdb var each time
	protected $db; 
	public function __construct() {
		$this->db = $GLOBALS['wpdb'];	
		$this->wp_user_sync = get_option('pg_wp_user_sync');
	}
		
	
    
    
	/* 
	 * USERS QUERY - fetches also User Data add-on
	 * @param (array) $args = query array - here's the legend
	 *
	 *  (int/array) user_id = specific user ID or IDs array to fetch (by default queries every user)
	 *  (int) limit = query limit (related to users) - default 100 (use -1 to fetch any)
	 *  (int) offset = query offset (related to users)
	 *  (int/array) status = user status (1 = active / 2 = disabled / 3 = pending) - by default uses false to fetch any
	 *  (int/array) categories = pc category IDs to filter users
	 *  (array) to_get = user data to fetch - by default is everything
	 *  (string) orderby = how to sort query results - default id
	 *  (string) order = sorting method (ASC / DESC) - default ASC
     
	 *  (array) search = multidimesional array of associative array, inspired by WP_query tax_query format. 
        
            - Each search block is joint with AND condition
            - supported operators (=, !=, >, <, >=, <=, IN, NOT IN, LIKE, NOT LIKE, EXISTS, NOT EXISTS)
            - Structure example:
     
            array(
                array(
                    'relation' => 'AND', // AND or OR
                    array('key'=>$field_key, 'operator'=>'=', 'val'=>$value),
                    array('key'=>$field_key, 'operator'=>'=', 'val'=>$value)
                )
            )
     
	 *  (string) custom_search = custom WHERE parameters to customize the search. Is added to the dynamically created ones
	 *  (bool) count = if true returns only query rows count
	 *
	 * @return (int/array) associative array(key=>val) for each user or query row count
	 */
	public function get_users($args = array()) {
		$supported_operators = array('=', '!=', '>', '<', '>=', '<=', 'IN', 'NOT IN', 'LIKE', 'NOT LIKE', 'EXISTS', 'NOT EXISTS');

		$def_args = array(
			'user_id'	    => false,
			'limit' 	    => 100,
			'offset' 	    => 0,
			'status'	    => false,
			'categories'    => false,
			'to_get'	    => array(), 
			'orderby'	    => 'id',
			'order'		    => 'ASC',
			'search'	    => array(),
			'custom_search' => false,
			'count'		    => false
		);
		
		// replace occurrences - should use array_replace() but avoid bad server hassles
		foreach($def_args as $key => $dv) {
			if(!isset($args[$key])) {
                $args[$key] = $dv;
            }
            
            if($key == 'search' && !is_array($args[$key])) {
                $args[$key] = array();    
            }
		}
        
        
        // reset prepared args
        foreach($this->prepared_vars as $key => $arr) {
            $this->prepared_vars[$key] = array();    
        }
        
        
		/*** where parameters ***/

        // pvtCont < 8.4 retrocompatibility
        if(is_array($args['search']) && !empty($args['search']) && isset($args['search'][0]['key'])) {
            $args['search'] = array(0 => $args['search']);
            $args['search'][0]['relation'] = (!isset($args['search_operator']) || !in_array($args['search_operator'], array('AND', 'OR'))) ? 'AND' : $args['search_operator'];   
        }
        
        
        // direct parameters to be searched with AND condition
        if(!empty($args['user_id']) || !empty($args['status']) || !empty($args['categories'])) {
            $dir_params_search = array();
            
            if(!empty($args['user_id'])) {
                $dir_params_search[] = array('key' => 'id', 'operator' => 'IN', 'val' => $args['user_id']);
            }
            if(!empty($args['status'])) {
                $dir_params_search[] = array('key' => 'status', 'operator' => 'IN', 'val' => $args['status']);
            }	
            if(!empty($args['categories'])) {
                $dir_params_search[] = array('key' => 'categories', 'operator' => 'IN', 'val' => $args['categories']);
            }
            
            $next_array_key = count($args['search']);
            $args['search'][ $next_array_key ] = $dir_params_search;
            $args['search'][ $next_array_key ]['relation'] = 'AND';
        }
        
        
        // process search 
        $where = array();
        
        foreach($args['search'] as $s) {

            $s_block_parts = array();
            foreach($s as $key => $s_part) {
                if((string)$key == 'relation') {
                    continue;    
                }
                if(in_array($s_part['operator'], array('EXISTS', 'NOT EXISTS'))) {
                    $s_part['val'] = '';        
                }
                
                if(!is_array($s_part) || !isset($s_part['key']) || !isset($s_part['operator']) || !isset($s_part['val'])) {
                    trigger_error('PrivateContent get_users() - bad search array structure - '. json_encode($s_part));
                    continue;
                }
                
                $s_block_parts[] = $this->get_where_part($s_part['key'], $s_part['operator'], $s_part['val']);
            }
            
            if(empty($s_block_parts)) {
                continue;   
            }
            
            $relation = (isset($s['relation']) && in_array($s['relation'], array('AND', 'OR'))) ? esc_sql($s['relation']) : 'AND';
            $where[] = '('. implode(' '. $relation .' ', $s_block_parts) .')';
        }
        
        $where = implode(' AND ', $where);
		
        
        // custom search code?
        if(!empty($args['custom_search'])) {
            $where = (empty($where)) ? $args['custom_search'] : '('. $where .')'. $args['custom_search'];    
        }
        
        if(empty($where)) {
            $where = '1';
        }
		
        
        
		/*** what to get ***/
		$args['to_get'] = (array)$args['to_get'];
		$meta_on_get = '';
		$final_group_by_id = '';
        
		// if counting - get only IDs
		if(!empty($args['count'])) {
            $args['to_get'] = array('id');
            $final_group_by_id = 'GROUP BY id';
        }
		elseif(empty($args['to_get']) || in_array('*', $args['to_get'])) { // check the asterisk
			$args['to_get'] = array('*');
			$this->use_meta_join = true;
            $meta_on_get = " AND pcumj.meta_key NOT IN ('". implode("','", array_map('esc_sql', $this->fixed_fields)) ."')"; // be safe even if somehow meta key is equal to fixed field
		} 
		else {
			$meta_get = array();
			$get_not_in_fixed = (empty($args['to_get']) || in_array('*', $args['to_get'])) ? array() : array_diff($args['to_get'], $this->fixed_fields);
			
			// understand if is searching a meta
			if(!$this->use_meta_join && count($get_not_in_fixed)) {
				$this->use_meta_join = true;	
			} 
			
			// if get meta - split to_get 
			if($args['to_get'][0] != '*' && count($get_not_in_fixed)) {
				$meta_get = array();
				
				foreach($args['to_get'] as $k => $val) {
					if(!in_array($val, $this->fixed_fields)) {
						$meta_get[] = $val;
						unset($args['to_get'][$k]);	
					}
				}
				
				// query part - to append to ON join
				$meta_on_get = " AND pcumj.meta_key IN ('". implode("','", array_map('esc_sql', $meta_get)) ."')"; 
			}
			else {
                $meta_on_get = " AND pcumj.meta_key NOT IN ('". implode("','", array_map('esc_sql', $this->fixed_fields)) ."')"; // be safe even if somehow meta key is equal to fixed field
            }
			
			// be sure to fetch the user ID
			if(!in_array('id', $args['to_get']) ) {
				array_unshift($args['to_get'], "id");
			}
			
			// if meta query - get meta columns
			if(!empty($get_not_in_fixed)) {
				array_push($args['to_get'], 'pcumj.meta_id', 'pcumj.meta_key', 'pcumj.meta_value');
			}
            else {
                $final_group_by_id = 'GROUP BY id';
            }
		}
		
		
        
		/*** order-by part ***/
		if(in_array($args['orderby'], $this->fixed_fields)) {
			$orderby = ' ORDER BY '. esc_sql($args['orderby'] .' '. $args['order']);
            $assoc_orderby = $orderby;
		} 
        else {
            $join_assoc_num = array_search($args['orderby'], $this->where_joins_assoc);
			
            $assoc_orderby = ' ORDER BY FIELD (pcumj'. esc_sql($join_assoc_num) .'.meta_key, "'. esc_sql($args['orderby']) .'") DESC, pcumj'. esc_sql($join_assoc_num) .'.meta_value '. esc_sql($args['order']) .', id ASC';
            $orderby = ' ORDER BY FIELD (pcumj.meta_key, "'. esc_sql($args['orderby']) .'") DESC, pcumj.meta_value '. esc_sql($args['order']) .', id ASC';
		}
		
		
		/*** limit part ***/
		if($args['limit'] === -1) {
            $args['limit'] = 9999999;
        }
		
		$limit_part = ' LIMIT %d,';
		$limit_part .= (!empty($args['count'])) ? '9999999' : '%d';
		
        $this->prepared_vars['limit'][] = absint($args['offset']);     
        if(empty($args['count'])) {
            $this->prepared_vars['limit'][] = absint($args['limit']);
        }
            
        
		/*** SETUP QUERY ***/
		$query = 'SELECT ';
		$query .= implode(',', array_map('esc_sql', array_unique($args['to_get'])));
		$query .= ' FROM ';
	
		
        
		/*** FROM management - to be as light as possible ***/
		// if not fetching meta and sorting by fixed field
		if(!$this->use_meta_join && in_array($args['orderby'], $this->fixed_fields) && !count($this->multi_meta_s_join) && empty($args['custom_search'])) {
			$query .= ' '. esc_sql(PC_USERS_TABLE) .'
				WHERE '. $where.
				$orderby .
				$limit_part;
		}
		
		// fetching meta and/or sorting by them
		else {
            
            // create JOINs related to WHERE conditions
            $where_joins = '';
            if(!empty($this->where_joins_assoc)) {
                foreach($this->where_joins_assoc as $a => $meta_key) {
                    $a = esc_sql($a);
                    $where_joins .= ' LEFT OUTER JOIN '. esc_sql(PC_META_TABLE) .' AS pcumj'. $a .' ON id = pcumj'. $a .'.user_id';    
                }
            }
            
            // create JOINs related to EXISTS WHERE conditions
            $meta_exists_joins = '';
            if(!empty($this->exists_joins_assoc)) {
                foreach($this->exists_joins_assoc as $a => $meta_key) {
                    $a = esc_sql($a);
                    $meta_exists_joins .= ' LEFT OUTER JOIN '. esc_sql(PC_META_TABLE) .' AS pcumej'. $a .' ON id = pcumej'. $a .'.user_id AND pcumej'. $a .'.meta_key = "'. esc_sql($meta_key) .'"';        
                }
            }
               
			$query .= ' (
				SELECT '. esc_sql(PC_USERS_TABLE) .'.* FROM '. esc_sql(PC_USERS_TABLE) .'
				'. $where_joins .'
                '. $meta_exists_joins .'
				WHERE '. $where .'
			   	GROUP BY id'. 
				$assoc_orderby .
				$limit_part .'
			) as users_table 
			LEFT OUTER JOIN '. esc_sql(PC_META_TABLE) .' AS pcumj ON users_table.id = pcumj.user_id '. $meta_on_get .
            $final_group_by_id .    
			$orderby .'
			LIMIT 9999999'; 
		}

        
        $wrapped_prep_vars = array_merge($this->prepared_vars['where'], $this->prepared_vars['limit']);
        
		// DEBUG - print query structure before prepare
		if(isset($_GET['pc_np_query_debug'])) {
			echo '<p>'. esc_html($query) .' (replaced with: '. esc_html(implode(' ~ ', $wrapped_prep_vars)) .')</p>';
		}

        // perform
        $prepared = $this->db->prepare(
            $query,
            $wrapped_prep_vars
        );

        
        $data = $this->db->get_results($prepared);
        
        // DEBUG - print query structure after execution
		if(isset($_GET['pc_query_debug'])) {
			echo '<p>'. esc_html($this->db->last_query) .')</p>';
		}
        
		if(empty($args['count'])) {
			if(!is_array($data)) {
                return array();
            }
			$users = array();
			
			// create array using user ID as index and managing metas
			foreach($data as $row) {
				$uid = 'u'.$row->id; // array index
				
				// flag to fixed data once
				if(!isset($users[$uid])) {	
					$new_user = true;
					$users[$uid] = array();
				} 
				else {
                    $new_user = false;
                }
				
				// fixed fields
				foreach($row as $field => $val) {
					if(in_array($field, $this->fixed_fields) && $new_user) {
						$users[$uid][$field] = ($field == 'categories') ? unserialize($val) : $val;	
					}
				}
				
				// meta
				if(isset($row->meta_key) && !empty($row->meta_key)) {
					/* PC-FILTER - add the ability to manage fetched meta value */
					$users[$uid][$row->meta_key] = apply_filters('pc_meta_val', maybe_unserialize($row->meta_value), $row->meta_key);	
				}
			}
			
			// if searching specific metas - check if some user hasn't got it and add empty array keys
			if(isset($get_not_in_fixed)) {
				foreach($users as $key => $user) {
					foreach($get_not_in_fixed as $meta) {
						if(!isset($user[$meta])) {
							$users[$key][$meta] = false;	
						}
					}
				}
			}
			
			// recreate the array, using standard numeric indexes
			$final_arr = array();
			foreach($users as $key => $val) {
				$final_arr[] = $val;	
			}
			
			return $final_arr;
		}
        
		else {
			return $this->db->num_rows;
		}	
	}

    
    
    /* utility - get query's WHERE part */
	private function get_where_part($field, $operator, $val) {
        $val = (in_array($operator, array('LIKE', 'NOT LIKE'))) ? $val : esc_sql($val);
        
        // fixed fields
        if(in_array($field, $this->fixed_fields)) {
            if(in_array($operator, array('EXISTS', 'NOT EXISTS'))) {
                return '';    
            }
            elseif($field == 'categories') {
                return $this->categories_query($val, $operator);
            } 
            else {
                return $field . $this->get_search_part($val, $operator);
            }
        }

        // user meta
        else {
            $this->use_meta_join = true;
            $this->is_searching_metas = true;

            if(in_array($operator, array('EXISTS', 'NOT EXISTS'))) {
                $exists_join_num = $this->maybe_add_exists_joins_assoc($field);
                $cond = ($operator == 'NOT EXISTS') ? 'IS NULL' : 'IS NOT NULL';
                
                return "pcumej". esc_sql($exists_join_num) .".meta_value ". $cond;    
            }
            else {
                $join_num = $this->maybe_add_where_joins_assoc($field);
                
                $meta_val_col = "pcumj". esc_sql($join_num) .".meta_value";
                if(in_array($operator, array('<', '<=', '>', '>='))) {
                    $meta_val_col = "CAST(". $meta_val_col ." AS FLOAT)";    
                }
                
                return "(pcumj". esc_sql($join_num) .".meta_key = '". esc_sql($field) ."' AND ". $meta_val_col . $this->get_search_part($val, $operator) .")";
            }
        }
    }
    
    
    
    /* Adding a record in $this->where_joins_assoc array if the meta is not already contained. Returns the related array ID */
    private function maybe_add_where_joins_assoc($meta_key) {
        $already_in = array_search($meta_key, $this->where_joins_assoc);
        if($already_in !== false) {
            return $already_in;
        }
        
        $this->where_joins_assoc[] = $meta_key;
        return $this->maybe_add_where_joins_assoc($meta_key);
    }
    
    
    
    /* Adding a record in $this->exists_joins_assoc array if the meta is not already contained. Returns the related array ID */
    private function maybe_add_exists_joins_assoc($meta_key) {
        $already_in = array_search($meta_key, $this->exists_joins_assoc);
        if($already_in !== false) {
            return $already_in;
        }
        
        $this->exists_joins_assoc[] = $meta_key;
        return $this->maybe_add_exists_joins_assoc($meta_key);
    }
    
    
    
    
	/* utility - query search block setup according to value type and operator */
	private function get_search_part($val, $operator) {
		if(in_array($operator, array('IN', 'NOT IN'))) {
			if(!is_array($val)) {
                $val = array($val);
            }
            $placeholders = array();
            
            foreach($val as $v) {
                if(is_float($v)) {
                    $placeholders = '%f';
                    $this->prepared_vars['where'][] = (float)$v;
                }
                else {
                    $placeholders[] = (is_numeric($v)) ? '%d' : '%s';
                    $this->prepared_vars['where'][] = (is_numeric($v)) ? absint($v) : (string)$v;
                }
            }
            return ' '. $operator ." (". implode(',', $placeholders) .")";
		}
		else {
            if(in_array($operator, array('<', '<=', '>', '>=')) && is_numeric($val)) {
                $placeh = (is_float($val)) ? '%f' : '%d';
                $this->prepared_vars['where'][] = (is_float($val)) ? (float)$val : absint($val);
            }
            else {
                $placeh = '%s';
                $this->prepared_vars['where'][] = (string)$val;
            }
			return ' '. $operator .' '. $placeh;
		}
	}
	
    
    
    
	/* query part for categories
	 * @param (array) $cats = categories ID array
     * @param (string) $condition - LIKE, NOT LIKE, IN, NOT IN, = or !=
     *
	 * @return (string) WHERE condition part related to these categories
	 */
	public function categories_query($cats, $condition = 'LIKE') {
		$cat_s = array();
        
        switch($condition) {
            case 'LIKE' :
            case 'IN' :
            case '=' :   
            default :
                $condition = 'LIKE';
                break;
                
            case 'NOT LIKE' :
            case 'NOT IN' :
            case '!=' :
                $condition = 'NOT LIKE';
        }
        
		foreach((array)$cats as $cat_id) {
			$cat_s[] = "categories ". $condition ." %s";
            $this->prepared_vars['where'][] = "%". absint($cat_id) ."%";
		}
		return '(('.implode(') OR (', $cat_s) . '))';
	}
	
	
	
	/* GET SINGLE USER DATA
	 * @param (int) $user_id = the user ID to match
	 * @param (array) $args = get_users query args (except user_id index)
	 * @return (bool/array) false if user is not found otherwise associative data array for the user
	 */
	public function get_user($user_id, $args = array()) {
		$args['user_id'] = $user_id; 
		$data = $this->get_users($args);
		
		if(!is_array($data) || !count($data)) {
			return false;	
		} else {
			return $data[0];
		}
	}
	
	
	/* GET SINGLE FIELD FOR A SINGLE USER
	 * @param (int) $user_id = the user ID to match
	 * @param (string) $field = field name to retreve - could be a fixed field or a meta
	 * @return (bool/mixed) false if user is not found otherwise the field value
	 */
	public function get_user_field($user_id, $field) {
		$args = array(
			'user_id' 	=> $user_id,
			'to_get'	=> array($field)
		);
		$data = $this->get_users($args);
		
		if(!is_array($data) || !count($data)) {
			return false;	
		}
		else {
			return $data[0][$field];
		}
	}
	
	
	
	/* CONVERT FETCHED DATA TO A HUMAN READABLE FORMAT 
	 * @param (string) $index = index relative to the value stored in database (could be a fixed field or a meta key)
	 * @param (mixed) $data = fetched data related to the index
	 * @param (bool) $ignore_dates = whether to ignore insert and registration dates
     * @param (bool) $ignore_opt_fields = whether to not manage values coming from radio/check/dropdown fields
	 *
	 * @return (string) value string ready to be printed
	 */
	public function data_to_human($index, $data, $ignore_dates = false, $ignore_opt_fields = false) {
		include_once('pc_form_framework.php');
		$form_fw = new pc_form;	
		
		// date WP options
 		if(is_null($this->wp_date_format))    {$this->wp_date_format = get_option('date_format');}
		if(is_null($this->wp_time_format))    {$this->wp_time_format = get_option('time_format');}
		if(is_null($this->wp_time_format))    {$this->wp_timezone = get_option('timezone_string');}
		
		
		// PC-FILTER - given the index control how data are shown in human format
		$orig_data = $data;
		$data = apply_filters('pc_data_to_human', $data, $index);
        
		if($data !== $orig_data) {
            return $data;
        }
		
		// standard cases
		if($index == 'categories' && !empty($data)) {
			
			// be sure categories taxonomy is initialized
			include_once(PC_DIR .'/main_includes/user_categories.php');
			pvtcont_user_cat_taxonomy();
			
			if(!is_array($data)) {
                $data = unserialize($data);
            }
			$term_ids = get_terms(array(
                'taxonomy'   => 'pg_user_categories',
                'include'    => $data,
                'orderby'    => 'none',
                'fields'     => 'ids',
                'hide_empty' => false,
            ));
            
            // catch translations
            $all_translated = pc_static::user_cats();
            $translated = array();
            
            foreach($term_ids as $tid) {
                if(isset($all_translated[$tid])) {
                    $translated[$tid] = $all_translated[$tid];    
                }
            }
            
			$data = implode(', ', $translated);
		}
		elseif($index == 'insert_date' && !$ignore_dates) {
			$data = '<time title="'. date_i18n($this->wp_date_format.' - '.$this->wp_time_format ,strtotime($data)) .' '. $this->wp_timezone .' timezone">'. 
				date_i18n($this->wp_date_format, strtotime($data)) .'</time>';
		}
		elseif($index == 'last_access' && !$ignore_dates) {

			if(strtotime($data) < 0 || $data == '0000-00-00 00:00:00' || $data == '1970-01-01 00:00:01') {
                $data = '<small>'. esc_html__('no access', 'pc_ml') .'</small>';
            }
			else {
				$data = '<time title="'. date_i18n($this->wp_date_format.' - '.$this->wp_time_format, strtotime($data)) .' '. $this->wp_timezone .' timezone">'. 
				pc_static::elapsed_time($data).' '. esc_html__('ago', 'pc_ml') .'</time>';	
			}
		}
		elseif($index == 'status') {
            switch((int)$data) {
                default : $data = 'unknown'; break;
                case 1  : $data = esc_html__('active', 'pc_ml'); break;
                case 2  : $data = esc_html__('disabled', 'pc_ml'); break;
                case 3  : $data = esc_html__('pending', 'pc_ml'); break;
            }
        }
        
        // select field
        elseif(!$ignore_opt_fields && isset($form_fw->fields[$index]) && in_array($form_fw->fields[$index]['type'], array('select', 'checkbox', 'radio'))) {
            $new_vals = array();
            $f_opts = $form_fw->fields[$index]['opt'];
            
            foreach((array)$data as $val) {
                $new_vals[] = (isset($f_opts[ $val ])) ? $f_opts[ $val ] : $val; 
            }
            $data = $new_vals;
        }
        
		// if field is single-opt checkbox - print a check
		elseif(isset($form_fw->fields[$index]) && $form_fw->fields[$index]['type'] == 'single_checkbox' && !empty($data)) {
			$data = '&#10003;';	
		}
		
		return (is_array($data)) ? implode(', ', $data) : $data;	
	}
	
	
	
	/* 
     * Given a ->get_users() data array, returns it with user ID as array key
     * This will destroy the array order defined by the query, but makes it way easier to be used via code
	 */
    public function uid_as_user_data_array_key($array) {
        $reidexed = array();
        if(!is_array($array)) {
            return $reidexed;    
        }
        
        foreach($array as $udata) {
            if(!isset($udata['id'])) {
                continue;   
            }
            
            $reidexed[ (int)$udata['id'] ] = $udata;
        }
        
        return $reidexed;
    }
	
	
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////
	
	
	
	
	
	/* INSERT USER - performs a basic data validation + psw strength + username and mail unicity + WP sync outcome, specific ones must be performed using the pc_insert_user_data_check filter
	 * eventually performs WP-user-sync
	 * performs meta insertion
	 *
	 * @param (array) $data = user data, associative array containing fixed ones and extra fields registered through pc_form_fields_filter
	 *
	 * fixed fields array indexes(
		  name, 		(string) max 150 chars
		  surname, 		(string) max 150 chars 
		  username, 	(string) max 150 chars - mandatory 
		  tel, 			(string) max length 20 chars
		  email, 		(string) valid e-mail max length 255 chars - if WP-sync enabled is mandatory 
		  psw 			(string) (mandatory), 
		  disable_pvt_page, (bool) 1 or 0 
		  categories, (array) containing categories ID (mandatory)
	 * )
	 * @param (int) $status = user status (1=active, 2=disabled, 3=pending)
	 * @param (bool) $allow_wp_sync_fail = whether to allow registration also if WP user sync fails
	 *
	 * @return (int/bool) the user ID if is successfully added otherwise false
	 */
	public function insert_user($data, $status = 1, $allow_wp_sync_fail = false) {
		include_once('pc_form_framework.php');
		$form_fw = new pc_form;	
		
        // backup originl values in a dedicated $_POST index
        $_POST['pc_manage_user_orig'] = $_POST;
        
		// put array elements in $_POST globval to use validator
		foreach((array)$data as $key => $val) {
            $_POST[$key] = $val;
        }
		
		// if password repeat field is empty - clone automatically (useful for import)
		if(!isset($_POST['check_psw'])) {
			$_POST['check_psw'] = (isset($_POST['psw'])) ? sanitize_text_field(wp_unslash($_POST['psw'])) : '';	
		}
		
		// form structure - mandatory registration fields
		$form_fields = array('username', 'psw', 'categories');
		$require = ($form_fw->mail_is_required) ? array('email') : array();
		
		// add $data fields
		$form_fields = array_merge($form_fields, array_keys($data));

		/* PC-FILTER - customize required fields for user registration */
		$require = apply_filters('pc_insert_user_required_fields', $require);
		
		$form_structure = array(
			'include' => array_unique($form_fields),
			'require' => array_unique($require)
		);	

        // if password field is in - use addslashes to avoid slash char removal by form validator
        if(isset($_POST['psw']) && in_array('psw', $form_structure['include'])) {
            $_POST['psw'] = addslashes(sanitize_text_field(wp_unslash($_POST['psw'])));       
            
            if(isset($_POST['check_psw'])) {
                $_POST['check_psw'] = addslashes(sanitize_text_field(wp_unslash($_POST['check_psw'])));    
            }
        }
        
		// validation structure
		$indexes = $form_fw->generate_validator($form_structure);

		// add index for disable_pvt_page
		if(in_array('disable_pvt_page', $form_fields)) { 
			$indexes[] = array('index'=>'disable_pvt_page', 'label'=>esc_html__("Disable private page", 'pc_ml'), 'type'=>'int', 'max_len'=>1);
		}
		
		/*** standard validation ***/
		$this->validation_errors = ''; // reset
		
		$is_valid = $form_fw->validate_form($indexes);
		$fdata = $form_fw->form_data;
        
        unset($_POST['check_psw']); // reset psw check trick
        
		/*** advanced/custom validations ***/
		if($is_valid) {
			// if allow WP-sync error - set global to disable debug (might broke ajax answers)
			if($allow_wp_sync_fail) {
                $GLOBALS['pvtcont_disable_debug'] = true;
            }
			
			$params = array(
				'fdata' => $fdata,
				'allow_wp_sync_fail' => $allow_wp_sync_fail
			);
			$this->specific_user_check('insert', $params);
			if(!empty($this->validation_errors)) {
                return false;
            }
			
			
			// adhere to WP username santizing (only alphanumeric characters plus these: _, space, ., -, *, and @)
			if(!validate_username($fdata['username'])) {
				$this->validation_errors .= esc_html__('Username', 'pc_ml') .' - '. esc_html__('forbidden character used', 'pc_ml');	
			}
			
			
			/* PC-FILTER - custom data validation before user insertion - pass/return HTML code for error message */
			$this->validation_errors = apply_filters('pc_insert_user_data_check', $this->validation_errors, $fdata);
			if(!empty($this->validation_errors)) {
                return false;
            }
		}
		

		// abort or create
		if(!$is_valid) {
			$this->validation_errors = $form_fw->errors;
			return false;
		}
		else {
			$this->validation_errors = '';
			
			// create user page
			global $current_user;
			$fdata = $form_fw->form_data;

			$new_entry = array();
			$new_entry['post_author'] = $current_user->ID;
			$new_entry['post_content'] = get_option('pg_pvtpage_default_content', '');
			$new_entry['post_status'] = 'publish';
			$new_entry['post_title'] = $fdata['username'];
			$new_entry['post_type'] = 'pg_user_page';
			$pvt_pag_id = wp_insert_post($new_entry, true);
			
			if(is_wp_error($pvt_pag_id)) {
				$this->debug_note(esc_html__('Error during user page creation', 'pc_ml') .': '. $pvt_pag_id->get_error_message());
				return false;
			}
			else {
				/*** add user ***/
				// prepare query array with fixed fields
				$query_arr = array();
				
				// replace with crypted psw
				if(isset($fdata['psw'])) {
                    
                    // to comply with WP, password must be registered "addslashed"
                    $fdata['psw'] = addslashes($fdata['psw']);
					$fdata['psw'] = $this->encrypt_psw($fdata['psw']); 
				}

				foreach($this->fixed_fields as $ff) {
					switch($ff) {
						case 'categories' : 
                            
                            // must be saved as string for the mySQL query
                            foreach((array)$fdata[$ff] as $key => $val) {
                                $fdata[$ff][$key] = (string)$val;  
                            }
                            
                            $val = serialize((array)$fdata[$ff]); 
                            break;
                            
                            
						case 'psw' 			: $val = $fdata['psw']; break;
						default				: $val = isset($fdata[$ff]) ? $fdata[$ff] : false; break;	
					}
					if($val !== false) {
                        $query_arr[$ff] = $val;
                    }	
				}
				$query_arr['insert_date'] = current_time('mysql');
				$query_arr['page_id'] = $pvt_pag_id;
				$query_arr['status'] = ((int)$status >= 1 && (int)$status <= 3) ? (int)$status : 1;

				// wp-user-sync
				if($this->wp_user_sync && isset($fdata['email']) && !empty($fdata['email'])) {
					include_once('wp_user_sync.php');
					
					global $pc_wp_user;	
					$wp_user_id = $pc_wp_user->sync_wp_user($fdata);
					
					if($wp_user_id) {
						$query_arr['wp_user_id'] = $wp_user_id;
						$this->wp_sync_error = '';
					}
					else {
                        $this->wp_sync_error = $pc_wp_user->pwu_sync_error;
                        if(!$allow_wp_sync_fail) {
                            return false;   
                        }
                    }
				}
                
				// insert
				$result = $this->db->insert(PC_USERS_TABLE, $query_arr);	
				
				if(!$result) {
					
                    
                    $this->debug_note(esc_html__('Error inserting user data into database', 'pc_ml') .' - '. $this->db->last_error);
					$this->validation_errors = esc_html__('Error inserting user data into database', 'pc_ml');
					return false;	 
				} 
				else {
					$user_id = $this->db->insert_id;

					// insert metas
					$this->save_meta_fields($user_id, $form_structure['include'], $fdata);
					
					/* PC-ACTION - triggered when user is added */
					do_action('pc_user_added', $user_id);
					
					return $user_id;
				}
			}
		}
	}


	
	/* UPDATE USER - performs data validation following what declared in registered pvtContent fields 
	 * and eventually psw strength + username and mail unicity + WP sync outcome, specific ones must be performed using pc_update_user_data_check filter
	 * performs meta update
	 * 
	 * @param (int) $user_id = user id to update
	 * @param (array) $data = user data, associative array containing fixed ones and extra ones regitered through pc_form_fields_filter. Check insert_user for fields legend + you can use status key
	 *
	 * @return (bool) true is successfully updated otherwise false
	 */
	public function update_user($user_id, $data) {
        include_once('pc_form_framework.php');
		$form_fw = new pc_form;	
		
		// wp-sync init
		if($this->wp_user_sync) {
			include_once('wp_user_sync.php');
			global $pc_wp_user;
			$is_wp_synced = $pc_wp_user->pvtc_is_synced($user_id);		
		}
		else {
            $is_wp_synced = false;
        }
		
        // backup originl values in a dedicated $_POST index
        $_POST['pc_manage_user_orig'] = $_POST;
        
		// put array elements in $_POST globval to use validator
		foreach((array)$data as $key => $val) {
            $_POST[$key] = $val;
        }
		
		/*** form structure ***/
		$form_fields = array();
		$require = (isset($data['email']) && $form_fw->mail_is_required) ? array('email') : array();
		
		// add $data fields
		foreach((array)$data as $key => $val) {
            $form_fields[] = $key;
        }
		
		/* PC-FILTER - customize required fields for user update */
		$require = apply_filters('pc_update_user_required_fields', $require);
		
		$form_structure = array(
			'include' => array_unique($form_fields),
			'require' => array_unique($require)
		);

		// if WP synced - ignore username
		if($this->wp_user_sync && $is_wp_synced) {
			if(($key = array_search('username', $form_structure['include'])) !== false) {
				unset($form_structure['include'][$key]);
			}	
		}
		
		// if password is empty - ignore
		if(in_array('psw', $form_structure['include']) && (!isset($data['psw']) || empty($data['psw']))) {
			if(($key = array_search('psw', $form_structure['include'])) !== false) {
				unset($form_structure['include'][$key]);
			}		
		}
		
		// if password is ok but repeat password doesn't exist - set it
		if(in_array('psw', $form_structure['include']) && !isset($data['check_psw'])) {
			$_POST['check_psw'] = $data['psw']; 	
			$data['check_psw'] = sanitize_text_field($_POST['check_psw']);		
		}

		// validation structure
		$indexes = $form_fw->generate_validator($form_structure);

		// add index for disable_pvt_page
		if(in_array('disable_pvt_page', $form_fields)) { 
			$indexes[] = array('index'=>'disable_pvt_page', 'label'=>esc_html__("Disable private page", 'pc_ml'), 'type'=>'int', 'max_len'=>1);
		}

		/*** standard validation ***/
		$is_valid = $form_fw->validate_form($indexes, array(), $user_id);
		$fdata = $form_fw->form_data;

        // to comply with WP, password must be registered "addslashed"
        if(isset($fdata['psw'])) {
            $fdata['psw'] = addslashes($fdata['psw']);
        }
        
		/*** advanced/custom validations ***/
		if($is_valid) {
			$params = array(
				'fdata'		=> $fdata,
				'user_id' 	=> $user_id,
				'wp_synced' => $is_wp_synced
			);
			$this->specific_user_check('update', $params);
			if(!empty($this->validation_errors)) {
                return false;
            }
			
			
			// adhere to WP username santizing (only alphanumeric characters plus these: _, space, ., -, *, and @)
			if(isset($fdata['username']) && !validate_username($fdata['username'])) {
				$this->validation_errors .= esc_html__('Username', 'pc_ml') .' - '. esc_html__('forbidden character used', 'pc_ml');	
			}
			
			
			/* PC-FILTER - custom data validation before user insertion - pass/return HTML code for error message */
			$this->validation_errors = apply_filters('pc_update_user_data_check', $this->validation_errors, $fdata);
			if(!empty($this->validation_errors)) {
                return false;
            }
		}
		

		// abort or update
		if(!$is_valid) {
			$this->validation_errors = $form_fw->errors;
			return false;
		}
		else {
			$this->validation_errors = '';
			
			/*** update user ***/
			// replace with crypted psw
			if(isset($fdata['psw'])) {
				$fdata['psw'] = $this->encrypt_psw($fdata['psw']); 
			}
 
			// prepare query array with fixed fields
			$query_arr = array();
			foreach($this->fixed_fields as $ff) {
				if(isset($fdata[$ff])) {
					switch($ff) {
						case 'categories' : $val = serialize((array)$fdata[$ff]); break;
						case 'psw' 		  : $val = $fdata['psw']; break; 
						default           : $val = (isset($fdata[$ff])) ? $fdata[$ff] : false; break;	
					}	
					if($val !== false) {
                        $query_arr[$ff] = $val;
                    }	
					
					// sanitize known data for saving
					if(isset($query_arr['disable_pvt_page'])) {
                        $query_arr['disable_pvt_page'] = (int)$query_arr['disable_pvt_page'];
                    }
				}
			}

			// only if there are fixed fields to save
			if(!empty($query_arr)) {
				/* PC-ACTION - triggered right before user is updated - passes user id and query array */
				do_action('pc_pre_user_update', $user_id, $query_arr);
				
				$result = $this->db->update(PC_USERS_TABLE, $query_arr,  array('id' => (int)$user_id));
			} else {
				$result = 0; // simulate "no fields updated" response
			}
			
			if($result === false) { // if data is same, returns 0. Check for false
				$this->debug_note(esc_html__('Error updating user data into database', 'pc_ml'));
				$this->validation_errors = esc_html__('Error updating user data into database', 'pc_ml');
				return false;	
			} 
			else {
				// update metas
				$this->save_meta_fields($user_id, $form_structure['include'], $fdata);
				
				// if is wp-synced
				if($this->wp_user_sync && $is_wp_synced) {
					$wp_user_id = $pc_wp_user->sync_wp_user($fdata, $is_wp_synced->ID);
				}
				
				// password has been changed and user is logged? update cookie!
				if(isset($query_arr['psw']) && isset($GLOBALS['pc_user_id']) && $GLOBALS['pc_user_id'] == $user_id) {
					
                    $remember_me = (isset($_COOKIE['pc_remember_login'])) ? true : false;
					pc_static::setcookie('pc_user', $user_id .'|||'. $query_arr['psw'], pc_static::login_cookie_duration($remember_me));	
				}
				
				
				/* PC-ACTION - triggered when user is updated - passes user id */
				do_action('pc_user_updated', $user_id);
				
				return true;
			}
		}
	}
	
	
	/* SAVE/UPDATE METAS DURING USER SAVE/UPDATE 
	 * @param (array) $user_id - id of the updated/inserted user
	 * @param (array) $fields - fields passed in the insert/update function
	 * @param (array) $fdata - fields data, fetched by simple_form_validator engine
	 */
	private function save_meta_fields($user_id, $fields, $fdata) {
		$remaining = array_diff((array)$fields, $this->fixed_fields);
		if(!count($remaining)) {
            return true;    
        }
			
        include_once('meta_manag.php');	
        $meta = new pc_meta;

        /* PC-FILTER - allow extra control over meta fields saved during user insert/update operations - pass fetched data (associative array), user ID and fields array to iterate */
		$fdata = apply_filters('pc_save_meta_fields', $fdata, $user_id, $fields);
            
        foreach($fields as $f) {
            if(in_array($f, $this->fixed_fields) || $f == 'check_psw') { // eventually skip check_psw meta
                continue;
            }

            if(!isset($fdata[$f]) || is_null($fdata[$f])) {
                $fdata[$f] = false;	
            }
            $meta->add_meta($user_id, $f, $fdata[$f]);
        }
	}
	
	
	/* CHECK MAIL UNICITY, STATUS VALUE AND WP-SYNC OUTCOME (if required)
	 * @param (string) $action = when function is called? (insert/update)
	 * @param (array) $params = associative array containing parameters used in the function
	 * @return (string) error message to stop user insertion/update or empty string
	 */
	private function specific_user_check($action, $params) {
		$fdata = $params['fdata'];
		
		// WP user sync - includes and declarations
		if($this->wp_user_sync) {
			include_once('wp_user_sync.php');
			global $pc_wp_user;
		}
		else {
            $is_wp_synced = false;
        }
		
		
		// status
		if($action == 'update' && isset($fdata['status'])) {
			if(!in_array((int)$fdata['status'], array(1,2,3))) {
				$this->validation_errors .= esc_html__('Wrong status value', 'pc_ml').'<br/>';
			}
		}
		
		// mail unicity 
		if(isset($fdata['email']) && !empty($fdata['email']) && !get_option('pg_allow_duplicated_mails')) {
			$user_id = ($action == 'update') ? $params['user_id'] : false;
			
			if($this->user_mail_exists($fdata['email'], $user_id)) {
				$this->validation_errors .= esc_html__('Another user has the same e-mail', 'pc_ml').'<br/>';
				return false;
			}
		}

		// check possible WP sync
		if($this->wp_user_sync) {
			if($action == 'insert') {
				if(!$params['allow_wp_sync_fail']) {
					if($pc_wp_user->wp_user_exists($fdata['username'], $fdata['email'])) {
						$this->validation_errors .= esc_html__('WP sync - another user has the same username or e-mail', 'pc_ml');	
						return false;
					}	
				}
			}
			else {
				if(isset($fdata['email'])) {
					$wp_user_id = ($params['wp_synced']) ? $params['wp_synced']->ID : 0; 
					if($params['wp_synced']) {
						if(!$pc_wp_user->new_mail_is_ok($wp_user_id, $fdata['email'])) {
							$this->validation_errors .= esc_html__('WP sync - another user has the same e-mail', 'pc_ml');	
							return false;	
						}
					}
				}
			}
		}
			
		return false;	
	}
	
	
	/* CHANGE USERS STATUS  (enable/activate or disable)
	 * @param (int/array) $users_id - one target user ID or ID's array
	 * @param (int) $new_status - new status to apply (1=active, 2=disabled)
	 * @return (int) number of users with changed status (zero could mean user already had that status)
	 */
	public function change_status($users_id, $new_status) {
		if(!in_array((int)$new_status, array(1, 2))) {
			$this->debug_note('wrong status value');
			return 0;
		}
		
		// get current user without new status to avoid useless changes
		$args = array(
			'user_id' 	=> (array)$users_id,
			'to_get'	=> array('id', 'status'),
			'search'	=> array(
                array(
				    array('key' => 'status', 'val' => (int)$new_status, 'operator' => '!=')
                ),
			)
		);
		$result = $this->get_users($args);
		
		$affected = array();
		foreach($result as $user) {
			if($user['status'] != (int)$new_status) {
				if($user['status'] == 3) {
					// PC-ACTION - pending user is activated (thrown just BEFORE database change) - passes user ID
					do_action('pc_user_activated', $user['id']);
				}
				
				$affected[] = $user['id'];
			}
		}
		
		// update affected ones
		if(count($affected)) {
            $users_placeh = implode( ', ', array_fill(0, count($affected), '%d'));
            $placeh_vals = array_merge(array(absint($new_status)), $affected);
            
			$result = $this->db->query( 
				$this->db->prepare(
					"UPDATE ". esc_sql(PC_USERS_TABLE) ." SET status = %d WHERE ID IN (". $users_placeh .")",
					$placeh_vals
				) 
			);
			
			foreach($affected as $uid) {

				/* PC-ACTION - triggered when user status is changed - passes user id and new status */
				do_action('pc_user_staus_changed', $uid, $new_status);
				
				/* PC-ACTION - triggered when user is updated - passes user id */
				do_action('pc_user_updated', $uid);	
			}
		}
		
		return count($affected);
	}
	
	
	/* DELETE USER (and private page and metas and WP unsync) 
	 * @param (int) $user_id - the user ID to remove 
	 * @return (bool) true if user has been deleted otherwise false
	 */
	public function delete_user($user_id) {
		if(empty((int)$user_id)) {
			$this->debug_note('invalid user ID');	
			return false;
		}
		
		// get private page ID and synced WP user id
		$udata = $this->get_user($user_id, array('to_get' => array('page_id', 'wp_user_id')));
		
		if(empty($udata)) {
			$this->debug_note('invalid user ID');	
			return false;
		}
		
		// PC-ACTION - triggered before deleting a user
		do_action('pc_pre_user_delete', $user_id);
		
		// delete WP-synced user before - to allow hooks to perform normally
		if($this->wp_user_sync && $udata['wp_user_id']) {
			include_once('wp_user_sync.php');
			global $pc_wp_user;	
			$pc_wp_user->detach_wp_user($user_id, $save_in_db = false, $udata['wp_user_id']);		
		}
		
		// delete pvtContent user
		$result = $this->db->delete(PC_USERS_TABLE, array('id' => $user_id));
		
		if(!$result) {
			$this->debug_note('error deleting user');	
			return false;
		}
		else {
			// PC-ACTION - triggered after user (and meta) deletion
			do_action('pc_deleted_user', $user_id);	
			
			// delete private page
			wp_delete_post($udata['page_id'], $force_delete = true);
			
			// delete metas
			$result = $this->db->delete(PC_META_TABLE, array('user_id' => $user_id));
			
			return true;
		}
	}
	 

    
	/////////////////////////////////////////////////////////////////////////////////////////////

	
    
	/* CHECK WHETHER E-MAIL IS ALREADY USED
	 * @param (string) $email = the user e-mail
	 * @param (int) $user_id = user_id to exclude
	 * @return (int/bool) the user ID with same e-mail or false if is unique
	 */
	public function user_mail_exists($email, $user_id = false) {
        if($user_id) {
            $prepared = $this->db->prepare(
                "SELECT id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE email = %s AND id != %d LIMIT 1", 
				$email,
                absint($user_id)
			);
        }
        else {
            $prepared = $this->db->prepare(
                "SELECT id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE email = %s LIMIT 1", 
				$email
			);
        }
        
		$val = $this->db->get_results($prepared);
		return(!empty($val)) ? $val[0]->id : false;	
	}

	
	/* password hashing system - from v7 uses WP hasing */
	public function encrypt_psw($psw) {
		return wp_hash_password($psw);
	}
	public function decrypt_psw($psw) {
		$clean = (array)unserialize(base64_decode($psw));
		return base64_decode($clean[0]);
	}
	

	/* username to ID */
	public function username_to_id($username) {

		// reduce DB load using globals
		if(!isset($GLOBALS['pvtcont_un_to_id'])) {$GLOBALS['pvtcont_un_to_id'] = array();}
		if(isset($GLOBALS['pvtcont_un_to_id'][$username])) {return $GLOBALS['pvtcont_un_to_id'][$username];}
		
		$val = $this->db->get_results(
			$this->db->prepare( "SELECT id FROM ". esc_sql(PC_USERS_TABLE) ." WHERE username = %s AND status != 0 LIMIT 1", $username)
		);

		if(!count($val)) {
			$this->debug_note('no user found with this username');
			$this->user_id = false;
			return false;
		}
		else {
			$this->user_id = (int)$val[0]->id;
			$GLOBALS['pvtcont_un_to_id'][$username] = (int)$val[0]->id;
			return (int)$val[0]->id;
		}
	}
	
	
	/* ID to username */
	public function id_to_username($user_id) {
		// reduce DB load using globals
		if(!isset($GLOBALS['pvtcont_id_to_un'])) {$GLOBALS['pvtcont_id_to_un'] = array();}
		if(isset($GLOBALS['pvtcont_id_to_un'][$user_id])) {return $GLOBALS['pvtcont_id_to_un'][$user_id];}
		
		$val = $this->db->get_results(
			$this->db->prepare("SELECT username FROM ". esc_sql(PC_USERS_TABLE) ." WHERE id = %d AND status != 0 LIMIT 1", $user_id)
		);

		if(!count($val)) {
			$this->debug_note('no user found with this id');
			return false;
		}
		else {
			$GLOBALS['pvtcont_id_to_un'][$user_id] = $val[0]->username;
			return $val[0]->username;
		}
	}	
	
	
	/* BE SURE that variable contains a user id (int value)
	 *
	 * @param (int/string) $subj = variable used to target anuser via id or username
	 * @return (int) the user id or zero
	 */
	protected function check_user_id($subj) {
		if(!filter_var($subj, FILTER_VALIDATE_INT) || isset($GLOBALS['pvtcont_check_user_id_is_username'])) {
			return (int)$this->username_to_id($subj);
		} else {
			$this->user_id = $subj;
			return $subj;
		}
	}
	
	
	/* UTILITY - use trigger_error function to track debug notes */
	protected function debug_note($message) {
		if(!isset($GLOBALS['pvtcont_disable_debug']) && defined('WP_DEBUG') && WP_DEBUG) {
			trigger_error('PrivateContent - '. esc_html($message));	
		}
		return true;
	}
}

$GLOBALS['pc_users'] = new pc_users();
