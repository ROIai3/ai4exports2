<?php
// ARRAY CONTAINING OPTION VALUES TO SETUP PRESET STYLES
if(!defined('ABSPATH')) {exit;}


// preset style names
function pc_preset_style_names() {
	return array(
		'minimal' => esc_html__("Minimal", 'pc_ml'),
		'light'   => esc_html__("Light", 'pc_ml'),
		'dark'	  => esc_html__("Dark", 'pc_ml'),
	);			
}


// option values to apply
function pc_preset_styles_data($style = '') {
	$styles = array();
	
	// NB: only fields with difference from standard values and affecting styles are set
    
    
	/*** MINIMAL ***/
	$styles['minimal'] = array(
        'pg_form_add_padding'   => array(0, 0),
		'pg_field_padding'      => array(7, 7),
        'pg_buttons_padding'    => array(7, 15),
        
        'pg_forms_border_w'     => 1,
        'pg_field_border_w'     => 1,
        'pg_btn_border_w'       => 1,
        'pg_form_border_radius' => 0,
        'pg_field_border_radius'=> 1,
        'pg_btn_border_radius'  => 0,
        
        'pg_forms_bg_col'       => '#fefefe',
        'pg_forms_border_col'   => '#ebebeb',
        'pg_label_col'          => '#373737',
        
        'pg_fields_bg_col'      => '#fefefe',
        'pg_fields_border_col'  => '#cfcfcf',
        'pg_fields_placeh_col'  => '#888888',
        'pg_fields_txt_col'     => '#808080',
        'pg_fields_icon_col'    => '#808080',
        'pg_fields_icon_bg'     => '#f6f6f6',
        
        'pg_fields_bg_col_h'    => '#ffffff',
        'pg_fields_border_col_h'=> '#aaaaaa',
        'pg_fields_placeh_col_h'=> '#929292',
        'pg_fields_txt_col_h'   => '#333333',
        'pg_fields_icon_col_h'  => '#636363',
        'pg_fields_icon_bg_h'   => '#f1f1f1',
        
        'pg_btn_bg_col'         => '#f4f4f4',
        'pg_btn_border_col'     => '#dddddd',
        'pg_btn_txt_col'        => '#444444',
        'pg_btn_bg_col_h'       => '#efefef',
        'pg_btn_border_col_h'   => '#cacaca',
        'pg_btn_txt_col_h'      => '#111111',
        
        'pg_fpp_bg'             => '#e4e4e4',
        'pg_fpp_col'            => '#373737',
        'pg_fpp_bg_h'           => '#74b945',
        'pg_fpp_col_h'          => '#ffffff',
	);
    
    
    
	/*** LIGHT ***/
	$styles['light'] = array(
		'pg_form_add_padding'   => array(0, 0),
		'pg_field_padding'      => array(7, 7),
        'pg_buttons_padding'    => array(8, 15),
        
        'pg_forms_border_w'     => 1,
        'pg_field_border_w'     => 1,
        'pg_btn_border_w'       => 1,
        'pg_form_border_radius' => 4,
        'pg_field_border_radius'=> 3,
        'pg_btn_border_radius'  => 2,
        
        'pg_forms_bg_col'       => '#FAFAFA',
        'pg_forms_border_col'   => '#dddddd',
        'pg_label_col'          => '#333333',
        
        'pg_fields_bg_col'      => '#fefefe',
        'pg_fields_border_col'  => '#bbbbbb',
        'pg_fields_placeh_col'  => '#888888',
        'pg_fields_txt_col'     => '#808080',
        'pg_fields_icon_col'    => '#f8f8f8',
        'pg_fields_icon_bg'     => '#909090',
         
        'pg_fields_bg_col_h'    => '#ffffff',
        'pg_fields_border_col_h'=> '#999999',
        'pg_fields_placeh_col_h'=> '#929292',
        'pg_fields_txt_col_h'   => '#333333',
        'pg_fields_icon_col_h'  => '#ffffff',
        'pg_fields_icon_bg_h'   => '#7f7f7f',
        
        'pg_btn_bg_col'         => '#efefef',
        'pg_btn_border_col'     => '#bdbdbd',
        'pg_btn_txt_col'        => '#444444',
        'pg_btn_bg_col_h'       => '#e8e8e8',
        'pg_btn_border_col_h'   => '#aaaaaa',
        'pg_btn_txt_col_h'      => '#222222',
        
        'pg_fpp_bg'             => '#e4e4e4',
        'pg_fpp_col'            => '#373737',
        'pg_fpp_bg_h'           => '#74b945',
        'pg_fpp_col_h'          => '#ffffff',
	);
    
    
    
    /*** DARK ***/
	$styles['dark'] = array(
		'pg_form_add_padding'   => array(0, 0),
		'pg_field_padding'      => array(7, 7),
        'pg_buttons_padding'    => array(8, 15),
        
        'pg_forms_border_w'     => 1,
        'pg_field_border_w'     => 1,
        'pg_btn_border_w'       => 1,
        'pg_btn_border_w'       => 1,
        'pg_field_border_radius'=> 2,
        'pg_btn_border_radius'  => 2,
        
        'pg_forms_bg_col'       => '#4f4f4f',
        'pg_forms_border_col'   => '#5a5a5a',
        'pg_label_col'          => '#fdfdfd',
        
        'pg_fields_bg_col'      => '#474747',
        'pg_fields_border_col'  => '#797979',
        'pg_fields_placeh_col'  => '#bbbbbb',
        'pg_fields_txt_col'     => '#dddddd',
        'pg_fields_icon_col'    => '#efefef',
        'pg_fields_icon_bg'     => '#777777',
        
        'pg_fields_bg_col_h'    => '#3f3f3f',
        'pg_fields_border_col_h'=> '#888888',
        'pg_fields_placeh_col_h'=> '#b8b8b8',
        'pg_fields_txt_col_h'   => '#fafafa',
        'pg_fields_icon_col_h'  => '#ffffff',
        'pg_fields_icon_bg_h'   => '#7f7f7f',
        
        'pg_btn_bg_col'         => '#555555',
        'pg_btn_border_col'     => '#878787',
        'pg_btn_txt_col'        => '#f3f3f3',
        'pg_btn_bg_col_h'       => '#484848',
        'pg_btn_border_col_h'   => '#333333',
        'pg_btn_txt_col_h'      => '#fdfdfd',
        
        'pg_fpp_bg'             => '#999999',
        'pg_fpp_col'            => '#f2f2f2',
        'pg_fpp_bg_h'           => '#74b945',
        'pg_fpp_col_h'          => '#ffffff',
	);

    
    
    // PC-FILTER - allows preset styles data extension
    $styles = (array)apply_filters('pc_preset_styles_data', $styles);
    
		
	if(empty($style)) {
        return $styles;
    } else {
		return (isset($styles[$style])) ? $styles[$style] : false;
	}	
}




// override only certain indexes to write less code
function pc_ps_override_indexes($array, $to_override) {
	foreach($to_override as $key => $val) {
		$array[$key] = $val;	
	}
	
	return $array;
}

