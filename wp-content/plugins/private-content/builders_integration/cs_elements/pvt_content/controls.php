<?php
if(!defined('ABSPATH')) {exit;}


/**
 * Element Controls
 */
 

/* FIELDS */
$fields =  array(
	'allow' => array(
		'type'    => 'multi-choose',
		'ui' => array(
			'title'   => esc_html__('Who can see contents?', 'pc_ml'),
			'tooltip' => '',
		),
		'options' => array(
			'choices' => pc_cs_uc_array()
		),
	),
	
	'block' => array(
		'type'    => 'multi-choose',
		'ui' => array(
			'title'   => esc_html__('Who to block? (optional)', 'pc_ml'),
			'tooltip' => '',
		),
		'options' => array(
			'choices' => pc_cs_uc_array(false)
		),
	),
	
	'warning' => array(
		'type'    => 'toggle',
		'ui' => array(
			'title'   => esc_html__('Show warning box?', 'pc_ml'),
			'tooltip' => esc_html__('Check to display a yellow warning box', 'pc_ml'),
		),
	),
	
	'message' => array(
		'type'    => 'text',
		'ui' => array(
			'title'   => esc_html__('Custom message for not allowed users', 'pc_ml'),
		),
	),
	
	'content' => array(
		'type'    => 'editor',
		'ui' => array(
			'title'   => esc_html__("Contents", "'pc_ml'"),
			'tooltip' => '',
		),
		'context' => 'content',
		'suggest' => ''
    ),
);



return $fields;
