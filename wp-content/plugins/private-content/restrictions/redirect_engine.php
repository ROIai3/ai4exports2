<?php
// REDIRECT ENGINE
if(!defined('ABSPATH')) {exit;}


// performs redirect restriction
add_action('template_redirect', function() {
	global $pc_restr_wizard;
	
    if(isset($_REQUEST['pc_logout'])) {
        return true;       
    }
    
    
	// main redirect page
	$orig_redirect_val = get_option('pg_redirect_page');
	$redirect_url = pc_man_redirects('pg_redirect_page');
	
	// blocked users redirect target page
	$blocked_users_url = pc_man_redirects('pg_blocked_users_redirect');
	$final_redirect_url = (pc_user_logged(false) && $blocked_users_url) ? $blocked_users_url : $redirect_url;
    $skip_antiloop_check = false;
    
    
    // special case: is viewing main redirect target page as logged user. Move users to "pg_blocked_users_redirect" if URL doesn't coincide with "pg_redirect_page"
    if(pc_user_logged(false) && $redirect_url != $final_redirect_url && is_page() && $GLOBALS['post']->ID == pc_static::wpml_translated_pag_id($orig_redirect_val)) {
        $skip_antiloop_check = true;    
    }
    
    
	// only if redirect option is setted and redirect URL is different from current one (to avoid loops)
	if(!$skip_antiloop_check && (empty($final_redirect_url) || $final_redirect_url == pc_static::curr_url())) {
		return false;
	}

	// do not act in elementor builder
	if(class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
		return false;	
	}
    
    // do not act for admin previewing user reserved page
    if(is_user_logged_in() && isset($_GET['pc_pvtpag']) && isset($_GET['pc_utok']) && pc_static::verify_nonce($_GET['pc_utok'], 'lcwp_nonce')) {
        return false;    
    }




	//////////////////////////////////////////////////////////////
	// complete website lock
	if(get_option('pg_complete_lock') && pc_user_check('all', '', true) !== 1) { // use pc_user_check to let WP users to test restrictions
		global $post;
		
		$excluded_pages = (filter_var($orig_redirect_val, FILTER_VALIDATE_INT)) ? array($orig_redirect_val) : array();
		$excluded_pages = array_merge($excluded_pages, (array)get_option('pg_complete_lock_excluded_pages', array()));
		
		// PC-FILTER - add page IDS to exclude from complete site lock - page IDs array
		$excluded_pages = apply_filters('pc_complete_lock_exceptions', $excluded_pages);
		
		// exceptions check
		foreach((array)$excluded_pages as $pag_id) {				
			if($pag_id == $post->ID) {
				$exception_page = true;
				break;		
			}
			
			// WPML integration - if current page is translation of an exception
			elseif(pc_static::wpml_translated_pag_id($pag_id) == $post->ID) {
				$exception_page = true;
				break;	
			}
		}
		
		if(!isset($exception_page)) {
			pvtcont_redirect($final_redirect_url);
		}	
	}
	
	
	
	//////////////////////////////////////////////////////////////
	// single page/post redirect
	else if(is_page() || is_single()) {
		global $post;				
		
        if(is_object($post)) {
        
            // PC-ACTION - forces page restriction - passes false and page object
            if(apply_filters('pc_forced_page_restriction', false, $post)) {
                pvtcont_redirect($final_redirect_url);		
            }

            $post_restr = $pc_restr_wizard->get_entity_full_restr('post', $post->ID); 

            if(isset($post_restr['redirect']) && $pc_restr_wizard->user_passes_restr($post_restr['redirect']) !== 1) {
                pvtcont_redirect($final_redirect_url);
            }
        }
	}
	
	
	
	//////////////////////////////////////////////////////////////
	// if is category or archive
	else if(is_category() || is_archive()) {
		$term_id = get_query_var('cat');
		
		// PC-ACTION - forces term restriction - passes false and term ID
		if(apply_filters('pc_forced_term_restriction', false, $term_id)) {
			pvtcont_redirect($final_redirect_url);
		}
		
		$term_data = pc_static::term_obj_from_term_id($term_id);
		$term_restr = $pc_restr_wizard->get_entity_full_restr('term', $term_id, $term_data);

		if(isset($term_restr['redirect']) && $pc_restr_wizard->user_passes_restr($term_restr['redirect']) !== 1) {
			pvtcont_redirect($final_redirect_url);
		}
	}
	
	
	
	//////////////////////////////////////////////////////////////
	// WooCommerce category
	if(function_exists('is_product_category') && is_product_category()) {
		$term_slug = get_query_var('product_cat');
		$term_data = get_term_by('slug', $term_slug, 'product_cat');

		if($term_data) {
			$term_restr = $pc_restr_wizard->get_entity_full_restr('term', $term_id, $term_data);

			if(isset($term_restr['redirect']) && $pc_restr_wizard->user_passes_restr($term_restr['redirect']) !== 1) {
				pvtcont_redirect($final_redirect_url);
			}
		}
	}


	
	//////////////////////////////////////////////////////////////
	//// PC-FILTER custom restriction (URL based) - associative array('url' => array('allowed' => , 'blocked' => ))
	// URL passes through preg_match, then supports regular expressions
	$restrictet_urls = apply_filters('pc_custom_restriction', array());
    
	if(is_array($restrictet_urls) && count($restrictet_urls)) {
		$curr_url = pc_static::curr_url();
		
		foreach((array)$restrictet_urls as $url => $val) {
			if(!isset($val['allowed']) || empty($val['allowed'])) {continue;}
			$blocked = (isset($val['blocked'])) ? $val['blocked'] : ''; 
			
			// differentiate between URL and regexp
			if(filter_var($url, FILTER_VALIDATE_URL)) {
				
				// strip arguments if are not included in restricted URL
				if(strpos($url, '?') === false && strpos($curr_url, '?') !== false) {
					$raw = explode('?', $curr_url);
					$curr_url = $raw[0];	
				}
				
				if($url == $curr_url) {
					if(pc_user_check($val['allowed'], $blocked, true) !== 1) {
						pvtcont_redirect($final_redirect_url);
					}		
				}
			}
			
			else {
                $url = str_replace('/', '\/', $url);
                $url = str_replace('\\/', '\/', $url);
                
				// sanitize for patterns
				if(substr($url, 0, 1) != '/') {
                    $url = '/'. $url;
                }
				if(substr($url, -1) != '/') {
                    $url .= '/';
                }
                
				if(preg_match((string)$url, $curr_url)) {
					if(pc_user_check($val['allowed'], $blocked, true) !== 1) {
						pvtcont_redirect($final_redirect_url);
					}	
				}
			}
		}	
	}
}, 2);






