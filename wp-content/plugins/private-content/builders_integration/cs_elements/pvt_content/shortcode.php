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
	'allow' 	=> implode(',', (array)$allow),
	'block'		=> implode(',', (array)$block),
	'warning'	=> (int)$warning,
	'message'	=> $message
);

$params = '';
foreach($atts as $key => $val) {
	$params .= ' '. $key .'="'. esc_attr($val) .'"';
}

echo do_shortcode('[pc-pvt-content '. $params .']'. $content .'[/pc-pvt-content]');
