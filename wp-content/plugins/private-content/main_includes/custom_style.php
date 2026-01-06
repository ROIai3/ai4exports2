<?php
////////////////////////////////////
// DYNAMICALLY CREATE THE CSS //////
////////////////////////////////////
if(!defined('ABSPATH')) {exit;}

// be sure they are included
include_once(PC_DIR .'/classes/pc_static.php');
include_once(PC_DIR .'/classes/messages_style.php');


// shortcut function
function pcgofc($opt_name, $echo = true) {
    return pc_static::get_opt_for_css($opt_name, $echo);
}




// v6 fields padding - vert/horiz
$fields_padding = pcgofc('pg_field_padding', false);
if(!is_array($fields_padding)) {
	$fields_padding = array($fields_padding, $fields_padding);	
}

// buttons padding
$btn_padding = pcgofc('pg_buttons_padding', false);

// form's shadow
$form_shadow = get_option('pg_forms_shadow');
$form_shadow_col = get_option('pg_forms_shadow_col', '#000000');
?>

/*****************************
 SUCCESS AND ERROR MESSAGES
 *****************************/
<?php 
if(class_exists('pvtcont_messages_style')) {
    pvtcont_messages_style::get_css( get_option('pg_messages_style', 'outlined_squared') ); 
}
?>


/***********************************
    GLOBAL ELEMENTS
 ***********************************/
  
/* containers style */
.pc_login_form:not(.pc_widget_login),
.pvtcont_form {
	background-color: <?php pcgofc('pg_forms_bg_col') ?>;
	color: <?php pcgofc('pg_label_col') ?>;	
}
.pc_login_form:not(.pc_widget_login),
.pvtcont_form {
	border: <?php pcgofc('pg_forms_border_w') ?>px solid <?php pcgofc('pg_forms_border_col') ?>;
    border-radius: <?php pcgofc('pg_form_border_radius') ?>px;

    <?php if($form_shadow == 'light') : ?>
        box-shadow: 0 2px 7px <?php echo esc_attr(pc_static::hex2rgba($form_shadow_col, '0.17')) ?>;

    <?php elseif($form_shadow == 'medium') : ?>
        box-shadow: 0 3px 10px <?php echo esc_attr(pc_static::hex2rgba($form_shadow_col, '0.25')) ?>;

    <?php elseif($form_shadow == 'heavy') : ?>
        box-shadow: 0 3px 15px <?php echo esc_attr(pc_static::hex2rgba($form_shadow_col, '0.4')) ?>;

    <?php endif; ?>
}



/* fields gap */
.pc_form_flist {
	<?php $fluid_fields_gap = (array)pcgofc('pg_reg_fblock_gap', false); ?>
    grid-gap: <?php echo absint($fluid_fields_gap[0]) ?>px <?php echo absint($fluid_fields_gap[1]) ?>px;
}


/* one-col form sizing */
.pc_one_col_form.pvtcont_form {
	max-width: <?php pcgofc('pg_onecol_form_max_w') ?>px;
}
@media screen and (max-width: <?php echo (int)pcgofc('pg_onecol_form_max_w', false) + 50 ?>px) { 
	.pc_one_col_form.pvtcont_form {
		max-width: 100%;   
	}
}



<?php 
// forms additional padding
$fap = pcgofc('pg_form_add_padding', false);
if(is_array($fap) && count($fap) == 2 && ((int)$fap[0] || (int)$fap[1])) :
?>
.pc_login_form,
.pvtcont_form {
    padding: <?php echo 19 + absint($fap[0]) ?>px <?php echo 24 + absint($fap[1]) ?>px;
}
.pc_login_form {
	padding: <?php echo 15 + absint($fap[0]) ?>px <?php echo 24 + absint($fap[1]) ?>px <?php echo 23 + absint($fap[0]) ?>px;	
}
.pc_nolabel .pc_login_form {
	padding: <?php echo 19 + absint($fap[0]) ?>px <?php echo 24 + absint($fap[1]) ?>px <?php echo 23 + absint($fap[0]) ?>px;		
}
.pc_fluid_form .pc_form_flist {
	padding-right: <?php echo absint($fap[1]) ?>px;
}
<?php endif; ?>



