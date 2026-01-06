<?php
// REGISTER BLOCK
if(!defined('ABSPATH')) {exit;}


// structure
$defaults = array(
	'redirect' => array(
		'label'		=> esc_html__('Custom redirect', 'pc_ml'),
		'help'		=> esc_html__('Use a valid URL or "refresh" keyword', 'pc_ml'),
		'type'		=> 'text',
		'default' 	=> '',
		'panel'		=> 'main',
	),
);




$defaults = pc_fix_block_defs($defaults);

register_block_type('lcweb/pc-logout-box', array(
	'editor_script' 	=> 'pc_logout_on_guten',
	'render_callback' 	=> 'pvtcont_logout_guten_handler',
	'attributes' 		=> $defaults
));


wp_localize_script('wp-blocks', 'pc_logout_defaults', $defaults);
