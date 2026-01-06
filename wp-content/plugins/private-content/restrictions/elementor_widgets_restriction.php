<?php
/* NFPCF */

// ELEMENTOR WIDGETS RESTRICTION
if(!defined('ABSPATH')) {exit;}


// user categories array builder for Elementor controls
function pvtcont_uc_array_on_elementor($bulk_opts = true, $apply_filter = true) {

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





add_action('elementor/element/after_section_end', function($element, $section_id, $args) {    
	if(in_array($section_id, array('section_advanced', '_section_style'))) {

        $element->start_controls_section(
			'section_pvtcont_visibility',
			array(
				'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
				'label' => esc_html__('PrivateContent - Element Visibility', 'pc_ml'),
			)
		);

		$element->add_control(
		   'pc_vis_allow',
		   array(
			  'label' 	=> esc_html__('Who can see this element?', 'pc_ml') .'<br/>',
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'multiple'=> true,
			  'default' => '',
			  'options' => pvtcont_uc_array_on_elementor()
		   )
		);
		
		
		$element->add_control(
		   'pc_vis_block',
		   array(
			  'label' 	=> esc_html__('Among allowed, want to block specific categories?', 'pc_ml'),
			  'type' 	=> \Elementor\Controls_Manager::SELECT2,
			  'multiple'=> true,
			  'default' => '',
			  'options' => pvtcont_uc_array_on_elementor(false)
		   )
		);

		$element->end_controls_section();
	}
}, 9999, 3);





function pvtcont_apply_elementor_widget_restr($bool, $element) {
    $settings = $element->get_settings();

    if(isset($settings['pc_vis_allow']) && !empty($settings['pc_vis_allow'])) {
        $allow = (array)$settings['pc_vis_allow'];
        $block = (isset($settings['pc_vis_block']) && !empty($settings['pc_vis_block'])) ? (array)$settings['pc_vis_block'] : array(); 
    
        return (pc_user_check($allow, $block, true) !== 1) ? false : true;
    }
    
    return $bool;
}
$priority = 99999;
add_filter('elementor/frontend/section/should_render', 'pvtcont_apply_elementor_widget_restr', $priority, 2);
add_filter('elementor/frontend/container/should_render', 'pvtcont_apply_elementor_widget_restr', $priority, 2);
add_filter('elementor/frontend/widget/should_render', 'pvtcont_apply_elementor_widget_restr', $priority, 2);
add_filter('elementor/frontend/repeater/should_render', 'pvtcont_apply_elementor_widget_restr', $priority, 2);







add_filter('elementor/widget/render_content', function($content, $element) {
    if(!\Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return $content;    
    }
    $settings = $element->get_settings();
       
    if(isset($settings['pc_vis_allow']) && !empty($settings['pc_vis_allow'])) {
        $allow = (array)$settings['pc_vis_allow'];
        $block = (isset($settings['pc_vis_block']) && !empty($settings['pc_vis_block'])) ? (array)$settings['pc_vis_block'] : array();
        
        if(pc_user_check($allow, $block, true) !== 1) {
            $content = '<div class="pc_restricted_elementor_widget" title="PrivateContent: restricted element">'. $content .'</div>';
        }
    }
    
	return $content;
}, 10, 2);


