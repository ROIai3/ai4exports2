<?php
// RESTRICTIONS WIZARD - IMPLEMENTATION AND TOOLSET TO SET / GET RESTRICTIONS
if(!defined('ABSPATH')) {exit;}


class pc_restr_wizard {
	
	// restriction's option structure
	private $restr_structure = array(
		'cont_hide_allow' 	=> array(), // contents hiding system - allowed users
		'cont_hide_block' 	=> array(), // contents hiding system - blocked users
		
		'comm_hide_allow' 	=> array(), // comments hiding system - allowed users
		'comm_hide_block' 	=> array(), // comments hiding system - blocked users
		
		'redirect_allow' 	=> array(), // redirect system - allowed users
		'redirect_block'	=> array(), // redirect system - blocked users

        'block_woo_sell'    => array(), /* NFPCF */
		'lb_on_open'		=> 'inherit', 	// lightbox on opening - empty or LB instance ID
	);
	
	
	// keys of final restriction's array 
	private $final_restr_structure = array('cont_hide', 'comm_hide', 'redirect', 'lb_on_open');


	// post parents restrictions - to be used on recursive mode
	private $parents_restr = array();
	
	// parent terms restriction - to be used on recursive mode
	private $terms_restr = array();

	// uses to cache found restrictions (to limit recurring queries and server processes)
	private $restr_db = array(
		'post' => array(),
		'term' => array()
	);

	// cache elaborated restriction's tree - used for helper
	private $man_restr_db = array(
		'post' => array(),
		'term' => array()
	);
	
	
	// term data - used only in terms list and "edit term" screen
	private $term_data = false;
	
	
	// last restriction array tested by user_passes_restr() - useful to know what blocks user
	public $last_matched_restr = array('allow' => array(), 'block' => array());
	
	
	/////////////////////////////////////////////////////////////
	
	
	/* initialization - setup hooks */
	function __construct() {

		### taxonomies implementation ###
		foreach(pc_static::affected_tax() as $tax) {
		
			// add the fields to the affected taxonomies
			add_action($tax.'_add_form_fields', array($this, 'terms_form_implementation'));
			add_action($tax.'_edit_form_fields', array($this, 'terms_form_implementation'));
			
			// save fields
			add_action('created_'.$tax, array($this, 'term_fields_save'));
			add_action('edited_'.$tax, array($this, 'term_fields_save'));
			
			// terms list - add column
			add_filter('manage_edit-'.$tax.'_columns', array($this, 'postNterms_list_pc_col_head'));
			add_filter('manage_'.$tax.'_custom_column', array($this, 'postNterms_list_pc_col_txt'), 10, 3);
		}	
		
		// clean database on term's deletion
		add_action('deleted_term_taxonomy', array($this, 'clean_db_on_terms_remove'));
		
				
		### post types implementation ###
		add_action('admin_init', array($this, 'pt_metabox_setup')); 
		add_action('save_post', array($this, 'post_fields_save'), 10); // use 10 to allow restrictions cache to be executed after
		
		
		### WP pointer tooltip ###
		add_action('admin_enqueue_scripts', array($this, 'wp_pointer_load'), 1000);
	}
	
    
    
    /*
     * Simply applies WP filter to $this->restr_structure 
     * @param (string) $subj_type = term or post
	 * @param (int) $subj_id = term/post ID
     */
    private function apply_restr_structure_filter($subj_type, $subj_id) {
        
        // PC-FILTER - allows restriction wizard fields structure manipulation - passe ssubj type (post/term) and its ID
        $this->restr_structure = apply_filters('pc_restr_wizard_structure', $this->restr_structure, $subj_type, $subj_id);
    }
	
	
	
	/* Save restrictions - if value isn't found - set it to null 
	 * @param (string) $subj_type = term or post
	 * @param (int) $subj_id = term/post ID
     * @param (bool) $is_qer_update - whether save function is used by the ajax handler for quick restrictions editor in posts list
	 */
	public function save_restrictions($subj_type, $subj_id, $is_qer_update = false) {
		if(!$is_qer_update && (!isset($_POST['pc_rw_nonce']) || !pc_static::verify_nonce($_POST['pc_rw_nonce'], 'pc_rw'))) {
			$GLOBALS['pvtcont_skip_restr_cache'] = true;
			return false;
		}
        
		$fetched_data = array();
        $this->apply_restr_structure_filter($subj_type, $subj_id);
		
		foreach($this->restr_structure as $key => $def_val) {
			if(!isset($_POST['pc_'.$key])) {
				$fetched_data[$key] = $def_val;	
			}
			else {
				$val = pc_static::sanitize_val($_POST['pc_'.$key]);
				
				// check array values	
				if(!in_array($key, array('lb_on_open', 'block_woo_sell'))) {
					if(!is_array($val)) {
						$fetched_data[$key] = $def_val;		
						continue;
					}
					
					// if "all" is in the array - discard the rest
					if(in_array('all', $val) && count($val) > 1) {
						$val = array('all');	
					}
				}
				
				$fetched_data[$key] = $val;	
				if(is_array($fetched_data[$key])) {
					sort($fetched_data[$key]);
				}
			}
		}
        
        
		// redirect fields - check for changes and setup globals to avoid useless queries
		$redir_allow_check = explode(',', pc_static::sanitize_val($_POST['pc_redirect_allow_check']));
		sort($redir_allow_check);
		
		$redir_block_check = explode(',', pc_static::sanitize_val($_POST['pc_redirect_block_check']));
		sort($redir_block_check);

		if($fetched_data['redirect_allow'] !== $redir_allow_check || $fetched_data['redirect_block'] !== $redir_block_check) {		
			$GLOBALS['pvtcont_restr_cache_cmd'] = array(
				'cmd' 		=> 'update',
				'subj' 		=> $subj_type,
				'subj_id' 	=> $subj_id,
				'restr_data'=> array(
					'allow' => $fetched_data['redirect_allow'],
					'block' => $fetched_data['redirect_block']
				)
			);
		}
		else {
			$GLOBALS['pvtcont_skip_restr_cache'] = true;
		}
        
		// save
		if($subj_type == 'term') {
            update_term_meta($subj_id, 'pc_restrictions', $fetched_data);
		} else {
			update_post_meta($subj_id, 'pg_restrictions', $fetched_data);
		}
		
        // PC-ACTION - triggered when a quick restriction is updated
        do_action('pc_qe_restr_wiz_in_list_updated', $subj_type, $subj_id, $fetched_data);
        
		return $fetched_data;
	}
	
	
	
