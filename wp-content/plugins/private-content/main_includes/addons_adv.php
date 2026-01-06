<?php
/* NFPCF */
if(!defined('ABSPATH')) {exit;}


$banners_baseurl = PC_URL .'/img/ADVs/'; 

$addons = pc_static::addons_db();
$missing = pc_static::addons_not_installed();
?>

<div id="pcaa_wrap">
    <div class="pcaa_inner">
        <h1 id="pcaa_h1">
            PrivateContent plugin is just the beginning
            <small>Through its add-ons you can extend the capabilities, covering a lot of possible implementations!</small>
        </h1>

        <div id="pcaa_banners_wrap">
            <?php 
            foreach($addons as $id => $data) {
                $owned_class 	= (in_array($id, $missing)) ? '' : 'pcaa_owned';
                $link 			= ($owned_class) ? 'javascript:void(0)' : $data['link'];
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