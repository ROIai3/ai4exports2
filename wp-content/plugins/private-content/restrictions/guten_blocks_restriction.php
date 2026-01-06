<?php
/* NFPCF */

// GUTENBERG BLOCKS RESTRICTION
if(!defined('ABSPATH')) {exit;}


// register JS file and inline JS code
add_action('enqueue_block_editor_assets', function() {     
   
    // options as global vars 
    $opts_js = '
    window.pc_gbr_labels = {
        panel_heading   : "PrivateContent - '. esc_attr__('Block Visibility', 'pc_ml') .'",
        allow_label     : "'. esc_attr__('Who can see it?', 'pc_ml') .'",
        block_label     : "'. esc_attr__('Among them - want to block someone?', 'pc_ml') .'",
    };
    
    window.pc_gbr_opts = [';

    foreach(pc_static::restr_opts_arr() as $sect => $opts) {
        foreach($opts['opts'] as $val => $label) {
            $opts_js .= '{label: "'. esc_attr($label) .'", value: "'. esc_attr($val) .'"},';  
        }
    }

    $opts_js .= "];";
    
    // LC select for options and initial "block" visibility
    $opts_js .= ";
    setInterval(function() {
        if(typeof(lc_select) == 'undefined') {
            return true;   
        }
            
        document.querySelectorAll('.pc_gbr_wrap').forEach(wrap => {

            wrap.querySelector('.pc_gbr_allow select').setAttribute('name', 'pc_gbr_allow');
            wrap.querySelector('.pc_gbr_block select').setAttribute('name', 'pc_gbr_block');

            const selected = wrap.querySelectorAll('.pc_gbr_allow select option:checked');
            const allow_val = Array.from(selected).map(el => el.value);

            wrap.querySelector('.pc_gbr_block').style.display = (!allow_val.length || allow_val.indexOf('unlogged') !== -1) ? 'none' : 'block';
        });";
        
        
        if(ISPCF) {
            $opts_js .= "window.nfpcf_inject_infobox('.pc_gbr_allow select, .pc_gbr_block select', true);";
        }
        else {
            $opts_js .= "
            new lc_select('.pc_gbr_allow select, .pc_gbr_block select', {
                wrap_width : '100%',
                addit_classes : ['lcslt-lcwp'],
            });";
        }
    
        $opts_js .= "
    }, 50);
    
    document.addEventListener('pc_gbr_cv_event', (e) => {
        setTimeout(function() {
            const resyncEvent = new Event('lc-select-refresh');
            document.querySelector('.pc_gbr_allow select').dispatchEvent(resyncEvent); 
        }, 60);
    });";
    
    wp_add_inline_script('wp-blocks', $opts_js, 'before');
    wp_enqueue_script('pc_blocks_restr', PC_URL .'/js/guten_blocks_restriction.js', 'wp-blocks', PC_VERS, true);     
}, 'pc_guten_blocks_restriction_js');  








// Perform restriction /* NFPCF */
add_filter('render_block', function($block_content, $block_data) {  
    if(!isset($block_data['attrs']) || !isset($block_data['attrs']['pvtcont_allow'])) {
        return $block_content;
    }
    
    $pc_allow = (array)$block_data['attrs']['pvtcont_allow']; 
    $pc_block = (isset($block_data['attrs']['pvtcont_block'])) ? (array)$block_data['attrs']['pvtcont_block'] : array(); 
    
    return (pc_user_check($pc_allow, $pc_block, true) === 1) ? $block_content : false;
}, 9999, 2);
    