	/* Can current user pass a restriction? Pass a and re
	 * @param (array) $restr - full restriction (with inheritances) 
	 	array(
	 		'self' 			=> array('allow' => restrictions, 'block' => restrictions),
			'post_*ID*' 	=> array('allow' => restrictions, 'block' => restrictions),
			'term_*ID*' 	=> array('allow' => restrictions, 'block' => restrictions),
	 *	)
	 *	
	 * @return (int) the pc_user_check() result across restrictions
	 */
	public function user_passes_restr($restr) {
		if(!is_array($restr) || empty($restr)) {
            return 1;
        }
		$result = 1;
			
		foreach($restr as $subj => $restrictions) {
			if(empty($restrictions) || !isset($restrictions['allow'])) {
                continue;
            }
			
			$allow = $restrictions['allow'];
			$block = isset($restrictions['block']) ? $restrictions['block'] : array(); 
			
			$this->last_matched_restr = array(
				'allow' => $allow, 
				'block' => $block
			);
            
			$response = pc_user_check($allow, $block, true);
			if($response !== 1) {
				$result = $response;
				break;	
			}
		}
		
		return $result;
	}
	
	
	/////////////////////////////////////////////////////////////
	
	
	
	/* Get term restrictions array */
	public function get_term_restr($term_id = false) {
		if(empty($term_id)) {
            return $this->restr_structure;
        }
		
		// PC < 6 - retrocompatibility
        $data = pc_static::retrocomp_get_term_meta($term_id, 'pc_restrictions', "pc_term_". $term_id ."_restr");

		if(!$data) {
			$data = array(
				'cont_hide_allow'	=> explode(',', get_option("taxonomy_". $term_id ."_pg_cats", '')),
				'redirect_allow'	=> explode(',', get_option("taxonomy_". $term_id ."_pg_redirect", '')),	
			);
			
			if(isset($data['redirect_allow'][0]) && empty($data['redirect_allow'][0])) {
                $data['redirect_allow'] = array();
            }
			if(isset($data['cont_hide_allow'][0]) && empty($data['cont_hide_allow'][0])) {
                $data['cont_hide_allow'] = array();
            }
        }
        if(!is_array($data)) {
            $data = array();    
        }
		
        
		$restr = $this->restr_structure;
		foreach($restr as $key => $val) {
			if(isset($data[$key])) {
				$restr[$key] = $data[$key];
			}
		}
        
        // debug
        if(isset($_GET['pc_restr_debug'])) {
            var_dump('term '. (string)$term_id, $restr);   
        }
		
		return $restr;
	}
	
    
	
	/* Get post restrictions array */
	public function get_post_restr($post_id = false) {
		if(empty($post_id)) {
            return $this->restr_structure;
        }
		
		// PC < 6 - retrocompatibility
		$data = get_post_meta($post_id, 'pg_restrictions', true);
        
        if(empty($data)) {
			$data = array(
				'redirect_allow'	=> (array)get_post_meta($post_id, 'pg_redirect', true),
				'comm_hide_allow'	=> (array)get_post_meta($post_id, 'pg_hide_comments', true),	
			);
			
			if(isset($data['redirect_allow'][0]) && empty($data['redirect_allow'][0])) {
                $data['redirect_allow'] = array();
            }
			if(isset($data['comm_hide_allow'][0]) && empty($data['comm_hide_allow'][0])) {
                $data['comm_hide_allow'] = array();
            }
		}
        if(!is_array($data)) {
            $data = array();    
        }
        
		
		$restr = $this->restr_structure;
		foreach($restr as $key => $val) {
			if(isset($data[$key])) {
				$restr[$key] = $data[$key];
			} 
		}
		
        // debug
        if(isset($_GET['pc_restr_debug'])) {
            var_dump('post '. (string)$post_id, $restr);   
        }
        
		return $restr;
	}
	
	
	/* Chain restrictions - eg. add parent/term restrictions to post ones or even setup post ones to be chained
	 * chain only if compatible with existing restrictions (eg. if stored restr allows any user or only unlogged ones - don't chain anything)
	 *
	 * @param (array) $stored_restr = element's restrictions (stored and chained) already checked
	 * @param (array) $to_be_chained = restrictions array to be chained
	 * @param (string) $tbc_subj = term or post or self
	 * @param (int) $tbc_subj_id = term/post ID
	 *
	 * @return (array) a filtered restrictions array (non-chainable arrays are returned as empty array)
	 */
	private function chain_restrictions($stored_restr, $to_be_chained, $tbc_subj, $tbc_subj_id = false) {
		if(empty($stored_restr)) {
			$stored_restr = $this->restr_structure;
		}

		if($tbc_subj != 'self') {

			// create an unique array for each restriction type
			$wrapped = array();
			foreach($stored_restr as $restr_type => $restr_arr) {			
				
				$wrapped[$restr_type] = array();
				foreach((array)$restr_arr as $key => $val) {
					
					if($restr_type != 'lb_on_open') { // normal restrictions
						$wrapped[$restr_type] = array_merge($wrapped[$restr_type], (array)$val);
					}
					else {
						$wrapped[$restr_type][] = $val;	
					}
				}
			}
			
			// filter	
			$tbc = array();
			foreach($wrapped as $key => $val) {
			
                if($key != 'lb_on_open') { // normal restrictions
	           
				    // non chainable if self is "all" or contains only "unlogged"
					$tbc[$key] = (!in_array('all', $val) && (count($val) > 1 || !in_array('unlogged', $val))) ? $tbc[$key] = $to_be_chained[$key] : array(); 
				}
				else {
					$tbc[$key] = (end($val) == 'inherit') ? $to_be_chained[$key] : '';
				}
			}
		}
		else {
			$tbc = $to_be_chained;	
		}
		
		// chain
		$tbc_key = ($tbc_subj != 'self') ? $tbc_subj.'_'.$tbc_subj_id : 'self';
		foreach($tbc as $key => $val) {
			if(!empty($val)) {
				
				// fix for lb on open - if no value was set before
				if(!is_array($stored_restr[$key])) {
					$stored_restr[$key] = array();	
				}
				
				$stored_restr[$key][$tbc_key] = $val;	
			}
		}

		return $stored_restr;
	}
	
	
	