/* fields style */
.pc_form_field input, 
.pc_form_field textarea,
.pc_login_row input, 
.pcma_psw_username,
.lcslt-pc-skin .lcslt {
	background: <?php pcgofc('pg_fields_bg_col') ?>;
    border: <?php pcgofc('pg_field_border_w') ?>px solid <?php pcgofc('pg_fields_border_col') ?>;
    color: <?php pcgofc('pg_fields_txt_col') ?>;	
	padding: <?php echo absint($fields_padding[0]) ?>px <?php echo absint($fields_padding[1]) ?>px !important;
	border-radius: <?php pcgofc('pg_field_border_radius') ?>px !important;
}
.pc_form_field input:hover, .pc_form_field textarea:hover,
.pc_form_field input:active, .pc_form_field textarea:active,
.pc_form_field input:focus, .pc_form_field textarea:focus,
.pc_login_row input:hover, .pcma_psw_username:hover,
.pc_login_row input:active, .pcma_psw_username:active,
.pc_login_row input:focus, .pcma_psw_username:focus,
.lcslt-pc-skin .lcslt:not(.lcslt-disabled):hover, 
.lcslt-pc-skin .lcslt.lcslt_dd-open, 
.lcslt-pc-skin#lc-select-dd,
.lcslt-pc-skin .lcslt-search-li input {
	background: <?php pcgofc('pg_fields_bg_col_h') ?>;
    border: <?php pcgofc('pg_field_border_w') ?>px solid <?php pcgofc('pg_fields_border_col_h') ?>;
    color: <?php pcgofc('pg_fields_txt_col_h') ?>;		
}
.pc_login_form:not(.pc_widget_login) label, 
.pc_form_flist, 
.pc_form_flist label,
.pc_psw_helper {
	color: <?php pcgofc('pg_label_col') ?>;
}
.pvtcont_form .lcs_cursor {
    background: <?php pcgofc('pg_lcswitch_knob_col') ?>;
}
.pvtcont_form .lcs_switch.lcs_off {
    background: <?php pcgofc('pg_lcswitch_off_col') ?>;
}
.pvtcont_form .lcs_switch.lcs_on {
    background: <?php pcgofc('pg_lcswitch_on_col') ?>;
}

<?php if(get_option('pg_single_psw_f_w_reveal')) : ?>
.pc_f_psw .pc_field_icon,
.pc_lf_psw .pc_field_icon {
    cursor: pointer;
}
<?php endif; ?>

<?php if(get_option('pg_separator_margin')) : ?>
.pc_disclaimer_f_sep {
    margin-top: <?php pcgofc('pg_separator_margin') ?>px;
    margin-bottom: <?php pcgofc('pg_separator_margin') ?>px;
}
<?php endif; ?>





