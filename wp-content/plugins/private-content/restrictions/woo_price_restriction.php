<?php
/* NFPCF */

// WOOCOMMERCE PRICE RESTRICTION
if(!defined('ABSPATH')) {exit;}


// settings options block
add_filter('pc_settings_structure', function($structure) {
    if(!is_plugin_active('woocommerce/woocommerce.php')) {
        return $structure;    
    }
    
	$to_edit = $structure['main_opts'];
	$to_add = array(
		'pc_wph_opts' => array(
			'sect_name'	=> 'WooCommerce Products Purchase Lock',
			'fields' 	=> array(
				
                'pc_global_block_woo_sell' => array(
                    'label' => esc_html__('Globally lock products sell?', 'pc_ml'),
                    'type'	=> 'checkbox',
                    'note'	=> esc_html__('If checked, external users will be prevented from purchasing products and see their prices.<br/>You can change this behavior individually for each product category or product', 'pc_ml')
                ),
                'pc_wph_message' => array(
                    'label' => esc_html__("Custom text shown to external users instead of product price", 'pc_ml'),
                    'type'	=> 'wp_editor',
                    'rows' 	=> 2,
                    'note'  => esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__('Login to see the price and purchase', 'pc_ml').'"',
                ), 
			),
		),
	);
	
    include_once(PC_DIR .'/settings/settings_engine.php');
	$structure['main_opts'] = lcwp_settings_engine::inject_array_elem($to_add, $to_edit, 'contents_hiding');
	return $structure;	
}, 10);






//////////////////////////////////////////////////////////////////////////






// given a subject_type (tax/post) and its ID, knows if it woocommerce product or category
function pvtcont_wph_is_woo($subj_type, $subj_id) {
    $is_woo = false;
    if(!$subj_id) {
        return false;    
    }
    
    if($subj_type == 'tax' || $subj_type == 'term') {
        $term = get_term($subj_id);
        
        if($term->taxonomy == 'product_cat') {
            $is_woo = true;    
        }
    }
    elseif(get_post_type($subj_id) == 'product') {
        $is_woo = true;    
    }
    
    return $is_woo;
}






// add the related array values for restriction wizard class
add_filter('pc_restr_wizard_structure', function($structure, $subj_type, $subj_id) {
    if(pvtcont_wph_is_woo($subj_type, $subj_id)) {
        $structure['block_woo_sell'] = 'inherit';    
    }
    return $structure;
}, 100, 3);


add_filter('pc_final_restr_structure', function($structure, $subj_type, $subj_id) {
    if(pvtcont_wph_is_woo($subj_type, $subj_id)) {
        $structure[] = 'block_woo_sell';    
    }
    return $structure;
}, 100, 3);







// show the additional wizard field
add_filter('pc_restr_wizard_code', function($code, $restr_data, $subj_type, $subj_id, $class) { 
    if(!pvtcont_wph_is_woo($subj_type, $subj_id)) {
        return $code;    
    }

    $val = (isset($restr_data['block_woo_sell'])) ? $restr_data['block_woo_sell'] : 'inherit'; 
    $tax_tb = ($subj_type == 'tax') ? '<br/>' : '';
    
    $code .= '
    <hr/>
    <div class="pc_restr_wizard_block pc_rw_wph">
        <legend>
            <i class="dashicons dashicons-cart"></i><strong>'. esc_html__("Block external visitors purchases?", 'pc_ml') .'</strong> 
        </legend>'. $tax_tb .'
        <fieldset>
            <label>'. esc_html__('Product price will be hidden too', 'pc_ml') .'</label>
            '. $class->inherited_restr_helper('block_woo_sell', $subj_type, $subj_id) .'
            
            <select name="pc_block_woo_sell" autocomplete="off">
                
                <option value="inherit" '. selected($val, "inherit", false) .'>('. esc_html__('inherit', 'pc_ml') .')</option>
                <option value="no" '. selected($val, "no", false) .'>'. esc_html__('No', 'pc_ml') .'</option>
                <option value="yes" '. selected($val, "yes", false) .'>'. esc_html__('Yes', 'pc_ml') .'</option>
            </select>
        </fieldset>
    </div>';
    
    return $code;
}, 100, 5);






//////////////////////////////////////////////////////////////////////////






