<?php
// RETURNING THE CSS CODE TO SHAPE UP SUCCESS/ERROR/WARNING MESSSAGES
if(!defined('ABSPATH')) {exit;}


class pvtcont_messages_style {
    
    //**/
    public static function get_css($style = false, $no_square_roundings = false) {
        switch($style) {
         
case 'outlined_w_icon' :
default :
?>
.pc_error_mess,
.pc_success_mess {
	border-style: solid;
	border-color: #999;
	border-width: 2px 2px 2px 43px;
	border-radius: 2px;
}
.pc_error_mess:before,
.pc_success_mess:before {
    color: #fff;
    font-family: "Font Awesome 5 Free", "fontawesome";
    font-weight: 900;
    font-size: 24px;
    line-height: 26px;
    position: absolute;
    text-align: center;
	width: 42px;
	left: -42px;
	text-shadow: 0 0 8px rgba(0, 0, 0, 0.05);
    z-index: 10;
}
.pc_error_mess > span,
.pc_success_mess > span {
    padding-left: 3px;
}
.pc_error_mess:after,
.pc_success_mess:after {
	position: absolute;
	content: "";
	left: -43px;
	top: -2px;
	bottom: -2px;
	background: linear-gradient(115deg, #ea0606 0%, #c33 60%);
	width: 43px;
	z-index: 5;
}
.pc_success_mess:after {
    background: linear-gradient(115deg, #50b020 0%, #4d9629 60%);
}

.pc_error_mess {
	border-color: #cc3333;
}
.pc_success_mess {
    border-color: #4d9629;
}
.pc_error_mess:before {
    content: "\f057";	
}
.pc_success_mess:before {
	content: "\f058";	
}
.pc_error_mess,
.pc_success_mess {
	background-color: <?php pc_static::get_opt_for_css('pg_forms_bg_col', true) ?>;
	color: <?php pc_static::get_opt_for_css('pg_label_col', true) ?>;	
}

.pc_warn_box {
	border-color: #ffcc47;
    border-style: solid;
    border-width: 4px 4px 4px 56px;
}
.pc_warn_box:before {
	content: "\f06a";
	font-family: "Font Awesome 5 Free", "fontawesome";
    font-weight: 900;
	position: absolute;
	left: -52px;
	top: 50%;
	width: 50px;
	text-align: center;
	height: 30px;
	color: #fff;
	font-size: 30px;
	line-height: 26px;
	margin-top: -13px;
	text-shadow: 2px 2px 6px rgba(100, 100, 100, 0.05);
    z-index: 10;
}
.pc_warn_box:after {
    position: absolute;
    content: "";
    left: -54px;
    top: -4px;
    bottom: -4px;
    background: linear-gradient(115deg, #ffc41d 0%, #ffcc47 60%);
    width: 54px;
    z-index: 5;
}
<?php             
break;
                
        
                
  
case 'soft_colors' :
?>
.pc_error_mess, 
.pc_success_mess,
.pc_warn_box {
    border-width: 1px;
    border-style: solid;
}
.pc_error_mess {
    border-color: #f1aeb5;
    color: #58151C;
    background: #f8d7da;
}
.pc_success_mess {
    border-color: #a3cfbb;
    color: #0a3622;
    background: #d1e7dd;
}
.pc_warn_box {
    border-color: #ffe69c;
    color: #664d03;
    background: #fff3cd;
    padding: 15px 20px; 
}
.pc_error_mess > span,
.pc_success_mess > span {
    padding-left: 5px;
    padding-right: 5px;
}
<?php
break;
                                
        
                
  
case 'soft_colors_w_icon' :
    self::get_css('soft_colors');            
?>
.pc_error_mess,
.pc_success_mess {
    padding-left: 40px;
}
.pc_error_mess ul, 
.pc_success_mess ul {
    margin-left: 12px !important;
    position: relative;
}
.pc_error_mess ul:before, 
.pc_success_mess ul:before {
    content: "";
    position: absolute;
    opacity: 0.6;
    left: -10px;
    top: 0;
    bottom: 0;
    width: 1px;
    background: #986368;
}
.pc_success_mess ul:before {
    background: #5a7c6c;
}
.pc_error_mess:before,
.pc_success_mess:before,
.pc_warn_box:before {
    opacity: 0.6;
}
.pc_error_mess:before,
.pc_success_mess:before {
    font-family: "Font Awesome 5 Free", "fontawesome";
    font-weight: 900;
    font-size: 22px;
    line-height: 24px;
    position: absolute;
    text-align: center;
	width: 40px;
	left: 0;
    z-index: 10;
}
.pc_error_mess:before {
    content: "\f057";
    color: #58151C;
}
.pc_success_mess:before {
	content: "\f058";
    color: #0a3622;
}

.pc_warn_box {
    padding-left: 70px;
}
.pc_warn_box:before {
	content: "\f06a";
	font-family: "Font Awesome 5 Free", "fontawesome";
    font-weight: 900;
	position: absolute;
	left: 4px;
	top: 50%;
	width: 50px;
	text-align: center;
	height: 30px;
	color: #664d03;
	font-size: 30px;
	line-height: 26px;
	margin-top: -13px;
    z-index: 10;
}
.pc_warn_box:after {
	content: "";
	position: absolute;
	left: 54px;
	top: 15px;
	bottom: 15px;
    opacity: 0.3;
	border-right: 1px solid #664d03;
}
<?php
break;                
   
                
                
                
case 'bold_colors' :
?>
.pc_error_mess, 
.pc_success_mess,
.pc_warn_box,
.pc_error_mess:before,
.pc_success_mess:before,
.pc_warn_box:before {
    color: #fff;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.05);
}
.pc_error_mess, 
.pc_success_mess,
.pc_warn_box {
    box-shadow: 0px 0 7px rgba(0,0,0,0.1);
}
.pc_error_mess {
	background: linear-gradient(15deg, #c01414 0%, #e23535 70%);
    border-bottom: 4px solid #a21515;
}
.pc_success_mess {
    background: linear-gradient(15deg, #3b8318 0%, #58a333 70%);
    border-bottom: 4px solid #3e7622;
}
.pc_warn_box {
	background: linear-gradient(15deg, #df9c00 40%, #e7ab1b 100%);
	padding: 15px 20px;
	text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    border-bottom: 6px solid #c18800;
    color: #fcfcfc;
}
.pc_warn_box .pc_warn_box_btn_wrap {
    text-shadow: none;
}
.pc_error_mess > span,
.pc_success_mess > span {
    padding-left: 5px;
    padding-right: 5px;
}
<?php             
break;

                
                
                
case 'bold_colors_w_icon' :
    self::get_css('bold_colors');            
?>     
.pc_error_mess,
.pc_success_mess,
.pc_warn_box {
    border-bottom: none !important;
}
.pc_error_mess,
.pc_success_mess {
    padding-left: 45px;
}
.pc_error_mess ul, 
.pc_success_mess ul {
    margin-left: 6px !important;
}
.pc_error_mess:before,
.pc_success_mess:before,
.pc_warn_box:before {
    opacity: 0.9;
}
.pc_error_mess:before,
.pc_success_mess:before {
    font-family: "Font Awesome 5 Free", "fontawesome";
    font-weight: 900;
    font-size: 16px;
    line-height: 24px;
    position: absolute;
    text-align: center;
	width: 40px;
	left: 0;
    z-index: 10;
}
.pc_error_mess:after,
.pc_success_mess:after {
	content: "";
	position: absolute;
    width: 40px;
	left: 0;
	top: 0;
	bottom: 0;
    background: rgba(0,0,0,0.2);
    z-index: 5;
}
.pc_error_mess:before {
    content: "\f00d";
}
.pc_success_mess:before {
	content: "\f00c";
}

.pc_warn_box {
    padding-left: 70px;
}
.pc_warn_box:before {
	content: "\f12a";
	font-family: "Font Awesome 5 Free", "fontawesome";
    font-weight: 900;
	position: absolute;
	left: 4px;
	top: 50%;
	width: 50px;
	text-align: center;
	height: 30px;
	font-size: 22px;
	line-height: 26px;
	margin-top: -13px;
    z-index: 10;
}
.pc_warn_box:after {
	content: "";
	position: absolute;
    width: 56px;
	left: 0;
	top: 0;
	bottom: 0;
    background: rgba(0,0,0,0.2);
    z-index: 5;
}
<?php             
break;  
                
   
                
                
case 'minimal' :
?>
.pc_error_mess, 
.pc_success_mess,
.pc_warn_box {
	background-color: <?php pc_static::get_opt_for_css('pg_forms_bg_col', true) ?>;
	color: <?php pc_static::get_opt_for_css('pg_label_col', true) ?>;	
}
.pc_error_mess,
.pc_success_mess {
    box-shadow: 0px 1px 6px #d1d1d1;
}
.pc_warn_box {
    padding: 12px 15px 15px 73px;
    box-shadow: 0px 4px 15px rgba(182, 141, 36, 0.5);
}
.pc_error_mess,
.pc_success_mess {
    padding-left: 51px;
}
.pc_error_mess:before,
.pc_success_mess:before,
.pc_warn_box:before {
    text-shadow: 0 0 3px rgba(0,0,0,0.05);
}
.pc_error_mess:before,
.pc_success_mess:before {
    font-family: "Font Awesome 5 Free", "fontawesome";
    font-weight: 900;
    font-size: 20px;
    line-height: normal;
    position: absolute;
	width: 40px;
	left: 0;
    text-align: center;
    z-index: 10;
}
.pc_error_mess:before {
    content: "\f057";
    color: #cc3333;
}
.pc_success_mess:before {
	content: "\f058";
    color: #4d9629;
}
.pc_error_mess:after,
.pc_success_mess:after {
	content: "";
    position: absolute;
    left: 39px;
    top: 0;
    width: 1px;
    bottom: 0;
    border-right: 1px solid #e3e3e3;
}

.pc_warn_box:before {
	content: "\f06a";
	font-family: "Font Awesome 5 Free", "fontawesome";
	font-weight: 900;
	color: #ffbe14;
	position: absolute;
	left: 4px;
	top: 50%;
	width: 50px;
	text-align: center;
	height: 30px;
	font-size: 28px;
	line-height: 26px;
	margin-top: -13px;
	z-index: 10;
}
.pc_warn_box:after {
	content: "";
    position: absolute;
    left: 55px;
    width: 1px;
    top: 0;
    bottom: 0;
    border-right: 1px solid #e3e3e3;
}
<?php             
break;
                
                
        }
        
        if(!$no_square_roundings) {
            self::squared_rounding_css();   
        }  
    }
    
    
    
    
    
    
    /* getting the CSS code to follow forms/fields roundings and apply it to mesages */
    private static function squared_rounding_css() {
        $forms_radius = pc_static::get_opt_for_css('pg_form_border_radius', false) .'px';
        $fields_radius = pc_static::get_opt_for_css('pg_field_border_radius', false) .'px';
        
        ?>
.pc_error_mess,
.pc_success_mess {
 	border-radius: <?php echo esc_attr($fields_radius) ?>;
}
.pc_error_mess:after,
.pc_success_mess:after {
 	border-radius: <?php echo esc_attr($fields_radius) ?> 0 0 <?php echo esc_attr($fields_radius) ?>;
}
.pc_warn_box {
    border-radius: <?php echo esc_attr($forms_radius) ?>;
}
.pc_warn_box:after {
    border-radius: <?php echo esc_attr($forms_radius) ?> 0 0 <?php echo esc_attr($forms_radius) ?>;
}
        <?php
    }
    
    
    
}