/* LC SELECT */
.lcslt-pc-skin#lc-select-dd li {
    color: <?php pcgofc('pg_fields_txt_col_h') ?>;		
    border-top: 1px solid <?php pcgofc('pg_fields_border_col_h') ?>;
}
.lcslt-pc-skin .lcslt.lcslt_dd-open {
    border-radius: <?php pcgofc('pg_field_border_radius') ?>px;
}
.lcslt-pc-skin .lcslt-search-li:before {
    background: <?php pcgofc('pg_fields_txt_col_h') ?>;
}
.lcslt-pc-skin#lc-select-dd {
    border-radius: 0 0 <?php pcgofc('pg_field_border_radius') ?>px <?php pcgofc('pg_field_border_radius') ?>px;
    border-width: 0 <?php pcgofc('pg_field_border_w') ?>px <?php pcgofc('pg_field_border_w') ?>px;
}
.lcslt-pc-skin .lcslt-search-li {
    border-bottom-color: <?php pcgofc('pg_fields_border_col_h') ?>;
    background: <?php pcgofc('pg_fields_bg_col_h') ?>;
}
.lcslt-pc-skin .lcslt-search-li input::-webkit-input-placeholder {
	color: <?php pcgofc('pg_fields_placeh_col') ?>;	
}
.lcslt-pc-skin .lcslt-search-li input::-moz-placeholder {
	color: <?php pcgofc('pg_fields_placeh_col') ?>;		
}
.lcslt-pc-skin .lcslt-multi-selected,
.lcslt-pc-skin .lcslt-multi-callout {
    background: <?php pcgofc('pg_fields_txt_col') ?>;
    color: <?php pcgofc('pg_fields_bg_col') ?>;
}
.lcslt-pc-skin .lcslt:not(.lcslt-disabled):hover .lcslt-multi-selected,
.lcslt-pc-skin .lcslt.lcslt_dd-open .lcslt-multi-selected,
.lcslt-pc-skin .lcslt:not(.lcslt-disabled):hover .lcslt-multi-callout,
.lcslt-pc-skin .lcslt.lcslt_dd-open .lcslt-multi-callout{
    background: <?php pcgofc('pg_fields_txt_col_h') ?>;
    color: <?php pcgofc('pg_fields_bg_col_h') ?>;
}
.lcslt-pc-skin .lcslt-multi-selected span,
.lcslt-pc-skin .lcslt-multi-callout {
    font-size: <?php echo esc_attr(((float)pcgofc('pg_fields_font_size', false) * 0.9) . get_option('pg_fields_font_size_type', 'px')) ?> !important;
}
.lcslt-pc-skin .lcslt-multi-callout {
    <?php $lcs_mc_val = 'calc('. ((float)pcgofc('pg_fields_font_size', false) * 0.9 . get_option('pg_fields_font_size_type', 'px')) .' + 7px) !important'; ?>	

    padding: 0 !important;
	width: <?php echo esc_attr($lcs_mc_val) ?>;
	height: <?php echo esc_attr($lcs_mc_val) ?>;
	text-align: center;
	line-height: <?php echo esc_attr($lcs_mc_val) ?>;
}
.lcslt-pc-skin .lcslt:not(.lcslt-multiple):after {
    border-top-color: <?php pcgofc('pg_fields_border_col') ?>;
}
.lcslt-pc-skin .lcslt:not(.lcslt-disabled):not(.lcslt-multiple):hover:after, 
.lcslt-pc-skin .lcslt.lcslt_dd-open:not(.lcslt-multiple):after {
    border-top-color: <?php pcgofc('pg_fields_border_col_h') ?>;
}




/* placeholders - requires one line per browser */
.pc_form_field *::-webkit-input-placeholder, 
.pc_login_row *::-webkit-input-placeholder {
	color: <?php pcgofc('pg_fields_placeh_col') ?>;	
}
.pc_form_field *::-moz-placeholder, 
.pc_login_row *::-moz-placeholder {
	color: <?php pcgofc('pg_fields_placeh_col') ?>;		
}

.pc_form_field *:hover::-webkit-input-placeholder, 
.pc_form_field *:focus::-webkit-input-placeholder, 
.pc_form_field *:active::-webkit-input-placeholder, 
.pc_login_row *:hover::-webkit-input-placeholder, 
.pc_login_row *:focus::-webkit-input-placeholder, 
.pc_login_row *:active::-webkit-input-placeholder {
	color: <?php pcgofc('pg_fields_placeh_col_h') ?>;	
}
.pc_form_field *:hover::-moz-input-placeholder, 
.pc_form_field *:focus::-moz-input-placeholder, 
.pc_form_field *:active::-moz-input-placeholder, 
.pc_login_row *:hover::-moz-input-placeholder, 
.pc_login_row *:focus::-moz-input-placeholder, 
.pc_login_row *:active::-moz-input-placeholder {
	color: <?php pcgofc('pg_fields_placeh_col_h') ?>;
}



/* field icons */
.pc_field_w_icon input {
	padding-left: <?php echo 35 + absint($fields_padding[1]) + 7 ?>px !important;	
}
.pc_field_icon {
    padding-right: <?php echo absint($fields_padding[1]) ?>px;
    text-indent: <?php echo absint($fields_padding[1]) ?>px;
    left: <?php pcgofc('pg_field_border_w') ?>px;
	top: <?php pcgofc('pg_field_border_w') ?>px;
	bottom: <?php pcgofc('pg_field_border_w') ?>px;
    border-radius: <?php pcgofc('pg_field_border_radius') ?>px 0 0 <?php pcgofc('pg_field_border_radius') ?>px;
    
	color: <?php pcgofc('pg_fields_icon_col') ?>;
    background: <?php pcgofc('pg_fields_icon_bg') ?>;
}
.pc_field_container:hover .pc_field_icon,
.pc_focused_field .pc_field_icon {
	color: <?php pcgofc('pg_fields_icon_col_h') ?>;
    background: <?php pcgofc('pg_fields_icon_bg_h') ?>;
}


