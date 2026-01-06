(function($) {
    "use strict";

    /* NFPCF */
    if(typeof(window.dike_plc) != 'undefined' && !window.dike_plc('lcweb', pc_vars.dike_slug)) {
        console.error('PrivateContent - validate the license');
        return false;    
    }
    

    // actions tracking flags
	let pc_form_pag_acting 	    = false, // just one form pagination per time
        pc_reg_is_acting        = false, // know when registration form is being submitted
        pc_login_is_acting 		= false, // security var to avoid multiple calls
        pc_del_user_is_acting 	= false; // security var to avoid multiple calls
	

    
    // passing a page URL, returns it with the anti-cache parameter applied (if should be used) - if url is not passed, the current page's one is used
    window.pc_set_pcac_param = function(url = false, force_it = false) {
        if(!url) {
            url = window.location.href;
        }
        if(!pc_vars.use_pcac && !force_it) {
            return url;
        }
        
        const obj = new URL(url);
        obj.searchParams.set('pcac', new Date().getTime());
    
        return obj.toString();
    };
    
    
    
	// fields focus/blur tracking
	const focus_track_subj = '.pc_field_container input';
    
	$(document).on('focus', focus_track_subj, function(e) {
		$(this).parents('.pc_field_container').addClass('pc_focused_field');
		
	}).on('blur', focus_track_subj, function(e) {
		$(this).parents('.pc_field_container').removeClass('pc_focused_field');
	});
	
    
    
    /* NFPCF */
    // google recaptcha v2 callback 
    window.pc_gcaptcha_v2_to_reset; // v2 widget ID to reset after answer handle
    
    window.pc_gcaptcha_v2_validated = function(token) {
        const action = window.pc_gcaptcha_v2_action;
        
        if(action == 'login') {
            submit_login(
                window.pc_gcaptcha_v2_form, 
                window.pc_gcaptcha_v2_f_data, 
                token
            );    
        }
        else if(action == 'register') {
            submit_registration(
                window.pc_gcaptcha_v2_form, 
                window.pc_gcaptcha_v2_f_data, 
                token
            );
        }
        else {
            $(document).trigger('pc_gcaptcha_v2_'+ action +'_validated', [token]);        
        }
        
        setTimeout(function() {
            grecaptcha.reset(window.pc_gcaptcha_v2_to_reset);
        }, 1500);
    };
    
    
    
    // honeypot JS validation
    window.pc_honeypot_is_valid = function($form) {
        if(!$form.find('[name="pc_hnpt_1"]').length || !$form.find('[name="pc_hnpt_2"]').length || !$form.find('[name="pc_hnpt_3"]').length) {
            return false;   
        }
        if($form.find('[name="pc_hnpt_1"]').val() !== '') {
            return false;    
        }
        if($form.find('[name="pc_hnpt_2"]').val().match(/^[0-9]+$/) == null || $form.find('[name="pc_hnpt_3"]').val().match(/^[0-9]+$/) == null) {
            return false;    
        }
        return true;
    };
    
    
    
    // remove anti-cache parameter on page's loading
    if(window.location.href.indexOf('?pcac=') || window.location.href.indexOf('&pcac=')) {
        const arr = window.location.href.split('?');
        let params = (arr.length > 1) ? arr[1].split('&') : []; 
        
        params.some((param, index) => {
            if(param.indexOf('pcac=') !== -1) {
                params.splice(index, 1)        
            }
        });

        let new_url = arr[0];
        if(params.length) {
            new_url += '?'+ params.join('&');     
        }
        
        window.history.replaceState(null, null, new_url);      
    }
    
    
    
	
    
	/**************************
			 LOGIN
	**************************/	
	
    // handler
    $(document).on('submit', '.pc_login_form', function(e) {
        e.preventDefault();

        const $target_form = $(this),
              f_data = new FormData($target_form[0]);
        
        if($target_form.find('.pc_spinner_btn').length) {
            return false;    
        }

        f_data.append('pvtcont_nonce', pc_vars.nonce);
        submit_login($target_form, f_data);
    });
	
	
	// handle form
	const submit_login = async function($form, f_data, recaptcha_token) {
		if(pc_login_is_acting) {
            return false;    
        }
        
        if(!$form.find('input[name="pc_auth_username"]').val() || !$form.find('input[name="pc_auth_psw"]').val()) {
            return false;	
        }
        
        // anti bruteforce
        if(pc_vars.abfa_blocked) {
            $form.find('#pc_auth_message').empty().append('<div class="pc_error_mess"><span>'+ pc_vars.abfa_error_mess +'</span></div>');
            return false;
        }

        const forced_redirect = $form.data('pc_redirect');
        $form.find('#pc_auth_message').empty();
        
        
        // using honeypot?
        if($form.find('.pc_hnpt_code').length) {
            if(!window.pc_honeypot_is_valid($form)) {
                $form.find('#pc_auth_message').empty().append('<div class="pc_error_mess"><span>Bot test not passed</span></div>');
                return false;
            }
        }
        
        // using recaptcha? /* NFPCF */
        else if($form.find('.pc_grecaptcha').length && typeof(recaptcha_token) == 'undefined') {

            // check if it hasn't been done yet
            if(typeof(grecaptcha) == 'undefined') {
                alert('recaptcha script not loaded');
            }
            const sitekey = pc_vars.recaptcha_sitekey;
            
            // recaptcha v3
            if(pc_vars.antispam_sys == 'recaptcha') {
                grecaptcha.ready(function() {
                    grecaptcha.execute( 
                        sitekey, 
                        {
                            action: 'submit'
                        }
                    ).then(function(token) {	
                        submit_login($form, f_data, token);
                        return true;
                    });
                });
            }
            
            // recaptcha v2 /* NFPCF */
            else if(pc_vars.antispam_sys == 'recaptcha_v2') {
                grecaptcha.ready(function() {
                    const $grecaptcha_wrap = $form.find('.pc_grecaptcha'),
                          elem_id = $form.find('.pc_grecaptcha').attr('id');
                    
                    let opt_widget_id;
                    
                    if($grecaptcha_wrap.is(':empty')) {
                        opt_widget_id = grecaptcha.render(elem_id, {
                            sitekey : sitekey,
                            size: 'invisible',
                        });
                        
                        $grecaptcha_wrap.data('opt-widget-id', opt_widget_id);
                    }
                    else {
                        opt_widget_id = $grecaptcha_wrap.data('opt-widget-id');
                    }
                    
                    window.pc_gcaptcha_v2_action = 'login';
                    window.pc_gcaptcha_v2_form = $form;
                    window.pc_gcaptcha_v2_f_data = f_data;
                    
                    window.pc_gcaptcha_v2_to_reset = opt_widget_id;
                    grecaptcha.execute(opt_widget_id);
                });
            }
            return false;	
        }
        /////


        pc_login_is_acting = true;
        $form.find('.pc_auth_btn').addClass('pc_spinner_btn');
        $form.addClass('pc_login_form_acting');
        
        f_data.append('action', 'pc_login_form_submit');
        
        if(typeof(recaptcha_token) != 'undefined') {
            f_data.append('grecaptcha_token', recaptcha_token);       
        }
        
        return await fetch(
            pc_vars.ajax_url,
            {
                method      : 'POST',
                credentials : 'same-origin',
                keepalive   : false,
                body        : f_data,
            }
        )
        .then(async response => {
            if(!response.ok) {
                return Promise.reject(response);
            }
            const pc_data = await response.json().catch(e => {
                e.status = 500;
                return Promise.reject(e);
            });

            // success
            if(pc_data.resp == 'success') {
                $form.find('#pc_auth_message').append('<div class="pc_success_mess"><span>'+ pc_data.mess +'<span></div>');
                let red_url;

                if(typeof(forced_redirect) == 'undefined' || forced_redirect == 'refresh') {
                     red_url = pc_data.redirect;

                    if(!pc_data.redirect || forced_redirect == 'refresh') {
                        red_url = pc_set_pcac_param();
                    }
                }
                else {
                    red_url = forced_redirect;
                }

                pc_vars.uid = pc_data.uid;
                $(document).trigger('pc_user_login', [pc_data.uid]);

                // GA4 analytics event
                if(typeof(window.pc_ga4_event) == 'function') {
                    window.pc_ga4_event('pc_user_login', {}, pc_data.uid);
                }

                // redirect
                setTimeout(function() {
                    window.location.href = red_url;
                }, 1000);
            }
            
            // error
            else {
                $form.find('#pc_auth_message').empty().append('<div class="pc_error_mess"><span>'+ pc_data.mess +'</span></div>');	

                // anti bruteforce
                if(pc_data.abfa) {
                    pc_vars.abfa_blocked = true;    
                }
                
                $form.removeClass('pc_login_form_acting');
            }
        })
        .catch(e => {
            if(e.status) {
                console.error(e);
                $form.find('#pc_auth_message').empty().append('<div class="pc_error_mess"><span>'+ pc_vars.ajax_failed_mess +'</span></div>');
            }
            return false;
        })
        .finally(() => {
            pc_login_is_acting = false;       
            
            // a bit of delay to hide the loader
            setTimeout(function() {
                $form.find('.pc_auth_btn').removeClass('pc_spinner_btn');
            }, 370);
        });
	};
	
	
	/* manage checkbox status on "remember me" label click */
	$(document).on('click', '.pc_login_remember_me small', function() {
		$(this).parents('.pc_login_remember_me').find('.pc_checkbox').trigger('click');
	});


	/* login form - long labels check */
	window.pc_lf_labels_h_check = function() {
        document.querySelectorAll('.pc_login_form:not(.pc_forced_lf_long_labels)').forEach(function($form) {
            if(!$form.querySelector('.pc_lf_username label')) {
                return false;        
            }
            
            const user_h 	= $form.querySelector('.pc_lf_username label').getBoundingClientRect().height,
                  user_f_h 	= $form.querySelector('input[name="pc_auth_username"]').getBoundingClientRect().height,
                  
                  psw_h 	= $form.querySelector('.pc_lf_psw label').getBoundingClientRect().height,
                  psw_f_h   = $form.querySelector('input[name="pc_auth_psw"]').getBoundingClientRect().height;
            
            ((user_h > user_f_h || psw_h > psw_f_h) && window.innerWidth >= 440) ? $form.classList.add('pc_lf_long_labels') : $form.classList.remove('pc_lf_long_labels');
        });
	};
	
    
    /* login form - overlapping single small/button */
	window.pc_lf_overlapping_smalls_check = function() {
        document.querySelectorAll('.pc_login_form:not(.pc_fullw_login_btns)').forEach(function($form) {
            if(!$form.querySelectorAll('.pc_login_smalls')[0].children.length || $form.querySelectorAll('.pc_login_smalls')[0].children.length > 1) {
                return false;        
            }
            
            const form_w    = $form.getBoundingClientRect().width,
                  btn_w 	= $form.querySelector('.pc_auth_btn').getBoundingClientRect().width,
                  small_w 	= (!$form.querySelectorAll('.pc_login_smalls')[0].children.length) ? 0 : $form.querySelectorAll('.pc_login_smalls')[0].children[0].getBoundingClientRect().width;
            
            ((btn_w + small_w + 300) > form_w) ? $form.classList.add('pc_long_smalls_fix') : $form.classList.remove('pc_long_smalls_fix');
        });
	};
    
	
	// on resize
	let pc_is_resizing;
        
    $(window).resize(function() {
        if(pc_is_resizing) {
            clearTimeout(pc_is_resizing);
        }

        pc_is_resizing = setTimeout(function() {
            pc_lf_labels_h_check();
            window.pc_lf_overlapping_smalls_check();
        }, 50); 
    });
	
	
	
	
	/**************************
           USER DELETION
	**************************/
	   
    $(document).on('submit', '.pc_del_user_form', async function(e) {
        e.preventDefault();
        
        const $form     = $(this),
              redirect  = $form.data('pc_redirect'),
              val       = $form.find('input[name=pc_del_user_psw]').val();	
	
        if(!val.trim() || pc_del_user_is_acting) {
            return false;    
        }
        pc_del_user_is_acting = true;	

        $form.find('.pc_del_user_btn').addClass('pc_spinner_btn');
        $form.find('.pc_user_del_message').empty();
		
        let f_data = new FormData();
        f_data.append('action', 'pc_user_del_ajax');
        f_data.append('pc_ud_psw', val);
        f_data.append('pvtcont_nonce', pc_vars.nonce);
        
        await fetch(
            pc_vars.ajax_url,
            {
                method      : 'POST',
                credentials : 'same-origin',
                keepalive   : false,
                body        : f_data,
            }
        )
        .then(async response => {
            if(!response.ok) {
                return Promise.reject(response);
            }
            const pc_data = await response.json().catch(e => {
                e.status = 500;
                return Promise.reject(e);
            });
            
            // success
            if(pc_data.resp == 'success') {
                $form.find('.pc_user_del_message').append('<div class="pc_success_mess"><span>'+ pc_data.mess +'<span></div>');

                $(document).trigger('pc_user_profile_deletion');

                // GA4 analytics event
                if(typeof(window.pc_ga4_event) == 'function') {
                    window.pc_ga4_event('pc_user_profile_deletion');
                }

                // redirect
                setTimeout(function() {
                    window.location.href = redirect;
                }, 1000);
            }
            
            
            // error
            else {
                $form.find('.pc_user_del_message').empty().append('<div class="pc_error_mess"><span>'+ pc_data.mess +'</span></div>');
                pc_del_user_is_acting = false;
            }
        })
        .catch(e => {
            if(e.status) {
                console.error(e);
                $form.find('.pc_user_del_message').empty().append('<div class="pc_error_mess"><span>'+ pc_vars.ajax_failed_mess +'</span></div>');
            }
            return false;
        })
        .finally(() => {
            // a bit of delay to hide the loader
            setTimeout(function() {
                $form.find('.pc_del_user_btn').removeClass('pc_spinner_btn');
            }, 370);     
        });
	});
	
	
	
	
	/**************************
			 LOGOUT
	**************************/
		 
	$(document).on('click', '.pc_logout_btn', async function() {
		const forced_redirect = $(this).data('pc_redirect'),
              $btn = $(this);
        
		$btn.addClass('pc_spinner_btn');
		
        let f_data = new FormData();
        f_data.append('action', 'pc_logout_btn_handler');
        f_data.append('pvtcont_nonce', pc_vars.nonce);
        
        return await fetch(
            pc_vars.ajax_url,
            {
                method      : 'POST',
                credentials : 'same-origin',
                keepalive   : false,
                body        : f_data,
            }
        )
        .then(async response => {
            if(!response.ok) {return Promise.reject(response);}
            const resp = (await response.text()).trim();
            
            // be sure there are no errors
            if(resp && !/^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i.test(resp)) {
                alert(resp);
                return false;
            }
            
            
            $(document).trigger('pc_user_logout');
            
            // GA4 analytics event
            if(typeof(window.pc_ga4_event) == 'function') {
                window.pc_ga4_event('pc_user_logout');
            }

            if(typeof(forced_redirect) == 'undefined' || !forced_redirect) {
                window.location.href = (!resp) ? pc_set_pcac_param() : resp;
            }
            else {
                window.location.href = (forced_redirect == 'refresh') ? pc_set_pcac_param() : forced_redirect;
            }
        })
        .catch(e => {
            if(e.status) {
                console.error(e);
                alert(pc_vars.ajax_failed_mess);
            }
            return false;
        })
        .finally(() => {
            $btn.removeClass('pc_spinner_btn');  
        });
	});
	
	
			
		
	/**************************
		   REGISTRATION
	**************************/	

    $(document).on('submit', '.pc_registration_form', function(e) {
        e.preventDefault();
        
        const $target_form = $(this),
              f_data = new FormData($target_form[0]);
        
        f_data.append('pvtcont_nonce', pc_vars.nonce);
        submit_registration($target_form, f_data);
    });
	
	
	// handle form
	const submit_registration = async function($form, f_data, recaptcha_token) {
		if(pc_reg_is_acting) {
            return false;
        }
			
		// HTML5 validate first
		if(!$form.pc_validate_form_fieldset()) {
			return false;	
		}
		
        // needs to simply go to next page?
        if($form.find('.pc_pag_next:not(.pc_pag_btn_hidden)').length) {
            $form.find('.pc_pag_next:not(.pc_pag_btn_hidden)').click();
            return false;
        }
        
		const fid     = $form.attr('id'),	
              cc      = (typeof($form.data('pc_cc')) == 'undefined') ? '' : $form.data('pc_cc'),
              redir   = $form.data('pc_redirect');
			
        $form.find('.pc_form_response').empty();
        
        
        // using honeypot?
        if($form.find('.pc_hnpt_code').length) {
            if(!window.pc_honeypot_is_valid($form)) {
                $form.find('.pc_form_response').empty().append('<div class="pc_error_mess"><span>Bot test not passed</span></div>');
                return false;
            }
        }
        
		// using recaptcha? /* NFPCF */
		else if($form.find('.pc_grecaptcha').length && typeof(recaptcha_token) == 'undefined') {

			// check if it hasn't been done yet
			if(typeof(grecaptcha) == 'undefined') {
				alert('recaptcha script not loaded');
			}
			const sitekey = pc_vars.recaptcha_sitekey;
            
            
            // recaptcha v3 /* NFPCF */
            if(pc_vars.antispam_sys == 'recaptcha') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(
                        sitekey, 
                        {
                            action: 'submit'
                        }
                    ).then(function(token) {	
                        submit_registration($form, f_data, token);
                        return true;
                    });
                });
            }
            
            // recaptcha v2
            else if(pc_vars.antispam_sys == 'recaptcha_v2') {
                grecaptcha.ready(function() {
                    const $grecaptcha_wrap = $form.find('.pc_grecaptcha'),
                          elem_id = $form.find('.pc_grecaptcha').attr('id');
                    
                    let opt_widget_id;
                    
                    if($grecaptcha_wrap.is(':empty')) {
                        opt_widget_id = grecaptcha.render(elem_id, {
                            sitekey : sitekey,
                            size: 'invisible',
                        });
                        
                        $grecaptcha_wrap.data('opt-widget-id', opt_widget_id);
                    }
                    else {
                        opt_widget_id = $grecaptcha_wrap.data('opt-widget-id');
                    }
                    
                    window.pc_gcaptcha_v2_action = 'register';
                    window.pc_gcaptcha_v2_form = $form;
                    window.pc_gcaptcha_v2_f_data = f_data;
                    
                    window.pc_gcaptcha_v2_to_reset = opt_widget_id;
                    grecaptcha.execute(opt_widget_id);
                });
            }
			return false;	
		}
		/////
		

		pc_reg_is_acting = true;
        $(window).trigger('pc_pre_form_submit', [$form]);
		$form.find('.pc_reg_btn').addClass('pc_spinner_btn');
		
        f_data.append('action', 'pc_reg_form_submit');
        f_data.append('form_id', $form.data('form-id'));
        f_data.append('pc_cc', cc);
        
        if(typeof(recaptcha_token) != 'undefined') {
            f_data.append('grecaptcha_token', recaptcha_token);
        }
        
        return await fetch(
            pc_vars.ajax_url,
            {
                method      : 'POST',
                credentials : 'same-origin',
                keepalive   : false,
                body        : f_data,
            }
        )
        .then(async response => {
            if(!response.ok) {
                return Promise.reject(response);
            }
            const pc_data = await response.json().catch(e => {
                e.status = 500;
                return Promise.reject(e);
            });
            
            // success
            if(pc_data.resp == 'success') {
                // emulate user ID for PCUA add-on
                const bkp_val = pc_vars.uid;
                pc_vars.uid = pc_data.new_uid;

                $(document).trigger('pc_successful_registr', [$form, pc_data.new_uid, pc_data.fid]);
                pc_vars.uid = bkp_val;

                // GA4 analytics event
                if(typeof(window.pc_ga4_event) == 'function') {
                    window.pc_ga4_event('pc_user_registration', {}, pc_data.new_uid);
                }


                $form.find('.pc_form_response').append('<div class="pc_success_mess"><span>'+ pc_data.mess +'<span></div>');
                if(pc_vars.hide_reg_btn_on_succ) {
                    $form.find('.pc_reg_btn').remove();    
                }


                //// redirect
                let redirect;

                // special case for premium plans add-on - overrides any other case
                if(
                    (typeof(redir) != 'undefined' && redir.substr(0,2) != 'f-') &&
                    pc_data.redirect && 
                    pc_data.redirect.indexOf('pay_for_order=true') !== -1
                ) {
                    redirect = pc_data.redirect	
                }
                else {
                    if(typeof(redir) != 'undefined') {
                        redirect = (redir.substr(0,2) == 'f-') ? redir.substr(2) : redir;
                    } else {
                        redirect = pc_data.redirect;	
                    }

                    if(redirect == 'refresh') {
                        redirect = window.location.href;
                    }
                }

                if(redirect) {
                    setTimeout(function() {
                        window.location.href = redirect;
                    }, 1000);	
                }
            }
            
            
            // error
            else {
                $form.find('.pc_form_response').append('<div class="pc_error_mess">'+ pc_data.mess +'</div>');

                // if exist recaptcha - reload
                if( $('#recaptcha_response_field').length) {
                    Recaptcha.reload();	
                }
            }
        })
        .catch(e => {
            if(e.status) {
                console.error(e);
                $form.find('.pc_form_response').append('<div class="pc_error_mess">'+ pc_vars.ajax_failed_mess +'</div>');
            }
            return false;
        })
        .finally(() => {
            pc_reg_is_acting = false;    
            
            // a bit of delay to hide the loader
            setTimeout(function() {
                $form.find('.pc_reg_btn').removeClass('pc_spinner_btn');
            }, 370);
        });
	};
	
	
	
	// given the form object - returns the recaptcha widget ID
	const get_recaptcha_widget_id = function($form) {
		var gre_id_arr = $form.find('.g-recaptcha-response').attr('id').split('-');
		return (gre_id_arr.length == 4) ? parseInt(gre_id_arr[3], 10) : 0;	
	};	

    
    
    // mail-only registration - copy the email field in the username one
    $(document).ready(function($) {
        $(document).on('keyup input', '.pc_onlymail_reg .pc_f_email input', function() {
            
            const $username_f = $(this).parents('.pc_registration_form').find('.pc_f_username input');
            $username_f.val( $(this).val() );
        });    
    });
    
    
	
	///////////////////////////////////
	
	
	
    /* LC Select setup */
    window.pc_lc_select_setup = function() {
        if(!$('.pc_multiselect, .pc_singleselect').length) {
            return true;    
        }
        if(typeof(lc_select) == 'undefined') {
            console.error('pvtContent: LC select script not found');
            return false;  
        }
           
        new lc_select('.pc_multiselect select, .pc_singleselect select', {
            wrap_width      : '100%',
            addit_classes   : ['lcslt-pc-skin'],
            pre_placeh_opt  : true, 
            labels : [ 
                pc_vars.lcslt_search,
                pc_vars.lcslt_add_opt,
                pc_vars.lcslt_select_opts +' ..',
                '.. '+ pc_vars.lcslt_no_match +' ..',
            ],
        });
    };
	$(document).ready(function() {
		pc_lc_select_setup();
	});
	
	
	
	/* setup custom checkboxes */
	window.pc_checkboxes_setup = function() {
        lc_switch('.pc_single_check input[type=checkbox], .pc_disclaimer_f input[type=checkbox]', {
            on_txt      : 'YES',
            off_txt     : 'NO',  
            compact_mode: true,
        });
        
		$('.pc_login_form input[type=checkbox], .pc_check_wrap input[type=checkbox], .pc_check_wrap input[type=radio], .pc_manag_check input[type=checkbox], .pc_manag_check input[type=radio]').each(function() {
			if($(this).hasClass('pc_checkboxed')) {
                return true;
            }
			
			const $subj          = $(this),
                  checked        = ($subj.is(':checked')) ? 'pc_checked' : '',
                  
                  is_radio_class = ($subj.attr('type') == 'radio') ? 'pc_radio_cb' : '',
                  check_content  = (is_radio_class) ? '&bull;' : '&#10003;';

			$subj
                .addClass('pc_checkboxed')
                .after('<div class="pc_checkbox '+ is_radio_class +' '+ checked +'" data-name="'+ $subj.attr('name') +'" data-val="'+ $subj.val() +'"><span>'+ check_content +'</span></div>');
		});
	};
	$(document).ready(function() {
		pc_checkboxes_setup();
	});
    
    
    // handle click on PC custom checkboxes
    $(document).on('click', 'div.pc_checkbox', function() {
        const $subj     = $(this),
              type      = ($subj.hasClass('pc_radio_cb')) ? 'radio' : 'checkbox', 
              $input = $subj.prev('input[type="'+ type +'"][name="'+ $subj.data('name') +'"][value="'+ $subj.data('val') +'"]');

        if(!$input.length) {
            return true;
        }

        if($subj.hasClass('pc_checked')) {
            if(type == 'radio') {
                return false;    
            } else {
                $subj.removeClass('pc_checked');        
            }
        }
        else {
            if(type == 'radio') {
                $subj.parents('.pc_check_wrap').find('.pc_checkbox').removeClass('pc_checked');    
                $subj.parents('.pc_check_wrap').find('.pc_checkboxed').each(function() {
                    this.checked = false;    
                });  
            }
            
            $subj.addClass('pc_checked');        
        }

        $input[0].checked = !$input[0].checked;
        $input.trigger('input');
    });
    
    
    // use custom checkbox on label's click
    $(document).on('click', '.pc_check_label', function() {
        $(this).prev('.pc_checkbox').trigger('click');    
    });
    
	

	
	
	/* fluid forms - columnizer */
	window.pc_fluid_form_columnizer = function() {
		const threshold = (typeof(pc_vars) == 'object' && typeof(pc_vars.fluid_form_thresh) != 'undefined') ? parseInt(pc_vars.fluid_form_thresh, 10) : 370;
        
        document.querySelectorAll('.pc_fluid_form').forEach($form => {
            
            const computedStyle = getComputedStyle($form),
                  form_w        = $form.clientWidth - parseFloat(computedStyle.paddingLeft) - parseFloat(computedStyle.paddingRight), 
                  min_col_w     = 120;

            let cols = 1; 
            while(Math.ceil(form_w / cols) > threshold && Math.ceil(form_w / (cols + 1)) > min_col_w) {
                cols++;            
            }

            $form.querySelectorAll('fieldset').forEach($fieldset => {
                $fieldset.style.gridTemplateColumns = 'repeat('+ cols +', 1fr)';
            });
            
            $form.querySelectorAll('.pc_fullw_field').forEach($fullw => {
                $fullw.style.gridColumn = '1 / span '+ cols;
            });
            
            $form.classList.add('pc_fluid_form_columnized');
            $form.setAttribute('data-col', cols);
            
            // trigger event to allow custom add-on events on columnification
            const event = new CustomEvent("pc_form_columnized", {detail: {
                form: $form,
                cols: cols,
            }});
            document.dispatchEvent(event);
        });
	};
    pc_fluid_form_columnizer();
    
    
    let pc_ffc;
    $(window).resize(function() { 
        if(pc_ffc) {
            clearTimeout(pc_ffc);
        }
        pc_ffc = setTimeout(function() {
            pc_fluid_form_columnizer();
        }, 50);
	});
	
    
    
    
    /* revealable password */
    if(typeof(pc_vars.revealable_psw) == 'undefined' || pc_vars.revealable_psw) {
        $(document).on('click', '.pc_f_psw .pc_field_icon, .pc_lf_psw .pc_field_icon, .pc_del_user_form .pc_field_icon', function() {
            
            const vis_class = 'pc_visible_psw',
                  $field    = $(this).parents('.pc_field_container').find('input'),
                  $icon     = $(this).find('i');
            
            if($field.hasClass(vis_class)) {
                $field.removeClass(vis_class);
                $field.attr('type', 'password');
                $icon.attr('class', 'far fa-eye');
            }
            else {
                $field.addClass(vis_class);   
                $field.attr('type', 'text');
                $icon.attr('class', 'far fa-eye-slash');
            }
        });            
    }
    
    

	

	/**************************
		  FORM PAGINATION
	**************************/	
	
    // paginate on buttons click
	$(document).on('click', '.pc_pag_btn', function(e) {
        const $form = $(this).parents('form').first();
        
        let curr_pag = parseInt($form.data('form-pag'), 10),
            new_pag = ($(e.target).hasClass('pc_pag_next')) ? curr_pag + 1 : curr_pag - 1;
        
        paginate_form($form, new_pag);
    });
    
    
    // paginate on progres bar click
	$(document).on('click', '.pc_form_pag_progress span:not(.pc_fpp_current)', function(e) {
        const $form = $(this).parents('form').first();
        
        let curr_pag = parseInt($form.data('form-pag'), 10),
            new_pag = parseInt($(this).data('pag'), 10);
        
        paginate_form($form, new_pag);
    });
    
    
    // perform form pagination
	const paginate_form = function($form, new_pag) {
        if(pc_form_pag_acting || pc_reg_is_acting) {
            return true;
        }

        let curr_pag = parseInt($form.data('form-pag'), 10);
        const tot_pag = $form.find('fieldset').length;
        
        if(new_pag < 0 || new_pag > tot_pag) {
            return false;    
        }
        
        const $new_fieldset = $form.find('fieldset.pc_f_pag_'+new_pag),
              $curr_fieldset = $form.find('fieldset.pc_f_pag_'+curr_pag);

        // HTML5 validate first
        if(!$form.pc_validate_form_fieldset()) {
            $("body, html").animate({
                scrollTop: ($form.find('fieldset').not('.pc_hidden_fieldset').find('.pc_field_error').first().offset().top - 50)
            }, 500);

            return true;	
        }

        // apply
        pc_form_pag_acting = true;

        $form.css('height', $form.outerHeight());
        $form.data('form-pag', new_pag);
        $form.find('> *').not('script, .pc_form_pag_progress').animate({opacity : 0}, 150);;

        
        // pagination progress bar? adjust
        if($form.find('.pc_form_pag_progress').length) {
            $form.find('.pc_form_pag_progress span').removeClass('pc_fpp_current pc_fpp_active');
            
            for(let c=1; c <= new_pag; c++) {
                $form.find('.pc_form_pag_progress span[data-pag="'+ c +'"]').addClass('pc_fpp_active');       
            }
            
            $form.find('.pc_form_pag_progress span[data-pag="'+ new_pag +'"]').addClass('pc_fpp_current');  
            
            const progressbar_w = (100 / (tot_pag - 1)) * (new_pag - 1);
            $form.find('.pc_form_pag_progress i').css('width', progressbar_w + '%');
        }
        
        
        // apply
        setTimeout(function() {
            $new_fieldset.removeClass('pc_hidden_fieldset');

            const new_form_h = ($form.outerHeight() - $curr_fieldset.outerHeight(true)) + $new_fieldset.outerHeight(true);  
            $form.animate({height : new_form_h}, 300);

            $curr_fieldset.addClass('pc_hidden_fieldset');
            (new_pag == tot_pag) ? $form.find('.pc_pag_submit').show() : $form.find('.pc_pag_submit').hide();	

            setTimeout(function() {	
                $form.find('fieldset, .pc_pag_submit, .pc_pag_btn, .pc_form_response').animate({opacity : 1}, 150);

                // next btn and submit visibility
                if(new_pag == tot_pag) {
                    $form.find('.pc_pag_next').addClass('pc_pag_btn_hidden');
                } else {
                    $form.find('.pc_pag_next').removeClass('pc_pag_btn_hidden');	
                }

                // prev btn visibility
                if(new_pag < 2) {
                    $form.find('.pc_pag_prev').addClass('pc_pag_btn_hidden');
                } else {
                    $form.find('.pc_pag_prev').removeClass('pc_pag_btn_hidden');	
                }

                $form.css('height', 'auto');
                pc_form_pag_acting = false;
            }, 350);
        }, 300);        
    };
    
    
	
	

	/**************************
		  FORM VALIDATION
	**************************/	
	
	
	// validate fields using HTML5 engine
	$.fn.pc_validate_fields = function() {
		if(typeof(pc_vars.html5_validation) == 'undefined' || !pc_vars.html5_validation) {
            return true;
        }
		
		// if browser doesn't support validation - ignore
		if(!(typeof document.createElement('input').checkValidity == 'function')) {
            return true;
        }
		
		let errorless = true,
            multicheck_objs = {}; // store multi-checkbox wrapper's obj to be validated after
		 
		$(this).each(function() {
            if(!$(this).parents('section').first().is(':visible')) {
                return true;    
            }
            
			// multicheck element
			if($(this).parents('.pc_check_wrap').length && $(this).parents('section').find('.pc_req_field').length) {
				multicheck_objs[ $(this).attr('name') ] = $(this).parents('section.pc_form_field');
				return true;	
			}
			
			// avoid select search field
			if($(this).is('input') && typeof($(this).attr('name')) == 'undefined') {
				return true;	
			}
			
			// remove old errors
			$(this).parents('section.pc_form_field, section.pc_disclaimer_f').find('.pc_field_error').remove();
			
			// validate
            if( !$(this)[0].checkValidity() ) {

				errorless = false;
				let mess = $(this)[0].validationMessage; 
				
				// remove ugly point at the end
				if( mess.substr(mess.length - 1) == '.') {
					mess = mess.substr(0, (mess.length - 1));
				}
				
				$(this).parents('section.pc_form_field, section.pc_disclaimer_f').prepend('<div class="pc_field_error">'+ mess +'</div>');	
			}
        });
		

		// validate multichecks
		$.each(multicheck_objs, function(i, $wrap) {
			var show_mess = true;
			$wrap.find('.pc_field_error').remove();
			
			$wrap.find('input[type=checkbox], input[type=radio]').each(function() {
				if(this.checked) {
					show_mess = false;
					return false;	
				}
			});
			
			if(show_mess) {
				// generate message to append
				var mess = $('<input type="checkbox" name="" required="required" />')[0].validationMessage;
				
				// remove ugly point at the end
				if( mess.substr(mess.length - 1) == '.') {
					mess = mess.substr(0, (mess.length - 1));
				}
				
				$wrap.prepend('<div class="pc_field_error">'+ mess +'</div>');
                errorless = false;
			}
		});
        
		return errorless;
	};
	
    
	// shortcut to validate active fieldset fields
	$.fn.pc_validate_form_fieldset = function() {
		return $(this).find('fieldset').not('.pc_hidden_fieldset').find('input, select, textarea').pc_validate_fields();
	};
	
	
	// re-validate on field change
	$(document).ready(function($) {
		$('body, form').on('change keyup input', '.pc_form_field input, .pc_form_field select, .pc_form_field textarea, .pc_disclaimer_f input', function() {
			
			if($(this).pc_validate_fields()) {
				$(this).parents('.pc_form_field').find('.pc_field_error').pc_close_tooltip();	
			}
		});
	});
	
	
	// close field error tooltip
	$.fn.pc_close_tooltip = function() {
		var $subj = $(this);
		$subj.addClass('pc_fe_closing');
		
		setTimeout(function() {
			$subj.remove();
		}, 310);
	};
	
	// close form tooltips on single tooltip click
	$(document).ready(function($) {
		$('body, form').on('click', '.pc_field_error', function() {
			$(this).parents('form').find('.pc_field_error').each(function() {
				$(this).pc_close_tooltip();
			});
		});
	});
	
	

    
    /* NFPCF */
	/**************************
			LIGHTBOX 
	**************************/	
	    
	$(document).ready(function() {
        init_lightbox_engine();
	});
    
    const init_lightbox_engine = function() {
        let is_getting_lb = false;
        
        if(typeof(window.pc_lb_classes) == 'undefined' || typeof(jQuery.magnificPopup) == 'undefined') {
            setTimeout(() => {
                init_lightbox_engine();   
            }, 75);
            
            return false;
        }
         
        // persistent check to preload contents
        var pc_lb_load_intval = setInterval(async function() {
            if(is_getting_lb) {
                return;   
            }
            
            let to_load = [];

            $.each(window.pc_lb_classes, function(i, v) {
                const id = parseInt(v.replace('.pc_lb_trig_', ''), 10);
                if($.inArray(id, window.pc_ready_lb) !== -1) {
                    return true;
                }

                // ajax call to get
                if($(v).length) {
                    to_load.push(id);
                }
            });

            if(to_load.length) {
                is_getting_lb = true;
                
                let f_data = new FormData();
                f_data.append('action', 'pc_lightbox_load');
                f_data.append('ids', to_load);
                f_data.append('pvtcont_nonce', pc_vars.nonce);

                await fetch(
                    pc_vars.ajax_url,
                    {
                        method      : 'POST',
                        credentials : 'same-origin',
                        keepalive   : false,
                        body        : f_data,
                    }
                )
                .then(async response => {
                    if(!response.ok) {
                        return Promise.reject(response);
                    }
                    const data = await response.json().catch(e => {
                        e.status = 500;
                        return Promise.reject(e);
                    });
                    
                    
                    Object.keys(data).forEach(function(lb_id) {
                        window.pc_ready_lb.push( parseInt(lb_id, 10) );
                        
                        $('.pc_lightbox_contents.pc_lb_'+ lb_id).remove(); // be sure in any case to remove existing ones
                        $('#pc_lb_codes').append(data[lb_id]);
                    });
                })
                .catch(e => {
                    if(e.status) {
                        console.error('pvtContent lightbox codes loading error', e);
                    }
                    return false;
                })
                .finally(() => {
                    is_getting_lb = false;
                });
            }

            // if loaded every lightbox - end interval 
            if(window.pc_lb_classes.length == pc_ready_lb.length) {
                clearInterval(pc_lb_load_intval);	
            }
        }, 200);



        // track lightbox triggers click
        $.each(window.pc_lb_classes, function(i,v) {
            const lb_id = v.replace('.pc_lb_trig_', '');

            $(document).on('click', v, function(e) {
                if(!$('.pc_lb_'+lb_id).length) {
                    return true;
                }
                e.preventDefault();
                
                let extra_classes = ($(this).hasClass('pc_modal_lb')) ? 'pc_modal_lb' : '';
                
                jQuery.magnificPopup.open({
                    items : {
                        src: '.pc_lb_'+lb_id,
                        type: 'inline'
                    },
                    mainClass			: 'pc_lightbox '+ extra_classes,
                    closeOnContentClick	: false,
                    closeOnBgClick		: false, 
                    preloader			: false,
                    modal				: ($(this).hasClass('pc_modal_lb')) ? true : false,
                    focus				: false,
                    removalDelay		: 300,
                    callbacks: {
                        open: function() {
                            pc_lc_select_setup();
                            pc_checkboxes_setup();
                            pc_fluid_form_columnizer();

                            // if last element is a form - remove bottom margin
                            if($('.pc_lightbox_contents > *').eq(-2).hasClass('pc_aligned_form')) {
                                $('.pc_lightbox_contents > *').eq(-2).find('form').css('margin-bottom', 0);
                            }

                            // allow other plugins to hook here
                            $(document).trigger('pc_opening_lightbox');
                        }
                    }
                });

                return false;
            });
        });	
        
        
        
        // fix MagPop issue focusing LC Select search field
        jQuery.magnificPopup.instance._onFocusIn = function(e) {
            if($(e.target).is('input[name="lcslt-search"]')) {
                return true;
            } 
            
            jQuery.magnificPopup.proto._onFocusIn.call(this, e);
        };
        
        
        // force modal lb to not close on background mobile tap
        $(document).on('touchstart touchend tap', '*', function(e) {
            const $clicked = $(e.target);
            
            if($clicked.parents('.pc_modal_lb .mfp-content').length) {
                e.stopPropagation();
                return true;
            }
            
            if($clicked.parents('.pc_modal_lb').length || $clicked.is('.pc_modal_lb')) {
                e.preventDefault();
                return false;        
            } 
        });
    };
	
	
    
    // remove URL attributes linked to WP form login errors to avoid chained false negatives
    const wplupr_url = window.location.href.replace(window.location.hash, '');
    if(wplupr_url.indexOf('pc_wp_login_status_err') !== -1 || wplupr_url.indexOf('pc_wp_login_cust_err') !== -1) {
        let arr = wplupr_url.split('?');
        const base = arr[0];
        const parts = arr[1].split('&');
        
        let new_parts = [];
        parts.forEach((part) => {
            
            if(part.indexOf('pc_wp_login_status_err') === -1 && part.indexOf('pc_wp_login_cust_err') === -1) {
                new_parts.push(part);   
            }
        });
        
        let new_url = (new_parts.length) ? base +'?'+ new_parts.join('?') : base;
        if(window.location.hash) {
            new_url += window.location.hash;    
        }
        
        history.replaceState(null, null, new_url);
    }
    
    
})(jQuery);
