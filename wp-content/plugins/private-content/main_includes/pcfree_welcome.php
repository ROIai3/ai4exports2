<?php
if(!defined('ABSPATH')) {exit;}

if(ISPCF) {
    add_action('admin_notices', function() {
        if(get_option('pcf_welcome_dismissed')) {
            return false;   
        }
        ?>
        <div class="pcf_welcome_notice notice notice-info is-dismissible">
            <img src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyBpZD0iTGl2ZWxsb18xIiBkYXRhLW5hbWU9IkxpdmVsbG8gMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgMTYgMTYiPgogIDxkZWZzPgogICAgPHN0eWxlPgogICAgICAuY2xzLTEgewogICAgICAgIGZpbGw6ICM3NWMxMmU7CiAgICAgICAgc3Ryb2tlLXdpZHRoOiAwcHg7CiAgICAgIH0KICAgIDwvc3R5bGU+CiAgPC9kZWZzPgogIDxwb2x5Z29uIGNsYXNzPSJjbHMtMSIgcG9pbnRzPSIxMy43NCAwIDIuNDMgMCAwIDEuOTQgLjE4IDEuOTQgMTYgMS45NCAxMy43NCAwIi8+CiAgPHBhdGggY2xhc3M9ImNscy0xIiBkPSJNMTUuNSwyLjY1SDB2MTMuMzVoMTZWMi42NWgtLjVaTTgsNC4xMmMxLjQsMCwyLjUzLDEuMTMsMi41MywyLjUzcy0xLjEzLDIuNTMtMi41MywyLjUzLTIuNTMtMS4xMy0yLjUzLTIuNTMsMS4xMy0yLjUzLDIuNTMtMi41M1pNMTEuNDgsMTQuMjVoLTYuOTVjLS41NSwwLS45Ni0uNDYtLjk2LTEuMDEuMTgtMS42NywxLjIyLTMuMDQsMi42NS0zLjcuMzIuNjYsMSwxLjE3LDEuNzgsMS4xN3MxLjQ2LS41MSwxLjc4LTEuMTdjMS40My42NiwyLjQ3LDIuMDMsMi42NSwzLjcsMCwuNTYtLjQxLDEuMDEtLjk2LDEuMDFaIi8+Cjwvc3ZnPg==" alt="pc" />

            <div>
                <?php 
                /* translators: 1: html code, 2: html code. */
                echo '<strong>'. esc_html__('Welcome in the PrivateContent world!', 'privatecontent-free') .'</strong> <span>'. sprintf(esc_html__('Get started in minutes checking the %1$sdocumentation%2$s', 'privatecontent-free'), '<a href="https://doc.lcweb.it/privatecontent/?ispcf#init_req_steps" target="_blank">', ' &raquo;</a>') .'</span>'; ?>
            </div>
        </div>
        <?php

        $inline_js = '
        (function($) { 
            "use strict";

            $(document).on(`click`, `.pcf_welcome_notice .notice-dismiss`, () => {
                fetch(ajaxurl, {
                    method      : `POST`,
                    credentials : `same-origin`,
                    keepalive   : true,
                    headers     : {
                        `Content-Type`: `application/x-www-form-urlencoded`,
                        `Cache-Control`: `no-cache`,
                    },
                    body : new URLSearchParams({
                        action : `pcf_dismiss_welcome`,
                        nonce  : `'. esc_js(wp_create_nonce('lcwp_ajax')) .'`,
                    })
                })
                .then(response => response.text())
                .then(function(resp) {
                    // notice hidden by WP - do nothing
                });
            });
        })(jQuery);';
        wp_add_inline_script('pc-free-extra-js', $inline_js);
    }, 9999);
}




add_action('wp_ajax_pcf_dismiss_welcome', function() {
	if(!isset($_POST['nonce']) || !pc_static::verify_nonce($_POST['nonce'], 'lcwp_ajax')) {
        wp_die('Cheating?');
    }
    
    update_option('pcf_welcome_dismissed', 1, false);
	wp_die('success');	
});