/* Performs the redirect - also sets last restricted page's URL for login redirect */
function pvtcont_redirect($redir_url, $no_lru = false) {
    
    /* NFPCF */
	if(get_option('pg_redirect_back_after_login') && pc_static::curr_url() && !$no_lru) {
        $expir = (int)gmdate('u') + (60 * 60 * 24);
        pc_static::setcookie('pc_last_restricted_page', pc_static::curr_url(), $expir); // 1 hour
        
        $secure = (is_ssl() && 'https' === wp_parse_url(get_option('home'), PHP_URL_SCHEME)) ? 'true' : 'false';
        header("Set-cookie: pc_last_restricted_page=". pc_static::curr_url() ."; expires=". $expir ." GMT; path=". COOKIEPATH ."; HttpOnly; secure=". $secure ."; SameSite=Lax"); // forces the cookie even with the PHP redirect 
	}	
	
	wp_safe_redirect($redir_url);
	exit;
}



/////////////////////////////////////////////////////////////////////



// CUSTOM URL-BASED RESTRICTIONS (settings driven) /* NFPCF */
add_filter('pc_custom_restriction', function($restrictions) {
	if(is_admin()) {return $restrictions;}
	
	$urls = (array)get_option('pg_cr_url', array());
	if(empty($urls)) {return $restrictions;}
	
	$allow = (array)get_option('pg_cr_allow', array()); 
	$block = (array)get_option('pg_cr_block', array()); 
	
	$a = 0;
	foreach($urls as $url) {
		if(isset($allow[$a]) && !empty($allow[$a])) {
			$restrictions[$url] = array(
				'allowed' => implode(',', (array)$allow[$a]), 
				'blocked' => (isset($block[$a]) && is_array($block[$a])) ? implode(',', (array)$block[$a]) : array()
			);	
		}
		$a++;
	}
	
	return $restrictions;
}, 100);



