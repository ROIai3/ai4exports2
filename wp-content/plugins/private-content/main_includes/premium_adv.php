<?php
if(!defined('ABSPATH')) {exit;}

$banners_baseurl = PC_URL .'/img/ADVs/'; 

$addons = pc_static::addons_db();
$missing = pc_static::addons_not_installed();
?>


<div id="pcaa_wrap">
    <div class="pcaa_inner">
        <h1 id="pcaa_h1">
            You just started discovering the PrivateContent universe
            <small>The premium version empowers thousands and thousands of websites since 2012!</small>
        </h1>

        
        <div class="pcaa_compare_wrap">
            <div class="pcaa_compare_block">
                <table class="widfat striped">
                    <thead>
                        <tr>
                            <th>
                                <strong>
                                    <i class="dashicons dashicons-star-empty"></i>Free version
                                </strong>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="pcaa_compare_feat_sect">Users membership</td></tr>
                        <tr><td>Unlimited user levels</td></tr>
                        <tr><td>Pending/disabled user statuses</td></tr>
                        <tr><td>Basic users search</td></tr>
                        <tr><td>Users reserved page with basic implementation</td></tr>
                        <tr><td>Basic WP User Sync system usage</td></tr>
                        <tr><td>(only one) customizable registration form</td></tr>
                        
                        <tr><td class="pcaa_compare_feat_sect">Contents restriction systems</td></tr>
                        <tr><td>Redirect-only pages/posts restriction</td></tr>
                        <tr><td>1-click full website lock</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pcaa_compare_block pcaa_compare_highlight">
                <table class="widfat striped">
                    <thead>
                        <tr>
                            <th>
                                <strong>
                                    <i class="dashicons dashicons-star-filled"></i>Premium version
                                </strong>
                                
                                <a href="https://charon.lcweb.it/8260d9df?ref=pc_addons_adv" target="_blank" class="pcaa_buy_btn">Buy now!</a>
                                <a href="https://charon.lcweb.it/9f9757b3?domain=<?php echo esc_html(urlencode(site_url())) ?>" target="_blank">Try it</a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="pcaa_compare_feat_sect">Users membership</td></tr>
                        <tr><td>Advanced users search</td></tr>
                        <tr><td>Search-matching users export</td></tr>
                        <tr><td>Preset/fixed contents for Users reserved page with advanced themes/builders integrations</td></tr>
                        <tr><td>Advanced WP User Sync (eg. emulate WP roles)</td></tr>
                        <tr><td>User sessions control (no concurrent login)</td></tr>
                        <tr><td>Google Analytics users tracking</td></tr>
                        
                        <tr><td class="pcaa_compare_feat_sect">Contents restriction systems</td></tr>
                        <tr><td>Targeted page contents restriction with (optional) dedicated notice</td></tr>
                        <tr><td>Extend restrictions engine to custom post types and taxonomies</td></tr>
                        <tr><td>Menu items, sidebar widgets and page comments restriction</td></tr>
                        <tr><td>WooCommerce product's price hiding with purchase block</td></tr>
                        <tr><td>Prevent contents view showing a lightbox on page's opening</td></tr>
                        <tr><td>URL-based pages restriction</td></tr>
                        
                        <tr><td class="pcaa_compare_feat_sect">Additional Core Systems</td></tr>
                        <tr><td>Lightbox engine (eg. let user login/register through a lightox)</td></tr>
                        <tr><td>Native <a href="https://be.elementor.com/visit/?bta=1930&brand=elementor" target="_blank">Elementor</a>, Divi, WPBakery Builder widgets integration</td></tr>
                        
                        <tr><td class="pcaa_btns_td">
                            <div class="pcaa_btns_wrap">
                                <a href="https://charon.lcweb.it/8260d9df?ref=pc_addons_adv" target="_blank" class="pcaa_buy_btn"><i class="dashicons dashicons-cart"></i>Buy now!</a>
                                
                                <span>
                                    <a href="https://charon.lcweb.it/9f9757b3?domain=<?php echo esc_html(urlencode(site_url())) ?>" target="_blank"><i class="dashicons dashicons-thumbs-up"></i> Try it</a>
                                    <a href="mailto:support@lcweb.it" target="_blank"><i class="dashicons dashicons-format-chat"></i>Need more infos?</a>
                                </span>
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>    
        </div>
        
        
        <h2 class="pcaa_mid_heading">
            <span>.. but there's much more than this!</span>
        </h2>
        <br/>

        <div id="pcaa_banners_wrap">
            <?php 
            foreach($addons as $id => $data) {
                $owned_class 	= (in_array($id, $missing)) ? '' : 'pcaa_owned';
                $link 			= ($owned_class) ? '" role="button' : $data['link'];
                $target 		= ($owned_class) ? '' : 'target="_blank"'; 

                $txt = ($owned_class) ? '<strong>Installed!</strong><i class="dashicons dashicons-thumbs-up"></i>' : '<strong>Check it!</strong> <i class="dashicons dashicons-controls-forward"></i>'; 

                echo '
                <a href="'. esc_attr($link) .'" '. esc_html($target) .' class="'. esc_attr($owned_class) .'" title="'. esc_attr($data['descr']) .'">'.
                    '<img src="'. esc_attr($banners_baseurl . $id) .'.png" alt="'. esc_attr($data['name']) .'" />'.
                    '<span>'. wp_kses_post($txt) .'</span>'.
                '</a>';
            }
            ?>
            
            <a id="pcaa_bundle_block" href="https://charon.lcweb.it/0243fcfc?ref=pc_addons_adv" target="_blank">
                <h2>Need everything?<br/>Get the bundle pack and save now!
                <small>Future add-ons will be included for free!</small></h2>

                <span id="pcaa_bundle_btn" href="https://charon.lcweb.it/0243fcfc?ref=pc_addons_adv" target="_blank">
                    <img src="<?php echo esc_attr($banners_baseurl) ?>bundle_logo.svg" /> Get the bundle!
                </span>
            </a>
        </div>
    </div>    
</div>