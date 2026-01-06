<?php
// SETUP AND MANAGE POSTS RESTRICTION CACHE
//// restrictions can be read using $GLOBALS['pvtcont_query_filter_post_array']

/*	structure:
	array(
		*POST-ID* => array(
			'self' 			=> array('allow' => restrictions, 'block' => restrictions), -> self restrictions
			'post_*ID*' 	=> array('allow' => restrictions, 'block' => restrictions), -> parent post restrictions
			'term_*ID*' 	=> array('allow' => restrictions, 'block' => restrictions), -> associated term restrictions
			...
		)
	)
*/
if(!defined('ABSPATH')) {exit;}


include_once('restrictions_wizard.php');
class pvtcont_posts_restr_cache extends pc_restr_wizard {
	
	// option name
	private $opt_name = 'pc_restr_posts_cache';
	
	// posts restriction array	
	public $posts_restr = array();
	
	
	
	/* INIT - check stored values and add hooks */
	public function __construct() {

		// get cached array
		$this->posts_restr = $this->get_opt();

		// if option doesn't exist - setup
		if($this->posts_restr === false) {
			$this->get_restr_posts();
		}
		
		
		// hooks implementation on admin side
		if(is_admin()) {
			
			// post removal check
			add_action('delete_post', array($this, 'remove_del_post'), 100);
			
			// hooks
			$hooks_cb = array($this, 'get_restr_posts');
			
			add_action('save_post', $hooks_cb, 100);
			add_action('deleted_term_taxonomy', $hooks_cb, 100);
            add_action('pc_qe_restr_wiz_in_list_updated', $hooks_cb, 100);
			
			foreach(pc_static::affected_tax() as $tax) {
				add_action('created_'.$tax, $hooks_cb, 100);
				add_action('edited_'.$tax, $hooks_cb, 100);
			}
		}
	
		//var_dump($this->posts_restr); // debug
		$GLOBALS['pvtcont_query_filter_post_array'] = $this->posts_restr;
	}
	
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	
	/* 
	 * get restrictions option - uncompressing it 
	 * @return (bool/array) false if option hasn't been populated yet - otherwise restricted elements array
	 */
	public function get_opt() {
		$data = get_option($this->opt_name);
		if($data === false) {
            return false;
        }
        
		return (array)pc_static::decompress_data($data);
	}
	
	
	/* save restrictions - compressing it */
	public function save_opt() {
		$str = pc_static::compress_data($this->posts_restr);
		update_option($this->opt_name, $str);
	}
	
	
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////////////	
	
	
	
	/* remove deleted post from restrictions cache and update the option */
	public function remove_del_post($post_id) {
		$GLOBALS['pvtcont_restr_cache_cmd'] = array(
			'cmd' 		=> 'delete',
			'subj' 		=> 'post',
			'subj_id' 	=> $post_id
		);
		$this->get_restr_posts();
	}
	
	
	
	/* get restricted posts */
	public function get_restr_posts() {
        
		// really needs to re-fetch everything or just manipulate a single change?
		if(get_option($this->opt_name) && isset($GLOBALS['pvtcont_restr_cache_cmd'])) {
			$this->selective_restr_update($GLOBALS['pvtcont_restr_cache_cmd']);	
			unset($GLOBALS['pvtcont_restr_cache_cmd']);
		}
		
		else {
			if(isset($GLOBALS['pvtcont_skip_restr_cache'])) { // coming from restr wizard - nothing changed then is useless to fetch
				return true;	
			}
			$GLOBALS['pvtcont_is_fetching_posts_restr'] = true; // add flag invalidating PC posts filter	
			
			// reset container
			$this->posts_restr = array();
	
			$query = new WP_Query( array (
				'post_type' 			=> pc_static::affected_pt(),
				'orderby' 				=> 'ID',
				'order' 				=> 'ASC',
				'posts_per_page' 		=> -1,
				'post_status' 			=> 'publish',
				'fields' 				=> 'ids',
				
				'suppress_filters' 		=> 1,
				'cache_results'  		=> false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false
			));
			$posts = $query->posts;
	
			foreach($posts as $post_id) { 
				
				// get post restriction's tree
				$restr = $this->get_entity_full_restr('post', $post_id);
				
				if(isset($restr) && !empty($restr['redirect'])) {
					$this->posts_restr[$post_id] = $restr['redirect'];
				}
	
			}	
			
			// remove flag invalidating PC posts filter	
			unset($GLOBALS['pvtcont_is_fetching_posts_restr']);	
		}
		
			
		// update database record	
		$this->save_opt();
			
		// set global with restrictions
		$GLOBALS['pvtcont_query_filter_post_array'] = $this->posts_restr;	
	}
	
	
	
