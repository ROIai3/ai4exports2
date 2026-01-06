<?php
if(!defined('ABSPATH')) {exit;}


/**
 * Element Controls
 */
 

/* FIELDS */
$fields =  array(
	'redirect' => array(
		'type'    => 'text',
		'ui' => array(
			'title'   => esc_html__('Custom Redirect', 'pc_ml'),
			'tooltip' => esc_html__('Set a custom redirect after login - use a full page URL', 'pc_ml'),
		),
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
