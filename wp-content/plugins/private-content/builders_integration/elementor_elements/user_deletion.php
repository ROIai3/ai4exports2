<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if(!defined('ABSPATH')) exit;



class pc_user_del_on_elementor extends Widget_Base {
	
	 public function get_icon() {
      return 'emtr_lcweb_icon';
   }
	
	public function get_name() {
		return 'pc-user-del';
	}

	public function get_categories() {
		return array('privatecontent');
	}

   public function get_title() {
      return 'PC - '. esc_html__('User Deletion', 'pc_ml');
   }



   protected function register_controls() {

		$this->start_controls_section(
			'main',
			array(
				'label' => 'PrivateContent - '. esc_html__('User Deletion', 'pc_ml'),
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
		
		$this->add_control(
		   'align',
		   array(
			  'label' 	=> esc_html__('Alignment', 'pc_ml'),
			  'type' 	=> Controls_Manager::SELECT,
			  'default' => 'center',
			  'options' => array(
			  	'center' 	=> esc_html__('Center', 'pc_ml'),
				'left'		=> esc_html__('Left', 'pc_ml'),
				'right'		=> esc_html__('Right', 'pc_ml'),
			  )
		   )
		);
			
		$this->end_controls_section();
   }


	
	////////////////////////



	protected function render() {
     	$vals = $this->get_settings();
		//var_dump($vals);

		$parts = array('redirect', 'align');
		$params = '';
		
		foreach($parts as $part) {
			$params .= $part.'="';
			
			if(!isset($vals[$part])) {$vals[$part] = '';}
			$params .= $vals[$part].'" ';	
		}
		
        echo (isset($GLOBALS['pc_user_id']) && $GLOBALS['lcwp_is_elementor_builder']) ? 
            '[PrivateContent '. esc_html__('user deletion box - logout to see it', 'pc_ml') .']' : 
            do_shortcode('[pc-user-del-box '. $params .']');
	}

}