	/* Get full restrictions tree for a specific entity (post or term)
	 *
	 * structure: array(
	 		'cont_hide' => array(
				'self' 			=> array('allow' => restrictions, 'block' => restrictions), -> self restrictions
				'post_*ID*' 	=> array('allow' => restrictions, 'block' => restrictions), -> parent post restrictions
				'term_*ID*' 	=> array('allow' => restrictions, 'block' => restrictions), -> associated term restrictions
			),
			
			...
			
			'lb_on_open' => array(
				'self' 			=> behavior, self lb on open behavior
				'post_*ID*' 	=> behavior, -> parent post lb on open behavior
				'term_*ID*' 	=> behavior, -> associated term lb on open behavior
			),
		)
	 *	
	 *
	 * @param (string) $subj_type = term or post
	 * @param (int) $subj_id = term/post ID
	 * @param (object|false) $term_data = term data retrieved by get_term_by() and required to manage chainings - internally, clas sets directle the property
	 *
	 * @return (array) restrictions array (multidimentional array )
	 */
	public function get_entity_full_restr($subj_type, $subj_id, $term_data = false) {
		$this->apply_restr_structure_filter($subj_type, $subj_id);
        
        if($term_data) {
			$this->term_data = $term_data;	
		}
		
		// check in cached elements
		if(isset($this->man_restr_db[$subj_type][$subj_id])) {
			return $this->man_restr_db[$subj_type][$subj_id];	
		}
		
		$restr 		= array();		
		$self_restr = false;
		$terms 		= array();
	
		// search in self restrictions		
		if(isset($this->restr_db[$subj_type][$subj_id])) {
			$self_restr = $this->restr_db[$subj_type][$subj_id];
		}
		else {
			$self_restr = ($subj_type == 'term') ? $this->get_term_restr($subj_id) : $this->get_post_restr($subj_id);
			$this->restr_db[$subj_type][$subj_id] = $self_restr;
		}
		
		// store if not empty
		if($self_restr && $self_restr != $this->restr_structure) {
			$restr = $this->chain_restrictions($restr, $self_restr, 'self');	
		}
		

		// if post
		if($subj_type == 'post') {
			
			// check among parents
			$child_id = $subj_id;
			$parent_id = wp_get_post_parent_id($subj_id);
			
			while(!empty($parent_id)) {
				$this->restr_db['post'][$parent_id] = (isset($this->restr_db['post'][$parent_id])) ? $this->restr_db['post'][$parent_id] : $this->get_post_restr($parent_id);
				$restr = $this->chain_restrictions($restr, $this->restr_db['post'][$parent_id], 'post', $parent_id);
				
				$child_id = $parent_id;
				$parent_id = wp_get_post_parent_id($child_id);	
			}
			
			// has associated terms?
			$terms = wp_get_post_terms($subj_id, pc_static::affected_tax());
		}
		
		
		// if term check - simulate an associated term to allow easy recursions
		else {
			if(!empty($this->term_data) && !empty($this->term_data->parent)) {
				$terms[] = get_term_by('id', $this->term_data->parent, $this->term_data->taxonomy);
			}
		}
		
	
		// check in associated terms
		if(!empty($terms) && !is_wp_error($terms)) {
			foreach($terms as $term) {
				$term_data 	= $term;
				$child_id 	= $subj_id;
				$parent_id 	= $term->term_id;
				
				while(!empty($parent_id)) {
					$this->restr_db['term'][$parent_id] = (isset($this->restr_db['term'][$parent_id])) ? $this->restr_db['term'][$parent_id] : $this->get_term_restr($parent_id);
					$restr = $this->chain_restrictions($restr, $this->restr_db['term'][$parent_id], 'term', $parent_id);
					
					if(!empty($term_data->parent)) {
						$term_data 	= get_term_by('id', $parent_id, $term_data->taxonomy);
						$child_id 	= $parent_id;
						$parent_id 	= $term_data->parent;
					}
					else {
						$parent_id 	= false;
					}
				}
			}	
		}
	
		
		// if every restriction is empty and lb on open has only inherit - return an empty array
		$has_restr = false;
		foreach($restr as $key => $val) {
			if($key != 'lb_on_open') { 
				if(!empty($val)) {
					$has_restr = true;
					break;
				}
			}
			else {
				$unique = array_unique(array_values((array)$val));
				if(end($unique) != 'inherit') {
					$has_restr = true;
					break;	
				}
			}
		}
		if(!$has_restr) {$restr = array();}
		
		
		
		// wrap allowances and blocks to be faster in management
		$final_restr = array();
		foreach($restr as $restr_type => $rs) {
			
			if(in_array($restr_type, array('lb_on_open', 'block_woo_sell'))) { // lightbox on open - is already ok
				$final_restr[$restr_type] = $rs;	
				continue;
			}
			
			if(in_array($restr_type, array('cont_hide_block', 'comm_hide_block', 'redirect_block'))) { // ignore blocks since are calculated in allows
				continue;		
			}
				
			
			// default basic structure
			$block_key 	= str_replace('allow', 'block', $restr_type);
			$new_key	= str_replace('_allow', '', $restr_type);
			$final_restr[$new_key] = array();
			
			foreach($rs as $rs_subj => $rs_val) {
				$final_restr[$new_key][$rs_subj]['allow'] = $rs_val;
				
				// has it also blocks 
				if( isset($restr[$block_key]) && isset($restr[$block_key][$rs_subj]) ) {
					$final_restr[$new_key][$rs_subj]['block'] = (array)$restr[$block_key][$rs_subj];
				}
			}	
		}
		
		
		// reset properties
		$this->reset_prop();
		
        
        // PC-FILTER - allow post/term restrictions array structure management
        $final_restr = apply_filters('pc_entity_full_restr', $final_restr, $subj_type, $subj_id);
        
        
		// cache and return
		$this->man_restr_db[$subj_type][$subj_id] = $final_restr;	
		return $final_restr;
	}
	
	
	/* Reset parents_restr and terms_restr properties */
	private function reset_prop() {
		$this->parents_restr = array();
		$this->terms_restr = array();	
	}
	
	
	
	/////////////////////////////////////////////////////////////
	
	
	
	/* terms wizard implementation */
	public function terms_form_implementation($term_data) {

		//check for existing taxonomy meta for term ID
		$term_id = (is_object($term_data)) ? $term_data->term_id : false;
		
		// if "edit term" view
		if(!is_object($term_data)) : 
			?>
			<div class="form-field pc_tax_restr_wizard">
				<h4>PrivateContent - <?php esc_html_e("Restrictions Wizard", 'pc_ml') ?></h4>
				<?php echo pc_static::wp_kses_ext($this->wizard_code('tax', $term_id)) ?>
			</div>
	
    
		<?php else: 
			$this->term_data = $term_data;
		?>
        
		 <tr class="form-field">
			<th scope="row" valign="top"></th>
	
			<td class="pc_tax_restr_wizard_td">
				<div class="form-field pc_tax_restr_wizard">
					<h4>PrivateContent - <?php esc_html_e("Restrictions Wizard", 'pc_ml') ?></h4>
					<?php echo pc_static::wp_kses_ext($this->wizard_code('tax', $term_id)) ?>
				</div>
			</td>
		</tr>
		<?php
		endif;
	}
	
	/*  term fields save */
	public function term_fields_save($term_id) {
		$data = $this->save_restrictions('term', $term_id);
	}
	
	/* on term's deletion - delete related option */
	public function clean_db_on_terms_remove($term_id) {
		$GLOBALS['pvtcont_restr_cache_cmd'] = array(
			'cmd' 		=> 'delete',
			'subj' 		=> 'term',
			'subj_id' 	=> $term_id,
		);
	}
	
	
	