///////////////////////////////////////////////////////////////////////



// REMOVE RESTRICTED TERMS / POSTS FROM WP_QUERY
add_filter('pre_get_posts', function($query) {
	global $pc_restr_wizard;
	
	if($query->is_admin || isset($GLOBALS['pvtcont_is_fetching_posts_restr'])) {
        return $query;
    }
	if(($query->is_admin && !defined('DOING_AJAX')) || $query->is_single || $query->is_page || !isset($GLOBALS['pvtcont_query_filter_post_array'])) {
        return $query;
    }
    
	// PC-FILTER - alterate posts restriction cache - passes array defined in classes/posts_restr_cache.php
	$pr = apply_filters('pc_posts_restr', $GLOBALS['pvtcont_query_filter_post_array']);
	if(empty($pr)) {
        return $query;
    }
	
	
	// check one by one
	$to_remove = array();
	foreach($pr as $post_id => $restr) {
		if($pc_restr_wizard->user_passes_restr($restr) !== 1) {
			$to_remove[] = $post_id;		
		}
	}
	
	
	// apply
	if(!empty($to_remove)) {

        // consider already excluded pages
		$to_remove = array_merge((array)$query->get('post__not_in'), $to_remove);
		$query->set('post__not_in', $to_remove);	
	}
	
	return $query;
}, 99999999);



////////////////////////////////////////////////////////////////////////////////////////////////////



// REMOVE TERMS FROM CATEGORIES WIDGET
add_filter('widget_categories_args', function($cat_args) {
	global $pvtcont_query_filter_post_array;
	
	// remove restricted terms
	$exclude_cats = pvtcont_query_filter_cat_array(); 
	if(count($exclude_cats) > 0) {
		if (isset($cat_args['exclude']) && $cat_args['exclude']) {
			$cat_args['exclude'] = $cat_args['exclude'] . ',' . implode(',', $exclude_cats);
		} else {
			$cat_args['exclude'] = implode(',', $exclude_cats);
		}
	}
	   
	return $cat_args;
}, 10, 1 );



// create an array of restricted terms
function pvtcont_query_filter_cat_array() {
	
	// be sure class is initialized
	if(!isset($GLOBALS['pc_restr_wizard'])) {
		include_once(PC_DIR .'/classes/restrictions_wizard.php');
		pc_init_restr_wizard();	
	}
	global $pc_restr_wizard;
	

	if(isset($GLOBALS['pvtcont_query_filter_cat_array'])) {
		return $GLOBALS['pvtcont_query_filter_cat_array']; // cache	
	}
	
	$exclude_array = array();
    $terms = get_terms( array(
        'taxonomy'   => pc_static::affected_tax(),
        'hide_empty' => 0,
    ));
    
	foreach($terms as $term) { 
		$term_restr = $pc_restr_wizard->get_entity_full_restr('term', $term->term_id, $term);
		if(!is_array($term_restr) || !isset($term_restr['redirect'])) {continue;}

		if($pc_restr_wizard->user_passes_restr($term_restr['redirect']) !== 1) {
			$exclude_array[] = '-'.$term->term_id;
		}	
	}
	
	// PC-FILTER - these categories will be hidden from queries - passes array of already restricted categories ID (negative numbers)
	$exclude_array = apply_filters('pc_cats_wp_query_filter', $exclude_array);
	
	$GLOBALS['pvtcont_query_filter_cat_array'] = $exclude_array;
	return $exclude_array;	
}

