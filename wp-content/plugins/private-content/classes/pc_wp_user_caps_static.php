<?php
// PLUGIN STATIC METHODS ABOUT WP USERS CAPABILITIES AND THEIR INTERACTIONS WITH PC SYSTEMS
if(!defined('ABSPATH')) {exit;}


class pc_wpuc_static {
    

    /* translates pvtcont_wp_roles() settings function from capability to role ID, to allow only targeted users without using capabilities */
    public static function cap_to_role_id($capability) {
        switch($capability) {
            
            case 'read' : 
                $role = 'subscriber';
                break;
            
            case 'edit_posts' :
                $role = 'contributor';
                break;
                
            case 'upload_files' :
                $role = 'author';
                break;
            
            case 'edit_pages' :
                $role = 'editor';
                break;
                
            case 'manage_options' :
            default :
                $role = 'administrator';
                break;
        }
        
        return $role;
    }
    
    
    
    
    /* 
     * Knows whether currently logged WP admin user is able to bypass frontend restrictions
     * @return (bool)
     */
    public static function current_wp_user_bypass_restrictions() {
        if(!is_user_logged_in()) {
            return false;    
        }
        if(current_user_can('manage_options') || current_user_can('pvtcontent_admin')) {
            return true;        
        }
        
        global $pc_users;
        $min_role = get_option('pg_min_role', 'upload_files');
        
        // inherit role to manage users
        if($min_role == 'inherit_tmu') {
            return self::current_wp_user_can_edit_pc_user('some');
        }
        
        // targeted users
        elseif($min_role == 'only_targeted') {
            $allowed = (array)get_option('pg_users_tup', array());
            return in_array(get_current_user_id(), $allowed);        
        }

        else {
            return current_user_can($min_role);   
        }
    }
    
    
    
    
    /* 
     * Gets which pvtContent user categories are editable by currently logged WP user 
     * @return (array|string) user categories IDs array or "any"
     */
    public static function get_wp_user_editable_pc_cats() {
        if(isset($GLOBALS['pvtcont_wpuepcc'])) {
            return $GLOBALS['pvtcont_wpuepcc'];     
        }
        
        if(
            current_user_can('manage_options') || 
            current_user_can( self::cap_to_role_id(get_option('pg_min_role_tmu', 'edit_pages')) ) ||
            (get_option('pg_any_pc_admin_cmu') && current_user_can('pvtcontent_admin'))
        ) {
            return 'any';    
        }

        $curr_user_id = get_current_user_id();
        if(empty($curr_user_id)) {
            return array();    
        }
        
        $editable = array();
        $pc_ucats = get_terms(array(
            'taxonomy'   => 'pg_user_categories',
            'hide_empty' => 0,
            'fields'     => 'ids',
        ));
        
        foreach($pc_ucats as $ucat_id) {
            
            $allowed = get_term_meta($ucat_id, 'pg_ucat_manag_by_users', true);    
            if(!is_array($allowed)) {
                continue;    
            }
            
            foreach($allowed as $user_id) {
                if($curr_user_id == $user_id) {
                    $editable[] = $ucat_id;    
                }
            }
        }
        
        $GLOBALS['pvtcont_wpuepcc'] = $editable;
        return $editable;
    }
    
    
    
    
    /* 
     * Gets which pvtContent user categories can't be managed by currently logged WP user 
     * @return (array) user categories IDs array
     */
    public static function get_wp_user_prevented_pc_cats() {
        if(isset($GLOBALS['pvtcont_wpuppcc'])) {
            return $GLOBALS['pvtcont_wpuppcc'];     
        }
        
        if(
            current_user_can('manage_options') || 
            current_user_can( self::cap_to_role_id(get_option('pg_min_role_tmu', 'edit_pages')) ) ||
            (get_option('pg_any_pc_admin_cmu') && current_user_can('pvtcontent_admin'))
        ) {
            return array();    
        }

        $curr_user_id = get_current_user_id();
        if(empty($curr_user_id)) {
            return array();    
        }
        
        $prevented = array();
        $pc_ucats = get_terms(array(
            'taxonomy'   => 'pg_user_categories',
            'hide_empty' => 0,
            'fields'     => 'ids',
        ));
        
        foreach($pc_ucats as $ucat_id) {
            
            $allowed = get_term_meta($ucat_id, 'pg_ucat_manag_by_users', true);    
            if(!is_array($allowed)) {
                $prevented[] = $ucat_id;
                continue;    
            }
            
            $to_prevent = true;
            foreach($allowed as $user_id) {
                if($curr_user_id == $user_id) {
                    $to_prevent = false;   
                }
            }
            
            if($to_prevent) {
                $prevented[] = $ucat_id;    
            }
        }
        
        $GLOBALS['pvtcont_wpuppcc'] = $prevented;
        return $prevented;
    }
    
    
    
    
    /* 
     * Knows whether currently logged WP admin user is able to EDIT specific pvtContent users data (bool)
     * @param (string|int) $user_id - pvtContent user ID or "some" key (for a generic check)
     * @return (bool)
     */
    public static function current_wp_user_can_edit_pc_user($user_id) {
        if(!is_user_logged_in()) {
            return false;    
        }
        else if(
            current_user_can('manage_options') || 
            current_user_can( self::cap_to_role_id(get_option('pg_min_role_tmu', 'edit_pages')) ) ||
            (get_option('pg_any_pc_admin_cmu') && current_user_can('pvtcontent_admin'))
        ) {
            return true;        
        }
        
        else {
            $editable_cats = self::get_wp_user_editable_pc_cats();      
            
            if(empty($editable_cats)) {
                return false;    
            }
            elseif($user_id == 'some') {
                return true;    
            }
            else {
                global $pc_users;
                $user_cats = $pc_users->get_user_field($user_id, 'categories'); 
                
                foreach($user_cats as $ucat_id) {
                    if(in_array((int)$ucat_id, $editable_cats)) {
                        return true; 
                        break;    
                    }
                }
            }
        }

        return false;
    }
    
    
    
    
    /* Manage user pvt pages management capabilities on settings save */
    public static function upp_manag_wp_roles_setup($cap) {
        $cpt   = 'pg_user_page';
        $fixed = array('administrator', 'pvtcontent_admin'); 

        switch($cap) {
            case 'read' 		: 
                $add = array('subscriber', 'contributor', 'author', 'editor');
                $remove = array(); 
                break;

            case 'edit_posts' 	: 
                $add = array('contributor', 'author', 'editor');
                $remove = array('subscriber');  
                break;

            case 'upload_files' : 
            default :    
                $add = array('author', 'editor');
                $remove = array('subscriber', 'contributor'); 
                break;	

            case 'edit_pages' :
                $add = array('editor');
                $remove = array('subscriber', 'contributor', 'author'); 
                break;

            case 'manage_options' :
                $add = array();
                $remove = array('subscriber', 'contributor', 'author', 'editor'); 
                break;	
        }

        $add = array_merge($add, $fixed);
        
        foreach($add as $subj) {
            $role = get_role($subj);

            if(is_object($role)) {
                $role->add_cap("edit_".$cpt);
                $role->add_cap("read_".$cpt);
                $role->add_cap("delete_".$cpt);
                $role->add_cap("edit_".$cpt."s");
                $role->add_cap("edit_others_".$cpt."s");
                $role->add_cap("publish_".$cpt."s");
                $role->add_cap("read_private_".$cpt."s");
                $role->add_cap("delete_".$cpt."s");
                $role->add_cap("delete_private_".$cpt."s");
                $role->add_cap("delete_published_".$cpt."s");
                $role->add_cap("delete_others_".$cpt."s");
                $role->add_cap("edit_private_".$cpt."s");
                $role->add_cap("edit_published_".$cpt."s");
            }
        }
        foreach($remove as $subj) {
            $role = get_role($subj);

            if(is_object($role)) {
                $role->remove_cap("edit_".$cpt);
                $role->remove_cap("read_".$cpt);
                $role->remove_cap("delete_".$cpt);
                $role->remove_cap("edit_".$cpt."s");
                $role->remove_cap("edit_others_".$cpt."s");
                $role->remove_cap("publish_".$cpt."s");
                $role->remove_cap("read_private_".$cpt."s");
                $role->remove_cap("delete_".$cpt."s");
                $role->remove_cap("delete_private_".$cpt."s");
                $role->remove_cap("delete_published_".$cpt."s");
                $role->remove_cap("delete_others_".$cpt."s");
                $role->remove_cap("edit_private_".$cpt."s");
                $role->remove_cap("edit_published_".$cpt."s");
            }
        }
    }
    
    
    
