<?php
// PRIVATECONTENT USER ACTIVITIES ADD-ON INTEGRATION
if(!defined('ABSPATH')) {exit;}


// ACTIVITY TYPES
add_filter('pcua_act_types', function($at) {

    // user login
    $at['pc_user_login'] = array(
        'name'  => esc_html__('User login', 'pc_ml'),
        'helper'=> esc_html__('Reporting whenever a user logs in', 'pc_ml'),
        'group' => 'core',
        'metas' => array(),
    );
    
    
    // user logout
    $at['pc_user_logout'] = array(
        'name'  => esc_html__('User logout', 'pc_ml'),
        'helper'=> esc_html__('Reporting whenever a user logs out', 'pc_ml'),
        'group' => 'core',
        'metas' => array(),
    );
    
    
    // user registration
    $at['pc_user_registration'] = array(
        'name'  => esc_html__('User registration', 'pc_ml'),
        'helper'=> esc_html__('Reporting whenever a user registers through a PrivateContent form', 'pc_ml'),
        'group' => 'core',
        'metas' => array(
            'form_id' => array(
                'name'      => esc_html__('Form ID', 'pc_ml'),
                'data_type' => 'mixed',
                'num_unit'  => '',
                'helper'    => '',
            ),
            'form_name' => array(
                'name'      => esc_html__('Form name', 'pc_ml'),
                'data_type' => 'mixed',
                'num_unit'  => '',
                'helper'    => '',
            ),
        ),
    );
    
    
    // user deleted its account
    $at['pc_user_deleted_itself'] = array(
        'name'  => esc_html__('User deleted its account', 'pc_ml'),
        'helper'=> esc_html__('Reporting whenever a user self-deletes its account', 'pc_ml'),
        'group' => 'core',
        'metas' => array(),
    );
    
    
    // user created from WP form registration
    if(get_option('pg_wp_user_sync')) {
        $at['pc_user_created_from_wp_registr'] = array(
            'name'  => esc_html__('User created from WP registration', 'pc_ml'),
            'helper'=> esc_html__('Reporting PrivateContent users generated from a WordPress registration', 'pc_ml'),
            'group' => 'core',
            'metas' => array(),
        );
    }
    
    return $at;
});








// ACTIVITY TRIGGERS
/*
array(
    'trigger_slug' => array(
        'act_type' => activity_slug,
        'trig_type' => wp_hook || js_event
        'helper' =>,
        'js_event' => array(
            'selector' =>,
            'event' =>,
            'is_jquery' => 1|0,
            'once_per_page' => 1|0,
        ),
        'wp_hook' => array(
            'type' => action || filter,
            'name' => ,
        ),
        'meta_assoc'=> array(
            'type_meta_slug'   => JS code returning a value (if js_event)
            'type_meta_slug'   => e.attr_name (if js_event > e is event object)
            'type_meta_slug'   => (int) jquery custom param num (if jquery js_event)

            'type_meta_slug'   => (int) hook param num (if wp_hook)
            'type_meta_slug'   => (string) PHP function name (if wp_hook)
        )
    )
) 
*/
add_filter('pcua_act_triggers', function($atrig) {
    
    // user login
    $atrig['pc_user_login_php'] = array( // wrapping also wp-form ones 
        'act_type'  => 'pc_user_login',
        'trig_type' => 'wp_hook',
        'helper'    => esc_html__('Official PrivateContent way to track logins', 'pc_ml'),
        'meta_assoc'=> array(),
        
        'wp_hook' => array(
            'type' => 'action',
            'name' => 'pc_user_login',
        ),
    );
    
    
    // user logout
    $atrig['pc_user_logout_php'] = array(
        'act_type'  => 'pc_user_logout',
        'trig_type' => 'wp_hook',
        'helper'    => esc_html__('Official PrivateContent way to track logouts', 'pc_ml'),
        'meta_assoc'=> array(),
        
        'wp_hook' => array(
            'type' => 'action',
            'name' => 'pc_user_logout',
        ),
    );
    
    
    // user registration
    $atrig['pc_user_registration_js'] = array(
        'act_type'  => 'pc_user_registration',
        'trig_type' => 'js_event',
        'helper'    => esc_html__('Official PrivateContent way to track user registrations', 'pc_ml'),
        
        'js_event' => array(
            'selector'      => 'document',
            'event'         => 'pc_successful_registr',
            'is_jquery'     => 1,
            'once_per_page' => 1,
        ),
        'meta_assoc'=> array(
            'form_id'   => 3,
            'form_name' => 3,
        )
    );
    
    
    // user self-deletion
    $atrig['pc_user_deleted_itself_js'] = array(
        'act_type'  => 'pc_user_deleted_itself',
        'trig_type' => 'js_event',
        'helper'    => esc_html__('Official PrivateContent way to track users deleting their accounts', 'pc_ml'),
        'meta_assoc'=> array(),
        
        'js_event' => array(
            'selector'      => 'document',
            'event'         => 'pc_user_profile_deletion',
            'is_jquery'     => 1,
            'once_per_page' => 1,
        ),
    );
    
    
    // user created from WP form registration
    if(get_option('pg_wp_user_sync')) {
        $atrig['pc_user_created_from_wp_registr_trig'] = array(
            'act_type'  => 'pc_user_created_from_wp_registr',
            'trig_type' => 'wp_hook',
            'helper'    => esc_html__('Official PrivateContent way to track users registered through a third-part registration form', 'pc_ml'),
            'meta_assoc'=> array(),
            
            'wp_hook' => array(
                'type' => 'action',
                'name' => 'pc_user_created_from_wp_register',
            ),
        );
    }
    
    return $atrig;
});






// set form name for pc_user_registration activity
add_filter('pcua_add_act_metas', function($metas, $act_slug, $params) {
    if($act_slug != 'pc_user_registration') {
        return $metas;    
    }
    
    $term = get_term_by('id', $metas['form_id'], 'pc_reg_form');
    $metas['form_name'] = $term->name;
    return $metas;
}, 10, 3);