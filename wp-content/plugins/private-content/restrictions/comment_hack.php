<?php
/* NFPCF */

// empty page to override comments template - checks comments restriction parameter
if(!defined('ABSPATH')) {exit;}


if(get_option('pg_hc_warning') && isset($GLOBALS['pvtcont_restricted_comm_result']) && !isset($GLOBALS['pvtcont_cr_warning_shown']) && !isset($GLOBALS['pvtcont_pag_contents_hidden'])) {

	$rcr = $GLOBALS['pvtcont_restricted_comm_result'];
	$rcm = $GLOBALS['pvtcont_restricted_comm_matching'];
	
	$key = ($rcr === 2) ? 'pc_default_hcwp_mex' : 'pc_default_hc_mex'; 
	$txt = pc_get_message($key);

	echo do_shortcode('[pc-pvt-content allow="'. esc_attr(implode(',', (array)$rcm['allow'])) .'" block="'. esc_attr(implode(',', (array)$rcm['block'])) .'" message="'. esc_attr($txt) .'" warning="1"][/pc-pvt-content]');
}