    /* PC Admin (WP role) capabilities setup on settings save */
    public static function man_pc_admin_role_caps($to_add_raw) {
        include_once(PC_DIR .'/settings/field_options.php');
        
        $role = get_role('pvtcontent_admin');
        if(!is_object($role)) {
            return false;    
        }
        
        $fixed      = array('read', 'view_admin_dashboard');
        $to_add     = array();
        $to_remove  = array();    
        
        foreach(pvtcont_admin_role_addit_caps() as $cap => $label) {
            
            // CPT caps shortcuts
            if(substr($cap, 0, 8) == 'man_cpt_') {
                    
                $cpt = str_replace('man_cpt_', '', $cap);
                $cpt_caps = array(
                    "edit_".$cpt,
                    "read_".$cpt,
                    "delete_".$cpt,
                    "edit_".$cpt."s",
                    "edit_others_".$cpt."s",
                    "publish_".$cpt."s",
                    "read_private_".$cpt."s",
                    "delete_".$cpt."s",
                    "delete_private_".$cpt."s",
                    "delete_published_".$cpt."s",
                    "delete_others_".$cpt."s",
                    "edit_private_".$cpt."s",
                    "edit_published_".$cpt."s",    
                );
                
                if(in_array($cap, $to_add_raw)) {
                    $to_add = array_merge($to_add, $cpt_caps);       
                } else {
                    $to_remove = array_merge($to_remove, $cpt_caps);         
                }
            }
            else {
                if(in_array($cap, $to_add_raw)) {
                    $to_add[] = $cap;   
                } else {
                    $to_remove[] = $cap;    
                }
            }
        }
        
        // PC-FILTER - allow further control over "pvtcontent_admin" role capabilities - pass an array containing caps to add and to remove
        list($to_add, $to_remove) = apply_filters('pc_pvtc_admin_role_caps', array($to_add, $to_remove));
        
        // be sure "read" and "view_admin_dashboard" are not excluded
        $to_add = array_unique(array_merge((array)$to_add, $fixed));

        // add
        foreach($to_add as $ta) {
            $role->add_cap($ta);         
        }
        
        // remove
        foreach((array)$to_remove as $index => $tr) {
            if(in_array($tr, $fixed)) {
                continue;
            }
            
            $role->remove_cap($tr); 
        }
        
        return true;
    }
    
    
    
