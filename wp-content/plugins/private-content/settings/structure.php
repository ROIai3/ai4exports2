<?php 
if(!defined('ABSPATH')) {exit;}


include_once(PC_DIR .'/settings/field_options.php'); 
include_once(PC_DIR .'/settings/preset_styles.php'); 


//// custom post type and taxonomies
$cpt = pc_static::get_cpt();
$ct  = pc_static::get_ct();

# fields code
$cpt_n_ct_code = array();
if(!empty($cpt)) {
	$cpt_n_ct_code['pg_extend_cpt'] = array(
		'label' 	=> esc_html__("Enable restrictions engine on these post types", 'pc_ml'),
		'type'		=> 'select',
		'val' 		=> $cpt,
		'multiple'	=> true,
		'fullwidth' => true,
		'note'		=> '', 
	); 	
}
if(!empty($ct)) {
	$cpt_n_ct_code['pg_extend_ct'] = array(
		'label' 	=> esc_html__("Enable restrictions engine on these taxonomies", 'pc_ml'),
		'type'		=> 'select',
		'val' 		=> $ct,
		'multiple'	=> true,
		'fullwidth' => true,
		'note'		=> '', 
	); 	
}
//////////////////////////////////


// WP pages list
$pages = pc_static::get_pages(); 

// PC lightbox instances
$lb_instances = pc_static::get_lb_instances();

// using WP user sync?
$wps_enabled = ((!isset($_POST['pc_settings']) && get_option('pg_wp_user_sync')) || (isset($_POST['pc_settings']) && isset($_POST['pg_wp_user_sync']) && $_POST['pg_wp_user_sync'])) ? true : false;



////////////////////////////////////////////////////////////



// PC-FILTER - manipulate settings tabs
$tabs = array(
	'main_opts' 	=> esc_html__('Main Options', 'pc_ml'),
	'registration' 	=> esc_html__('Registration', 'pc_ml'),
	'wp_user_sync' 	=> esc_html__('WP User Sync', 'pc_ml'),
	'messages' 		=> esc_html__('Messages', 'pc_ml'),
	'pc_lightbox'	=> esc_html__('Lightbox', 'pc_ml'),
	'styling' 		=> esc_html__('Styling', 'pc_ml'),
	'pc_cust_restr' => esc_html__('Custom Restrictions', 'pc_ml'),
);
$GLOBALS['pc_settings_tabs'] = apply_filters('pc_settings_tabs', $tabs);	




// STRUCTURE
/* tabs index => array( 
	'sect_id' => array(
		'sect_name'	=> name
		'fields'	=> array(
			...
		)
	)
   )
*/

$structure = array();
$doc_url = (defined('ISPCF') && ISPCF) ? 'https://doc.lcweb.it/privatecontent/?ispcf#init_req_steps' : 'https://doc.lcweb.it/privatecontent/#init_req_steps';