// given the product id, knows if it has to be blocked
function pvtcont_block_woo_product_sell($product_id) {
    global $pc_restr_wizard;
	if(!$pc_restr_wizard || is_admin() || isset($GLOBALS['pc_user_id'])) {
        return false;
    }
		
	// get specific restrictions
	$restr_arr = $pc_restr_wizard->get_entity_full_restr('post', $product_id);
    $val = 'inherit';
    
    if(is_array($restr_arr) && isset($restr_arr['block_woo_sell'])) {
        foreach($restr_arr['block_woo_sell'] as $restr) {
            if($restr != 'inherit') {
                $val = $restr;
            }
        }    
    }
    
    if($val == 'inherit') {
        $val = (get_option('pc_global_block_woo_sell')) ? 'yes' : 'no';   
    }
    
    $to_return = ($val == 'yes') ? true : false;
    
    // PC-FILTER - allows a final change for woo products purchase lock - passes product ID
    return apply_filters('pc_block_woo_product_sell', $to_return, $product_id);
}





//// disable price
function ptcont_woo_price_hider($price, $product) {
    $prod_id = $product->get_id();
    return (pvtcont_block_woo_product_sell($prod_id)) ? '' : $price;
}

function pvtcont_woo_variable_price_hider($price, $variation, $product) {
    return ptcont_woo_price_hider($price, $product);
}

function ptcont_woo_price_hider_for_variation_prices_hash($price_hash, $product, $for_display) {
    $prod_id = $product->get_id();
    
    if(pvtcont_block_woo_product_sell($prod_id)) {
        $price_hash[] = '';        
    }
    return $price_hash;
}

// Simple, grouped and external products
add_filter('woocommerce_product_get_price', 'ptcont_woo_price_hider', 9999, 2);
add_filter('woocommerce_product_get_regular_price', 'ptcont_woo_price_hider', 9999, 2);

// Variations 
add_filter('woocommerce_product_variation_get_regular_price', 'ptcont_woo_price_hider', 9999, 2);
add_filter('woocommerce_product_variation_get_price', 'ptcont_woo_price_hider', 9999, 2);

// Variable (price range)
add_filter('woocommerce_variation_prices_price', 'pvtcont_woo_variable_price_hider', 9999, 3);
add_filter('woocommerce_variation_prices_regular_price', 'pvtcont_woo_variable_price_hider', 9999, 3);

// Handling price caching (see explanations at the end)
add_filter( 'woocommerce_get_variation_prices_hash', 'ptcont_woo_price_hider_for_variation_prices_hash', 9999, 3);






// set product as not purchasable (also for redirect-restricted products)
function pvtcont_change_products_purchaseable_state($is_purchasable, $product ) {
    global $pc_restr_wizard;
    
    if(!$pc_restr_wizard) {
        return $is_purchasable;
    }
    
    $prod_id = $product->get_id();
    $post_restr = $pc_restr_wizard->get_entity_full_restr('post', $prod_id); 
    
    if(
        (isset($post_restr['redirect']) && $pc_restr_wizard->user_passes_restr($post_restr['redirect']) !== 1) || 
        pvtcont_block_woo_product_sell($prod_id)
    ) {
        return false;
    }
    
    return $is_purchasable;
}
add_filter('woocommerce_is_purchasable', 'pvtcont_change_products_purchaseable_state', 9999, 2);
add_filter('woocommerce_variation_is_purchasable', 'pvtcont_change_products_purchaseable_state', 9999, 2);






// if price is hidden, show a message 
function pvtcont_warning_for_woo_hidden_price($html, $class) {
    global $post;

    if(is_single() && pvtcont_block_woo_product_sell($post->ID)) {
        $GLOBALS['pvtcont_restricted_woo_price'] = true;
        
        $custom_message = get_option('pc_wph_message');
        $to_use = (empty(wp_strip_all_tags($custom_message))) ? esc_html__('Login to see the price and purchase', 'pc_ml') : $custom_message; 
        
        $to_use = strip_tags($to_use, '<a><span><b><i><em><strong><font><br><hr><img>');
        $html = '
        <span class="woocommerce-Price-amount amount pc_wph_message">'. $to_use .'</span>
        
        <script type="text/javascript">
        (function() { 
            "use strict";  
            
            document.body.classList.add("pc_woo_prod_price_hidden");
        })();
        </script>';
    }
    
    return $html;
}
add_filter('woocommerce_empty_price_html', 'pvtcont_warning_for_woo_hidden_price', 9999, 2);
add_filter('woocommerce_get_price_html', 'pvtcont_warning_for_woo_hidden_price', 9999, 2);







// remove "out of stock" message for restricted products
add_filter('woocommerce_out_of_stock_message', function($text) {
    if(isset($GLOBALS['pvtcont_restricted_woo_price'])) {
        return false;
    }
    return $text;
}, 9999);

