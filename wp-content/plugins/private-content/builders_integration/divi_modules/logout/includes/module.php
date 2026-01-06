<?php
// DEFINING MODULE STRUCTURE AND FIELDS
if(!defined('ABSPATH')) {exit;}


class pc_logout_divi_module extends ET_Builder_Module {

	public $slug       = 'pc_logout';
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
		$this->name               = 'PC - '. esc_html__('Logout Button', 'pc_ml');
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
 
    
	public function get_fields() {
        $fields =array(
            'redirect' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Custom Redirect', 'pc_ml'),
				'type'            => 'text',
                'default'         => '',
				'default_on_front'=> '',
                'description'     => esc_html__('Custom redirect (optional - use a valid URL or "refresh" keyword)', 'pc_ml'),
			),
		);
        
        
        $GLOBALS[ $this->slug .'_divi_field_indexes'] = array_keys($fields);
        return $fields;
	}


    
    public function render($attrs, $content = null, $render_slug = null) {
        return pc_divi_modules::front_shortcode_render($this->slug, $this->props);  
	}
}

new pc_logout_divi_module;