	/////////////////////////////////////////////////////////////
	
	
	
	/* Post types metabox setup */
	public function pt_metabox_setup() {

		// add a meta box for affected post types and be sure excerpt is there for Contents hiding
		foreach(pc_static::affected_pt() as $type){
			add_meta_box('pc_redirect_meta', "PvtContent - ". esc_html__(" Restrictions Wizard", 'pc_ml'), array($this, 'posts_form_implementation'), $type, 'side', 'default');
			add_post_type_support($type, 'excerpt');
			
			add_filter('manage_edit-'.$type.'_columns', array($this, 'postNterms_list_pc_col_head')); 
			add_action('manage_'.$type.'_posts_custom_column', array($this, 'post_type_list_pc_col_txt_trick'), 10, 2);
		}  
	}

	/* posts wizard implementation */
	public function posts_form_implementation() {
		global $post;
		echo pc_static::wp_kses_ext($this->wizard_code('post', $post->ID));
	}
	
	/* post fields save */
	public function post_fields_save($post_id) {
		$data = $this->save_restrictions('post', $post_id);
	}
	
	
	
	/////////////////////////////////////////////////////////////

	
	
	/* Returns restrictions wizard HTML to be inserted in metabox
	 * @param (string) $subj_type = tax or post
	 * @param (int|bool) $subj_id = term/post ID or false
     * @param (bool) $bulk_restr_wizard - whether the code is shown for bulk restrictions lightbox
	 */
	public function wizard_code($subj_type, $subj_id = false, $bulk_restr_wizard = false) {
        global $pc_users;
        
        $this->apply_restr_structure_filter($subj_type, $subj_id);
		$data = ($subj_type == 'tax') ? $this->get_term_restr($subj_id) : $this->get_post_restr($subj_id); 
		
        $redirect_block_vis  = (empty($data['redirect_allow']) || (count($data['redirect_allow']) == 1 && $data['redirect_allow'][0] == 'unlogged')) ? 'pc_displaynone' : '';
        
		$cont_hide_block_vis = (empty($data['cont_hide_allow']) || (count($data['cont_hide_allow']) == 1 && $data['cont_hide_allow'][0] == 'unlogged')) ? 'pc_displaynone' : '';
		$comm_hide_block_vis = (empty($data['comm_hide_allow']) || (count($data['comm_hide_allow']) == 1 && $data['comm_hide_allow'][0] == 'unlogged')) ? 'pc_displaynone' : '';
		
		// PC lightbox instances
		$lb_instances = pc_static::get_lb_instances();
		
		$tax_tb = ($subj_type == 'tax') ? '<br/>' : '';
		
		
		// Wizard's code - Contents Hiding
		$code = '
		<div class="pc_restr_wizard_wrap">';
            
			// REDIRECT  
			$code .= '
			<div class="pc_restr_wizard_block pc_rw_redir">
				<legend>
					<i class="fas fa-lock" ></i><strong>'. esc_html__('Redirect', 'pc_ml') .'</strong>
					'. $this->inherited_restr_helper('redirect', $subj_type, $subj_id) .'
				</legend>'.$tax_tb.'
				<fieldset>
					<label>'. esc_html__('Who can access this page?', 'pc_ml') .'</label>
					<select name="pc_redirect_allow[]" multiple="multiple" class="pc_restr_wiz_lcslt" data-placeholder="'. esc_attr__('Leave empty to ignore', 'pc_ml') .'" autocomplete="off">
						'. pc_static::user_cat_dd_opts($data['redirect_allow']) .'
					</select>
					<input type="hidden" name="pc_redirect_allow_check" value="'. esc_attr(implode(',', $data['redirect_allow'])) .'" />
				</fieldset>
				<fieldset class="'. $redirect_block_vis .'">
					<hr/>
					<label>'. esc_html__('Among them - want to block someone?', 'pc_ml') .'</label>
					<select name="pc_redirect_block[]" multiple="multiple" class="pc_restr_wiz_lcslt" data-placeholder="'. esc_attr__('Leave empty to ignore', 'pc_ml') .'" autocomplete="off">
						'. pc_static::user_cat_dd_opts($data['redirect_block'], false) .'
					</select>
					<input type="hidden" name="pc_redirect_block_check" value="'. esc_attr(implode(',', $data['redirect_block'])) .'" />
				</fieldset>
			</div>';
        
        
            // CONTENTS HIDING
            $code .= '
            <hr/>
			<div class="pc_restr_wizard_block pc_rw_cont_h">
				<legend>
					<i class="fas fa-eye-slash" ></i><strong>'. esc_html__('Contents Hiding', 'pc_ml') .'</strong>
					'. $this->inherited_restr_helper('cont_hide', $subj_type, $subj_id) .'
				</legend>'.$tax_tb.'
				<fieldset>
					<label>'. esc_html__('Who can see contents?', 'pc_ml') .'</label>
					<select name="pc_cont_hide_allow[]" multiple="multiple" class="pc_restr_wiz_lcslt" data-placeholder="'. esc_attr__('Leave empty to ignore', 'pc_ml') .'" autocomplete="off">
						'. pc_static::user_cat_dd_opts($data['cont_hide_allow'], true, true, array('unlogged')) .'
					</select>
				</fieldset>
				<fieldset class="'. $cont_hide_block_vis .'">
					<hr/>
					<label>'. esc_html__('Among them - want to block someone?', 'pc_ml') .'</label>
					<select name="pc_cont_hide_block[]" multiple="multiple" class="pc_restr_wiz_lcslt" data-placeholder="'. esc_attr__('Leave empty to ignore', 'pc_ml') .'" autocomplete="off">
						'. pc_static::user_cat_dd_opts($data['cont_hide_block'], false) .'
					</select>
				</fieldset>
			</div>';
		
		
			// COMMENTS
			if($pc_users->wp_user_sync && ($subj_type == 'tax' || comments_open($subj_id) || $bulk_restr_wizard)) {
				$code .= '
                <hr/>
				<div class="pc_restr_wizard_block pc_rw_comm_h">
					<legend>
						<i class="fas fa-comment-slash" ></i><strong>'. esc_html__('Comments', 'pc_ml') .'</strong>
						'. $this->inherited_restr_helper('comm_hide', $subj_type, $subj_id) .'
					</legend>'.$tax_tb.'
					<fieldset>
						<label>'. esc_html__('Who can see comments?', 'pc_ml') .'</label>
						<select name="pc_comm_hide_allow[]" multiple="multiple" class="pc_restr_wiz_lcslt" data-placeholder="'. esc_attr__('Leave empty to ignore', 'pc_ml') .'" autocomplete="off">
							'. pc_static::user_cat_dd_opts($data['comm_hide_allow'], true, true, array('unlogged')) .'
						</select>
					</fieldset>
					<fieldset class="'. $comm_hide_block_vis .'">
						<hr/>
						<label>'. esc_html__('Among them - want to block someone?', 'pc_ml') .'</label>
						<select name="pc_comm_hide_block[]" multiple="multiple" class="pc_restr_wiz_lcslt" data-placeholder="'. esc_attr__('Leave empty to ignore', 'pc_ml') .'" autocomplete="off">
							'. pc_static::user_cat_dd_opts($data['comm_hide_block'], false) .'
						</select>
					</fieldset>
				</div>';		
			}
			
		
			// LIGHTBOX ON OPENING 
			if(!empty($lb_instances)) {
				$lb_instances = array(
					'inherit' 	=> '('. esc_html__('inherit', 'pc_ml') .')',
					'none' 			=> esc_html__('No', 'pc_ml')
				) + $lb_instances;
				
				$code .= '
				<hr/>
				<div class="pc_restr_wizard_block pc_rw_lb">
					<legend>
						<i class="far fa-window-restore" ></i><strong>'. esc_html__("Lightbox on page's opening", 'pc_ml') .'</strong>
					</legend>'.$tax_tb.'
					<fieldset>
						<label>'. esc_html__('Displayed for unlogged users', 'pc_ml') .'</label>
                        '. $this->inherited_restr_helper('lb_on_open', $subj_type, $subj_id) .'
                        
						<select name="pc_lb_on_open" autocomplete="off">';
							
							foreach($lb_instances as $k => $v) {
								$code .= '<option value="'. $k .'" '. selected($data['lb_on_open'], $k, false) .'>'. $v .'</option>';	
							}
						
						$code .= '
						</select>
					</fieldset>
				</div>';
			}
        
        
        // PC-FILTER - allow extra fields to be output in the restr wizard block - passes the HTML structure + restriction data array + subj type (post/term) and its ID + class instance
        $code = apply_filters('pc_restr_wizard_code', $code, $data, $subj_type, $subj_id, $this);
		
		$code .= '	
			<input type="hidden" name="pc_rw_nonce" value="'. wp_create_nonce('pc_rw') .'" />
		</div>';
		
		
		// javascript
		$inline_js = '
        (function($) { 
            "use strict";';
        
            if(ISPCF) {
                $inline_js .= "
                document.querySelectorAll('.pc_rw_comm_h, .pc_rw_cont_h, .pc_tax_restr_wizard .pc_rw_redir').forEach(function(wrap) {
                    if(wrap.querySelectorAll('fieldset').length > 1) {
                        wrap.querySelectorAll('fieldset')[1].remove();
                    }
                });
                window.nfpcf_inject_infobox('.pc_rw_comm_h fieldset, .pc_rw_cont_h fieldset, .pc_rw_wph fieldset, .pc_rw_lb fieldset', true);";
            }
        
            $inline_js .= '
            $(document).ready(function($) {

                // lc select
                new lc_select(".pc_restr_wizard_block select", {
                    wrap_width : "100%",
                    addit_classes : ["lcslt-lcwp"],
                });
                
                // fields toggles
                let fields = [`cont_hide_allow[]`, `comm_hide_allow[]`, `redirect_allow[]`];

                $.each(fields, function(i, v) {
                    $(".pc_restr_wizard_wrap").each(function() {
                        const field_name    = `pc_`+ v,
                              $fields_wrap  = $(this),
                              $block_field  = $fields_wrap.find(`select[name="${ field_name.replace("allow", "block") }"]`).parents("fieldset").first(); 	

                        // track changes
                        $fields_wrap.off("change", `select[name="${field_name}"]`);
                        $fields_wrap.on("change", `select[name="${field_name}"]`, function() {
                            let val = ($(this).val() !== null && typeof($(this).val()) == "object") ? $(this).val() : [],
                                unlogged_chosen = false;

                            // if ALL is selected, discard the rest
                            if($.inArray("all", val) !== -1) {
                                $(this).find("option").prop("selected", false);
                                $(this).find(".pc_all_field").prop("selected", true);

                                val = ["all"];

                                const resyncEvent = new Event("lc-select-refresh");
                                this.dispatchEvent(resyncEvent);
                            }

                            // if only UNLOGGED is selected, hide block
                            else if($.inArray("unlogged", val) !== -1) {
                                if(val.length == 1 && val[0] == "unlogged") { 
                                    unlogged_chosen = true;
                                }
                            }


                            // toggle block-user-cat field
                            if(unlogged_chosen || !val.length) {
                                $block_field.addClass("pc_displaynone");
                            } else {
                                $block_field.removeClass("pc_displaynone");
                            }
                        });
                    });
                });
            });
        })(jQuery);';
		wp_add_inline_script('lcwp_magpop', $inline_js);
        
		return $code;
	}
	
	
	
