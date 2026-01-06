<?php
// USER DASHBOARD FRAMEWORK
if(!defined('ABSPATH')) {exit;}


class pvtcont_user_dashboard_engine {
	
	public $tabs 		= array(); // dashboard tabs array
	public $structure 	= array(); // tab sections array
	public $user_id		= false; // if we are editing, contains the user ID

	
	
	/* INIT - setup tabs and filter */ 
	public function __construct($tabs, $structure, $user_id) {
		$this->tabs 		= $tabs;
		$this->structure	= $structure;
		$this->user_id		= $user_id;
	}
	
	
	
	
	

	/* print dashboard code (tabs + fields) */
	public function get_code() {
		echo '<form method="post" class="pc_user_dashboard_form form-wrap" action="'. esc_attr(str_replace( '%7E', '~', sanitize_text_field($_SERVER['REQUEST_URI']))) .'">';
		
			// only one tab? hide it!
			if(count($this->tabs) > 1) {
			
				// tabs code
				$limited_top_margin = (!$this->user_id) ? 'pc_udt_ltd_top_margin' : '';
				echo '<div class="nav-tab-wrapper pc_user_dashboard_tabs '. esc_attr($limited_top_margin) .'">';
			
				foreach($this->tabs as $i => $v) {
					echo '<a id="pc_user_dashboard_'. esc_attr($i) .'_tab" class="nav-tab" href="#pc_ud_'. esc_attr($i) .'">'. esc_html($v) .'</a>';		
				}

				echo '</div>';
			}
			
			
			
			// tab sections
			foreach($this->tabs as $tab_id => $tab_name) {

				echo '
				<div id="pc_ud_'. esc_attr($tab_id) .'" class="pc_user_dashboard_'. esc_attr($tab_id) .'_tab_contents">';
				
					foreach($this->structure[$tab_id] as $sect_id => $sect_data) {
						
						// be sure the callback function exists
						if(!is_array($sect_data) || !isset($sect_data['callback'])) {
							continue;	
						}
                        
                        // static methods - split into array
                        if(is_string($sect_data['callback']) && strpos($sect_data['callback'], '::') !== false) {
                            $sect_data['callback'] = explode('::', $sect_data['callback']);  
                        }
                        
                        if(
                            (!is_array($sect_data['callback']) && !function_exists($sect_data['callback'])) || 
                            (is_array($sect_data['callback'] && method_exists($sect_data['callback'][0], $sect_data['callback'][1])))
                        ) {
                            continue;    
                        }
						
						
						$classes = (isset($sect_data['classes'])) ? esc_attr($sect_data['classes']) : '';
						echo '<div class="pc_user_dashboard_block '. esc_attr($classes) .'" data-sect-id="'. esc_attr($sect_id) .'">';
						
							if(isset($sect_data['name']) && !empty($sect_data['name'])) {
								echo '<h3 class="pc_user_dashboard_sect_title">'. wp_kses_post(trim($sect_data['name'])) .'</h3>';	
							}
									
							// the code must be printed by the callback function
							echo '<div class="pc_user_dashboard_section">';
                        
								call_user_func($sect_data['callback'], $this->user_id);
							
							echo '</div>
						</div>';
					}
				
				echo '
				</div>';
			}
			
			
			
			// nonce & submit button
			if($GLOBALS['pvtcont_ud_cuc_edit']) {
				$btn_txt = ($this->user_id) ? esc_html__('Edit User', 'pc_ml') : esc_html__('Add User', 'pc_ml'); 
				echo '<input type="submit" name="pc_submit_user_dashboard" value="'. esc_attr($btn_txt) .'" class="button-primary pc_user_dashboard_submit" />';
			}
		
		echo '	
		</form>';
	}
	
}