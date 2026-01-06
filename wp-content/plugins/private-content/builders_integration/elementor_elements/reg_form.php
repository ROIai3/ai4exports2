<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if(!defined('ABSPATH')) exit;



class pc_reg_form_on_elementor extends Widget_Base {
	
	 public function get_icon() {
      return 'emtr_lcweb_icon';
   }
	
	public function get_name() {
		return 'pc-reg-form';
	}

	public function get_categories() {
		return array('privatecontent');
	}

   public function get_title() {
      return 'PC - '. esc_html__('Registration Form', 'pc_ml');
   }



   protected function register_controls() {

		// forms list
		pc_reg_form_ct();
		$reg_forms = get_terms(array(
            'taxonomy'   => 'pc_reg_form',
            'hide_empty' => 0,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));	
		
        $reg_form_array = array();
		foreach($reg_forms as $rf) {
			$reg_form_array[$rf->term_id] = $rf->name;
		}
		

		// user categories list
		$pc_cats = array('' => esc_html__('none', 'pc_ml')) + pc_static::user_cats();
		
		


		$this->start_controls_section(
			'main',
			array(
				'label' => 'PrivateContent - '. esc_html__('Registration form', 'pc_ml'),
			)
		);
  
  
		$this->add_control(
		   'form_id',
		   array(
			  'label' 	=> esc_html__('Which form to use?', 'pc_ml'),
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'default' => current(array_keys($reg_form_array)),
			  'options' => $reg_form_array
		   )
		);
		
		
		$this->add_control(
		   'layout',
		   array(
			  'label' 	=> esc_html__('Layout', 'pc_ml'),
			  'type' 	=> Controls_Manager::SELECT,
			  'default' => '',
			  'options' => array(
			  	'' 			=> esc_html__('Default one', 'pc_ml'),
				'one_col'	=> esc_html__('Single column', 'pc_ml'),
				'fluid'		=> esc_html__('Fluid (multi column)', 'pc_ml'),
			  )
		   )
		);
		

		$this->add_control(
		   'custom_categories',
		   array(
			  'label' 		=> esc_html__('Custom categories assignment', 'pc_ml'),
			  'description' => esc_html__('Ignored if field is in form', 'pc_ml'),
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'default' => false,
			  'options' => $pc_cats
		   )
		);
		
		
		$this->add_control(
		   'redirect',
		   array(
			  	'label' 		=> esc_html__('Custom redirect', 'pc_ml'),
				'description' 	=> esc_html__('Use a valid URL', 'pc_ml'),
				'default' 		=> '',
				'type' 			=> Controls_Manager::TEXT,
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

		$parts = array('form_id', 'layout', 'custom_categories', 'redirect', 'align');
		$params = '';
		
		foreach($parts as $part) {
			$params .= $part.'="';
			
			if(!isset($vals[$part])) {$vals[$part] = '';}
			$params .= $vals[$part].'" ';	
		}
		
		echo do_shortcode('[pc-registration-form '. $params .']');
	}

}
