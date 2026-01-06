<?php
// DEFINING MODULE STRUCTURE AND FIELDS
if(!defined('ABSPATH')) {exit;}


class pc_reg_form_divi_module extends ET_Builder_Module {

	public $slug       = 'pc_reg_form';
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
		$this->name               = 'PC - '. esc_html__('Registration Form', 'pc_ml');
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
        
        
        
        $fields =array(
            'form_id' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Which form to use?', 'pc_ml'),
				'type'            => 'select',
				'default'         => current(array_keys($reg_form_array)),
				'default_on_front'=> current(array_keys($reg_form_array)),					
				'options'         => $reg_form_array
			),
            'layout' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Layout', 'pc_ml'),
				'type'            => 'select',
				'default'         => current(array_keys($reg_form_array)),
				'default_on_front'=> current(array_keys($reg_form_array)),					
				'options'         => array(
                    '' 			=> esc_html__('Default one', 'pc_ml'),
                    'one_col'	=> esc_html__('Single column', 'pc_ml'),
                    'fluid'		=> esc_html__('Fluid (multi column)', 'pc_ml'),
			    )
			),
            'custom_categories' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Custom categories assignment', 'pc_ml'),
				'type'            => 'categories',
                'renderer_options' => array(
                    'use_terms'    => true,
                    'term_name'    => 'pg_user_categories',
                ),
                'default'         => false,
				'default_on_front'=> false,
				//'description'     => esc_html__( 'Choose whether your linklink opens in a new window or not', 'dicm-divi-custom-modules' ),
			),
            'redirect' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Custom Redirect', 'pc_ml'),
				'type'            => 'text',
                'default'         => '',
				'default_on_front'=> '',
                'description'     => esc_html__('Custom redirect (optional - use a valid URL or "refresh" keyword)', 'pc_ml'),
			),
            'align' => array(
                'toggle_slug'     => 'main',
				'label'           => esc_html__('Alignment', 'pc_ml'),
				'type'            => 'select',
				'default'         => 'center',
				'default_on_front'=> 'center',					
				'options'         => $GLOBALS['pvtcont_divi_forms_align']
			),
		);
        
        
        $GLOBALS[ $this->slug .'_divi_field_indexes'] = array_keys($fields);
        return $fields;
	}


    
    public function render($attrs, $content = null, $render_slug = null) {
        return pc_divi_modules::front_shortcode_render($this->slug, $this->props);  
	}
}

new pc_reg_form_divi_module;
