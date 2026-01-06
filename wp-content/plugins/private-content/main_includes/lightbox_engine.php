<?php
/* NFPCF */
// TOOLSET TO MANAGE AND RENDER LIGHTBOX IN PAGES
if(!defined('ABSPATH')) {exit;}


// list lightboxes class to check and global var to know which one is loaded
add_action('wp_head', function() {
	if(!isset($GLOBALS['pvtcont_has_lightboxes'])) {
        return false;
    }
	
	$lb_instances = get_terms(array(
        'taxonomy'   => 'pc_lightboxes',
        'hide_empty' => 0,
        'order'      => 'ASC',
    ));
    
	$GLOBALS['pc_lb_instances'] = $lb_instances;
	
	$classes = array();
	foreach($lb_instances as $inst) {
		$classes[] = '.pc_lb_trig_'.$inst->term_id;	
	}
	
    echo '<script type="text/javascript">window.pc_lb_classes = '. wp_json_encode($classes) .'; window.pc_ready_lb = [];</script>
';
}, 1);




// add hidden container in footer
add_action('wp_footer', function() {
	if(!isset($GLOBALS['pvtcont_has_lightboxes'])) {
        return false;
    }
	
	// if there are lightboxes to be kept ready
	$loaded_lb = '';
	if(isset($GLOBALS['pvtcont_queued_lb']) && is_array($GLOBALS['pvtcont_queued_lb']) && !empty($GLOBALS['pvtcont_queued_lb'])) {
		$to_be_loaded = array_map('absint', array_unique($GLOBALS['pvtcont_queued_lb']));
		$loaded_lb .= implode('', pvtcont_lightbox_ajax($to_be_loaded));
		
		echo '
		<script type="text/javascript">
        (function($) { 
            "use strict";  
        
            window.pc_ready_lb = pc_ready_lb.concat('. wp_json_encode($to_be_loaded) .');
        })(jQuery);
		</script>';
	}
	
	echo '<div id="pc_lb_codes" class="pc_displaynone">'. pc_static::wp_kses_ext($loaded_lb) .'</div>';
}, 999);



/////////////////////////////////////



// LIGHTBOX CONTENTS - AJAX call handler and direct retrieval
//// direct call - contains lightbox IDs to return
function pvtcont_lightbox_ajax($direct_call = false) {
	if(!$direct_call) {
		if(!isset($_POST['ids']) || empty($_POST['ids'])) {
			wp_die('Lightbox IDs missing');
		}
	}
	
	$ids = ($direct_call) ? $direct_call : explode(',', pc_static::sanitize_val($_POST['ids'])); 
	$to_return = array();

	foreach($ids as $id) {
        $id = (int)$id;
        
		$term = get_term_by('id', $id, 'pc_lightboxes');
		if($term) {
			$contents = base64_decode($term->description);
			
			// has only a form?
			$only_form = (
				substr_count(wp_strip_all_tags($contents), '[') == 1 && substr(trim(wp_strip_all_tags($contents)), 0, 1) == '[' && substr(trim(wp_strip_all_tags($contents)), -1) == ']' && 
				(strpos($contents, '[pc-login-form') !== false || strpos($contents, '[pc-registration-form') !== false || strpos($contents, '[pcud-form') !== false)
			) ? true : false;
			
            
            // PC-FILTER - allow control over "only-form-lightbox" class - passees bool
            $only_form = apply_filters('pc_only_form_lb', $only_form, $id);
            if($only_form) {
                $only_form = 'pc_only_form_lb';    
            }
            
            $code = '<div class="pc_lightbox_contents '. $only_form .' pc_lb_'. $id .'">'. do_shortcode(wpautop(base64_decode($term->description))) .'</div>';	
            $to_return[$id] = $code;
		}
	}
	
	// how to return
	if($direct_call) {
		return $to_return;	
	} else {
        wp_die(json_encode($to_return));
	}
}
add_action('wp_ajax_pc_lightbox_load', 'pvtcont_lightbox_ajax');
add_action('wp_ajax_nopriv_pc_lightbox_load', 'pvtcont_lightbox_ajax');