/* custom checkbox */
.pc_checkbox {
	background: <?php pcgofc('pg_fields_bg_col') ?>;
    border-color: <?php pcgofc('pg_fields_border_col') ?>;
}
.pc_checkbox.pc_checked {
	border-color: <?php pcgofc('pg_fields_border_col_h') ?>;	
}
.pc_checkbox:before {
	background: <?php pcgofc('pg_fields_bg_col_h') ?>;
}
.pc_checkbox > span {
	color: <?php pcgofc('pg_fields_txt_col_h') ?>;
}


/* typography */
.pc_login_row label,
.pc_form_flist > section > label,
section.pc_single_check label {
	font-size: <?php echo esc_attr((float)pcgofc('pg_labels_font_size', false) . get_option('pg_labels_font_size_type', 'px')) ?>;
    line-height: normal;
}
.pc_form_field input, 
.pc_form_field textarea,
.pc_form_field .pc_check_label, 
.pc_login_row input, 
.pcma_psw_username,
.pc_field_icon i,
.lcslt-pc-skin .lcslt:not(.lcslt-multiple) span:not(.lcslt-multi-callout),
.lcslt-pc-skin .lcslt-multiple .lcslt-placeholder,
.lcslt-pc-skin#lc-select-dd li span {
	font-size: <?php echo esc_attr((float)pcgofc('pg_fields_font_size', false) . get_option('pg_fields_font_size_type', 'px')) ?> !important;
    line-height: normal !important;
}
<?php if(get_option('pg_forms_font_family')) : ?>
.pc_login_row label, .pc_form_field > label,
.pc_form_txt_block, .pc_disclaimer_ftxt,
.pc_auth_btn, .pc_reg_btn, .pc_logout_btn {
	font-family: "<?php pcgofc('pg_forms_font_family') ?>";
}
<?php endif; ?>


/* submit buttons */
.pc_login_form input[type="button"], .pc_login_form button, .pc_login_form input[type="button"]:focus, .pc_login_form button:focus,  
.pvtcont_form input[type="button"], .pvtcont_form input[type="button"]:focus,
.pvtcont_form button, .pvtcont_form button:focus,
.pc_logout_btn, .pc_logout_btn:focus,
.pc_warn_box_btn {
	background: <?php pcgofc('pg_btn_bg_col') ?> !important;
	border: <?php pcgofc('pg_btn_border_w') ?>px solid <?php pcgofc('pg_btn_border_col') ?> !important;
	border-radius: <?php pcgofc('pg_btn_border_radius') ?>px !important;
	box-shadow: none;
	color: <?php pcgofc('pg_btn_txt_col') ?> !important;	
    padding: <?php echo absint($btn_padding[0]) ?>px <?php echo absint($btn_padding[1]) ?>px !important;
    font-size: <?php echo esc_attr((float)pcgofc('pg_btns_font_size', false) . get_option('pg_btns_font_size_type', 'px')) ?> !important;
}
.pc_login_form input[type="button"]:hover, .pc_login_form input[type="button"]:active, 
.pc_login_form button:hover, .pc_login_form button:active, 
.pc_registration_form input[type="button"]:hover, .pc_registration_form input[type="button"]:active, 
.pc_registration_form button:hover, .pc_registration_form button:active, 
.pvtcont_form input[type="button"]:hover, .pvtcont_form input[type="button"]:active,
.pvtcont_form button:hover, .pvtcont_form button:active,
.pc_logout_btn:hover, .pc_logout_btn:active, 
.pc_spinner_btn:hover, .pc_spinner_btn:active, .pc_spinner_btn:focus,
.pc_warn_box_btn:hover {
	background: <?php pcgofc('pg_btn_bg_col_h') ?> !important;
	border-color: <?php pcgofc('pg_btn_border_col_h') ?> !important;
	color: <?php pcgofc('pg_btn_txt_col_h') ?> !important;
}
.pc_inner_btn:after {
    background: <?php pcgofc('pg_btn_txt_col_h') ?>;
}


/* warning box buttons */
.pc_warn_box_btn {
    background: <?php pcgofc('pg_mess_btn_bg_col') ?> !important;
	border: <?php pcgofc('pg_btn_border_w') ?>px solid <?php pcgofc('pg_mess_btn_border_col') ?> !important;
	color: <?php pcgofc('pg_mess_btn_txt_col') ?> !important;	
}
.pc_warn_box_btn:hover {
    background: <?php pcgofc('pg_mess_btn_bg_col_h') ?> !important;
	border-color: <?php pcgofc('pg_mess_btn_border_col_h') ?> !important;
	color: <?php pcgofc('pg_mess_btn_txt_col_h') ?> !important;	
}


