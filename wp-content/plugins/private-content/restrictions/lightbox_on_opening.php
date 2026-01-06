<?php
/* NFPCF */

// MANAGE COMMENTS RESTRICTION
if(!defined('ABSPATH')) {exit;}


add_filter('wp_footer', function() {
	global $pc_restr_wizard;
	
	// if user is logged - exit
	if(pc_user_check(array('all'), array(), true) === 1) {
		return false;	
	}
	
    
	//////////////////////////////////////////////////////////////
	// forced usage
    if(isset($GLOBALS['pvtcont_forced_lb_on_open'])) {
        $lb_to_use = 'inherit';  
    }    
    
    else {
        //////////////////////////////////////////////////////////////
        // single page/post redirect
        if(is_page() || is_single()) {
            global $post;
            $restr_arr = (is_object($post)) ? $pc_restr_wizard->get_entity_full_restr('post', $post->ID) : false;
        }


        //////////////////////////////////////////////////////////////
        // if is category or archive
        else if(is_category() || is_archive()) {
            $term_id = get_query_var('cat');
            $term_data = pc_static::term_obj_from_term_id($term_id);
            $restr_arr = $pc_restr_wizard->get_entity_full_restr('term', $term_id, $term_data);
        }


        //////////////////////////////////////////////////////////////
        // WooCommerce category
        else if(function_exists('is_product_category') && is_product_category()) {
            $tern_slug = get_query_var('product_cat');
            $term_data = get_term_by('slug', $term_slug, 'product_cat');

            if($term_data) {
                $restr_arr = $pc_restr_wizard->get_entity_full_restr('term', $term_id, $term_data);
            }
        }


        ###################


        // no restriction found - exit
        if(!isset($restr_arr) || empty($restr_arr) || !is_array($restr_arr) || !isset($restr_arr['lb_on_open']) || empty($restr_arr['lb_on_open'])) {
            return false;	
        }


        // last lb_on_open index is the one to follow
        $lb_to_use = end($restr_arr['lb_on_open']);
    }
    

	
	// if follows global setting
	if($lb_to_use == 'inherit') {
		$lb_to_use = get_option('pg_def_lb_on_open', 'none');	
	}
		
	
	// elaborate
	if(!$lb_to_use || $lb_to_use == 'no') {
        return false;
    }
	else {
		if(pc_static::enqueue_lb($lb_to_use)) {
			?>
            <div class="pc_lb_trig_<?php echo (int)$lb_to_use ?> pc_modal_lb"></div>
            
			<script type="text/javascript">
            (function($) { 
                "use strict";    
                
                $(document).ready(function($) {
                    $('.pc_modal_lb.pc_lb_trig_<?php echo (int)$lb_to_use ?>').trigger('click');

                    // keep pushing it even if hidden by furbacchioni
                    var keep_it_alive = function() {
                        setTimeout(function() {

                            if((!$('.pc_lightbox_contents').length || !$('.mfp-bg.pc_lightbox').length) && typeof($.magnificPopup) != 'undefined')  {
                                $.magnificPopup.close();

                                setTimeout(function() {
                                    $('.pc_modal_lb.pc_lb_trig_<?php echo (int)$lb_to_use ?>').trigger('click');	
                                }, 400);	
                            }
                            else {
                                keep_it_alive();	
                            }
                        }, 400);
                    }

                    // wait for first init
                    setTimeout(function() {
                        keep_it_alive();
                    }, 600);
                });
            })(jQuery);
			</script>
            <?php
		}
	}
}, 998); // use a value lower than 999 to pass values to lightbox_engine.php
