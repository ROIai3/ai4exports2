<?php
if(!defined('ABSPATH')) {exit;}
dike_lc('lcweb', PC_DIKE_SLUG, true); /* NFPCF */


global $pc_users, $pc_wp_user;

// editing user?
if(isset($_GET['user'])) {
	$user_id = absint($_GET['user']);

	// does user exist?
	$user_data = $pc_users->get_user($user_id, array(
    	'to_get' => array('username', 'name', 'surname', 'email', 'tel', 'status', 'insert_date', 'last_access')
	));
	
	if(!$user_data) {
		wp_die('
		<div class="notice notice-error">
			<p>'. esc_html__('User not found', 'pc_ml') .'</p>
		</div>');
	}
}
else {
	$user_id = false;	
}


// can access this page?
$cuc_user_id = ($user_id) ? $user_id : 'some'; 
$cuc_edit    = pc_wpuc_static::current_wp_user_can_edit_pc_user($cuc_user_id);
$GLOBALS['pvtcont_ud_cuc_edit'] = $cuc_edit;

if(!$cuc_edit) {
    wp_die('
    <div class="notice notice-error">
        <p>'. esc_html__('You are not allowed to access this page', 'pc_ml') .'</p>
    </div>');
}


$GLOBALS['pvtcont_user_dashboard_user_id'] = $user_id;
?>


<div class="wrap pc_user_dashboard_wrap">
	
    <?php $page_title = ($user_id) ? esc_html__('Edit', 'pc_ml') .' '. $user_data['username'] : esc_html__('Add PrivateContent User', 'pc_ml'); ?>
    <h1 class="wp-heading-inline pc_user_dashboard_heading pc_page_title">
    	<img src="<?php echo esc_url(PC_URL) ?>/img/pc_page_icon.png" />
		<?php echo esc_html($page_title) ?>
   	</h1>
	
    
    <?php 
	// status panel
	if($user_id) {
		switch((int)$user_data['status']) {
			case 1 : $txt = esc_html__('active', 'pc_ml'); break;
			case 2 : $txt = esc_html__('disabled', 'pc_ml'); break;
			case 3 : $txt = esc_html__('pending', 'pc_ml'); break;
			default: $txt = esc_html__('deleted', 'pc_ml'); break;	
		}
	
		echo 
		'<div class="pc_user_dashboard_status_panel">
			<div class="pc_user_dashboard_added_on">
				<div>
					<span>'. esc_html__('Added on', 'pc_ml') .'</span>
					<strong>'. wp_kses_post($pc_users->data_to_human('insert_date', $user_data['insert_date'])) .'</strong>
				</div>
				<div>
					<span>'. esc_html__('Last access', 'pc_ml') .'</span>
					<strong>'. wp_kses_post(strip_tags($pc_users->data_to_human('last_access', $user_data['last_access']), '<time>')) .'</strong>
				</div>
			</div>
			<div>
				<span>'. esc_html__('User status', 'pc_ml') .'</span>
				<div class="pc_edit_user_status pc_eus_'. esc_attr($user_data['status']) .'">'. esc_html($txt) .'</div>
				
				<div class="pc_user_dashboard_edit_status_wrap">';
			
				if($cuc_edit) {
					echo '
					<i class="fas fa-times-circle pc_del_user" title="'. esc_attr__('delete user', 'pc_ml') .'" data-status="0"></i>
					';
					
					if(in_array((int)$user_data['status'], array(2, 3))) {
						echo '<i class="fas fa-check-circle pc_enable_user" aria-hidden="true" title="'. esc_attr__('enable user', 'pc_ml') .'" data-status="1"></i>';
					}
					else {
						echo '<i class="fas fa-minus-circle pc_disable_user" aria-hidden="true" title="'. esc_attr__('disable user', 'pc_ml') .'" data-status="2"></i>';
					}
				}
				
				echo '
				</div>
			</div>
		</div>';
	}

	
	include_once('dashboard_engine.php'); 
	include_once('structure.php'); 
	
    $engine = new pvtcont_user_dashboard_engine($GLOBALS['pvtcont_user_dashboard_tabs'], $GLOBALS['pvtcont_user_dashboard_structure'], $user_id);
    echo pc_static::wp_kses_ext($engine->get_code());
    ?>
</div>





<?php // SCRIPTS 
$inline_js = '
(function($) { 
    "use strict";';
    
    // browser's page title tweak
    if($user_id) {
        $inline_js .= 'document.querySelector(`title`).innerText = document.querySelector(`title`).innerText.replace(`'. esc_html__('Add User', 'pc_ml') .'", "'. esc_html__('Edit User', 'pc_ml') .' '. esc_js($user_data['username']) .'`);';
    }
    
    $inline_js .= '
    // avoid fields autopopulation
    setTimeout(function() {
        document.querySelector(`#pc_ud_main input[name="psw"]`).value = ``;';
 
        $to_check = array('username', 'name', 'surname', 'email', 'tel');
        foreach($to_check as $tc) {
            if(!$user_id || (isset($user_data[$tc]) && empty($user_data[$tc]))) {
                $inline_js .= 'document.querySelector(`#pc_ud_main input[name="'. esc_js($tc) .'"]`).value = ``;';
            }
        }

        $inline_js .= '
    }, 200);
    
    
    $(document).ready(function($) {
        const pc_nonce = `'. esc_js(wp_create_nonce('lcwp_ajax')) .'`;
        let is_acting = false;

        // save user data (create/update)
        $(document).on(`click`, `.pc_user_dashboard_submit`, function(e) {
            e.preventDefault();	
            if(is_acting) {
                return false;
            }

            var $btn = $(`.pc_user_dashboard_submit`);
            $btn.fadeTo(200, 0.7);

            $(`.pc_user_dashboard_errors`).remove();
            is_acting = true;

            var data = `action=pvtcont_save_user_dashboard_ajax&pc_user_id='. absint($user_id) .'&pc_nonce=`+ pc_nonce +`&`+ $(`.pc_user_dashboard_form`).serialize();
            
            $.post(ajaxurl, data, function(response) {
                try {
                    const resp = JSON.parse(response);

                    if(resp.response == `success`) {
                        var message = `'. (($user_id) ? esc_attr__('User updated successfully!', 'pc_ml') : esc_attr__('User added successfully!', 'pc_ml')) .'`; 
                        lc_wp_popup_message(`success`, message);

                        setTimeout(function() { 
                            '. (($user_id) ? 
                                    'window.location.reload();' : 
                                    'window.location.href = window.location.href.replace( window.location.hash, "") + "&user=" + resp.user_id') .'
                        }, 1800);
                    }
                    else { 
                        lc_wp_popup_message(`error`, resp.text);
                        $(`.pc_user_dashboard_heading`).after(`<div class="error pc_user_dashboard_errors"><p>`+ resp.text +`</p></div>`)
                    }
                }
                catch(e) {
                    console.error(e);
                    lc_wp_popup_message(`error`, `'. esc_attr__('Error performing the action', 'pc_ml') .'`);
                }
            })
            .fail(function(e) {
                lc_wp_popup_message(`error`, `'. esc_attr__('Error performing the action', 'pc_ml') .'`);
                console.error(e.responseText);
            })
            .always(function() {
                $btn.fadeTo(200, 1);
                is_acting = false;       
            });

        });


        // form submission through "enter"
        $(document).on("keypress", ".pc_user_dashboard_wrap input", function(e) { 
            if(e.keyCode === 13){
                e.preventDefault();	
                var $trigger = $(`.pc_user_dashboard_submit`).trigger(`click`);
                return false;
            }
        });



        // change user status or delete him
        $(document).on(`click`, `.pc_user_dashboard_edit_status_wrap i`, function(e) {
            if(is_acting) {return false;}
            var new_status = parseInt($(this).data(`status`), 10);

            if(!new_status) {
                if(!confirm("'. esc_attr__('Do you really want to delete this user?', 'pc_ml') .'")) {
                    return false;	
                }
            }

            var $btn = $(this);
            $btn.fadeTo(200, 0.7);
            is_acting = true;

            var data = {
                action		: `pvtcont_user_dashboard_change_status`,
                pc_user_id	: '. absint($user_id) .',
                status		: new_status,
                pc_nonce	: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) == `success`) {

                    switch(new_status) {
                        case 0 : var txt = "'. esc_attr__('User deleted successfully!', 'pc_ml') .'"; break; 
                        case 1 : var txt = "'. esc_attr__('User enabled successfully!', 'pc_ml') .'"; break; 
                        case 2 : var txt = "'. esc_attr__('User disabled successfully!', 'pc_ml') .'"; break; 	
                    }

                    lc_wp_popup_message(`success`, txt);

                    setTimeout(function() {

                        if(!new_status) {
                            window.location.replace("'. esc_js(admin_url()) .'admin.php?page=pc_user_manage");
                        } else {
                            location.reload();
                        }
                    }, 1800);
                }
                else { 
                    lc_wp_popup_message(`error`, response);

                    $btn.fadeTo(200, 1);
                    is_acting = false;
                }
            });
        });







        ///////////////////////////////////////////



        // WP user sync
        $(document).on(`click`, `#pc_sync_with_wp`, function(e) {
            e.preventDefault();

            if(is_acting || !confirm("'. esc_attr__('A mirror WordPress user will be created. Continue?', 'pc_ml') .'")) {
                return false;
            }

            var $btn = $(this);
            $btn.fadeTo(200, 0.7);
            is_acting = true;

            var data = {
                action		: `pvtcont_wp_sync_single_user`,
                pc_user_id	: '. absint($user_id) .',
                pc_nonce	: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) == `success`) {
                    lc_wp_popup_message(`success`, "'. esc_attr__('User synced successfully!', 'pc_ml') .'");

                    setTimeout(function() {
                        location.reload();
                    }, 1800);
                }
                else { 
                    lc_wp_popup_message(`error`, response);

                    $btn.fadeTo(200, 1);
                    is_acting = false;
                }
            });
        });



        // WP user detach
        $(document).on(`click`, `#pc_detach_from_wp`, function(e) {
            e.preventDefault();

            if(is_acting || !confirm(`'. esc_attr__('WARNING: this will delete connected wordpres user and any related content will be lost. Continue?', 'pc_ml') .'`)) {
                return false;
            }

            var $btn = $(this);
            $btn.fadeTo(200, 0.7);
            is_acting = true;

            var data = {
                action		: `pvtcont_wp_detach_single_user`,
                pc_user_id	: '. absint($user_id) .',
                pc_nonce	: pc_nonce
            };
            $.post(ajaxurl, data, function(response) {
                if($.trim(response) == `success`) {
                    lc_wp_popup_message(`success`, "'. esc_attr__('User detached successfully!', 'pc_ml') .'");

                    setTimeout(function() {
                        location.reload();
                    }, 1800);
                }
                else { 
                    lc_wp_popup_message(`error`, response);

                    $btn.fadeTo(200, 1);
                    is_acting = false;
                }
            });
        });



        // WP user sync - edit WP fields through lightbox
        $(`#pc_wps_wp_fields a`).magnificPopup({
            type		: `iframe`,
            mainClass	: `pc_wps_wp_fields_lb`,
            iframe		: {
               markup	: 
               `<div class="mfp-iframe-scaler">`+
                  `<div class="mfp-close"></div>`+
                  `<iframe class="mfp-iframe" frameborder="0" onload="pc_wps_wpf_lb_height(this)" allowfullscreen></iframe>`+
              `</div>`
            },
            modal			: true, 
            closeOnBgClick	: false,
            preloader		: false,  
        });


        window.pc_wps_wpf_lb_height = function(iframe) {
            setTimeout(function() {
                var h = iframe.contentWindow.document.body.scrollHeight + "px";
                $(`.pc_wps_wp_fields_lb .mfp-content`).height(h);
                $(`.pc_wps_wp_fields_lb .mfp-close`).show();
            }, 400);
        };



        //////////////////////////////////////////////////

        
        
        // care crowded tabs
        const crowded_tabs_check = function() {
            const $tabs_wrap = $(`.pc_user_dashboard_tabs`),
                  max_w = $tabs_wrap.width();

            let sum = 0;
            $tabs_wrap.find(`.nav-tab`).each(function() {
                sum += $(this).outerWidth(true);
            });

            (sum > max_w) ? $tabs_wrap.addClass(`pc_user_dashboard_crowded_tabs`) : $tabs_wrap.removeClass(`pc_user_dashboard_crowded_tabs`);
        };
        setTimeout(() => {
            let ctb_tout;
            $(window).on(`resize`, function() {
                if(ctb_tout) {
                    clearTimeout(ctb_tout);
                }

                ctb_tout = setTimeout(() => {
                    crowded_tabs_check();
                }, 70);
            });
        }, 70);
        


        // replacing $ UI tabs 
        $(`.pc_user_dashboard_tabs`).each(function() {
            var sel = ``;
            var hash = window.location.hash;
            var $form = $(".pc_user_dashboard_form");

            // track URL on opening
            if(hash && $(this).find(`.nav-tab[href="`+ hash +`"]`).length) {
                $(this).find(`.nav-tab`).removeClass(`nav-tab-active`);
                $(this).find(`.nav-tab[href="`+ hash +`"]`).addClass(`nav-tab-active`);	
            }

            // if no active - set first as active
            if(!$(this).find(`.nav-tab-active`).length) {
                $(this).find(`.nav-tab`).first().addClass(`nav-tab-active`);	
            }

            // hide unselected
            $(this).find(`.nav-tab`).each(function() {
                var id = $(this).attr(`href`);

                if($(this).hasClass(`nav-tab-active`)) {
                    sel = id
                }
                else {
                    $(id).hide();
                }
            });

            // scroll to top by default
            $("html, body").animate({scrollTop: 0}, 0);

            // track clicks
            if(sel) {
                let hashchange_onclick = false;
                
                $(this).find(`.nav-tab`).on(`click`, function(e) {
                    e.preventDefault();
                    
                    if($(this).hasClass(`nav-tab-active`)) {
                        return false;
                    }
                    let sel_id = $(this).attr(`href`);
                    
                    if(!$(this).is(`:first-child`)) {
                        hashchange_onclick = true;
                        window.location.hash = sel_id.replace(`#`, ``);
                        hashchange_onclick = false;
                    }
                    else {
                        window.history.pushState(null, null, window.location.href.split(`#`)[0]);
                    }

                    // show selected and hide others
                    $(this).parents(`.pc_user_dashboard_tabs`).find(`.nav-tab`).each(function() {
                        var id = $(this).attr(`href`);

                        if(sel_id == id) {
                            $(this).addClass(`nav-tab-active`);
                            $(id).show();		
                        }
                        else {
                            $(this).removeClass(`nav-tab-active`);
                            $(id).hide();	
                        }
                    });
                    
                    window.scrollTo(0, 0);
                });
                
                
                // track hash change and reflect on tabs
                window.addEventListener("hashchange", () => {
                    if(hashchange_onclick) {
                        return;   
                    }
                    
                    if(!window.location.hash) {
                        $(`.pc_user_dashboard_tabs a`).first().click();
                    }
                    if($(`.pc_user_dashboard_tabs a[href="`+ window.location.hash +`"]`).length) {
                        $(`.pc_user_dashboard_tabs a[href="`+ window.location.hash +`"]`).click();
                    }
                });
            }
            
            
            crowded_tabs_check();
       });



       // lc switch
        var pc_live_checks = function() {
            lc_switch(`.lcwp_sf_check`, {
                on_txt      : "'. esc_js(strtoupper(esc_attr__('yes', 'pc_ml'))) .'",
                off_txt     : "'. esc_js(strtoupper(esc_attr__('no', 'pc_ml'))) .'",   
            });
        }
        pc_live_checks();



        // lc_select
        var pc_lc_select = function() { 
            new lc_select(`.pc_user_dashboard_wrap .pc_lc_select`, {
                wrap_width      : `98%`,
                addit_classes   : [`lcslt-lcwp`],
                pre_placeh_opt  : true,
            });
        }
        pc_lc_select();




        //////////////////////////////////////////////////




        // fixed submit position
        var pc_fixed_submit = function(btn_selector) {
            var $subj = $(btn_selector);
            if(!$subj.length) {return false;}

            var clone = $subj.clone().wrap("<div />").parent().html();

            setInterval(function() {

                // if page has scrollers or scroll is far from bottom
                if(($(document).height() > $(window).height()) && ($(document).height() - $(window).height() - $(window).scrollTop()) > 130) {
                    if(!$(`.pc_user_dashboard_fixed_submit`).length) {	
                        $subj.after(`<div class="pc_user_dashboard_fixed_submit">`+ clone +`</div>`);
                    }
                }
                else {
                    if($(`.pc_user_dashboard_fixed_submit`).length) {	
                        $(`.pc_user_dashboard_fixed_submit`).remove();
                    }
                }
            }, 50);
        };
        pc_fixed_submit(`.pc_user_dashboard_submit`);



        // auto password generator
        $(document).on(`click`, `.pc_psw_generator`, function() {
            const $this  = $(this),
                  $field = $(this).parents(`td`).first().find(`input`); 
            
            let psw = $this.data(`psw`);
                psw = psw.split(``);
                
            for (var i = psw.length - 1; i > 0; i--) {
                let rand = Math.floor(Math.random() * (i + 1));
                [psw[i], psw[rand]] = [psw[rand], psw[i]];
            }

            $field.val( psw.join(``) );
            
            // set field as visible
            if($field.attr(`type`) == `password`) {
                $(`.pc_toggle_psw_vis`).trigger(`click`);        
            }
        });
        
        
        
        // password visibility system
        $(document).on(`click`, `.pc_toggle_psw_vis`, function() {
            const $this  = $(this),
                  $field = $(this).parents(`td`).first().find(`input`); 

            if($field.attr(`type`) == `password`) {
                $field.attr(`type`, `text`);    
                $this.removeClass(`dashicons-visibility`).addClass(`dashicons-hidden`);          
            } 
            else {
                $field.attr(`type`, `password`);   
                $this.addClass(`dashicons-visibility`).removeClass(`dashicons-hidden`);
            }
        });

    });
    
})(jQuery);';
wp_add_inline_script('lcwp_magpop', $inline_js);



// PC-ACTION - allow extra code printing in user dashboard (for javascript/css)
do_action('pc_user_dashboard_extra_code');