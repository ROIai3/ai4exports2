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
	'pc_align' => array(
		'label'		=> esc_html__('Form alignment', 'pc_ml'),
		'type'		=> 'select',
		'opts'		=> array(
			'center'	=> esc_html__('Center', 'pc_ml'),
			'left'		=> esc_html__('Left', 'pc_ml'),
			'right'		=> esc_html__('Right', 'pc_ml'),
		),
		'default' 	=> 'center',
		'panel'		=> 'main',
	),
);




$defaults = pc_fix_block_defs($defaults);

register_block_type('lcweb/pc-user-del-box', array(
	'editor_script' 	=> 'pc_user_del_on_guten',
	'render_callback' 	=> 'pvtcont_user_del_guten_handler',
	'attributes' 		=> $defaults
));


wp_localize_script('wp-blocks', 'pc_user_del_defaults', $defaults);
