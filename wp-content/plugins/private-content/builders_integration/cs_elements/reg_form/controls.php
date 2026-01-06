<?php
if(!defined('ABSPATH')) {exit;}



/**
 * Element Controls
 */
 
 
// forms list
pc_reg_form_ct();
$reg_forms = get_terms(array(
    'taxonomy'   => 'pc_reg_form',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
));	

$reg_form_array = array();
foreach($reg_forms as $rf) {
	$reg_form_array[] = array(
		'value' => $rf->term_id,
		'label' => $rf->name
	);
}
 
 

/* FIELDS */
$fields = array(
	'fid' => array(
		'type'    => 'select',
		'ui' => array(
			'title'   => esc_html__('Which form to use?', 'pc_ml'),
			'tooltip' => '',
		),
		'options' => array(
			'choices' => $reg_form_array
		),
	),
	
	'layout' => array(
		'type'    => 'select',
		'ui' => array(
			'title'   => esc_html__('Layout', 'pc_ml'),
			'tooltip' => '',
		),
		'options' => array(
			'choices' => array(
				array('value' => '', 		'label' => esc_html__('Default one', 'pc_ml')),
				array('value' => 'one_col', 'label' => esc_html__('Single column', 'pc_ml')),
				array('value' => 'fluid',	'label' => esc_html__('Fluid (multi column)', 'pc_ml')),
			)
		)
	),
		
	'custom_categories' => array(
		'type'    => 'select',
		'ui' => array(
			'title'   => esc_html__('Custom categories assignment (ignored if field is in form)', 'pc_ml'),
			'tooltip' => '',
		),
		'options' => array(
			'choices' => (array)pc_cs_uc_array(false)
		)
	),
	
	'redirect' => array(
		'type'  => 'text',
		'ui' 	=> array(
			'title'   => esc_html__('Custom redirect (use a valid URL)', 'pc_ml'),
			'tooltip' => '',
		)
	),
	
	'align' => array(
		'type'    => 'select',
		'ui' => array(
			'title'   => esc_html__('Alignment', 'pc_ml'),
			'tooltip' => '',
		),
		'options' => array(
			'choices' => array(
				array('value' => 'center', 	'label' => esc_html__('Center', 'pc_ml')),
				array('value' => 'left', 	'label' => esc_html__('Left', 'pc_ml')),
				array('value' => 'right',	'label' => esc_html__('Right', 'pc_ml')),
			)
		)
	),
	
);


return $fields;
