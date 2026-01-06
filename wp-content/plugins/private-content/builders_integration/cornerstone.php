<?php
/* NFPCF */
if(!defined('ABSPATH')) {exit;}


// user categories array builder for VC
function pc_cs_uc_array($bulk_opts = true, $apply_filter = true) {

	// fix for PCPP
	if(function_exists('pcpp_is_integrated_flag')) {
		pcpp_is_integrated_flag();	
	}
	
	
	$raw = pc_static::restr_opts_arr($bulk_opts, $apply_filter);
	
	$arr = array();
	foreach($raw as $block) {
		foreach($block['opts'] as $id => $name) {
			$arr[] = array(
				'value' => $id,
				'label' => $name
			);
		}
	}
	return $arr;
}


add_action('cornerstone_register_elements', 'pc_cornerstone_register_elements');
add_filter('cornerstone_icon_map', 'pc_cornerstone_icon_map', 900);


function pc_cornerstone_register_elements() {
	include_once(PC_DIR .'/main_includes/user_categories.php'); // be sure tax are registered
	pvtcont_user_cat_taxonomy();
	
	
	cornerstone_register_element('lcweb_pc_login', 			'lcweb_pc_login', 	PC_DIR .'/builders_integration/cs_elements/login');
	cornerstone_register_element('lcweb_pc_logout', 		'lcweb_pc_logout', 	PC_DIR .'/builders_integration/cs_elements/logout');
	cornerstone_register_element('lcweb_pc_pvt_content', 	'lcweb_pc_pvt_content', PC_DIR .'/builders_integration/cs_elements/pvt_content');
	cornerstone_register_element('lcweb_pc_reg_form', 		'lcweb_pc_reg_form', 	PC_DIR .'/builders_integration/cs_elements/reg_form');
}


function pc_cornerstone_icon_map( $icon_map ) {
	$icon_map['lcweb_pc_login'] 		= PC_URL .'/img/cs_icon.svg';
	$icon_map['lcweb_pc_logout'] 		= PC_URL .'/img/cs_icon.svg';
	$icon_map['lcweb_pc_pvt_content'] 	= PC_URL .'/img/cs_icon.svg';
	$icon_map['lcweb_pc_reg_form'] 		= PC_URL .'/img/cs_icon.svg';
	
	return $icon_map;
}
