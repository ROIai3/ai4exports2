<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if(!defined('ABSPATH')) exit;



class pc_logout_on_elementor extends Widget_Base {
	
	 public function get_icon() {
      return 'emtr_lcweb_icon';
   }
	
	public function get_name() {
		return 'pc-logout';
	}

	public function get_categories() {
		return array('privatecontent');
	}

   public function get_title() {
      return 'PC - '. esc_html__('Logout Button', 'pc_ml');
   }



   protected function register_controls() {

		$this->start_controls_section(
			'main',
			array(
				'label' => 'PrivateContent - '. esc_html__('Logout', 'pc_ml'),
			)
		);
  
		$this->add_control(
		   'redirect',
		   array(
			  	'label' 		=> esc_html__('Custom Redirect', 'pc_ml'),
				'type' 			=> Controls_Manager::TEXT,
				'description'	=> esc_html__('Custom redirect (use a valid URL or "refresh" keyword)', 'pc_ml'),
		   )
		);
		
			
		$this->end_controls_section();
   }


	
	////////////////////////



	protected function render() {
     	$vals = $this->get_settings();
		//var_dump($vals);

		$parts = array('redirect');
		$params = '';
		
		foreach($parts as $part) {
			$params .= $part.'="';
			
			if(!isset($vals[$part])) {$vals[$part] = '';}
			$params .= $vals[$part].'" ';	
		}
		
        
        echo (!isset($GLOBALS['pc_user_id']) && $GLOBALS['lcwp_is_elementor_builder']) ? 
            '[PrivateContent '. esc_html__('logout button - login to see it', 'pc_ml') .']' : 
            do_shortcode('[pc-logout-box '. $params .']');
	}

}