	/* Get helper for specific restriction type
	 * @param (string) $restr_type = which restriction's type to check (cont_hide | comm_hide | redirect | lb_on_open)
	 * @param (string) $subj_type = term or post
	 * @param (int) $subj_id = term/post ID
	 *
	 * @return (string) helper HTML to be printed in restriction's type block
	 */
	public function inherited_restr_helper($restr_type, $subj_type, $subj_id, $suppress_self = true) {
		if(empty($subj_id)) {
            return '';
        }
		
		$elem_restr = $this->get_entity_full_restr($subj_type, $subj_id);
		if(empty($elem_restr) || empty($elem_restr[$restr_type])) {
            return '';
        }
		
		$sr = $elem_restr[$restr_type]; // specific restrictions
		$helper = '';
        
		// "lightbox on opening" - special case
		if($restr_type == 'lb_on_open') {
			
			$a = 1;
			foreach((array)$sr as $subj => $restr) {
				if(($suppress_self && $subj == 'self') || $restr == 'inherit') {
                    continue;
                }
				
				if($restr == 'no') {
                    $txt = esc_html__('No lightbox', 'pc_ml');
                }
				else {
					$lb_instances = pc_static::get_lb_instances();
					
					if(!isset($lb_instances[$restr])) {
						continue;	
					}
					else {
						$txt = $lb_instances[$restr];	
					}
				}
				
				$helper .= '
				<dl>
					<dt>'. $this->restr_subj_to_link($subj) .'</dt><dd>'. esc_html__('uses', 'pc_ml') .' <em>'. $txt .'</em></dd>
				</dl>';
			}
		}
		
		
        // "block woo purchase" - special case
		elseif($restr_type == 'block_woo_sell') {
			
			$a = 1;
			foreach((array)$sr as $subj => $restr) {
				if(($suppress_self && $subj == 'self') || $restr == 'inherit') {
                    continue;
                }
				
				$txt = ($restr == 'no') ? esc_html__('No', 'pc_ml') : esc_html__('Yes', 'pc_ml');
				
				$helper .= '
				<dl>
					<dt>'. $this->restr_subj_to_link($subj) .'</dt><dd>'. esc_html__('uses', 'pc_ml') .' <em>'. $txt .'</em></dd>
				</dl>';
			}
		}
        
        
		// normal restrictions
		else {
			foreach($sr as $subj => $restr) {
				if(($suppress_self && $subj == 'self') || empty($restr)) {continue;}	
				
				// check also blocks
				$blocks_txt = (isset($restr['block']) && !empty($restr['block'])) ? ' '. esc_html__('and blocks', 'pc_ml') .' <em class="pc_rwh_block">'. $this->restr_arr_to_string($restr['block']) .'</em>' : ''; 
				
				$helper .= '
				<dl>
					<dt>'. $this->restr_subj_to_link($subj) .'</dt><dd>'. esc_html__('allows', 'pc_ml') .' <em class="pc_rwh_allow">'. $this->restr_arr_to_string($restr['allow']) .'</em>' . $blocks_txt .'</dd>
				</dl>';
			}
		}
		
		
		// complete helper's code
		if(!empty($helper)) {
			$helper = '
			<div class="pc_restr_helper">
				'. $helper .'
			</div>';	
		}

		return $helper;
	}
	
	
	/* Get HTML link + name from restriction's subject (and also text for self restrictions) */
	private function restr_subj_to_link($subj) {

		// special case - self restrictions
		if($subj == 'self') {
			return '<a href="#"><i class="far fa-arrow-alt-circle-right"></i>' . esc_html__('self restriction', 'pc_ml') .'</a>';
		}
		
		// retrieve subj and ID
		$subj_arr = explode('_', $subj);
		$icon = '<i class="fas fa-level-up-alt"></i> ';
			
		if($subj_arr[0] == 'post') {
			$title = get_the_title($subj_arr[1]);
			if(empty($title)) {
                return '';
            } 
			
			return '<a href="'. esc_attr(admin_url('post.php?post='. $subj_arr[1] .'&action=edit')) .'" target="_blank" title="'. esc_attr__('inherited restriction', 'privatecontent-free') .'">'. $icon . $title .'</a>';
		}
		else if($subj_arr[0] == 'term') {
			$term = pc_static::term_obj_from_term_id($subj_arr[1]);
			if(empty($term)) {
                return '';
            } 
			
			return '<a href="'. esc_attr(admin_url('term.php?taxonomy='. $term->taxonomy .'&tag_ID='. $subj_arr[1])) .'" target="_blank" title="'. esc_attr__('inherited restriction', 'privatecontent-free') .'">'. $icon . $term->name .'</a>';	
		}
		else  {
			return '';
		}
	}
	
	
	/* Given an array of chosen restrictions - returns a string with related opt names */
	public function restr_arr_to_string($restrictions, $join_with = ', ') {
		if(empty($restrictions)) {return '';}
		
		// cache restriction options
		if(!isset($GLOBALS['pcpp_restr_assoc_arr'])) {
			$opts = pc_static::restr_opts_arr(true, true);
			$GLOBALS['pcpp_restr_assoc_arr'] = array();
			
			foreach($opts as $data) {
				foreach($data['opts'] as $opt_id => $opt_name) {
					$GLOBALS['pcpp_restr_assoc_arr'][$opt_id] = $opt_name;
				}
			}
		}

		if(!is_array($restrictions)) {
			$restrictions = explode(',', $restrictions);			
		}
		
		$names = array();
		foreach($restrictions as $restr) {
			if(isset($GLOBALS['pcpp_restr_assoc_arr'][$restr])) {$names[] = $GLOBALS['pcpp_restr_assoc_arr'][$restr];}	
		}
		
		return implode($join_with, $names);
	}
	
	
	