// MAIN OPTIONS
$structure['main_opts'] = array(
	
	'redirects' => array(
		'sect_name'	=> esc_html__('Redirects Scheme', 'pc_ml') . '<a href="'. $doc_url .'" target="_blank" class="pc_settings_doc_link">'. esc_html__("Check the documentation", 'pc_ml') .' <i class="dashicons dashicons-book"></i></a>',
		'fields' 	=> array(
			
			'pg_redirect_page' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_redirect_custom_field',
				'validation'=> array(
					array('index' => 'pg_redirect_page', 'label' => esc_html__("Main target for redirect-restricted pages", 'pc_ml'), 'required'=>true),
					array('index' => 'pg_redirect_page_custom', 'label' => esc_html__("Main target for redirect-restricted pages", 'pc_ml') .' - '. esc_html__('Custom URL', 'pc_ml'), 'type' => 'url'),
				)
			), 
			'pg_blocked_users_redirect' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_redirect_custom_field',
				'validation'=> array(
					array('index' => 'pg_blocked_users_redirect', 'label' => esc_html__("Redirect target for blocked users", 'pc_ml'), 'required'=>true),
					array('index' => 'pg_blocked_users_redirect_custom', 'label' => esc_html__("Redirect target for blocked users", 'pc_ml') .' - '. esc_html__('Custom URL', 'pc_ml'), 'type' => 'url'),
				)
			),
			'pg_registered_user_redirect' => array(
				'label' => esc_html__("Redirect page after registration", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array('' => '- '. esc_html__('Do not redirect users', 'pc_ml')) + $pages,
				'note'	=> esc_html__("This value can be overwritten in user categories or through shortcode", 'pc_ml'), 
			), 
			'pg_logged_user_redirect' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_redirect_custom_field',
				'validation'=> array(
					array('index' => 'pg_logged_user_redirect', 'label' => "Redirect page after user login"),
					array('index' => 'pg_logged_user_redirect_custom', 'label' => esc_html__("Redirect page after user login", 'pc_ml') .' - '. esc_html__('Custom URL', 'pc_ml'), 'type' => 'url'),
				)
			), 
			'pg_redirect_back_after_login' => array(
				'label' => esc_html__('Redirect users to the last restricted page?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('If checked, move logged users back to last restricted page they tried to see (where available)', 'pc_ml'),
			), 
			'pg_logout_user_redirect' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_redirect_custom_field',
				'validation'=> array(
					array('index' => 'pg_logout_user_redirect', 'label' => "Redirect page after user login"),
					array('index' => 'pg_logout_user_redirect_custom', 'label' => esc_html__("Redirect page after user logout", 'pc_ml') .' - '. esc_html__('Custom URL', 'pc_ml'), 'type' => 'url'),
				)
			), 
            'pg_logout_user_redirect_helper' => array(
                'type'      => "context_help",
                'arrow_pos' => 'top',
                
                /* translators: 1: html code, 2: html code. */
                'content'   => sprintf(esc_html__('This value can be overwritten through shortcode or using any website URL + %1$s?pc_logout%2$s', 'pc_ml'), '<strong>', '</strong>'),
                'linked_field' => 'pg_logout_user_redirect',
            ),
            'pg_do_not_use_pcac' => array(
				'label' => esc_html__('Do not use anti-cache parameter in redirects?', 'pc_ml'),
				'type'	=> 'checkbox',
                
                /* translators: 1: html code, 2: html code. */
				'note'	=> esc_html__('By default the plugin uses a self-removing URL parameter (pcac) to try serving fresh page contents, not influenced by cache systems', 'pc_ml') .'.<br/>'. sprintf(esc_html__('Check this option %1$sONLY IF%2$s you notice bad behaviors during redirects ruled by PrivateContent', 'pc_ml'), '<strong>', '</strong>'),
			),
		),
	),
    
    
	'user_pvt_pag' => array(
		'sect_name'	=> esc_html__('Users Reserved Page', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_target_page' => array(
				'label' => esc_html__("Page to use as users reserved page container", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array('' => '('. esc_html__('no reserved page', 'pc_ml') .')') + $pages,
                
                /* translators: 1: HTML code, 2: HTML code. */
				'note'	=> sprintf(esc_html__('Chosen page contents will be %1$soverwritten%2$s once user logs in', 'pc_ml'), '<strong>', '</strong>'), 
			), 
			'pg_target_page_content' => array(
				'label' => esc_html__("Default container page content", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> pvtcont_so_pvtpag_content(),
                
                /* translators: 1: HTML code, 2: HTML code. */
				'note'	=> sprintf(esc_html__('Define container page contents for %1$sunlogged%2$s users', 'pc_ml'), '<strong>', '</strong>'), 
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_target_page',
					'condition'		=> '',
					'operator'		=> '!=',
				)
			), 
            'pg_pvtpage_overriding_method' => array(
				'label' => esc_html__("User contents overriding mehod", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array(
                    'contents'      => esc_html__('Replace only contents', 'pc_ml'),
                    'full_page'     => esc_html__('Replace full page', 'pc_ml'),
                    'placeholder'   => esc_html__('Inject contents replacing placeholder', 'pc_ml')
                ),
				'note'	=> esc_html__('Define how user reserved page will be changed for logged users', 'pc_ml'), 
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_target_page',
					'condition'		=> '',
					'operator'		=> '!=',
				)
			),
            'pg_pvtpage_overriding_method_helper' => array(
                'type'      => "context_help",
                'arrow_pos' => 'top',
                
                /* translators: 1: html code, 2: html code, 3: html code, 4: html code. */
                'content'   => sprintf(esc_html__('If you are using a page builder (eg. %1$sElementor%2$s) is suggested to use the placeholder injection method. You can read more in the %3$srelated documentation page%4$s', 'pc_ml'), '<a href="https://be.elementor.com/visit/?bta=1930" target="_blank">', '</a>', '<a href="https://doc.lcweb.it/privatecontent/#user_pvt_page" target="_blank">', '</a>'),
                'linked_field' => 'pg_logout_user_redirect',
            ),
            'pg_pvtpage_om_placeh_legend' => array(
				'label'     => esc_html__("Required placeholder", 'pc_ml'),
				'type'      => 'label_message',
				'content'   => '<strong>%PC-USER-PAG-CONTENT%</strong>',
				'hide'      => (empty(get_option('pg_target_page'))) ? true : false,
                
				'js_vis'=> array(
					'linked_field' 	=> 'pg_pvtpage_overriding_method',
					'condition'		=> 'placeholder',
				)
			),
			'pg_pvtpage_default_content' => array(
				'label' => esc_html__("Default reserved page content for new users", 'pc_ml'),
				'type'	=> 'wp_editor',
				'rows' 	=> 4,
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_target_page',
					'condition'		=> '',
					'operator'		=> '!=',
				)
			), 
			'pg_pvtpage_enable_preset' => array(
				'label' => esc_html__('Enable preset content?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('If checked, displays the preset content in user reserved pages. Then every user will see it', 'pc_ml'),
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_target_page',
					'condition'		=> '',
					'operator'		=> '!=',
				)
			), 
			'pg_pvtpage_preset_pos' => array(
				'label' => esc_html__("Preset content position", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array('before' => esc_html__('Before page contents', 'pc_ml'), 'after' => esc_html__('After page contents', 'pc_ml')),
				'note'	=> esc_html__("Set preset content's position in users reserved page", 'pc_ml'), 
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_target_page',
					'condition'		=> '',
					'operator'		=> '!=',
				)
			), 
			'pg_pvtpage_preset_txt' => array(
				'label' => esc_html__("Preset content", 'pc_ml'),
				'type'	=> 'wp_editor',
				'rows' 	=> 4,
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_target_page',
					'condition'		=> '',
					'operator'		=> '!=',
				)
			), 
			'pg_pvtpage_wps_comments' => array(
				'label' => esc_html__('Allow comments for WP synced users?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('Gives the ability to communicate with the user through his reserved page', 'pc_ml'),
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_target_page',
					'condition'		=> '',
					'operator'		=> '!=',
				)
			), 
            'pg_pvtpage_wps_comments_helper' => array(
                'type'      => "context_help",
                'arrow_pos' => 'top',
                
                /* translators: 1: html code, 2: html code. */
                'content'   => sprintf(esc_html__('Only %1$sWP-synced users%2$s can use frontend comments!', 'pc_ml'), '<a href="#wp_user_sync">', '</a>'),
                'linked_field' => 'pg_pvtpage_wps_comments',
                
				'js_vis'=> array(
					'linked_field' 	=> 'pg_pvtpage_wps_comments',
					'condition'		=> true 
				)
            ),
		),
	),
	
	
	'contents_hiding' => array(
		'sect_name'	=> esc_html__('Page Contents Hiding System', 'pc_ml'),
		'fields' 	=> array(
			
			'pc_chs_behavior' => array(
				'label' => esc_html__("Restricted posts behavior", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array(
					'warning_box' 	=> esc_html__('Warning Box', 'pc_ml'),
					'no_contents' 	=> esc_html__('No contents', 'pc_ml'),
					'excerpt' 		=> esc_html__('Excerpt', 'pc_ml'),
					'excerpt_n_wb'	=> esc_html__('Excerpt + warning box', 'pc_ml'),
					'cust_content'	=> esc_html__('Custom contents', 'pc_ml'),
					'excerpt_n_cc'	=> esc_html__('Excerpt + custom contents', 'pc_ml'),
				),
				'note' => esc_html__('What replaces restricted element contents in their own pages', 'pc_ml'), 
			),
			'pc_chs_lists_behavior' => array(
				'label' => esc_html__("Restricted posts behavior (in lists)", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array(
					'no_contents' 	=> esc_html__('No contents', 'pc_ml'),
					'excerpt' 		=> esc_html__('Excerpt', 'pc_ml'),
					'cust_content'	=> esc_html__('Custom contents', 'pc_ml'),
                    'do_not_act'	=> esc_html__('Do not act', 'pc_ml'),
				),
				'note' => esc_html__('What replaces restricted element contents in searches, archives and categories', 'pc_ml'), 
			),
			'pc_chs_cust_content' => array(
				'label' => esc_html__("Restricted elements - custom contents", 'pc_ml') .'<br/><small>('. esc_html__('related option must be chosen to use this', 'pc_ml') .')</small>',
				'type'	=> 'wp_editor',
				'rows' 	=> 2,
			), 
			'pg_warn_box_login' => array(
				'label' => esc_html__("Warning box - Login button", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array('' => esc_html__('No login button', 'pc_ml')) + $lb_instances,
				'note'	=> esc_html__("Hide login button or select a lightbox instance to use", 'pc_ml'), 
			), 
			'pg_warn_box_registr' => array(
				'label' => esc_html__("Warning box - Registration button", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array('' => esc_html__('No registration button', 'pc_ml')) + $lb_instances,
                'def'   => '',
				'note'	=> esc_html__("Hide registration button or select a lightbox instance to use", 'pc_ml'), 
			),
		),
	),
	
	
	'site_lock' => array(
		'sect_name'	=>  esc_html__('Complete Site Lock', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_complete_lock' => array(
				'label' => esc_html__('Enable lock?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('Check to automatically restrict any website page except the "Restriction redirect target" page', 'pc_ml'),
			),
            'pg_complete_lock_helper' => array(
                'type'      => "context_help",
                'arrow_pos' => 'top',
                'content'   => esc_html__('"Restriction redirect target" is excluded from this lock in order to allow users login. Be sure it is set on a Wordpress page!', 'pc_ml'),
                'linked_field' => 'pg_complete_lock',
                
				'js_vis'=> array(
					'linked_field' 	=> 'pg_complete_lock',
					'condition'		=> true 
				)
            ),
			'pg_complete_lock_exclude_menu' => array(
				'label' => esc_html__('Avoid removing menus?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('Check to leave navigation menu visible', 'pc_ml'),
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_complete_lock',
					'condition'		=> true 
				)
			), 
			'pg_complete_lock_excluded_pages' => array(
				'label' 	=> esc_html__("Excluded Pages", 'pc_ml'),
				'type'		=> 'select',
				'val' 		=> pc_static::get_pages(),
				'multiple'	=> true,
				'fullwidth' => true,
				'note'		=> esc_html__('Chosen pages will not be restricted', 'pc_ml'),
				
				'js_vis'	=> array(
					'linked_field' 	=> 'pg_complete_lock',
					'condition'		=> true 
				)
			),	
				
		),
	),
    	
	
	'lb_on_open' => array(
		'sect_name'	=>  esc_html__("Lightbox on Page's Opening", 'pc_ml') .' <a href="#pc_lightbox">'. esc_html('manage lightboxes') .'</a>',
		'fields' 	=> array(
			
			'pg_def_lb_on_open' => array(
				'label' => esc_html__("Default lightbox shown to unlogged users", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array('none' => esc_html__('No lightbox', 'pc_ml')) + $lb_instances,
				'note'	=> esc_html__("Default value to be inherited in restricrion's wizard", 'pc_ml'), 
			), 
		),
	),
		
	
	'cpt_n_ct' => array(
		'sect_name'	=> esc_html__('Custom Post types and Taxonomies', 'pc_ml'),
		'fields' 	=> $cpt_n_ct_code
	),	
	
	
	'analytics' => array(
		'sect_name'	=> esc_html__('Google Analytics', 'pc_ml'),
		'fields' 	=> array(
            
            'pg_ga4_id' => array(
				'label' => esc_html__('Measurement ID', 'pc_ml'),
				'type'	=> 'text',
				'placeh'=> 'G-XXXXXXXXXX',
				'maxlen'	=> 12,
				'note'	=> '<a href="https://support.google.com/analytics/answer/9539598#find-G-ID" target="_blank">'. esc_html__('how to find it?', 'pc_ml') .'</a>',
			),
            'pg_ga4_api_secret' => array(
				'label' => esc_html__('API Secret Key', 'pc_ml'),
				'type'	=> 'text',
				'placeh'=> '',
				'maxlen'	=> 22,
				'note'	=> '<a href="https://doc.lcweb.it/privatecontent/#g_analytics" target="_blank">'. esc_html__('how to find it?', 'pc_ml') .'</a>',
                
                'js_vis'	=> array(
					'linked_field' 	=> 'pg_ga4_id',
					'condition'		=> '',
                    'operator'		=> '!=',
				)
			),
            'pg_gtm_id' => array(
				'label' => esc_html__('Google Tag Manager ID', 'pc_ml'),
				'type'	=> 'text',
				'placeh'=> 'GTM-XXXXXXX',
				'maxlen'	=> 15,
				'note'	=> '<a href="https://support.google.com/tagmanager/answer/6103696" target="_blank">'. esc_html__('how to find it?', 'pc_ml') .'</a>',
                
                'js_vis'	=> array(
					'linked_field' 	=> 'pg_ga4_id',
					'condition'		=> '',
                    'operator'		=> '!=',
				)
			),
            'pg_ga4_inject_js_code' => array(
				'label' => esc_html__('Install Google codes in the website?', 'pc_ml') ,
				'type'	=> 'checkbox',
                
                /* translators: 1: html code, 2: html code. */
				'note'	=> sprintf(esc_html__('Check to inject Google codes along with your account IDs (%1$swhich codes?%2$s)', 'pc_ml'), '<a href="https://developers.google.com/tag-platform/tag-manager/web" target="_blank">', '</a>') .'.<br/><strong>'. esc_html__('Be sure they are not already added by other SEO tool', 'pc_ml') .'</strong>',
                
                'js_vis'	=> array(
					'linked_field' 	=> 'pg_ga4_id',
					'condition'		=> '',
                    'operator'		=> '!=',
				)
			), 
            'pg_ga4_setup_note' => array(
                'type'      => 'message',
                
                /* translators: 1: html code, 2: html code, 3: html code, 4: html code. */
                'content'   => esc_html__("Once saved, PrivateContent will setup the 'pc_user_id' tag to start the User ID tracking and create specific events", 'pc_ml') .'.<br/>'. sprintf(esc_html__('%1$sRemember%2$s to include Google Analytics and Google Tag Manager codes into the website. Check the %3$sdocumentation chapter%4$s to know more.', 'pc_ml'), '<strong>', '</strong>', '<a href="https://doc.lcweb.it/privatecontent/#g_analytics" target="_blank">', '</a>'),
                
                'js_vis'	=> array(
					'linked_field' 	=> 'pg_ga4_id',
					'condition'		=> '',
                    'operator'		=> '!=', 
				)
            ),
		),
	), 
    
    
    'wp_users_to_manag_pc' => array(
		'sect_name'	=> esc_html__('WordPress Users Interactions', 'pc_ml'),
		'fields' 	=> array(

			'pg_min_role_tmu' => array(
				'label' => esc_html__("Minimum role to manage users", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> pvtcont_wp_roles(),
				'def'	=> "edit_pages",
				'note'	=> esc_html__("Minimum WordPress role to edit and manage users", 'pc_ml') .'.<br/><strong>'. esc_html__('This option can be extended for each user category in the categories manager area', 'pc_ml') .'.</strong>',  
			),  
            'pg_any_pc_admin_cmu' => array(
				'label' => esc_html__('Extend the privilege also to users having "PrivateContent admin" role?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('Normally they can manage only targered user categories. Checking this option any PrivateContent user will be manageable by them', 'pc_ml'),
			), 
            'pg_tu_can_edit_user_cats' => array(
				'label' => esc_html__('Specifically selected WP users can edit PrivateContent user categories?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('Checking this option, WP users allowed to edit targeted PrivateContent user categories will be also able to edit their categories assignment', 'pc_ml'),
			), 
            'spcr101' => array(
				'type' => 'spacer',
			),
            'pg_min_role' => array(
				'label' => esc_html__("Minimum role bypassing front restrictions", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array_merge(
                    array(
                        'inherit_tmu' => '- '. esc_html__('inherit "To manage users" value', 'pc_ml'),
                        'only_targeted' => '- '. esc_html__('only targeted users', 'pc_ml'),
                    ), 
                    pvtcont_wp_roles()
                ),
				'def'	=> "edit_pages",
				'note'	=> esc_html__("WordPress users owning the role capability, will be able to bypass frontend restrictions.", 'pc_ml') .'.<br/>'. esc_html__('NB: WP users having "PrivateContent admin" role bypass restrictions by default.', 'pc_ml'), 
			),
            'pg_users_tup_field' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_users_tup_cb',
                'validation'=> array(
					array('index' => 'pg_users_tup', 'label' => "Targeted users able to bypass front restrictions"),
				),
                
                'js_vis' => array(
					'linked_field' 	=> 'pg_min_role',
					'condition'		=> 'only_targeted' 
				)
			), 
            'pg_pc_admin_role_add_caps' => array(
				'label' => esc_html__("PrivateContent Admin (WP role) - additional capabilities", 'pc_ml'),
				'type'	=> 'select',
                'multiple' => true,
                'fullwidth'=> true,
				'val' 	=> pvtcont_admin_role_addit_caps(),
				'def'	=> array(),
				'note'	=> esc_html__("Defines additional capabilities for the special 'PrivateContent Admin' WordPress role. By default it is only able to manage targeted PrivateContent categories and bypasses frontend restrictions", 'pc_ml'), 
			),
            
        ),
    ),  
	
	
	'user_sessions_control' => array(
		'sect_name'	=> esc_html__("User Sessions Control", 'pc_ml'),
		'fields' 	=> array(
			
			'pg_use_session_token' => array(
				'label' => esc_html__('Control sessions?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, user sessions will be tracked to avoid multiple logins from different devices", 'pc_ml') .'<br/><em><strong>FYI:</strong> '. esc_html__('it will involve a new cookie named "pc_session_token"', 'pc_ml') .'</em>',
			), 
			'pg_allowed_simult_sess' => array(
				'label' 	=> esc_html__("How many allowed simultaneous sessions?", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 1,
				'max_val'	=> 5,	
				'step'		=> 1,
				'value'		=> '',
				'def'		=> 1,
                'respect_limits' => false,
				'note' => esc_html__('Logged users owning old login tokens will be logged-out', 'pc_ml'),
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_use_session_token',
					'condition'		=> true 
				)
			),
		),
	),
	
	
	
	'extra' => array(
		'sect_name'	=> esc_html__('Extra', 'pc_ml'),
		'fields' 	=> array(
			 
			'pg_use_remember_me' => array(
				'label' => esc_html__('Use "remember me" check in login form?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, allows users to be logged into the website for 14-days through cookies", 'pc_ml'),
			), 
			'pg_allow_email_login' => array(
				'label' => esc_html__('Allow login also through e-mail?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, allows user login inserting username or e-mail.", 'pc_ml') .'<br/><strong class="lcwp_settings_rednote">'. esc_html__('Be sure database is ok with this: every user should have a unique e-mail', 'pc_ml') .'</strong>',
			), 
			'pg_no_cookie_login' => array(
				'label' => esc_html__('Short login cookie', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, user session automatically ends 5 minutes after user closes its browser or has no interactions", 'pc_ml') . '.<br/><strong>NB:</strong> '. esc_html__('using WP user sync, this behavior could be overrided', 'pc_ml'),
			), 
            'pg_abfa_attempts' => array(
				'label' 	=> esc_html__("Anti bruteforce system - how many errors allow before block visitor?", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 20,	
				'step'		=> 1,
				'value'		=> '',
				'def'		=> 8,
                'respect_limits' => false,
				'note'		=> esc_html__("Control performed on login form, use zero to skip this check", 'pc_ml'),
			),
            'pg_htaccess_cred' => array(
				'label' => esc_html__("HTACCESS credentials needed?", 'pc_ml'),
                'already_set_label' => esc_html__('Credentials already set!', 'pc_ml'),
				'type'	=> 'psw',
                
                /* translators: 1: html code, 2: html code, 3: html code. */
				'note'	=> sprintf(esc_html__('In case of HTACCESS hidden website, its credentials are mandatory to remotely fetch page contents.%1$sInsert them in %2$sUSERNAME:PASSWORD%3$s format.', 'pc_ml'), '<br/>', '<strong>', '</strong>'), 
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_pvtpage_overriding_method',
					'condition'		=> 'full_page',
				)
			),
			'pg_use_first_last_name' => array(
				'label' => esc_html__('Use first/last name in forms?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, replaces name/surname with first/last name", 'pc_ml'),
			), 
			'pg_inline_css' => array(
				'label' => esc_html__('Use dynamic CSS inline?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, uses dynamic CSS inline (useful for multisite installations)", 'pc_ml'),
			),
		),
	),  
);



// REGISTRATION OPTIONS
$structure['registration'] = array(
	'main_reg_opts' => array(
		'sect_name'	=> esc_html__('General Registration Settings', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_allow_duplicated_mails' => array(
				'label' => esc_html__('Allow duplicated e-mails?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, allows users with same e-mail into the database", 'pc_ml') .'.<br/><strong>'. esc_html__('Strongly not recommended', 'pc_ml') .'</strong>',
			),
			'pg_registration_cat' => array(
				'label' => esc_html__("Default category for registered users", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> pc_static::user_cats(),
                'def'		=> (function_exists('array_key_first') && count(pc_static::user_cats())) ? array_key_first(pc_static::user_cats()) : '',
				'multiple' 	=> true,
				'required'	=> true,
				'note'	=> esc_html__("Default user registration categories (ignored if you use category field in forms)", 'pc_ml'), 
			), 
            'pg_registered_pvtpage' => array(
				'label' => esc_html__('Enable the reserved page for new registered users?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> '',
			),
			'pg_registered_pending' => array(
				'label' => esc_html__('Set users status as pending after registration?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> '',
			),
            'pg_onlymail_registr' => array(
				'label' => esc_html__('Use e-mail value as username in registration forms?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, username field will be hidden in registration form and e-mail value will be copied and used instead", 'pc_ml') .'.<br/><strong>'. esc_html__('Be sure e-mail field is in your forms', 'pc_ml') .'</strong>',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_allow_duplicated_mails',
					'condition'		=> false,
				)
			),
		),
	),
    
    'reg_form_opts' => array(
		'sect_name'	=> esc_html__('Registration Forms Settings', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_reg_cat_label' => array(
				'label' => esc_html__('"Category" field - custom label', 'pc_ml'),
				'type'	=> 'text',
				'note'	=> esc_html__("Set a custom label for category field in registration forms", 'pc_ml'),
			),
            'pg_reg_cat_placeh' => array(
				'label' => esc_html__('"Category" field - placeholder option', 'pc_ml'),
				'type'	=> 'text',
				'note'	=> 
                    esc_html__("Set a placeholder option for category field in registration forms, avoiding a default chosen category.", 'pc_ml'). '<br/><strong>'. esc_html__("Only for single-choice field. Leave empty to discard", 'pc_ml') .'</strong>',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_reg_multiple_cats',
					'condition'		=> false,
				)
			),
			'pg_reg_multiple_cats' => array(
				'label' => esc_html__('Allow multiple user categories selection during registration?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("Check to allow users choose multiple categories in registration forms", 'pc_ml'),
			),
            'pg_autologin_registered' => array(
				'label' => esc_html__('Auto-login registered users?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('Check to automatically login active users after their registration', 'pc_ml') .'<br/><strong>'. esc_html__("NB: obviously you should use the registration form redirect (or a page refresh) to effectively show changes", 'pc_ml') .'</strong>',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_registered_pending',
					'condition'		=> false,
				)
			),
            'pg_forms_pags_progress' => array(
				'label' => esc_html__('Show pagination progress on top of forms?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked a progress bar (with clickable page numbers) will be prepended to form fields", 'pc_ml'),
			),
            'pg_hide_reg_btn_on_success' => array(
				'label' => esc_html__('Hide submit button on successful registration?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> '',
                'def'   => 1,
			),
			'pg_no_html5_validation' => array(
				'label' => esc_html__('Disable HTML5 fields validation?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("Disables client-side fields validation", 'pc_ml'),
			),
			'pg_antispam_sys' => array(
				'label' => esc_html__("Anti-spam system", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array(
					'honeypot'     => esc_html__('Honey pot hidden system', 'pc_ml'), 
					'recaptcha'    => esc_html__("Google's invisible reCAPTCHA (v3)", 'pc_ml'),
                    'recaptcha_v2' => esc_html__("Google's invisible reCAPTCHA (v2)", 'pc_ml'),
				),
				'note'	=> esc_html__("Choose the anti-spam solution you prefer", 'pc_ml'), 
			),
			'pg_recaptcha_public' => array(
				'label' 	=> esc_html__('reCAPTCHA - site key', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 255,
				'fullwidth'	=> true,
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_antispam_sys',
					'condition'		=> 'honeypot',
                    'operator'		=> '!=',
				)
			),
			'pg_recaptcha_secret' => array(
				'label' 	=> esc_html__('reCAPTCHA - secret key', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 255,
				'fullwidth'	=> true,
                
                /* translators: 1: html code, 2: html code. */
				'note'		=> sprintf(esc_html__('To use reCAPTCHA you need to %1$screate your personal keys%2$s. Choose "invisible reCAPTCHA", insert the current domain and you will get your keys.', 'pc_ml'), '<a href="https://www.google.com/recaptcha/admin" target="_blank">', '</a>'),
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_antispam_sys',
					'condition'		=> 'honeypot',
                    'operator'		=> '!=',
				)
			),
			
		),
	),
	
	'disclaimer' => array(
		'sect_name'	=>  esc_html__('Registration Disclaimer', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_use_disclaimer' => array(
				'label' => esc_html__('Enable the disclaimer?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, append a disclaimer to registration forms", 'pc_ml'),
			),
			'pg_disclaimer_txt' => array(
				'label' => esc_html__("Disclaimer text", 'pc_ml'),
				'type'	=> 'wp_editor',
				'rows' 	=> 2,
			), 
		),
	),
	
	
	'psw_security' => array(
		'sect_name'	=>  esc_html__('Password Settings', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_psw_min_length' => array(
				'label' 	=> esc_html__('Minimum password length', 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 4,
				'max_val'	=> 10,	
				'step'		=> 1,
				'def'		=> 4,
                'respect_limits' => false,
				'note'		=> esc_html__("Set minimum characters length for user's password", 'pc_ml'),
			),
			'pg_psw_strength' => array(	
				'label' => esc_html__("Password strength options", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> pvtcont_psw_strength_opts(),
				'multiple' 	=> true,
				'note'	=> esc_html__("Improve passwords strength using these options", 'pc_ml'), 
			), 
            'spcr1' => array(
				'type' => 'spacer',
			),
            'pg_show_psw_helper' => array(
				'label' => esc_html__('Show helper in frontend forms?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, password requirements will be shown in frontend forms", 'pc_ml'),
			),
            'pg_single_psw_f_w_reveal' => array(
				'label' => esc_html__('Use only one password field with input revealer in front forms?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked", 'pc_ml') .':<br/>'.
                            '- '. esc_html__('"repeat password" field will be removed', 'pc_ml') .'<br/>'.
                            '- '. esc_html__('password field will always have the icon enabled', 'pc_ml') .'<br/>'.
                            '- '. esc_html__('a clickable eye will be always used as icon', 'pc_ml'),
			),
		),
	),
	
	
	'rf_builder' => array(
		'sect_name'	=>  esc_html__('Registration forms builder', 'pc_ml'),
		'fields' 	=> array(
			
			'pc_sc_reg_form_builder' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_sc_reg_form_builder',
			), 
		),
	),
);




// WP USER SYNC OPTIONS
$structure['wp_user_sync'] = array(
	'wps_main' => array(
		'sect_name'	=>  esc_html__('Wordpress Users System Integration', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_wp_user_sync' => array(
				'label' => esc_html__('Enable integration?', 'pc_ml'),
				'type'	=> 'checkbox',
                
                /* translators: 1: html code, 2: html code. */
				'note'	=> esc_html__('If checked, privateContent users will be logged also with basic WP account', 'pc_ml') .'<br/><strong><font class="lcwp_settings_rednote">'. sprintf(esc_html__('What does this imply? For more details, please check the %1$srelated documentation chapter%2$s', 'pc_ml'), '<a href="https://doc.lcweb.it/privatecontent/#wp_user_sync" target="_blank">', '</a>')
			), 
			
			'pg_require_wps_registration' => array(
				'label' => esc_html__('Require sync during frontend registration?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('Allows new users only if WP user sync is successful (automatically adds e-mail field into registration forms)', 'pc_ml'),
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_wp_user_sync',
					'condition'		=> true 
				)
			), 
			'pg_custom_wps_roles' => array(
				'label' 	=> esc_html__("Additional user roles", 'pc_ml'),
				'type'		=> 'select',
				'val' 		=> pvtcont_wps_emulable_roles(),
				'multiple'	=> true,
                
                /* translators: 1: html code, 2: html code. */
				'note'		=> sprintf(esc_html__('Set which roles will be %1$semulated%2$s to synced users', 'pc_ml'), '<strong>', '</strong>'),
				
				'js_vis'=> array(
					'linked_field' 	=> 'pg_wp_user_sync',
					'condition'		=> true 
				)
			),
		),
	),
		
	'wp_to_pc_registration' => array(
		'sect_name'	=>  esc_html__('WordPress to PrivateContent - Registration Sync', 'pc_ml'),
		'fields' 	=> array(
			
			'wp_to_pc_sync_on_register' => array(
				'label' => esc_html__('Sync newly registered WP users?', 'pc_ml'),
				'type'	=> 'checkbox',
                
                /* translators: 1: html code, 2: html code. */
				'note'	=> sprintf(esc_html__('If checked, WordPress users registered on %1$sfrontend%2$s will be automatically synced in PrivateContent', 'pc_ml'), '<strong>', '</strong>')
			), 
			'wp_to_pc_sync_on_register_cats' => array(
				'label' 	=> esc_html__("Default categories for synced users", 'pc_ml'),
				'type'		=> 'select',
				'val' 		=> pc_static::user_cats(),
				'multiple' 	=> true,
				'note'		=> esc_html__("Choose at least a category that will be assigned to imported users", 'pc_ml'), 
				
				'js_vis'	=> array(
					'linked_field' 	=> 'wp_to_pc_sync_on_register',
					'condition'		=> true 
				)
			), 
			'wp_to_pc_sync_on_register_roles' => array(
				'label' 	=> esc_html__("Limit sync to these roles", 'pc_ml'),
				'type'		=> 'select',
				'val' 		=> pvtcont_wps_emulable_roles(),
				'multiple'	=> true,
				'note'		=> esc_html__('Sync on WP registration will be limited only to these user roles. Leave empty to sync each user', 'pc_ml'),
				
				'js_vis'	=> array(
					'linked_field' 	=> 'wp_to_pc_sync_on_register',
					'condition'		=> true 
				)
			),
		),
	),
			
	'wps_comments_lock' => array(
		'sect_name'	=>  esc_html__('Comments Restriction', 'pc_ml'),
		'fields' 	=> array(	
			
			'pg_lock_comments' => array(
				'label' => esc_html__('Hide comments block in every page?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('If checked, totally hides comment block on site for unlogged users (overriding other restrictions)', 'pc_ml'),
			), 
			'pg_hc_warning' => array(
				'label' => esc_html__('Display warning for hidden comment blocks?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__('If checked, shows a warning box replacing comment form (can be overrided for single posts)', 'pc_ml'),
			), 
		),
	),	
			
	'wps_manual_bulk_sync' => array(	
		'sect_name'	=>  esc_html__('Manual Bulk Sync', 'pc_ml'),
		'fields' 	=> array(	
		
			'pc_do_wp_sync' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_do_wp_sync',
				'hide'	=> ($wps_enabled) ? false : true,
			), 
			'pc_wps_matches_sync' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_wps_matches_sync',
				'hide'	=> ($wps_enabled) ? false : true,
			),
			'pc_clean_wp_sync' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_clean_wp_sync',
				'hide'	=> ($wps_enabled) ? false : true,
			),  
			
			'wps_mbs_message' => array(
				'type'		=> 'message',
				'content'	=> esc_html__('Enable the sync and save settings to view options', 'pc_ml'),
				'hide'	=> ($wps_enabled) ? true : false,
			),  
			
		),
	),
);




// CUSTOM MESSAGES
$structure['messages'] = array(
	'restr_cont_mess' => array(
		'sect_name'	=>  esc_html__('Restricted Content', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_default_nl_mex' => array(
				'label' 	=> esc_html__('Default message for unlogged users', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 255,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__('You must be logged in to view this content', 'pc_ml').'"',
			),
			'pg_default_uca_mex' => array(
				'label' 	=> esc_html__('Custom message for users having wrong permissions', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 200,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__("Sorry, you don't have the right permissions to view this content", 'pc_ml').'"',
			),
		),
	),
	
	
	'restr_comm_mess' => array(
		'sect_name'	=>  esc_html__('Restricted Comments', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_default_hc_mex' => array(
				'label' 	=> esc_html__('Custom message for unlogged users', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 255,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__('You must be logged in to post comments', 'pc_ml').'"',
			),
			'pg_default_hcwp_mex' => array(
				'label' 	=> esc_html__('Custom message for users having wrong permissions', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 200,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__("Sorry, you don't have the right permissions to post comments", 'pc_ml').'"',
			),
		),
	),
	
	
	'pvtpag_mess' => array(
		'sect_name'	=>  esc_html__('User Reserved Page', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_default_nhpa_mex' => array(
				'label' 	=> esc_html__("Default message if user doesn't have its own reserved page", 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 255,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__("You don't have a reserved area", 'pc_ml').'"',
			),
		),
	),
	
	
	'login_mess' => array(
		'sect_name'	=> esc_html__('Login Form', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_login_ok_mex' => array(
				'label' 	=> esc_html__('Default message for successful login', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 170,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__("Logged successfully, welcome!", 'pc_ml').'"',
			),
			'pg_default_pu_mex' => array(
				'label' 	=> esc_html__('Default message for pending users', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 170,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__("Sorry, your account has not been activated yet", 'pc_ml').'"',
			),
			'pg_default_du_mex' => array(
				'label' 	=> esc_html__('Default message for disabled users', 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 170,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__("Sorry, your account has been disabled", 'pc_ml').'"',
			),
		),
	),
	
	
	'reg_form_mess' => array(
		'sect_name'	=>  esc_html__('Registration Form', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_default_sr_mex' => array(
				'label' 	=> esc_html__("Default message for successfully registered users", 'pc_ml'),
				'type'		=> 'text',
				'maxlen'	=> 200,
				'fullwidth'	=> true,
				'note'		=> esc_html__('Leave empty to use default message', 'pc_ml') .': "'. esc_html__("Registration was successful. Welcome!", 'pc_ml').'"',
			),
		),
	),
);



// LIGHTBOX
$structure['pc_lightbox'] = array(
	'lightbox_wizard' => array(
		'sect_name'	=> esc_html__('Lightbox Instances', 'pc_ml') .'<a id="pc_add_lb_trig">'. esc_html__('Add instance', 'pc_ml') .'</a>',
		'fields' 	=> array(
			
			// custom validation in view.php
			'pg_lightbox_instances' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_lightbox_instances',
			), 
		),
	),
);




// STYLING OPTIONS

// fixed field details - dynamically build array
$fix_fields = array('name', 'surname', 'username', 'psw', 'repeat_psw', 'categories', 'email', 'tel');
$ff_details_structure = array(
	'spcr1' => array('hide'	=> true, 'type' => 'spacer') // trick to print the table
);
foreach($fix_fields as $ff) {
	$ff_details_structure['pg_'. $ff .'_details'] = array(
		'type'		=> 'custom',
		'callback'	=> 'pvtcont_fix_field_detail',
		'cb_subj'	=> $ff,
		'validation'=> array(
			array('index' => 'pg_'. $ff .'_placeh', 'label' => $ff ." placeholder", 'maxlen'=> 150),
			array('index' => 'pg_'. $ff .'_icon', 'label' => $ff ." icon"),
		)
	);			
}

// buttons icon - dynamically build array
$buttons = array('register', 'login', 'logout', 'user_del');
$btn_icons_structure = array(
	'spcr1' => array('hide'	=> true, 'type' => 'spacer') // trick to print the table
);
foreach($buttons as $btn) {
	$btn_icons_structure['pg_'. $btn .'_btn_icon'] = array(
		'type'		=> 'custom',
		'callback'	=> 'pvtcont_btns_icon',
		'cb_subj'	=> $btn,
		'validation'=> array(
			array('index' => 'pg_'. $btn .'_btn_icon', 'label' => $btn ." icon"),
		)
	);			
}

$structure['styling'] = array(
	
    'preset_styles' => array(
		'sect_name'	=>  esc_html__('Forms Preset Styles', 'pc_ml'),
		'fields' 	=> array(
			
			'preset_styles_field' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_preset_styles'
			), 
		),
	),
    
    
	'elems_layout' => array(
		'sect_name'	=>  esc_html__('Form Elements Layout', 'pc_ml'),
		'fields' 	=> array(
            
			'pg_form_add_padding' => array(
                
                /* translators: 1: html code, 2: html code. */
				'label' 	=> sprintf(esc_html__('Forms additional padding %1$s(vertical / horizontal)%2$s', 'pc_ml'), '<small>', '</small>'),
				'type'		=> '2_numbers',
				'min_val'	=> 0,
				'max_val'	=> 40,	
				'value'		=> 'px',
				'def'		=> array(0, 0),
				'note'		=> ''
			),
			'pg_field_padding' => array(
                
                /* translators: 1: html code, 2: html code. */
				'label' 	=> sprintf(esc_html__('Fields padding %1$s(vertical / horizontal)%2$s', 'pc_ml'), '<small>', '</small>'),
				'type'		=> '2_numbers',
				'min_val'	=> 0,
				'max_val'	=> 15,	
				'value'		=> 'px',
				'def'		=> array(get_option('pg_field_padding', 3), get_option('pg_field_padding', 3)),
				'note'		=> '',
			),
			'pg_buttons_padding' => array(
                
                /* translators: 1: html code, 2: html code. */
				'label' 	=> sprintf(esc_html__('Buttons padding %1$s(vertical / horizontal)%2$s', 'pc_ml'), '<small>', '</small>'),
				'type'		=> '2_numbers',
				'min_val'	=> 0,
				'max_val'	=> 25,	
				'value'		=> 'px',
				'def'		=> array(6, 15),
				'note'		=> '',
			),
			'spcr1' => array(
				'type' => 'spacer',
			),
			'pg_forms_border_w' => array(
				'label' 	=> esc_html__("Forms border width", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 10,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 1,
                'respect_limits' => false,
				'note'		=> '',
			),
			'pg_field_border_w' => array(
				'label' 	=> esc_html__("Fields border width", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 5,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 1,
                'respect_limits' => false,
				'note'		=> '',
			),
			'pg_btn_border_w' => array(
				'label' 	=> esc_html__("Buttons border width", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 5,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 1,
                'respect_limits' => false,
				'note'		=> '',
			),
			'spcr2' => array(
				'type' => 'spacer',
			),
			'pg_form_border_radius' => array(
				'label' 	=> esc_html__("Forms border radius", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 50,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 2,
                'respect_limits' => false,
				'note'	=> '',
			),
            'pg_field_border_radius' => array(
				'label' 	=> esc_html__("Fields border radius", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 50,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 0,
                'respect_limits' => false,
				'note'	=> '',
			),
			'pg_btn_border_radius' => array(
				'label' 	=> esc_html__("Buttons border radius", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 50,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 2,
                'respect_limits' => false,
				'note'	=> '',
			),
            'spcr2a' => array(
				'type' => 'spacer',
			),
			'pg_reg_fblock_gap' => array(
				'label' 	=> esc_html__("Registration fields gap", 'pc_ml'),
                'type'		=> '2_numbers',
				'min_val'	=> 0,
				'max_val'	=> 200,	
				'value'		=> 'px',
				'def'		=> array(20, 35),
				'note'		=> esc_html__("Defines the vertical and horizontal gap between registration form fields (default: 20 / 35px)", 'pc_ml'),
			),
            'pg_login_fields_gap' => array(
				'label' 	=> esc_html__("Login fields gap", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 10,
				'max_val'	=> 40,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 15,
                'respect_limits' => false,
				'note'		=> esc_html__("Defines the vertical space between login form fields (default: 15px)", 'pc_ml'),
			),
            'pg_fullw_login_fields' => array(
				'label' => esc_html__('Fullwidth login fields?', 'pc_ml'),
				'type'	=> 'checkbox',
                'def'   => true,
				'note'	=> esc_html__("If checked, the login fields will fill the whole form's width", 'pc_ml'),
			),
            'pg_fullw_login_btn' => array(
				'label' => esc_html__('Fullwidth login form buttons?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, the login buttons will fill the whole form's width", 'pc_ml'),
			),
			'pg_separator_margin' => array(
				'label' 	=> esc_html__("Separator bar's extra margin", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 150,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 10,
                'respect_limits' => false,
				'note'		=> esc_html__("Defines an extra vertical margin for separator bar in registration forms (default: 10px)", 'pc_ml'),
			),
			'spcr3a' => array(
				'type' => 'spacer',
			),
            
			'pg_bottomborder' => array(
				'label' => esc_html__('Use bottom-border style?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, fields will just have a bottom border", 'pc_ml'),
			), 
			'pg_nolabel' => array(
				'label' => esc_html__('Hide labels?', 'pc_ml'),
				'type'	=> 'checkbox',
				'note'	=> esc_html__("If checked, input and dropdown labels will be hidden, focusing placeholders", 'pc_ml'),
			),
			'pg_reg_layout' => array(
				'label' => esc_html__("Default forms layout", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array(
					'one_col' => esc_html__('Single column', 'pc_ml'), 
					'fluid' => esc_html__('Fluid (multi column)', 'pc_ml')
				),
				'note'	=> esc_html__("Select default layout for registration and User Data add-on forms", 'pc_ml'), 
			),
            'pg_onecol_form_max_w' => array(
				'label' 	=> esc_html__("One-column forms - maximum width", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 50,
				'max_val'	=> 1500,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 460,
                'respect_limits' => false,
				'note'	    => ''
			),
            'pg_fluid_form_threshold' => array(
				'label' 	=> esc_html__("Fluid forms - columns maximum width", 'pc_ml'), 
				'type'		=> 'slider',
				'min_val'	=> 50,
				'max_val'	=> 1000,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 370,
                'respect_limits' => false,
				'note'	    => esc_html__("Threshold defining how fluid forms will be columnized", 'pc_ml'),
			),
		), 
	),	
    
    
	'fix_field_details' => array(
		'sect_name'	=>  esc_html__('Fixed Field Details', 'pc_ml'),
		'fields' 	=> $ff_details_structure 
	),
	
	'button_icons' => array(
		'sect_name'	=>  esc_html__('Buttons Icon', 'pc_ml'),
		'fields' 	=> $btn_icons_structure
	), 
    
    
	'typography' => array(
		'sect_name'	=>  esc_html__('Forms Typography', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_labels_font_size' => array(
				'label' 	=> esc_html__("Form labels - font size", 'pc_ml'),
                'type'			=> 'val_n_type',
				'max_val_len'	=> 4,
				'def'			=> 15,
				'types' 		=> array(
					'px'	=> 'px',
					'rem'	=> 'rem',
					'vmax'	=> 'vmax',
				),
                'required'  => true,
				'note'		=> esc_html__("Set labels size for any privateContent form (default: 15px)", 'pc_ml'),
			),
			'pg_fields_font_size' => array(
				'label' 	=> esc_html__("Form fields - font size", 'pc_ml'),
                'type'			=> 'val_n_type',
				'max_val_len'	=> 4,
				'def'			=> 14,
				'types' 		=> array(
					'px'	=> 'px',
					'rem'	=> 'rem',
					'vmax'	=> 'vmax',
				),
                'required'  => true,
				'note'		=> esc_html__("Set registration form labels size (default: 14px)", 'pc_ml'),
			),
			'pg_btns_font_size' => array(
				'label' 	=> esc_html__("Form buttons - font size", 'pc_ml'),
                'type'			=> 'val_n_type',
				'max_val_len'	=> 4,
				'def'			=> 14,
				'types' 		=> array(
					'px'	=> 'px',
					'rem'	=> 'rem',
					'vmax'	=> 'vmax',
				),
                'required'  => true,
				'note'		=> esc_html__("Set buttons font size (default: 14px)", 'pc_ml'),
			),
			'pg_forms_font_family' => array(
				'label' => esc_html__('Form elements - font family', 'pc_ml'),
				'type'	=> 'text',
				'note'	=> esc_html__("Set forms font family (leave empty to use theme's one)", 'pc_ml'),
			),
		),
	),	
	
	
	'form_colors' => array(
		'sect_name'	=>  esc_html__('Form Colors', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_forms_bg_col' => array(
				'label' => esc_html__('Background color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#fefefe',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
			'pg_forms_border_col' => array(
				'label' => esc_html__('Border color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#ebebeb',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
            'pg_forms_shadow' => array(
				'label' => esc_html__("Outer shadow?", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> array(
					'' 	=> esc_html__('No', 'pc_ml'),
					'light'    => esc_html__('Light', 'pc_ml'),
					'medium'   => esc_html__('Medium', 'pc_ml'),
					'heavy'    => esc_html__('Heavy', 'pc_ml'),
				),
				'note'	=> '', 
			),
            'pg_forms_shadow_col' => array(
				'label' => esc_html__("Shadow color", 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#000000',
                'extra_modes' => array(),
				'note'	=> '',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_forms_shadow',
					'condition'		=> '',
                    'operator'		=> '!=',
				)
			),
			'pg_label_col' => array(
				'label' => esc_html__('Labels color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#333333',
                'extra_modes' => array(),
				'note'	=> '',
			),
			'spcr1' => array(
				'type' => 'spacer',
			),
			'pg_fields_bg_col' => array(
				'label' => esc_html__('Fields background color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#fefefe',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_fields_border_col' => array(
				'label' => esc_html__('Fields border color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#cccccc',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_fields_placeh_col' => array(
				'label' => esc_html__('Fields placeholder color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#888888',
                'extra_modes' => array(),
				'note'	=> '',
			),
			'pg_fields_txt_col' => array(
				'label' => esc_html__('Fields text color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#808080',
                'extra_modes' => array(),
				'note'	=> '',
			),
			'pg_fields_icon_col' => array(
				'label' => esc_html__('Fields icon color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#808080',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_fields_icon_bg' => array(
				'label' 		=> esc_html__('Fields icon background - default status', 'pc_ml'),
				'type'			=> 'color',
				'def'		    => '#f8f8f8',
				'extra_modes'   => array('alpha', 'linear-gradient', 'radial-gradient'),
			),
			'pg_fields_bg_col_h' => array(
				'label' => esc_html__('Fields background color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#ffffff',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_fields_border_col_h' => array(
				'label' => esc_html__('Fields border color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#aaaaaa',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_fields_placeh_col_h' => array(
				'label' => esc_html__('Fields placeholder color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#666666',
                'extra_modes' => array(),
				'note'	=> '',
			),
			'pg_fields_txt_col_h' => array(
				'label' => esc_html__('Fields text color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#444444',
                'extra_modes' => array(),
				'note'	=> '',
			),
			'pg_fields_icon_col_h' => array(
				'label' => esc_html__('Fields icon color - hover status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#666666',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_fields_icon_bg_h' => array(
				'label' 			=> esc_html__('Fields icon background - hover status', 'pc_ml'),
				'type'				=> 'color',
				'def'				=> '#f0f0f0',
				'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'				=> '',
			),
			'spcr2' => array(
				'type' => 'spacer',
			),
			'pg_btn_bg_col' => array(
				'label' => esc_html__('Buttons background color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#f4f4f4',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
			'pg_btn_border_col' => array(
				'label' => esc_html__('Buttons border color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#cccccc',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_btn_txt_col' => array(
				'label' => esc_html__('Buttons text color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#444444',
                'extra_modes' => array(),
				'note'	=> '',
			),
			'pg_btn_bg_col_h' => array(
				'label' => esc_html__('Buttons background color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#efefef',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
			'pg_btn_border_col_h' => array(
				'label' => esc_html__('Buttons border color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#cacaca',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_btn_txt_col_h' => array(
				'label' => esc_html__('Buttons text color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#222222',
                'extra_modes' => array(),
				'note'	=> '',
			),
            'spcr3' => array(
				'type' => 'spacer',
			),
            'pg_lcswitch_knob_col' => array(
				'label' => esc_html__('Switch knob color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#ffffff',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
            'pg_lcswitch_off_col' => array(
				'label' => esc_html__('Switch color - off status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#cccccc',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
            'pg_lcswitch_on_col' => array(
				'label' => esc_html__('Switch color - on status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#75b936',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
            'spcr4a' => array(
				'type' => 'spacer',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_forms_pags_progress',
					'condition'		=> true 
				)
			),
            'pg_fpp_bg' => array(
				'label' => esc_html__('Pagination progressbar - default status - background', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#e4e4e4',
                'extra_modes' => array('linear-gradient', 'radial-gradient'),
				'note'	=> '',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_forms_pags_progress',
					'condition'		=> true 
				)
			),
            'pg_fpp_col' => array(
				'label' => esc_html__('Pagination progressbar - default status - color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#373737',
                'extra_modes' => array(),
				'note'	=> '',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_forms_pags_progress',
					'condition'		=> true 
				)
			),
            'pg_fpp_bg_h' => array(
				'label' => esc_html__('Pagination progressbar - active status - background', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#74b945',
                'extra_modes' => array('linear-gradient', 'radial-gradient'),
				'note'	=> '',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_forms_pags_progress',
					'condition'		=> true 
				)
			),
            'pg_fpp_col_h' => array(
				'label' => esc_html__('Pagination progressbar - active status - color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#ffffff',
                'extra_modes' => array(),
				'note'	=> '',
                
                'js_vis'=> array(
					'linked_field' 	=> 'pg_forms_pags_progress',
					'condition'		=> true 
				)
			),
		),
	),
	
	
    'mess_styling_opts' => array(
		'sect_name'	=>  esc_html__('Messages', 'pc_ml'),
		'fields' 	=> array(
            
            'pg_messages_style' => array(
				'label' => esc_html__("Messages style", 'pc_ml'),
				'type'	=> 'select',
				'val' 	=> pvtcont_mess_styles_opts(),
				'note'	=> 'Choose the style you want to use for success/error messages and for warning boxes', 
			),
            'pg_messages_style_preview' => array(
				'label' => esc_html__("Messages style's preview", 'pc_ml'),
				'type'	=> 'label_message',
				'content' => '<img src="" id="pg_messages_style_preview" />', 
			),
            
            'spcr2' => array(
				'type' => 'spacer',
			),
			'pg_mess_btn_bg_col' => array(
				'label' => esc_html__('Buttons background color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#f4f4f4',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
			'pg_mess_btn_border_col' => array(
				'label' => esc_html__('Buttons border color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#cccccc',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_mess_btn_txt_col' => array(
				'label' => esc_html__('Buttons text color - default status', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#444444',
                'extra_modes' => array(),
				'note'	=> '',
			),
			'pg_mess_btn_bg_col_h' => array(
				'label' => esc_html__('Buttons background color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#efefef',
                'extra_modes' => array('alpha', 'linear-gradient', 'radial-gradient'),
				'note'	=> '',
			),
			'pg_mess_btn_border_col_h' => array(
				'label' => esc_html__('Buttons border color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#cacaca',
                'extra_modes' => array('alpha'),
				'note'	=> '',
			),
			'pg_mess_btn_txt_col_h' => array(
				'label' => esc_html__('Buttons text color - on hover', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#222222',
                'extra_modes' => array(),
				'note'	=> '',
			),
		),
	), 
    
    
	'lightbox_styling' => array(
		'sect_name'	=> esc_html__('Lightbox', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_lb_padding' => array(
				'label' 	=> esc_html__("Padding", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 10,
				'max_val'	=> 35,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 25,
                'respect_limits' => false,
				'note'		=> esc_html__("Set lightbox padding - ignored if it contains only a form (default: 25px)", 'pc_ml'),
			),
			'pg_lb_border_radius' => array(
				'label' 	=> esc_html__("Border Radius", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 50,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 3,
                'respect_limits' => false,
				'note'		=> esc_html__("Set lightbox border radius - ignored if it contains only a form (default: 3px)", 'pc_ml'),
			),
			'pg_lb_border_w' => array(
				'label' 	=> esc_html__("Border width", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 10,	
				'step'		=> 1,
				'value'		=> 'px',
				'def'		=> 0,
                'respect_limits' => false,
				'note'		=> esc_html__("Set lightbox borders width (default: 0px)", 'pc_ml'),
			),
			'pg_lb_max_w' => array(
				'label' 	=> esc_html__("Maximum width", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 40,
				'max_val'	=> 95,	
				'step'		=> 1,
				'value'		=> '%',
				'def'		=> 70,
                'respect_limits' => true,
				'note'		=> esc_html__("Set lightbox max-width - on mobile it is fixed to 90% (default: 70%)", 'pc_ml'),
			),
			'spcr1' => array(
				'type' => 'spacer',
			),
			'pg_lb_border_col' => array(
				'label' => esc_html__('Border color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#dddddd',
				'note'	=> '',
			),
			'pg_lb_overlay_col' => array(
				'label' => esc_html__('Overlay color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#111111',
				'note'	=> '',
			),
			'pg_lb_overlay_alpha' => array(
				'label' 	=> esc_html__("Overlay opacity", 'pc_ml'),
				'type'		=> 'slider',
				'min_val'	=> 0,
				'max_val'	=> 100,	
				'step'		=> 5,
				'value'		=> '%',
				'def'		=> 80,
                'respect_limits' => true,
				'note'		=> '',
			),
			'pg_lb_bg' => array(
				'label' => esc_html__('Background color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#ffffff',
				'note'	=> ''
			),
			'pg_txt_col' => array(
				'label' => esc_html__('Text color', 'pc_ml'),
				'type'	=> 'color',
				'def'	=> '#555555',
				'note'	=> esc_html__("Note: this won't apply to PrivateContent forms", 'pc_ml'),
			),
		),
	),
    
    
	'cust_css' => array(
		'sect_name'	=> esc_html__('Custom CSS', 'pc_ml'),
		'fields' 	=> array(
			
			'pg_custom_css' => array(
				'label' => 'custom CSS',
				'type'	=> 'code_editor',
				'language'	=> 'css',
			),
		),
	),
);



// URL-BASED RESTRICTIONS
$structure['pc_cust_restr'] = array(
	'url_base_restr' => array(
		'sect_name'	=> esc_html__('URL-based Restrictions', 'pc_ml') .'<a id="pc_add_cr_trig">'. esc_html__('Add restriction', 'pc_ml') .'</a>',
		'fields' 	=> array(
			
			'pg_url_base_restr_field' => array(
				'type'		=> 'custom',
				'callback'	=> 'pvtcont_url_base_restr_field',
				'validation'=> array(
					array('index' => 'pg_cr_url', 'label'=>'Custom restrictions - url'),
					array('index' =>'pg_cr_allow', 'label'=>'Custom restrictions - allowed'),
					array('index' =>'pg_cr_block', 'label'=>'Custom restrictions - blocked')
				)
			), 
		),
	),
);




// PC-FILTER - manipulate settings structure
$GLOBALS['pc_settings_structure'] = apply_filters('pc_settings_structure', $structure);
