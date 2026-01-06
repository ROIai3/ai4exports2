<?php
// REGISTER BLOCK
if(!defined('ABSPATH')) {exit;}


// reg forms
$rf = array();
$rf_terms = get_terms( array(
    'taxonomy'   => 'pc_reg_form',
    'hide_empty' => 0,
    'orderby'    => 'name',
    'order'      => 'ASC',
));

foreach($rf_terms as $term) {
	$rf[ $term->term_id ] = $term->name;
}



// user cats
$uc = array();
$ucat_terms = get_terms( array(
    'taxonomy'   => 'pg_user_categories',
    'orderby'    => 'name',
    'hide_empty' => 0,
));

foreach($ucat_terms as $term) {
	$uc[ $term->term_id ] = $term->name;
}




/*
// user categories array builder
//// fix for PCPP
if(function_exists('pcpp_is_integrated_flag')) {
	pcpp_is_integrated_flag();	
}

$raw = pc_static::restr_opts_arr($bulk_opts, $apply_filter);

$pc_restr_opts = array();
foreach($raw as $block) {
	foreach($block['opts'] as $id => $name) {
		$pc_restr_opts[$id] = $name;	
	}
}
*/





// structure
$defaults = array(
	'id' => array(
		'label'		=> esc_html__('Which form?', 'pc_ml'),
		'type'		=> 'select',
		'opts'		=> $rf,
		'default' 	=> current(array_keys($rf)),
		'panel'		=> 'main',
	),
	'layout' => array(
		'label'		=> esc_html__('Layout', 'pc_ml'),
		'type'		=> 'select',
		'opts'		=> array(
			''			=> esc_html__('Default one', 'pc_ml'),
			'one_col'	=> esc_html__('Single column', 'pc_ml'),
			'fluid'		=> esc_html__('Fluid (multi column)', 'pc_ml'),
		),
		'default' 	=> '',
		'panel'		=> 'main',
	),
	'custom_categories' => array(
		'label'		=> esc_html__('Custom categories assignment (ignored if field is in form)', 'pc_ml'),
		'type'		=> 'multi-opt',
		'opts'		=> $uc,
		'default' 	=> '',
		'panel'		=> 'main',
	),
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

register_block_type('lcweb/pc-registration-form', array(
	'editor_script' 	=> 'pc_registr_on_guten',
	'render_callback' 	=> 'pvtcont_registr_guten_handler',
	'attributes' 		=> $defaults
));


wp_localize_script('wp-blocks', 'pc_registr_defaults', $defaults);