	/////////////////////////////////////////////////////////////
	
	
	
	/* Terms and posts list - add pvtContent restrictions column - heading */
	public function postNterms_list_pc_col_head($columns) {
		$columns_local = array();
		
		if(!isset($columns_local['pvtcontent'])) { 
			$columns_local['pvtcontent'] = '<span class="pc_help_cursor" title="'. esc_attr__('restrictions summary', 'pc_ml') .'">PrivateContent</span>';
		}
		return array_merge($columns, $columns_local);
	}
	
	
	/* Post type column - trick to use an unique function */
	public function post_type_list_pc_col_txt_trick($column, $post_id) {
		if($column != 'pvtcontent') {
            return false;
        }
		echo pc_static::wp_kses_ext($this->postNterms_list_pc_col_txt('', 'pvtcontent', $post_id, 'post'));
	}
	
	
	/* Terms and posts list - add pvtContent restrictions column - contents */
	public function postNterms_list_pc_col_txt($row_content, $column_name, $subj_id, $subj = 'term') {
		if($column_name != 'pvtcontent') {
            return false;
        }
		$elements = array();

        // PC-FILTER - allow final restriction array manipulation for restr helper
        $restr_arr = apply_filters('pc_final_restr_structure', $this->final_restr_structure, $subj, $subj_id);
        
        
		// if term - pass term data
		if($subj == 'term') {
			$this->term_data = pc_static::term_obj_from_term_id($subj_id);
		}
		
		foreach($restr_arr as $restr) {
			$list = $this->inherited_restr_helper($restr, $subj, $subj_id, $suppress_self = false);
			$id = esc_attr('pc_rw_'. $restr .'_elem_'. $subj_id);
			
			if(empty($list)) {
				$nr_class = 'pc_rw_no_restr';
				switch($restr) {
					case 'cont_hide' : 
						$icon = '<i class="fas fa-eye-slash '.$nr_class.'" title="'. esc_attr__('no contents restriction', 'pc_ml') .'"></i>'; 
						break;
						
					case 'comm_hide' : 
						$icon = '<i class="fas fa-comment-slash '.$nr_class.'" title="'. esc_attr__('no comments hiding restriction', 'pc_ml') .'"></i>'; 
						break;
						
					case 'redirect' : 
						$icon = '<i class="fas fa-lock '.$nr_class.'" title="'. esc_attr__('no redirect restrictions', 'pc_ml') .'"></i>'; 
						break;
						
					case 'lb_on_open' : 
						$icon = '<i class="far fa-window-restore '.$nr_class.'" title="'. esc_attr__('follows global lightbox-on-open setup', 'pc_ml') .'"></i>'; 
						break;
                        
                    case 'block_woo_sell' : 
						$icon = '<i class="dashicons dashicons-cart '.$nr_class.'" title="'. esc_attr__('follows global purchase lock setup', 'pc_ml') .'"></i>'; 
						break;    
				}
				
				$elements[$restr] = $icon;
			}
            
			else {
				$trig_class = 'pc_rw_pointer_trigger';
				switch($restr) {
					case 'cont_hide' : 
						$icon = '<i class="fas fa-eye-slash '.$trig_class.'" id="'.$id.'" title="'. esc_attr__('show applied contents hiding restrictions', 'pc_ml') .'"></i>'; 
						$pointer_title = '<i class="fas fa-eye-slash" ></i>'. esc_html__('Contents hiding restrictions', 'pc_ml');
						break;
						
					case 'comm_hide' : 
						$icon = '<i class="fas fa-comment-slash '.$trig_class.'" id="'.$id.'" title="'. esc_attr__('show applied comments hiding restrictions', 'pc_ml') .'"></i>'; 
						$pointer_title = '<i class="fas fa-comment-slash" ></i>'. esc_html__('Comments hiding restrictions', 'pc_ml');
						break;
						
					case 'redirect' : 
						$icon = '<i class="fas fa-lock '.$trig_class.'" id="'.$id.'" title="'. esc_attr__('show applied redirect restrictions', 'pc_ml') .'"></i>'; 
						$pointer_title = '<i class="fas fa-lock" ></i>'. esc_html__('Redirect restrictions', 'pc_ml');
						break;
						
					case 'lb_on_open' : 
						$icon = '<i class="far fa-window-restore '.$trig_class.'" id="'.$id.'" title="'. esc_attr__("show used lightbox on page's opening", 'pc_ml') .'"></i>'; 
						$pointer_title = '<i class="far fa-window-restore" ></i>'. esc_html__("Lightbox on page's opening", 'pc_ml');
						break;	
                        
                    case 'block_woo_sell' : 
                        $icon = '<i class="dashicons dashicons-cart '.$trig_class.'" id="'.$id.'" title="'. esc_attr__("show applied product purchase lock", 'pc_ml') .'"></i>'; 
						$pointer_title = '<i class="dashicons dashicons-cart" ></i>'. esc_html__("Product purchase lock", 'pc_ml');
						break;	
				}
				
				
				
				// use type icon in pointer  and set wrapper class to move arrow and customize better with CSS
				$pointer_title = '<h3 class="pc_rw_pointer_head">'. $pointer_title .'<i class="fas fa-minus-circle pc_rw_pointer_close" data-pcrwtt-id="'. $id .'" title="'. esc_attr__('close', 'pc_ml') .'"></i></h3>';
				
				$elements[$restr] = $icon;
                
                $inline_js = '
                (function($) { 
                    "use strict";
                    
                    $(document).ready(function() {
                        pc_rw_pointers["'. esc_js($id) .'"] = {
                            element : "'. esc_js($id) .'",
                            options : {
                                content		: `'. wp_kses_post(str_replace(array("\r", "\n"), '', $pointer_title . $list)) .'`,
                                position	: {
                                    edge : "top", 
                                    align : "right"
                                },
                                buttons: function() {
                                    var button = "";
                                },
                                open: function(event, t) {
                                    $(".wp-pointer-content .pc_restr_helper").parents(".wp-pointer").addClass("pc_rw_pointer_wrap");
                                },
                            }
                        }; 
                    });
                })(jQuery);';
                wp_add_inline_script('lcwp_magpop', $inline_js);
			}
		}

        
        $woo_purchae_lock_li = (isset($elements['block_woo_sell'])) ? '<li>'. $elements['block_woo_sell'] .'</li>' : '';
        
        // quick edit attributes only if user can edit it
        $qe_atts = '';
        if(
            ($subj == 'post' && current_user_can('edit_post', $subj_id)) || 
            ($subj == 'term' && current_user_can('edit_term', $subj_id)) 
        ) {
            $qe_atts = 'data-helper-txt="'. esc_attr__('double click to edit restrictions', 'pc_ml') .'" data-subj="'. esc_attr($subj) .'" data-subj-id="'. (int)$subj_id .'"';
        }
        
		return '
		<ul class="pc_restr_wiz_in_list" '. $qe_atts .'>
			<li>'. $elements['cont_hide'] .'</li>
			<li>'. $elements['comm_hide'] .'</li>
			<li>'. $elements['redirect'] .'</li>
			<li>'. $elements['lb_on_open'] .'</li>
            '. $woo_purchae_lock_li .'
		</ul>';
	}
	
	
	