    /* Setup custom capability to manage PC categories for WP roles allowed to do so */
    public static function pc_cats_manag_cap_setup($min_cap_tmu = "edit_pages", $include_pc_admin_role = false) {
        $cap   = 'man_pg_user_categories';
        
        switch($min_cap_tmu) {
            case 'read' 		: 
                $add = array('subscriber', 'contributor', 'author', 'editor');
                $remove = array(); 
                break;

            case 'edit_posts' 	: 
                $add = array('contributor', 'author', 'editor');
                $remove = array('subscriber');  
                break;

            case 'upload_files' : 
            default :    
                $add = array('author', 'editor');
                $remove = array('subscriber', 'contributor'); 
                break;	

            case 'edit_pages' :
                $add = array('editor');
                $remove = array('subscriber', 'contributor', 'author'); 
                break;

            case 'manage_options' :
                $add = array();
                $remove = array('subscriber', 'contributor', 'author', 'editor'); 
                break;	
        }

        $add[] = 'administrator';
        ($include_pc_admin_role) ? $add[] = 'pvtcontent_admin' : $remove[] = 'pvtcontent_admin';    
        
        foreach($add as $subj) {
            $role = get_role($subj);

            if(is_object($role)) {
                $role->add_cap($cap);
            }
        }
        foreach($remove as $subj) {
            $role = get_role($subj);

            if(is_object($role)) {
                $role->remove_cap($cap);
            }
        }
    }
    
    
    
    
    // autocomplete search + user picker module to pick single users able to do things 
    public static function autocomplete_users_search_n_pick($field_name, $users = array(), $custom_placeholder = false) {
        
        $placeh = ($custom_placeholder) ? $custom_placeholder : '🔍'. esc_attr__(' search users (username, names, e-mail)', 'pc_ml');
        ?>
        <div class="pc_ausnp_wrap">
            <input type="text" name="pc_ausnp" autocomplete="off" maxlength="255" placeholder="<?php echo esc_attr($placeh) ?>" />

            <ul class="pc_ucat_mbu_list">
                <?php 
                foreach((array)$users as $uid) {
                    $user_data = get_user_by('id', $uid);

                    // ignore synced users!
                    if($user_data && 
                        (
                            !get_option('pg_wp_user_sync') ||
                            (
                                get_option('pg_wp_user_sync') && (
                                    !is_array($user_data->wp_capabilities) ||
                                    (is_array($user_data->wp_capabilities) && !isset($user_data->wp_capabilities['pvtcontent']))
                                )
                            )
                        )
                    ) {
                        echo '
                        <li data-user-id="'. absint($uid) .'">
                            <span class="dashicons dashicons-no-alt" title="'. esc_attr__('remove user from selection', 'pc_ml') .'"></span>
                            <input type="hidden" name="'. esc_attr($field_name) .'[]" value="'. absint($uid) .'" />
                            '. esc_html($user_data->user_login) .'
                        </li>';
                    }   
                }
                ?>
            </ul>
        </div>

        <?php if(!isset($GLOBALS['pvtcont_ausnp_js_printed'])) : 
            $GLOBALS['pvtcont_ausnp_js_printed'] = true;    
        
            $inline_js = '
            (function($) { 
                "use strict";

                // autocomplete + ajax search + use selection
                $(document).ready(function() {
                    $(`input[name="pc_ausnp"]`).each(function() {

                        const $field        = $(this),
                              $wrap         = $(this).parents(`.pc_ausnp_wrap`),
                              $chosen_ul    = $wrap.find(`.pc_ucat_mbu_list`);

                        $field.autocomplete({
                            classes: {
                                "ui-autocomplete": "pc_ausnp_wrap_acpt",
                            },
                            source: function(request, response) {

                                // exclude already selected ones
                                let to_exclude = [];

                                $chosen_ul.find(`li`).each(function() {
                                    to_exclude.push( $(this).data(`user-id`) );    
                                });

                                var data = {
                                    action      : `pvtcont_ausnp_search`,
                                    search      : request.term,
                                    to_exclude  : to_exclude,
                                    nonce       : "'. esc_js(wp_create_nonce('lcwp_ajax')) .'"
                                };
                                $.post(ajaxurl, data, function(resp) {
                                    try {
                                        response( $.parseJSON(resp) );
                                    }
                                    catch(e) {
                                        console.error(e);

                                        response([{
                                            id      : ``,
                                            value   : ``,
                                            label   : "'. esc_attr__('Error retrieving users', 'pc_ml') .'"
                                        }]);     
                                    }
                                })
                                .fail(function(e) {
                                    console.error(e);

                                    response([{
                                        id      : ``,
                                        value   : ``,
                                        label   : "'. esc_attr__('Error retrieving users', 'pc_ml') .'"
                                    }]);
                                });	
                            },
                            minLength: 3,
                            select: function(event, ui) {

                                // check already selected users
                                if($chosen_ul.find(`[data-user-id="`+ ui.item.id +`"]`).length) {
                                    lc_wp_popup_message(`error`, "'. esc_attr__('User already selected', 'pc_ml') .'");
                                }
                                else {
                                    if(parseInt(ui.item.id, 10)) {
                                        $chosen_ul.append(`
                                        <li data-user-id="${ ui.item.id }">
                                            <span class="dashicons dashicons-no-alt" title="'. esc_attr__('remove user from selection', 'pc_ml') .'"></span>
                                            <input type="hidden" name="'. esc_attr($field_name) .'[]" value="${ ui.item.id }" />
                                            ${ ui.item.label }
                                        </li>`);
                                    }
                                }

                                $(this).val(``); 
                                return false;
                            },
                            open: function() {
                                $(this).removeClass("ui-corner-all").addClass("ui-corner-top");
                            },
                            close: function() {
                                $(this).removeClass("ui-corner-top").addClass("ui-corner-all");
                            }
                        });
                    }); 
                });


                // chosen user removal
                $(document).on(`click`, `.pc_ucat_mbu_list li span`, function() {
                    if(typeof(window.pc_ucat_reset_cust_fields) != `undefined` || confirm("'. esc_attr__('Remove selected user?', 'pc_ml') .'")) {
                        $(this).parents(`li`).remove();
                    }
                });

            })(jQuery);';
            wp_add_inline_script('lcwp_magpop', $inline_js);
            
        endif;
    }
    
}
