<?php
/* NFPCF */

// MENU ITEMS RESTRICTION
if(!defined('ABSPATH')) {exit;}


// SINGLE MENU ITEM CHECK
function pvtcont_single_menu_check($items, $item_id) {
	
	// security check - avoid endless recursions
	$GLOBALS['pvtcont_menu_restr_security_recurs_check']++; 
	if($GLOBALS['pvtcont_menu_restr_security_recurs_check'] >= 10) {
		return true;	
	}
	
	// process
	foreach($items as $item) {
		if($item->ID == $item_id) {

			// polylang language switche -skip to avoid server crash
			if(function_exists('pll__') && strpos($item->post_name, 'language-switcher') !== false) {
				continue;	
			}

			if($item->menu_item_parent) {
				$parent_check = pvtcont_single_menu_check($items, $item->menu_item_parent);	
				if(!$parent_check) {return false;}
			}

			// if allowed users array exist 
			if(isset($item->pc_hide_item) && is_array($item->pc_hide_item)) {
				$allowed = implode(',', $item->pc_hide_item);
				return (pc_user_check($allowed, '', true) === 1) ? true : false;
			}	
		}		
	}
	
	return true;
}



// HIDE MENU ITEMS IF USER HAS NO PERMISSIONS
add_action('wp_nav_menu_objects', function($items) {	
	$new_items = array();
		
	// full website lock 
	if((get_option('pg_complete_lock') && !get_option('pg_complete_lock_exclude_menu')) && pc_user_check('all', '', true) !== 1) {
		return $new_items;	
	}
	
	foreach($items as $item) {
		
		// skip if it's Polylang language switchwe
		if($item->url == '#pll_switcher' || $item->post_name == 'languages') {
			$new_items[] = $item; 
			continue;
		}


		if(isset($item->menu_item_parent) && $item->menu_item_parent) {
			$GLOBALS['pvtcont_menu_restr_security_recurs_check'] = 0;
			$parent_check = pvtcont_single_menu_check($items, $item->menu_item_parent);	
		}
		else {$parent_check = true;}
		
		if($parent_check) {

			// if allowed users array exist 
			if(isset($item->pc_hide_item) && is_array($item->pc_hide_item)) {
				$allowed = implode(',', $item->pc_hide_item);
				if(pc_user_check($allowed, '', true) === 1) {$new_items[] = $item;}	
			}
			else {$new_items[] = $item;}
		}
	}
	
	return $new_items;
}, 9999);