/* disclaimer */
.pc_disclaimer_f_sep {
	border-color: <?php pcgofc('pg_fields_border_col') ?>;	
}


/* pagination progressbar */
.pc_form_pag_progress span,
.pc_form_pag_progress:before {
    background: <?php pcgofc('pg_fpp_bg') ?>;
    color: <?php pcgofc('pg_fpp_col') ?>;
}
.pc_form_pag_progress span.pc_fpp_active,
.pc_form_pag_progress i {
    background: <?php pcgofc('pg_fpp_bg_h') ?>;
    color: <?php pcgofc('pg_fpp_col_h') ?>;
}



/*********************************
   STANDARD LOGIN FORM ELEMENTS
 ********************************/
  
/* container message */
.pc_login_block p {
    border-radius: <?php pcgofc('pg_field_border_radius') ?>px;
}

/* login fields gap */
.pc_lf_username {
    margin-bottom: <?php pcgofc('pg_login_fields_gap') ?>px;
}
.pc_login_form:not(.has_pcma_psw_recovery.pc_rm_login):not(.pc_fullw_login_btns) #pc_auth_message:empty,
.pc_rm_login:not(.has_pcma_psw_recovery):not(.pc_fullw_login_btns) #pc_auth_message:empty {
	padding-bottom: <?php pcgofc('pg_login_fields_gap') ?>px;
}

/* login form smalls */
.pc_login_form:not(.pc_widget_login) .pc_login_smalls small {
	color: <?php pcgofc('pg_label_col') ?>;	
    opacity: 0.8;
}

/* show and hide recovery form trigger */
.pc_rm_login .pcma_psw_recovery_trigger {
	border-left-color: <?php pcgofc('pg_forms_border_col') ?>;	
}




/*********************************
        LIGHTBOX
 ********************************/
.pc_lightbox.mfp-bg {
    background: <?php pcgofc('pg_lb_overlay_col') ?>; 	
}
.pc_lightbox.mfp-bg.mfp-ready {
    opacity: <?php echo (absint(pcgofc('pg_lb_overlay_alpha', false)) / 100) ?>;	
}
.pc_lightbox .mfp-content {
    padding-right: <?php echo ((100 - absint(pcgofc('pg_lb_max_w', false))) / 2) ?>vw;
    padding-left: <?php echo ((100 - absint(pcgofc('pg_lb_max_w', false))) / 2)  ?>vw;	
}
.pc_lightbox_contents:not(.pc_only_form_lb) {
    padding: <?php pcgofc('pg_lb_padding') ?>px;	
    border-radius: <?php pcgofc('pg_lb_border_radius') ?>px;
    border: <?php echo esc_attr(pcgofc('pg_lb_border_w', false) .'px solid '. pcgofc('pg_lb_border_col', false)) ?>;
    background-color: <?php pcgofc('pg_lb_bg') ?>;
    color: <?php pcgofc('pg_txt_col') ?>;
}
.pc_lightbox_contents .mfp-close {
    background-color: <?php pcgofc('pg_lb_bg') ?>;
    color: <?php pcgofc('pg_txt_col') ?>;	
    border-radius: <?php pcgofc('pg_lb_border_radius') ?>px;
}
.pc_lightbox_contents {
    max-width: <?php pcgofc('pg_lb_max_w') ?>vw;
}
.pc_lightbox_contents .pc_fluid_form {
    max-width: calc(<?php pcgofc('pg_lb_max_w') ?>vw - <?php echo absint(pcgofc('pg_lb_padding', false)) * 2 ?>px - <?php echo absint(pcgofc('pg_lb_border_w', false)) * 2 ?>px);
}
@media screen and (max-width:1100px) {
    .pc_lightbox_contents .pc_fluid_form {
        max-width: calc(90vw - <?php echo absint(pcgofc('pg_lb_padding', false)) * 2 ?>px - <?php echo absint(pcgofc('pg_lb_border_w', false)) * 2 ?>px);
    }
}



<?php 
// PC-ACTION - print code into custom style CSS
do_action('pc_custom_style_css'); 
?>



<?php 
// custom CSS
echo esc_html(trim(get_option('pg_custom_css', '')));
?>