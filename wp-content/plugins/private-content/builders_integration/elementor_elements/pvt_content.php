<?php
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if(!defined('ABSPATH')) exit;



class pc_pvt_content_on_elementor extends Widget_Base {
	
	 public function get_icon() {
      return 'emtr_lcweb_icon';
   }
	
	public function get_name() {
		return 'pc-pvt-content';
	}

	public function get_categories() {
		return array('privatecontent');
	}

   public function get_title() {
      return 'PC - '. esc_html__('Private Block', 'pc_ml');
   }



	protected function register_controls() {
		
		$lb_instances = array(
			'' => esc_html__('As default', 'pc_ml'), 
			'none' => esc_html__('No login button', 'pc_ml')
		) + pc_static::get_lb_instances();




		$this->start_controls_section(
			'main',
			array(
				'label' => 'PrivateContent - '. esc_html__('Private Block', 'pc_ml') .' (DEPRECATED)',
			)
		);
  
  
        $this->add_control(
			'deprecation_note',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => '<strong>'. esc_html__('Please note this widget is now deprecated, every elementor widget/column can have its visibility settings', 'pc_ml') .'</strong>',
			]
		);
        
        
		$this->add_control(
		   'allow',
		   array(
			  'label' 	=> esc_html__('Who can see contents?', 'pc_ml') .'<br/>',
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'multiple'=> true,
			  'default' => current(array_keys( pvtcont_uc_array_on_elementor() )),
			  'options' => pvtcont_uc_array_on_elementor()
		   )
		);
		
		
		$this->add_control(
		   'block',
		   array(
			  'label' 	=> esc_html__('Among allowed, want to block specific categories?', 'pc_ml'),
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'multiple'=> true,
			  'default' => current(array_keys( pvtcont_uc_array_on_elementor(false) )),
			  'options' => pvtcont_uc_array_on_elementor(false)
		   )
		);
		
		
		$this->add_control(
		   'warning',
		   array(
			  'label' 		=> esc_html__('Show warning box?', 'pc_ml'),
			  'type' 		=> Controls_Manager::SWITCHER,
			  'default' 	=> '1',
			  'label_on' 	=> esc_html__('Yes', 'pc_ml'),
			  'label_off' 	=> esc_html__('No', 'pc_ml'),
			  'return_value' => '1',
		   )
		);
		
		
		$this->add_control(
		   'message',
		   array(
			  	'label' => esc_html__('Custom message for not allowed users', 'pc_ml'),
				'type' 	=> Controls_Manager::TEXT,
				
				'condition' => array(
					'warning' => '1',
				),
		   )
		);
		
		
		$this->add_control(
		   'login_lb',
		   array(
			  'label' 	=> esc_html__("Login button's lightbox", 'pc_ml'),
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'multiple'=> true,
			  'default' => current(array_keys( $lb_instances )),
			  'options' => $lb_instances,
			  
			  'condition' => array(
				  'warning' => '1',
			  ),
		   )
		);
		
		
		$this->add_control(
		   'registr_lb',
		   array(
			  'label' 	=> esc_html__("Registration button's lightbox", 'pc_ml'),
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'multiple'=> true,
			  'default' => current(array_keys( $lb_instances )),
			  'options' => $lb_instances,
			  
			  'condition' => array(
				  'warning' => '1',
			  ),
		   )
		);
		
		
		// $this->add_control(
		//    'contents',
		//    array(
		// 	  	'label' => esc_html__("Contents to hide", "'pc_ml'"),
		// 		'type' => Controls_Manager::WYSIWYG,
		//    )
		// );

		$this->add_control(
			'content_type',
			[
				'label'       => esc_html__( 'Content Type', 'pc_ml'),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'editor',
				'options'     => [
                    'editor'   => esc_html__( 'Editor', 'pc_ml'),
					'template' => esc_html__( 'Template', 'pc_ml'),
				],
				'label_block' => 'true',
			]
		);

		$templates = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' )->get_items();

		$options['0'] = '— ' . esc_html__( 'Select Template', 'pc_ml') . ' —';

		$types = array();
		if (count($templates)) {
			foreach($templates as $template) {
				$options[ $template['template_id'] ] = $template['title'] . ' (' . $template['type'] . ')';
				$types[ $template['template_id'] ] = $template['type'];
			}
		} else {
			$options['0'] = esc_html__('You haven\'t saved any templates yet.', 'pc_ml');
		}

		$this->add_control(
			'template_id',
			[
				'label'       => esc_html__( 'Choose Template', 'pc_ml' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'default'     => '0',
				'options'     => $options,
				'types'       => $types,
				'label_block' => 'true',
				'condition'   => [
					'content_type' => 'template',
				]
			]
		);

		$this->add_control(
			'editor_content',
			[
				'label'      => esc_html__( 'Content', 'pc_ml' ),
				'type'       => Controls_Manager::WYSIWYG,
				'default'    => esc_html__( 'Content goes here.', 'pc_ml' ),
				'dynamic' => [
					'active' => true,
				],
				'condition'   => [
					'content_type' => 'editor',
				]
			]
		);





		
		$this->end_controls_section();
   }


	
	////////////////////////


	protected function render() {
     	$vals = $this->get_settings();
		//var_dump($vals);

		$allow = implode(',', (array)$vals['allow']);
		$block = implode(',', (array)$vals['block']);
		
		$parts = array('warning', 'message', 'login_lb', 'registr_lb');
		$params = '';
		
		foreach($parts as $part) {
			$params .= $part.'="';

			if(!isset($vals[$part])) {$vals[$part] = '';}
			$params .= $vals[$part].'" ';	
		}

		switch ( $vals['content_type'] ) {
			case 'editor':		
				$content_html = $vals['editor_content'];
			break;

			case 'template':
				if ( '0' !== $vals['template_id'] ) {
					$template = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $vals['template_id'] );

					if ( ! empty( $template ) ) {
						$content_html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $vals['template_id'] );
					} else {
						$content_html = '<span>' . esc_html__( 'The widget is working. Please, note, that you have to add a template to the library in order to be able to display it inside the widget.', 'pc_ml' ) . '</span>';
					}
				} else {
					$content_html = '<span>' . esc_html__( 'The widget is working. Please, note, that you have to add a template to the library in order to be able to display it inside the widget.', 'pc_ml' ) . '</span>';
				}
			break;

			default:
				return;
			break;
		}

		echo do_shortcode('[pc-pvt-content allow="'.$allow.'" block="'.$block.'" '. $params .']'. $content_html .'[/pc-pvt-content]');
	}

}
