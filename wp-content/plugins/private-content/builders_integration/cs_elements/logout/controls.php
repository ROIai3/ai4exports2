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
			'tooltip' => esc_html__('Set a custom redirect after logout - use a full page URL', 'pc_ml'),
		),
	),
);

return $fields;
