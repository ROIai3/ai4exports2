<?php
// add visibility option to each menu block
if(!defined('ABSPATH')) {exit;}


// Saves new field to postmeta for navigation
add_action('wp_update_nav_menu_item', function($menu_id, $menu_item_db_id, $args ) {
	if(!isset($GLOBALS['pvtcont_menu_restrictions'])) {$GLOBALS['pvtcont_menu_restrictions'] = array();}
	
	if(isset($_REQUEST['menu-item-'.$menu_item_db_id.'-pc-hide']) ) {
        $restr = array_map('sanitize_text_field', $_REQUEST['menu-item-'.$menu_item_db_id.'-pc-hide']);
		if(!is_array($restr)) {
            $restr = array();
        }
		
		if(in_array('all', $restr)) {
            $restr = array('all');} 
		if(in_array('unlogged', $restr)) {
            $restr= array('unlogged');
        } 
		
		$GLOBALS['pvtcont_menu_restrictions'][$menu_item_db_id] = (count($restr)) ? $restr : array('');
        update_post_meta( $menu_item_db_id, '_menu_item_pg_hide', $restr);
    }
	else {
		$GLOBALS['pvtcont_menu_restrictions'][$menu_item_db_id] = array('');
		update_post_meta( $menu_item_db_id, '_menu_item_pg_hide', '');
	}
},10, 3);



// Adds value of new field to $item object that will be passed to frontend menu object
add_filter('wp_setup_nav_menu_item', function($menu_item) {
    $menu_item->pc_hide_item = get_post_meta($menu_item->ID, '_menu_item_pg_hide', true);
    return $menu_item;
});



// javascript implementation of restriction wizard
add_action('admin_footer', function() {
	global $current_screen;
	
	if($current_screen->base == 'nav-menus') {	
		$sel_val = (isset($GLOBALS['pvtcont_menu_restrictions'])) ? $GLOBALS['pvtcont_menu_restrictions'] : array();
        ?>
        <div id="pc_menu_restr_dd" class="pc_displaynone">
        	<select name="menu-item-%MENU-ITEM-ID%-pc-hide[]" rel="%MENU-ITEM-ID%" multiple="multiple" class="pc_lc_select pc_menu_hide_dd" data-placeholder="<?php esc_attr_e('Select categories', 'pc_ml') ?> ..">
            	<?php
				echo pc_static::wp_kses_ext(pc_static::user_cat_dd_opts($sel_val));
				?>
            </select>
        </div>
        
        <?php
        $inline_js = '
        (function($) { 
            "use strict";
            
            $(document).ready(function(e) {
                if($(`#update-nav-menu`).length) {			
                    var menu_id = $(`#menu`).length;
                    var to_query = []; 
                    var tot_items = $(`#update-nav-menu .menu-item`).length;

                    var saved_vals = $.parseJSON(`'. ((isset($GLOBALS['pvtcont_menu_restrictions'])) ? wp_json_encode($GLOBALS['pvtcont_menu_restrictions']) : wp_json_encode('')) .'`);

                    var base_code = 
                    `<p class="field-custom description description-wide pc_menu_restr_wrap">
                        <label>'. esc_html__('Which PrivateContent user categories can see this menu item?', 'pc_ml') .'</label>
                        ${ $(`#pc_menu_restr_dd`).html() }
                    </p>`;

                    
                    // fetch values for every menu item
                    const pc_fetch_values = function() {
                        if(!saved_vals && to_query.length) {
                            var data = {
                                action: `pvtcont_menu_item_restrict`,
                                menu_items: to_query,
                                pc_nonce: `'. esc_js(wp_create_nonce('lcwp_ajax')) .'`
                            };
                            $.post(ajaxurl, data, function(response) {
                                var resp = $.parseJSON(response);

                                $(`#update-nav-menu .menu-item`).each(function() {
                                    var $subj = $(this);
                                    var item_id = $subj.find(`.menu-item-data-db-id`).val();

                                    if(typeof(resp[item_id]) != `undefined`) {
                                        $.each( resp[item_id], function(iid, val) {
                                            if(val) {
                                                $subj.find(`.pc_menu_hide_dd option[value=`+ val +`]`).attr(`selected`, `selected`);	
                                            }
                                        });
                                    }
                                });

                                pc_lc_select();
                            });	
                        }
                        else {
                            pc_lc_select();
                        }
                    };


                    // detect new menu additions
                    const pc_add_menu_detect = function() {
                        setInterval(function() {
                            if($(`.menu-item-page`).length < tot_items) {
                                tot_items = $(`.menu-item-page`).length;	
                            }
                            else if($(`.menu-item-page`).length > tot_items) {
                                tot_items = $(`.menu-item-page`).length;

                                $(`#update-nav-menu .menu-item`).each(function(i, v) {
                                    var $subj = $(this);
                                    if(!$subj.find(`.pc_menu_hide_dd`).length) {
                                        var item_id = $subj.find(`.menu-item-data-db-id`).val();
                                        to_query.push(item_id);

                                        var item_code = base_code.replace(/%MENU-ITEM-ID%/g, item_id);
                                        $subj.find(`.menu-item-actions`).before(item_code);
                                    }
                                });

                                pc_lc_select();
                            }
                        }, 100);
                    };


                    // initialize
                    var a = 1;
                    $(`#update-nav-menu .menu-item`).each(function(i, v) {
                        var $subj = $(this);
                        var item_id = $subj.find(`.menu-item-data-db-id`).val();
                        to_query.push(item_id);

                        var item_code = base_code.replace(/%MENU-ITEM-ID%/g, item_id);
                        $subj.find(`.menu-item-actions`).before(item_code);

                        // set value if just saved
                        if(saved_vals) {
                            $.each( saved_vals[item_id], function(i,v) {
                                if(v) {
                                    $subj.find(`.pc_menu_hide_dd option[value=`+ v +`]`).attr(`selected`, `selected`);	
                                }
                            });
                        }

                        if(a == tot_items) {
                            pc_add_menu_detect();
                            pc_fetch_values();	
                        }

                        a++;
                    });
                }
            });

            
            const pc_lc_select = function() {';
        
                if(ISPCF) {
                    $inline_js .= 'window.nfpcf_inject_infobox(`.pc_menu_restr_wrap .pc_lc_select`, true);';
                }
                else {
                    $inline_js .= '
                    new lc_select(`.pc_menu_restr_wrap .pc_lc_select`, {
                        wrap_width : `100%`,
                        addit_classes : [`lcslt-lcwp`],
                    });';
                }
        
            $inline_js .= '
            };
        })(jQuery);';
        wp_add_inline_script('lcwp_magpop', $inline_js);	
	}
});

