<?php
if(!defined('ABSPATH')) {exit;}



/**
 * Shortcode handler
 */

if(!isset($id)) 		{$id = '';}
if(!isset($class)) 		{$class = '';}
if(!isset($style)) 		{$style = '';}
 
cs_atts( array('id' => $id, 'class' => $class, 'style' => $style ) );

$atts = array(
	'id' 				=> $fid, // fake index to not conflict with CS
	'layout' 			=> $layout,
	'custom_categories' => implode(',', (array)$custom_categories),
	'redirect' 			=> $redirect
);

$params = '';
foreach($atts as $key => $val) {
	$params .= ' '. $key .'="'. esc_attr($val) .'"';
}

echo do_shortcode('[pc-registration-form '. $params .']');
