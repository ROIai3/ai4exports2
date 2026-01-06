<?php
// DEFINING MODULE STRUCTURE AND FIELDS
if(!defined('ABSPATH')) {exit;}


class pc_pvt_block_divi_module extends ET_Builder_Module {

	public $slug       = 'pc_pvt_block';
	public $vb_support = 'on';
    public $ml_key     = 'pc_ml';
    
    
	protected $module_credits = array(
        'module_uri' => 'https://lcweb.it/privatecontent',
        'author'     => 'LCweb',
        'author_uri' => 'https://lcweb.it/',
	);

    
    public function get_advanced_fields_config() {
        return unserialize(LC_DIVI_DEF_OPTS_OVERRIDE);
	}

    
	public function init() {
		$this->name               = 'PC - '. esc_html__('Private Block', 'pc_ml');
		$this->icon_path          =  $GLOBALS['pvtcont_divi_icon_path'];
		$this->main_css_element   = '%%order_class%%';	
        
        $this->settings_modal_toggles  = array(
			'general'  => array(
				'toggles' => array(
					'main'     => esc_html__('Main Options', 'pc_ml'),
					'styling'  => esc_html__('Styling', 'pc_ml'),
				),
			),
		);
	}
 
    
    
    
    // user categories array builder
	protected function pc_uc_array($bulk_opts = true, $apply_filter = true) {

		// fix for PCPP
		if(function_exists('pcpp_is_integrated_flag')) {
			pcpp_is_integrated_flag();	
		}
		
		
		$raw = pc_static::restr_opts_arr($bulk_opts, $apply_filter);
		
		$arr = array();
		foreach($raw as $block) {
			foreach($block['opts'] as $id => $name) {
				$arr[$id] = $name;	
			}
		}
		return $arr;
	}
    
    
    
	public function get_fields() {
        
        $lb_instances = array(
			''       => esc_html__('As default', 'pc_ml'), 
			'none'   => esc_html__('No login button', 'pc_ml')
		) + pc_static::get_lb_instances();
        
        
        $fields =array(
            'allow' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Who can see contents?', 'pc_ml'),
				'type'            => 'categories',
                'renderer_options' => array(
                    'use_terms'    => true,
                    'term_name'    => 'pg_user_categories',
                ),
                'default'         => false,
				'default_on_front'=> false,
				//'description'     => esc_html__('Choose whether your linklink opens in a new window or not', 'pc_ml'),
			),
            'block' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Among allowed, want to block specific categories?', 'pc_ml'),
				'type'            => 'categories',
                'renderer_options' => array(
                    'use_terms'    => true,
                    'term_name'    => 'pg_user_categories',
                ),
                'default'         => false,
				'default_on_front'=> false,
				//'description'     => esc_html__('Choose whether your linklink opens in a new window or not', 'pc_ml'),
			),
            'warning' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Show warning box?', 'pc_ml'),
				'type'            => 'yes_no_button',
                'default'         => 'off',
				'default_on_front'=> 'off',		
				'options'         => array(
					'off' => esc_html__('No', 'pc_ml'),
					'on'  => esc_html__('Yes', 'pc_ml'),
				),
			),	
            'message' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Custom message for not allowed users', 'pc_ml'),
				'type'            => 'text',
                'default'         => '',
				'default_on_front'=> '',
                'description'     => esc_html__('Message shown if "warning" option is enabled', 'pc_ml'),
			),
            'login_lb' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__("Login button's lightbox", 'pc_ml'),
				'type'            => 'select',
                'default'         => '',
				'default_on_front'=> '',
				'options'         => $lb_instances,
				//'description'     => esc_html__('Choose whether your linklink opens in a new window or not', 'pc_ml'),
			),
            'registr_lb' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__("Registration button's lightbox", 'pc_ml'),
				'type'            => 'select',
                'default'         => '',
				'default_on_front'=> '',
				'options'         => $lb_instances,
				//'description'     => esc_html__('Choose whether your linklink opens in a new window or not', 'pc_ml'),
			),
            'pvt_block_txt' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Content', 'pc_ml'),
				'type'            => 'textarea',
                'default'         => '',
				'default_on_front'=> '',
                //'description'     => esc_html__('Remember you can use placeholders and FontAwesome icons as explained in settings', GG_ML),
			),
		);
        
        
        $GLOBALS[ $this->slug .'_divi_field_indexes'] = array_keys($fields);
        return $fields;
	}


    
    public function render($attrs, $content = null, $render_slug = null) {
        return pc_divi_modules::front_shortcode_render($this->slug, $this->props);  
	}
}

new pc_pvt_block_divi_module;

