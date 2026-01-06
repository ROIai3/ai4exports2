<?php
/* NFPCF */

// Elementor shortcodes integration
if(!defined('ABSPATH')) {exit;}


function pc_on_elementor($widgets_manager) {
    $GLOBALS['lcwp_is_elementor_builder'] = (
        is_user_logged_in() && 
        (
            (isset($_GET['action']) && $_GET['action'] == 'elementor') ||
            (wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] == 'elementor_ajax')
        )
    ) ? true : false;
    
    
    
    $basepath = PC_DIR .'/builders_integration/elementor_elements';
    
    $widgets = array(
        'login'         => 'pc_login_on_elementor',
        'logout'        => 'pc_logout_on_elementor',
        'user_deletion' => 'pc_user_del_on_elementor',
        'pvt_content'   => 'pc_pvt_content_on_elementor',
        'reg_form'      => 'pc_reg_form_on_elementor',
    );
    
    foreach($widgets as $filename => $classname) {
        include_once($basepath .'/'. $filename .'.php');
        $widgets_manager->register( new $classname() );
    }
}
add_action('elementor/widgets/register', 'pc_on_elementor');




// add plugin section
add_action('elementor/init', function() {
   \Elementor\Plugin::$instance->elements_manager->add_category( 
   	'privatecontent',
   	array(
   		'title' => 'PrivateContent',
   		'icon' => 'fas fa-plug',
   	),
	3
   );
});




// style needed for LCweb icon
add_action('elementor/editor/after_enqueue_styles', function() {
	wp_enqueue_style('lcweb-elementor-icon', PC_URL .'/builders_integration/elementor_elements/lcweb_icon.css');	
});
