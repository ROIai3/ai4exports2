<?php
/* NFPCF */

// WIDGET RESTRICTION - BACKEND AND FRONTEND
if(!defined('ABSPATH')) {exit;}


// Add fields fields
add_action('in_widget_form', function($t, $return, $instance){
	global $current_screen;
	
	// not in live builder - avoid layers/elementor interference
	if(strpos($_SERVER["REQUEST_URI"], '/admin-ajax.php') !== false || !is_null($current_screen)) {
		if(is_object($current_screen) && $current_screen->base != 'widgets') {
            return false;
        }
	}

	$field_id = $t->get_field_id('pc_allow');
	$instance = wp_parse_args( (array)$instance, array( 'title' => '', 'text' => '', 'pc_allow' => array()));
    
	if(!isset($instance['pc_allow']) || !is_array($instance['pc_allow'])) {
        $instance['pc_allow'] = array();
    }
    ?>
    <p class="pc_widget_control_wrap">
        <label><?php esc_html_e('Which PrivateContent user categories can see this widget?', 'pc_ml') ?></label>
        <select id="<?php echo esc_attr($field_id) ?>" name="pc_allow[]" multiple="multiple"  class="pc_lc_select" data-placeholder="<?php esc_attr_e('select a category', 'pc_ml') ?> .." autocomplete="off">
        	<?php
			echo pc_static::wp_kses_ext(pc_static::user_cat_dd_opts($instance['pc_allow']));
			?>
        </select>
    </p>
    
	<?php
    $retrun = null;
    return array($t, $return, $instance);
}, 999, 3);




// Callback - save fields (ajax passed data - save and returns to in_widget_form) /* NFPCF */
add_filter('widget_update_callback', function($instance, $new_instance, $old_instance, $widget){
	
	if(isset($instance['design']) && !isset($_POST['pc_allow'])) {
		return $instance;	
	}
	$opt_name = 'pg_widget_control_'.$widget->id;
	
	// sanitize value
	$allow = (isset($_POST['pc_allow'])) ? pc_static::sanitize_val((array)$_POST['pc_allow']) : array(); 
	
	
	// no value - delete and stop
	if(empty($allow)) {
		$instance['pc_allow'] = $allow;	
		delete_option($opt_name);	
		return $instance;
	}
	
	
	// check and save
	if(in_array('all', $allow)) {
        $allow = array('all');
    } 
	if(in_array('unlogged', $allow)) {
        $allow = array('unlogged');
    } 
	
	// save in WP options to be faster
	$data = array(
		'allow' => $allow
	);
	update_option($opt_name, $data);
	
	if(isset($_POST['pc_allow'])) {
		$instance['pc_allow'] = $allow;	
	} 
	
	return $instance;
}, 5, 4);



// add lc select script into page
add_action('admin_footer', function() {
	global $current_screen;
	if(is_null($current_screen) || $current_screen->base == 'widgets') :
	?>
	<script type="text/javascript">
    (function($) { 
        "use strict"; 
        
        setInterval(() => {
            if($('.pc_widget_control_wrap select:visible').length) {
                <?php if(ISPCF) : ?>
                window.nfpcf_inject_infobox('.pc_widget_control_wrap select', true);
                <?php else : ?>
                window.pc_wr_lc_select();
                <?php endif; ?>
            }
        }, 200);
        
        
        jQuery(document).ready(function($) {
            var dd_count = $('#widgets-right select.pc_lc_select').length;
            pc_wr_lc_select();

            $(document).on('click', '.widgets-chooser-actions .button-primary', function() {
                let pc_lc_select_intval = setInterval(function() {
                    var new_count = $('#widgets-right select.pc_lc_select').length;

                    if(new_count != dd_count) {
                        dd_count = new_count;
                        clearInterval(pc_lc_select_intval);
                        
                        pc_wr_lc_select();
                    }
                }, 100); 
            });
        });


        window.pc_wr_lc_select = function() {
            new lc_select('.pc_widget_control_wrap select', {
                wrap_width : '100%',
                addit_classes : ['lcslt-lcwp'],
            });
        };
    })(jQuery);
    </script>
    <?php
	endif;
});




// widget deletion - clean database
add_action('sidebar_admin_setup', function() {
	if ( isset($_POST['widget-id']) ) {
		$widget_id = sanitize_text_field(wp_unslash($_POST['widget-id']));

        if (isset( $_POST['delete_widget']) && $_POST['delete_widget']) {
        	delete_option('pg_widget_control_'.$widget_id);
		}
	}
});




///////////////////////////////////////////////////////////////////////////////////////////




// APPLY - frontend implementation /* NFPCF */
add_filter('sidebars_widgets', function($sidebars_widgets) {
	$filtered_widgets = $sidebars_widgets;
	
	// in frontend and only if WP user functions are registered
    if(is_admin()) {
        return $filtered_widgets;
    }
    
    if(!isset($GLOBALS['pvtcont_widget_control_opts'])) {
        $GLOBALS['pvtcont_widget_control_opts'] = array();
    }
    $stored = $GLOBALS['pvtcont_widget_control_opts'];

    foreach($sidebars_widgets as $widget_area => $widget_list) {
        if ($widget_area == 'wp_inactive_widgets' || empty($widget_list) || !is_array($widget_list)) {
            continue;
        }

        foreach($widget_list as $pos => $widget_id) {
            if(isset($stored[$widget_id])) {
                $opt = $stored[$widget_id];	
            } else {
                $opt = get_option('pg_widget_control_'.$widget_id); 
                $GLOBALS['pvtcont_widget_control_opts'][$widget_id] = $opt;
            }

            if($opt) {
                if(isset($opt['allow']) && is_array($opt['allow']) && count($opt['allow']))	{
                    
                    $val = implode(',', $opt['allow']);
                    if(pc_user_check($val, $blocked = '', $wp_user_pass = true) !== 1) {
                        unset( $filtered_widgets[$widget_area][$pos] );	
                    }	
                }
            }
        }
	}
	
	return $filtered_widgets;
}, 999);
