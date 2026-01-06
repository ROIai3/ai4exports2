<?php
/* NFPCF */

// MANAGE COMMENTS RESTRICTION
if(!defined('ABSPATH')) {exit;}


add_filter('the_content', function($content) {
	global $post, $pc_users, $pc_restr_wizard;
	
	// WP user sync is required and comments must be open. On user pvt page there's already comments management
	if(!is_object($pc_users) || !$pc_users->wp_user_sync || isset($GLOBALS['pvtcont_user_reserved_page_is_displaying']) || !is_object($post) || !comments_open($post->ID)) {
		return $content;	
	}
	
	
	// get global restriction
	if(get_option('pg_lock_comments') && pc_user_check(array('all'), array(), true) !== 1) {
		
		$GLOBALS['pvtcont_restricted_comm_result'] = false;
		$GLOBALS['pvtcont_restricted_comm_matching'] = array('allow' => 'all', 'block' => array());
		
		
		add_filter('comments_template', 'pvtcont_comments_restriction_template', 750); 		
		
		return $content;	
	}
	
	
	
	// get specific restrictions
	if(!$pc_restr_wizard) {
        return $content;    
    }
    $post_restr = $pc_restr_wizard->get_entity_full_restr('post', $post->ID);
	if(isset($post_restr['comm_hide']) && ($restr_result = $pc_restr_wizard->user_passes_restr($post_restr['comm_hide'])) !== 1) {	
		
		$GLOBALS['pvtcont_restricted_comm_result'] = $restr_result;
		$GLOBALS['pvtcont_restricted_comm_matching'] = $pc_restr_wizard->last_matched_restr;
		
		add_filter('comments_template', 'pvtcont_comments_restriction_template', 750); 		
	}

	
	// PC-ACTION - restricted comments block is shown to user
	do_action('pc_restricted_comment_is_shown');	
	
	return $content;	
}, 750); // use 750 - before PC hide



// override comments template
function pvtcont_comments_restriction_template($template) {
	return PC_DIR ."/restrictions/comment_hack.php";
}
