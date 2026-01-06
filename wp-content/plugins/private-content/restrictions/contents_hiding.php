<?php
/* NFPCF */

// PAGE CONTENTS HIDING SYSTEM 
if(!defined('ABSPATH')) {exit;}


function pc_perform_contents_restriction($contents) { // Avada uses it - keep the function!
	global $post, $pc_restr_wizard;
	if(!$pc_restr_wizard || !isset($post->ID)) {
        return $contents;
    }
		
	
	// get specific restrictions
	$post_restr = $pc_restr_wizard->get_entity_full_restr('post', $post->ID);
    
	if(isset($post_restr['cont_hide']) && $pc_restr_wizard->user_passes_restr($post_restr['cont_hide']) !== 1) {	
		
		// restriction array blocking user - setup warning box shortcode
		$lmr = $pc_restr_wizard->last_matched_restr;
		$warn_box_sc =  '[pc-pvt-content allow="'. implode(',', $lmr['allow']) .'" block="'. implode(',', $lmr['block']) .'" warning="1"][/pc-pvt-content]'; 
	
	
		// what to show
		$is_page = (is_page() || is_single()) ? true : false;
		$wts = ($is_page) ? get_option('pc_chs_behavior', 'warning_box') : get_option('pc_chs_lists_behavior', 'no_contents');  
		
		// setup global var to automatically hide also comments
		if($is_page) {
			$GLOBALS['pvtcont_pag_contents_hidden'] = true;	
			add_filter('comments_template', 'pvtcont_cr_comments_restriction_template', 9999999);
		}
		
		// different restrictions type
		switch($wts) {
			case 'warning_box' :
				$contents = $warn_box_sc;
				$GLOBALS['pvtcont_cr_warning_shown'] = true;
				break;
				
			case 'no_contents' :
            default :    
				$contents = '';
				break;
			
			case 'excerpt' :
				$contents = (isset($post->post_excerpt)) ? wpautop($post->post_excerpt) : ''; 
				break;
				
			case 'excerpt_n_wb'	: // excerpt + warning box
				$contents = (isset($post->post_excerpt)) ? wpautop($post->post_excerpt) : ''; 
				$contents .= '<div class="excerpt_n_wb_spacer"></div>'. $warn_box_sc;
				
				$GLOBALS['pvtcont_cr_warning_shown'] = true;
				break;
			
			case 'cust_content' :
				$contents = wpautop( get_option('pc_chs_cust_content', ''));
				break;
				
			case 'excerpt_n_cc' : // excerpt + custom contents
				$contents = (isset($post->post_excerpt)) ? wpautop($post->post_excerpt) : '';
				$contents .= wpautop( get_option('pc_chs_cust_content', ''));
				break;
                
            case 'do_not_act' : // excerpt + custom contents
				return $contents;
				break;    
		}
	
		$contents = do_shortcode($contents);
	}
	
	
	return $contents;
}
add_filter('the_content', 'pc_perform_contents_restriction', 9999999); // use 9999999 - latest check




// override comments template if contents are hidden
function pvtcont_cr_comments_restriction_template($template) {
	return PC_DIR . "/restrictions/comment_hack.php";
}

