<?php 
add_action('admin_footer', function() {
    if(isset($GLOBALS['lcshop_adv_processed'])) {
        return false;   
    }
    $GLOBALS['lcshop_adv_processed'] = true;
    
    $notice_name = 'lcshop_adv_08_09_24';
    $notice_id = md5($notice_name);
    if(get_transient('dike_notice_'. $notice_id .'_dismissed') || isset($_COOKIE[$notice_name])) {
        return true;    
    }
    
    ?>
    <style>
    .lcshop_adv_modal {
        padding: 0 !important;        
    }
    #lcshop_adv_wrap {
        width: 100vw;
        max-width: min(90vw, 640px);
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        overflow: hidden;
    }
    #lcshop_adv_wrap * {
        box-sizing: border-box;
    }
    #lcshop_adv_wrap img {
        width: 100%;     
    }
    #lcshop_adv_wrap h3 {
        background: linear-gradient(360deg, rgb(87, 144, 36) 65%, rgb(84, 131, 41) 100%);
        transform: skew(-10deg, -2.8deg) translate3d(-100%, 0, 0); 
        width: calc(100% + 20px);
        padding: 20px;
        margin: -16px 0 0;
        overflow: hidden;
        transition: transform .7s ease-in-out 0s;
    }
    #lcshop_adv_wrap.lcshop_adv_wrap_shown h3 {
        transform: skew(0, -2.8deg); 
    }
    #lcshop_adv_wrap h3 span {
        display: inline-block;
        transform: skew(0, -2deg) translate3d(0, 80%, 0); 
        color: #fff;
        text-shadow: 0 0 5px rgba(0,0,0, 0.1);
        font-size: 1.5rem;
        line-height: normal;
        letter-spacing: 0.01rem;
        opacity: 0;
        transition: all .7s cubic-bezier(0.175, 0.885, 0.445, 1.605) .7s;
    }
    #lcshop_adv_wrap.lcshop_adv_wrap_shown h3 span {
        opacity: 1;
        transform: skew(-2deg, 0) translate3d(0, -2px, 0); 
    }
    .lcshop_adv_text,
    .lcshop_adv_btns {
        opacity: 0;
        transform: translate3d(-10px, 0, 0);
        transition: all 0.5s ease-out 1.3s;
    }
    #lcshop_adv_wrap.lcshop_adv_wrap_shown .lcshop_adv_text,
    #lcshop_adv_wrap.lcshop_adv_wrap_shown .lcshop_adv_btns {
        opacity: 1;
        transform: none;
    }
        
    .lcshop_adv_text {
        padding: 45px 45px 35px;
    }
    .lcshop_adv_text * {
        font-size: 1.05rem;
        line-height: 1.37;
    }
    .lcshop_adv_text p {
        margin: 0 0 27px !important;
        font-size: 1.1rem;
    }
    #lcshop_adv_wrap ul,
    #lcshop_adv_wrap ul li {
        text-align: left;
        margin: 0 -10px 0 0;
        padding: 0;
        list-style: disc outside;
    }
    #lcshop_adv_wrap ul li small * {
        font-size: 0.85rem;
    }
    #lcshop_adv_wrap ul li:not(:last-child) {
        margin-bottom: 4px;   
    }
    .lcshop_adv_btns {
        display: flex;
        width: 100%;
        flex-direction: row;
        gap: 15px;
        justify-content: space-around;
        align-items: center;
        padding: 0 30px 40px;
    }
    .lcshop_adv_btns a {
        display: inline-block;
        color: #fff;
        font-size: 1rem;
        line-height: 1.4;
        font-weight: 600;
        border-radius: 50px;
        padding: 9px 24px 12px;
        background: #63a031;
        text-decoration: none;
        transition: all .2s cubic-bezier(0.175, 0.885, 0.445, 1.605);
    }
    .lcshop_adv_btns a:hover {
        transform: scale(1.05) translate3d(0, -2.5%, 0);
        line-height: 1.5;
        background: #7fc241;
        text-shadow: 0 0 3px rgba(0,0,0,0.15);
        margin-bottom: -2px;
    }
    .lcshop_adv_btns a:hover,
    .lcshop_adv_btns a:focus,
    .lcshop_adv_btns a:active {
        box-shadow: none;
        text-decoration: none;
    }
    .lcshop_adv_btns .notice-dismiss {
        position: static !important;
    }
    .lcshop_adv_btns .notice-dismiss:before {
        display: none !important;
    }
        
    @media screen and (max-width: 700px) {
        #lcshop_adv_wrap h3 {
            padding: 15px;   
        }
        #lcshop_adv_wrap h3 span {
            font-size: 1.25rem;
        }
        .lcshop_adv_text {
            padding-top: 37px;
            padding-bottom: 37px;
        }
        .lcshop_adv_text * {
            font-size: 1rem;
            line-height: 1.3;
        }
        .lcshop_adv_text p {
            font-size: 1.02rem;
        }
        #lcshop_adv_wrap ul li:not(:last-child) {
            margin-bottom: 10px;
        }
        .lcshop_adv_btns a {
            font-size: 0.97rem;
            line-height: 1.3;
            padding: 7px 22px 10px;
        }
        .lcshop_adv_btns {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            padding-bottom: 30px;
        }
    }
    </style>
    
    <script type="text/javascript">
    (function() {
        "use strict";
        
        const base64_img = `data:image/webp;base64,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`;
        
        const contents = `
        <div id="lcshop_adv_wrap" data-notice-id="<?php echo esc_attr($notice_id) ?>" data-trans-time="99999999999">
            <img src="${ base64_img }" alt="lcweb shop" />
            <h3><span>Say hello to LCweb Shop!</span></h3>

            <div class="lcshop_adv_text">
                <p>Finally there's a new way for purchasing LCweb product licenses, giving you more than ever before:</p>
                
                <ul>
                    <li><strong>1-year support and automated updates</strong> <small><em>(instead of 6 months you get on <a href="https://1.envato.market/9WjgKj" target="_blank">Envato</a>)</em></small></li>
                    <li><strong>Automated</strong> license and support service registration</li>
                    <li><strong>1-click</strong> access to the support website. No more double registration</li>
                    <li>Multi-licenses purchase with <strong>special discounts</strong> (up to 35% off!)</li>
                </ul>
            </div>
        
            <div class="lcshop_adv_btns">
                <a href="https://lcweb.it/products-overview/?ref=wp_popup_080924" target="_blank">Check the portfolio</a>
                <a href="javascript:void(0);" class="notice-dismiss" onclick="window.lcwpm_close()">Okay, got it!</a>
            </div>
        <div>`;
        
        document.addEventListener("DOMContentLoaded", function() {
            let show_it = true;
             
            if(typeof(window.dike_lic_db['lcweb']) != 'object') {
                show_it = false;
            }
            else {
                let at_least_one_active = false;
                
                Object.entries(window.dike_lic_db['lcweb']).forEach(([prod_slug, data]) => {
                    if(data['is_active']) {
                        at_least_one_active = true;    
                        
                        if(data['type'] == 'trial' || data['shop'] == 'author') {
                            show_it = false;   
                        }
                    }
                });
                
                if(!at_least_one_active) {
                    show_it = false;
                }
            }
            if(!show_it) {
                return false;   
            }
            
            
            // be sure cookie is not set
            let cookie = {};
            document.cookie.split(';').forEach(function(el) {
                let split = el.split('=');
                cookie[split[0].trim()] = split.slice(1).join("=");
            });

            
            if(typeof(lc_wp_popup_message) != 'function' || typeof(cookie['<?php echo $notice_name ?>']) != 'undefined') {
                return false;
            }

            
            lc_wp_popup_message('modal', contents);
            
            if(document.querySelector('.lcwpm_modal')) {
                document.querySelector('.lcwpm_modal').classList.add('lcshop_adv_modal');
                
                setTimeout(() => {
                    const $adv_wrap = document.querySelector('#lcshop_adv_wrap');
                    $adv_wrap.classList.add('lcshop_adv_wrap_shown');
                    
                    window.bind_dike_dismissable_notice($adv_wrap);
                    
                    
                    document.querySelector('.lcshop_adv_modal .notice-dismiss').addEventListener('click', function() {
                        const CookieDate = new Date;
                        CookieDate.setFullYear(CookieDate.getFullYear() + 5);
                        document.cookie = '<?php echo $notice_name ?>=1; expires=' + CookieDate.toGMTString() + ';';
                    });
                    
                }, 500);
            }
        });
        
    })();
    </script>
    <?php 
}, 9999);