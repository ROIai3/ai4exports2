<?php if(!defined('ABSPATH')) {exit;} ?>

$(document).ready(function($) {
    const lcwp_nonce = '<?php echo esc_js(wp_create_nonce('lcwp_ajax')) ?>',
          settings_baseurl = `<?php echo esc_url($engine->baseurl) ?>`;

    document.body.classList.add('<?php echo esc_js(lcwp_settings_engine::$css_prefix) ?>lcwp_sf_page');

    // codemirror - execute before tabbing
    if(typeof(lc_settings_css_codemirror_config) != 'undefined') {
        wp.codeEditor.initialize($(`.lcwp_sf_code_editor`), lc_settings_css_codemirror_config);
    }
    else {
        $('.lcwp_sf_code_editor:visible').each(function() {
            CodeMirror.fromTextArea( $(this)[0] , {
                lineNumbers: true,
                mode: "css"
            });
        });
    }



    //////////////////////////////////////////////////



    // options search
    let lcwp_sf_search_tout = false;
    $(document).on('keyup', '.lcwp_sf_search_wrap input', function(e) {
        const val = $(this).val().trim();

        if(lcwp_sf_search_tout) {
            clearTimeout(lcwp_sf_search_tout);    
        }

        lcwp_sf_search_tout = setTimeout(function() {
            // reset
            $('.lcsw_sf_search_no_res').remove();
            $('.lcsw_sf_search_excluded').removeClass('lcsw_sf_search_excluded');

            // elaborate
            if(val.length < 3) {
                $('.lcwp_sf_search_wrap').removeClass('lcwp_sf_searching');
            }
            else {
                $('.lcwp_sf_search_wrap').addClass('lcwp_sf_searching');
                $('.lcwp_sf_spacer').parent().addClass('lcsw_sf_search_excluded');  

                // cycle through sections
                $('.lcwp_settings_table').each(function() {
                    let hide_table = true;

                    $(this).find('.lcwp_sf_label label').each(function() {
                        const $tr = $(this).parents('tr').first();

                        let matching_string = $(this).text().trim().toLowerCase();
                        if($tr.find('.lcwp_sf_note').length) {
                            matching_string += ' '+ $tr.find('.lcwp_sf_note').text();
                        }

                        if(matching_string.indexOf( val.toLowerCase() ) === -1) {
                            $tr.addClass('lcsw_sf_search_excluded');    
                        }
                        else {
                            hide_table = false;
                            $tr.removeClass('lcsw_sf_search_excluded');    
                        }
                    });

                    if(hide_table) {
                        $(this).addClass('lcsw_sf_search_excluded');
                        $(this).prev('h3').addClass('lcsw_sf_search_excluded');
                    }
                });

                // leave only tabs with matching options
                $('.lcwp_settings_block').each(function() {
                    if(!$(this).find('> *:not(.lcsw_sf_search_excluded):not(script):not(style)').length) {
                        $('a.nav-tab[href="#'+ $(this).attr('id') +'"]').addClass('lcsw_sf_search_excluded');    
                    }
                });

                // select first tab with matching options
                if($('a.nav-tab').not('.lcsw_sf_search_excluded').length) {
                    $('a.nav-tab').not('.lcsw_sf_search_excluded').first().click();
                } else {
                    $('.nav-tab-wrapper').append('<span class="lcsw_sf_search_no_res"><?php esc_html_e('No matching options', $ml_key) ?> ..</span>');    
                }
            }
        }, 500);
    });
    $('.lcwp_sf_search_wrap input').val(''); // avoid browser cache


    // reset search
    $(document).on('click', '.lcwp_sf_search_wrap i', function() {
        $('.lcwp_sf_search_wrap input').val('').trigger('keyup');        
    });



    //////////////////////////////////////////////////



    // care crowded tabs
    const crowded_tabs_check = function() {
        const $tabs_wrap = $('.lcwp_settings_tabs'),
              max_w = $tabs_wrap.width();
        
        let sum = 0;
        $tabs_wrap.find('.nav-tab').each(function() {
            sum += $(this).outerWidth(true);
        });

        (sum > max_w) ? $tabs_wrap.addClass('lcwp_settings_crowded_tabs') : $tabs_wrap.removeClass('lcwp_settings_crowded_tabs');
    };
    setTimeout(() => {
        let ctb_tout;
        $(window).on('resize', function() {
            if(ctb_tout) {
                clearTimeout(ctb_tout);
            }
            
            ctb_tout = setTimeout(() => {
                crowded_tabs_check();
            }, 70);
        });
    }, 70);
    


    // tabify
    $('.lcwp_settings_tabs').each(function() {
        var sel = '';
        var hash = window.location.hash;

        var $form = $(".lcwp_settings_form");
        var form_act = $form.attr('action');

        // track URL on opening
        if(hash && $(this).find('.nav-tab[href="'+ hash +'"]').length) {
            $(this).find('.nav-tab').removeClass('nav-tab-active');
            $(this).find('.nav-tab[href="'+ hash +'"]').addClass('nav-tab-active');	

            $form.attr('action', form_act + hash);
        }

        // if no active - set first as active
        if(!$(this).find('.nav-tab-active').length) {
            $(this).find('.nav-tab').first().addClass('nav-tab-active');	
        }

        // hide unselected
        $(this).find('.nav-tab').each(function() {
            var id = $(this).attr('href');

            if($(this).hasClass('nav-tab-active')) {
                sel = id
            }
            else {
                $(id).hide();
            }
        });

        // scroll to top by default
        window.scrollTo(0, 0);

        // track clicks
        if(sel) {
            let hashchange_onclick = false;

            $(this).find('.nav-tab').click(function(e) {
                e.preventDefault();

                if($(this).hasClass('nav-tab-active')) {
                    return false;
                }
                let sel_id = $(this).attr('href');

                if(!$(this).is(':first-child')) {
                    hashchange_onclick = true;
                    window.location.hash = sel_id.replace('#', '');
                    hashchange_onclick = false;
                } 
                else {
                    window.history.pushState(null, null, window.location.href.split('#')[0]);
                }

                $form.attr('action', form_act + sel_id);

                // show selected and hide others
                $(this).parents('.lcwp_settings_tabs').find('.nav-tab').each(function() {
                    var id = $(this).attr('href');

                    if(sel_id == id) {
                        $(this).addClass('nav-tab-active');
                        $(id).show();		
                    }
                    else {
                        $(this).removeClass('nav-tab-active');
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
                    $('.lcwp_settings_tabs a').first().click();
                }
                if($('.lcwp_settings_tabs a[href="'+ window.location.hash +'"]').length) {
                    $('.lcwp_settings_tabs a[href="'+ window.location.hash +'"]').click();
                }
            });
        }

        crowded_tabs_check();


        // back to the previous scrolling position
        if(window.location.href.indexOf('lcwpTopScroll') !== -1) {
            const curr_url = window.location.href.split('#')[0],
                  top_scroll = parseInt(curr_url.split('lcwpTopScroll=')[1], 10);

            window.scrollTo(0, top_scroll);
        }


        // utility event
        $(document).trigger('lcwp_settings_tabified');
    });



    // sticky tabs on scroll
    let lcwp_sf_sticky_tabs_tout = false;

    const $tabs = $('.lcwp_settings_tabs'),
          tabs_top_pos = Math.round($tabs.offset().top);

    const lcwp_sf_sticky_tabs = function() {
        if(lcwp_sf_sticky_tabs_tout) {
            clearTimeout(lcwp_sf_sticky_tabs_tout);    
        }

        lcwp_sf_sticky_tabs_tout = setTimeout(function() {
            if(document.documentElement.scrollTop > (tabs_top_pos + $tabs.outerHeight(true) + 20)) {
                $('.lcwp_settings_form').css('margin-top', $tabs.outerHeight(true));
                $tabs.addClass('lcwp_st_sticky');
            }
            else {
                $('.lcwp_settings_form')[0].style.removeProperty('margin-top');
                $tabs.removeClass('lcwp_st_sticky'); 
            }
        }, 10);
    };
    $(window).scroll(function() {
        lcwp_sf_sticky_tabs();
    });
    $(window).resize(function() {
        lcwp_sf_sticky_tabs();    
    });
    lcwp_sf_sticky_tabs(); // on page's show



    // set scroll position through settings save
    let lts_tout; 
    window.addEventListener('scroll', function(e) {
        if(lts_tout) {
            clearTimeout(lts_tout);
        }

        lts_tout = setTimeout(() => {
            const new_action = (!window.pageYOffset) ? settings_baseurl + window.location.hash : settings_baseurl +'&lcwpTopScroll='+ window.pageYOffset + window.location.hash;

            $('.lcwp_settings_form').attr('action', new_action);
        }, 100);
    });



    //////////////////////////////////////////////////////////



    // sliders
    if(typeof(lc_range_n_num) != 'undefined') {
        new lc_range_n_num('.lcwp_sf_slider_input', {
            unit_width: 17    
        });
    }


    // be sure numerical fields have prope values
    let lcwp_nfv_tout; 
    $(document).on('keyup input', '.lcwp_sf_4num_input, .lcwp_sf_2num_input', function(e) {
        const $f = $(this),
              val = $f.val().trim(),
              respect_limits = ($f[0].hasAttribute('data-respect-limits') && parseInt($f.data('respect-limits'), 10)) ? true : false,
              min = ($f[0].hasAttribute('min')) ? parseFloat($f.attr('min')) : false,
              max = ($f[0].hasAttribute('max')) ? parseFloat($f.attr('max')) : false,
              allow_negative = (min && min < 0) ? true : false,
              allow_float = ($f[0].hasAttribute('step') && $f.attr('step').indexOf('.') !== -1) ? true : false;
              

        $f.val(val);
                                            
        let pattern;
        if(allow_negative && allow_float) {
            pattern = '[-0-9\\.]+';
        }
        else if(allow_float) {
            pattern = '[0-9\\.]+';
        }
        else if(allow_negative) {
            pattern = '[-0-9]+';
        }
        else {
            pattern = '[0-9]+';
        }
                                               
        if(!$f[0].hasAttribute('pattern')) {
            $f.attr('pattern', pattern);
        }                                      
        if(lcwp_nfv_tout) {
            clearTimeout(lcwp_nfv_tout);
        }
        if(val === '') {
            return true;                                       
        }
        
        lcwp_nfv_tout = setTimeout(function() {                               
            if(!new RegExp(pattern, 'g').test(val)) {
                if(max && parseFloat(val) > max && !respect_limit) {
                    return true;
                }
                                               
                $f[0].reportValidity();
                if(min) {                            
                    $f.val(min);
                }
                                               
                e.preventDefault();
                return false;
            }
        }, 500);                 
    });



    // colorpicker
    $('.lcwp_sf_colpick').each(function() {
        let modes = $(this).data('modes'),
            alpha = (modes.indexOf('alpha') !== -1) ? true : false;

        modes = (modes) ? modes.trim().split(' ') : [];
        modes.push('solid');

        // remove alpha mode
        const index = modes.indexOf('alpha');
        if(index !== -1) {
          modes.splice(index, 1);
        }

        // def colors 
        let def_color = $(this).data('def-color');
        def_color = (def_color.indexOf('gradient') !== -1) ? ['#008080', def_color] : [def_color, 'linear-gradient(90deg, #ffffff 0%, #000000 100%)']; 

        new lc_color_picker('input[name="'+ $(this).attr('name') +'"]', {
            modes           : modes,
            transparency    : alpha,
            no_input_mode   : false,
            wrap_width      : '90%',
            fallback_colors : def_color,
            preview_style   : {
                input_padding   : 40,
                side            : 'right',
                width           : 35,
            },
        });
    });


    // lc switch
    window.lcwp_sf_live_check = function() {
        lc_switch('.lcwp_sf_check', {
            on_txt      : "<?php echo esc_js(strtoupper(esc_html__('yes', $ml_key))) ?>",
            off_txt     : "<?php echo esc_js(strtoupper(esc_html__('no', $ml_key))) ?>",   
        });
    };
    if(typeof(lc_switch) != 'undefined') {
        lcwp_sf_live_check();
    }


    // lc select
    window.lcwp_sf_live_select = function() {
        new lc_select('.lcwp_sf_select', {
            wrap_width : '90%',
            addit_classes : ['lcslt-lcwp'],
        });
    }
    if(typeof(lc_select) != 'undefined') {
        lcwp_sf_live_select();
    }


    // auto-height textarea
    window.lcwp_sf_textAreaAdjust = function(o) {
        o.style.height = "1px";
        o.style.height = (4 + o.scrollHeight)+"px";
    };
    $('.lcwp_sf_textarea').each(function() {
        lcwp_sf_textAreaAdjust(this);    
    });



    //////////////////////////////////////////////////

                  

    // fixed submit position
    const lcwp_sf_fixed_submit = function(btn_selector) {
        const $subj = $(btn_selector);
        if(!$subj.length) {return false;}

        let clone = $subj.clone().wrap("<div />").parent().html();

        setInterval(function() {

            // if page has scrollers or scroll is far from bottom
            if(($(document).height() > $(window).height()) && ($(document).height() - $(window).height() - $(window).scrollTop()) > 130) {
                if(!$('.lcwp_settings_fixed_submit').length) {	
                    $subj.after('<div class="lcwp_settings_fixed_submit">'+ clone +'</div>');
                }
            }
            else {
                if($('.lcwp_settings_fixed_submit').length) {	
                    $('.lcwp_settings_fixed_submit').remove();
                }
            }
        }, 50);
    };
    lcwp_sf_fixed_submit('.lcwp_settings_submit');


                  
    //////////////////////////////////////////////////

                  

    // popup message for better visibility
    if($('.lcwp_settings_result').length) {
        const $subj = $('.lcwp_settings_result');

        // if success - simply hide main one
        if($subj.hasClass('updated')) {
            $subj.remove();	
            lc_wp_popup_message('success', '<p>'+ $subj.find('p').html() +'</p>');   
        }

        // show errors but keep them visible on top
        else {
            const pre_heading = (window.location.href.indexOf('lcwp_sf_import') !== -1) ? 
                `<?php esc_html_e('One or more errors occurred during the import', $ml_key) ?>` : 
                `<?php esc_html_e('One or more errors occurred', $ml_key) ?>`;

            const error_contents = ($subj.find('ul').length) ? $subj.find('ul')[0].outerHTML : $subj.find('p')[0].innerHTML;

            lc_wp_popup_message('error', "<h4><?php esc_html_e('One or more errors occurred', $ml_key) ?>:</h4>" + error_contents);


            // try adding links bringing directly to option lines
            $subj.find('ul li').each(function() {
                const $err_li = $(this);

                let subjs = $err_li.text().split(' - ')[0];
                subjs = subjs.split(',');

                $.each(subjs, function(i, label) {
                    label = label.toString().trim();

                    $('.lcwp_sf_label label').each(function() {
                        if( $(this).text().trim().toLowerCase() == label.toLowerCase() ) {
                            $err_li.html( 
                                $err_li.html().replace(label, '<a href="#'+ $(this).parents('tr').first().attr('class') +'" title="<?php esc_attr_e('go to option', $ml_key) ?>" class="lcwp_sf_err_link">'+ label +'</a>')
                            );

                            return false;
                        }
                    });
                });
            });
        }

        // remove eventual url parameters
        setTimeout(() => {
            history.replaceState(null, null, settings_baseurl + window.location.hash);
        }, 50);
    }	


    // error-to-option direct search
    $(document).on('click', '.lcwp_sf_err_link', function(e) {
        e.preventDefault();
        const tr_selector = $(this).attr('href').replace('#', ''),
              label = $('.'+tr_selector +' .lcwp_sf_label').text();

        $('.lcwp_sf_search_wrap input').val(label).trigger('keyup');
    });
});