	/* 
	 * Method called on post/term creation/update/deletion only if restrictions cache is set and to avoid the huge query 
	 * @param (array) $data = data normally taken from $GLOBALS['pvtcont_restr_cache_cmd']
	 */
	private function selective_restr_update($data) {
		switch($data['cmd']) {
			case 'update' :
			
				// empty restriction? set to delete
				if(empty($data['restr_data']['allow'])) {
					$data['cmd'] = 'delete';	
					return $this->selective_restr_update($data);		
				}
			
				// doesn't exist? set to add
				if($data['subj'] == 'post' && !isset($this->posts_restr[$data['subj_id']])) {
					$data['cmd'] = 'add';	
					return $this->selective_restr_update($data);
				}
			
				/////

				// self restrictions
				if($data['subj'] == 'post') {
					$restr = $this->get_entity_full_restr('post', $data['subj_id']);
					
					if(isset($restr) && !empty($restr['redirect'])) {
						$this->posts_restr[$data['subj_id']] = $restr['redirect'];
					}
				}
				
				
				// if post - search in childs
				if($data['subj'] == 'post') {
					$to_refetch = $this->get_all_post_childs($data['subj_id'], get_post_type($data['subj_id']));
				} 
				
				// term - associated posts
				else {
					$term = get_term($data['subj_id']);
					$args = array(
						'post_type'     	=> 'any',
						'posts_per_page' 	=> -1,
						'fields'			=> 'ids',
						'post_status' 		=> 'publish', 
						
						'suppress_filters' 			=> 1,
						'cache_results'  			=> false,
						'update_post_meta_cache' 	=> false,
						'update_post_term_cache' 	=> false,
						
						'tax_query' => array(
							array(
								'taxonomy' 			=> $term->taxonomy,
								'field' 			=> 'id',             
								'terms' 			=> $data['subj_id'],
								'include_children' 	=> true,
								'operator' 			=> 'IN'
							),
						),
					);
					$query = new WP_Query($args);	
					$to_refetch = $query->posts;	
				}
				
				// refeth for these posts
				foreach($to_refetch as $post_id) {
					$restr = $this->get_entity_full_restr('post', $post_id);
				
					if(isset($restr) && !empty($restr['redirect'])) {
						$this->posts_restr[$post_id] = $restr['redirect'];
					}	
					else {
						if( isset($this->posts_restr[$post_id]) ) {
							unset( $this->posts_restr[$post_id] );	
						}
					}
				}
				break;	
				
		
			case 'add' :
				$restr = $this->get_entity_full_restr('post', $data['subj_id']);
				
				if(isset($restr) && !empty($restr['redirect'])) {
					$this->posts_restr[$data['subj_id']] = $restr['redirect'];
				}
				break;
				
			
			case 'delete' :
			
				// remove post restrictions
				if($data['subj'] == 'post' && isset($this->posts_restr[$data['subj_id']])) {
					unset($this->posts_restr[$data['subj_id']]);		
				}
				
				// cycle each stored restriction and remove the subject
				foreach($this->posts_restr as $post_id => $restr_arr) {
					
					$subj_key = $data['subj'].'_'.$data['subj_id'];
					if(isset($restr_arr[ $subj_key ])) {
						unset($this->posts_restr[$post_id][$subj_key]);	
					}
					
					if(empty($this->posts_restr[$post_id])) {
						unset($this->posts_restr[$post_id]);	
					}
				}	
				break;
		}
	}
	
	
	
	/* recursively retrieves post childrens */
	private function get_all_post_childs($parent_id, $post_type) {

		$args = array(
			'post_type'     	=> $post_type,
			'posts_per_page' 	=> -1,
			'post_parent'   	=> $parent_id,
			'fields'			=> 'ids',
			'post_status' 		=> 'publish', 
			
			'suppress_filters' 			=> 1,
			'cache_results'  			=> false,
			'update_post_meta_cache' 	=> false,
			'update_post_term_cache' 	=> false,
		);
		$query = new WP_Query($args);	
		$childs = $query->posts;
		
		// child of child?
		foreach($childs as $child_id) {
			$childs = array_merge($childs,  $this->get_all_post_childs($child_id, $post_type));	
		}
		return (is_array($childs)) ? array_unique($childs) : array();	
	}	
	
	
}





// init class
add_action('wp_loaded', function() {
	$restr_cache = new pvtcont_posts_restr_cache;
}, 120); // use a number > 100 to let restrictions wizard to be setup
