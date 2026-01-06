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
	'redirect' => $redirect,
);

$params = '';
foreach($atts as $key => $val) {
	$params .= ' '. $key .'="'. esc_attr($val) .'"';
}

echo do_shortcode('[pc-login-form '. $params .']');