	/////////////////////////////////////////////////////////////
	
	
	
	/* Enqueue WP pointers script and add script to dinamically show them */
	public function wp_pointer_load($hook_suffix) {
		$screen = get_current_screen();
	
		// only in taxonomy or PT overview
		if($screen->base != 'edit-tags' && $screen->base != 'edit') {
            return false;
        }	
			
		wp_enqueue_style('wp-pointer');
   		wp_enqueue_script('wp-pointer');	
		
		add_action('admin_head', array($this, 'pc_pl_restr_wizard_js'), 100);	
	}
	
    
	/* Print JS code for dynamic WP pointers on click and popup with restrictions wizard on doubleclick */
	public function pc_pl_restr_wizard_js() {
        global $current_screen;
		
        $inline_js = '
        (function($) { 
            "use strict";    
            
            window.pc_rw_pointers = [];
            const nonce = `'. esc_js(wp_create_nonce('lcwp_nonce')) .'`;
            

            // TOOLTIP/POINTERS 
            $(document).on(`click`, `.pc_rw_pointer_trigger`, function(e) {
                e.preventDefault();	

                // prior checks 
                if(typeof($().pointer) == `undefined`) {
                    console.error(`WP pointer script not loaded`); 
                    return false;
                }
                var pointer_id = $(this).attr(`id`);

                if(typeof(pc_rw_pointers[pointer_id]) == `undefined`) {
                    return false;
                }
                var pointer_data = pc_rw_pointers[pointer_id];

                // if a pointer is already open - close it using its ID
                if($(`.wp-pointer`).is(":visible")) { 
                    $(`.wp-pointer:visible`).find(`.close`).trigger(`click`);
                }

                // open
                $(`#`+ pointer_data.element).pointer(pointer_data.options).pointer(`open`);					
            });


            // close 
            $(document).on(`click`, `.pc_rw_pointer_close`, function() {
                var id = $(this).data(`pcrwtt-id`);
                $(`#`+ id).pointer("close");
            });	


            // close if clicking elsewhere
            $(document).on(`click`, `#wpbody-content *:not(.pc_rw_pointer_close)`, function(e) {

                // if clicking a trigger
                if($(e.target).hasClass(`pc_rw_pointer_trigger`)) {
                    var pointer_id = $(e.target).attr(`id`);
                    $(`.pc_rw_pointer_close:visible`).each(function() {
                        if($(this).data(`pcrwtt-id`) != pointer_id) {
                            $(this).trigger(`click`);	
                        }
                    });
                }	
                else {
                    $(`.pc_rw_pointer_close:visible`).trigger(`click`);	
                }
            });
            
            
            
            // QUICK-EDIT RESTRICTIONS WIZARD
            
            // open on single element doubleclick
            $(document).on(`dblclick doubletap`, `.pc_restr_wiz_in_list[data-helper-txt]`, function() {
                pc_qe_restr_wiz_show(
                    $(this).data(`subj`), 
                    [ parseInt($(this).data(`subj-id`), 10) ]
                );
            });
            
            
            // show bulk-restrictions-update button 
            $(document).on(`input`, `.check-column input`, function() {
                if($(`th.check-column input:checked`).length && $(`.tablenav .alignleft.actions`).length) {
                    
                    if(!$(`.pc_bulk_qe_restr_wiz_btn`).length) {
                        $(`.tablenav`).each(function() {
                            $(this).find(`.alignleft.actions`).last().append(`<input type="button" class="button pc_bulk_qe_restr_wiz_btn" value="'. esc_attr__('Change restrictions', 'pc_ml') .'" />`);
                        });
                    }
                }
                else {
                    $(`.pc_bulk_qe_restr_wiz_btn`).remove();  
                }
            });
            
            
            // bulk-restrictions-update - throw lightbox
            $(document).on(`click`, `.pc_bulk_qe_restr_wiz_btn`, function() {
                let subj_ids = [];
                $(`th.check-column input:checked`).each(function() {
                    subj_ids.push( parseInt($(this).val(), 10) );
                });
                
                if(!subj_ids.length) {
                    return false;   
                }
                
                pc_qe_restr_wiz_show(
                    $(`.pc_restr_wiz_in_list[data-helper-txt]`).first().data(`subj`), 
                    subj_ids
                );
            });
                
            
            
            $(document).ready(function() {
                $.magnificPopup.instance._onFocusIn = function(e) {
                    if($(e.target).is(`[name="lcslt-search"]`) ) {
                       return true;
                    }

                    // Else call parent method
                    $.magnificPopup.proto._onFocusIn.call(this, e);
                };
            });
            
            
            
            const pc_qe_restr_wiz_show = function(subj, subj_ids) {
                $.magnificPopup.open({
                    items : {
                        src: `<div class="pc_qe_restr_wiz_in_list_wrap"><div class="pc_center_spinner"><div class="pc_spinner pc_spinner_big"></div></div></div>`,
                        type: `inline`
                    },
                    mainClass	: `pc_qe_restr_wiz_in_list`,
                    closeOnContentClick : false,
                    closeOnBgClick		: false, 
                    preloader	        : false,
                    callbacks : {
                        beforeOpen: function() {
                            if($(window).width() < 800) {
                                this.st.focus = false;
                            }
                            
                            let data = {
                                action          : `pvtcont_qe_restr_wiz_in_list_form`,
                                subj            : subj,
                                subj_name       : `'. ((!empty($current_screen->taxonomy)) ? esc_js($current_screen->taxonomy) : esc_js($current_screen->post_type)) .'`,
                                subj_ids        : subj_ids,
                                pc_nonce        : nonce,
                            };
                            $.post(ajaxurl, data, function(response) {
                                try {
                                    response = JSON.parse(response);
                                    
                                    if(response.status == `success`) {
                                        $(`.pc_qe_restr_wiz_in_list_wrap`).html(response.contents);';
                                        
                                        if(ISPCF) {
                                            $inline_js .= "
                                            document.querySelectorAll('.pc_rw_comm_h, .pc_rw_cont_h, .pc_tax_restr_wizard .pc_rw_redir').forEach(function(wrap) {
                                                if(wrap.querySelectorAll('fieldset').length > 1) {
                                                    wrap.querySelectorAll('fieldset')[1].remove();
                                                }
                                            });
                                            window.nfpcf_inject_infobox('.pc_rw_comm_h fieldset, .pc_rw_cont_h fieldset, .pc_rw_wph fieldset, .pc_rw_lb fieldset', true);";
                                        }
                                        
                                        $inline_js .= '
                                        new lc_select(`.pc_qe_restr_wiz_in_list_wrap select`, {
                                            wrap_width : `100%`,
                                            addit_classes : [`lcslt-lcwp`],
                                        });
                                    }
                                    else {
                                        lc_wp_popup_message(`error`, response.message);	
                                        $.magnificPopup.close();
                                    }
                                }
                                catch(e) {
                                    lc_wp_popup_message(`error`, `Error parsing restrictions wizard code`);	
                                    $.magnificPopup.close();
                                }
                            })
                            .fail(function(e) {
                                if(e.status) {
                                    console.error(e);
                                    
                                    lc_wp_popup_message(`error`, "Error retrieving restrictions wizard code");
                                    $.magnificPopup.close();
                                }
                            });
                        },
                    }
				});
            };
            
            
            $(document).on(`click`, `.pc_qe_restr_wiz_in_list_btns_wrap .button-secondary`, function(e) {
                $.magnificPopup.close();
            });


            // save restriction changes
            $(document).on(`submit`, `.pc_qe_restr_wiz_in_list form`, function(e) {
                e.preventDefault();
                const $form = $(this); 
                
                if($(`.pc_qe_restr_wiz_in_list input[name="subj_ids"]`).val().indexOf(`,`) !== -1 && !confirm(`'. esc_html__('This will update every restriction for the chosen elements. Continue?', 'pc_ml') .'`)) {
                    return false;
                }
                
                let data = `action=pvtcont_qe_restr_wiz_in_list_update&pc_nonce=`+ nonce +`&subj_name='. ((!empty($current_screen->taxonomy)) ? esc_js($current_screen->taxonomy) : esc_js($current_screen->post_type)) .'&`+ $form.serialize();
                
                $form.attr(`disabled`, `disabled`);
                $.post(ajaxurl, data, function(response) {
                    try {
                        response = JSON.parse(response);

                        if(response.status == `success`) {
                            if(typeof(response.new_helpers) != `undefined`) {
                                Object.keys(response.new_helpers).forEach(subj_id => { 
                                    $(`.pc_restr_wiz_in_list[data-subj-id="`+ subj_id +`"]`).replaceWith( response.new_helpers[subj_id] );
                                });
                            }
                            
                            lc_wp_popup_message(`success`, `'. esc_html__('Restrictions successfully updated!', 'pc_ml') .'`);	
                            $.magnificPopup.close();
                        }
                        else {
                            alert(response.message);
                        }
                    }
                    catch(e) {
                        alert(`Error parsing server response`);
                    }
                })
                .fail(function(e) {
                    if(e.status) {
                        console.error(e);
                        alert("Error performing the operation");
                    }
                })
                .always(function(e) {
                    $form.removeAttr(`disabled`);
                });
                
                return false;
            });
            
        })(jQuery);';
		wp_add_inline_script('lcwp_magpop', $inline_js);
	}


}



// init class
add_action('wp_loaded', function() {
	$GLOBALS['pc_restr_wizard'] = new pc_restr_wizard;
}, 100);
