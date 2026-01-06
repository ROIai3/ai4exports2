<?php
/* NFPCF */

// INITIALIZE DIVI MODULES
if(!defined('ABSPATH')) {exit;}



class pc_divi_modules {

    // DEEFINE MODULES
    // module slug => php files slug
    private $modules = array(
        'pc_login'          => 'login',
        'pc_logout'         => 'logout',
        'pc_user_del'       => 'user_del',
        'pc_pvt_block'      => 'pvt_block',
        'pc_reg_form'       => 'reg_form',
    );
    
    
    
    /* 
     * static method to render elements from both builder and frontend 
     *
     * @param (string) $module_slug
     * @param (array) $vals = values passed by the builder
     */
    public static function front_shortcode_render($module_slug, $vals) {
        switch($module_slug) {
           
            case 'pc_login' :
                $shortcode = '[pc-login-form '. self::vals_to_sc_params($module_slug, $vals) .']';
                break;
                
                
            case 'pc_logout' :
                $shortcode = '[pc-logout-box '. self::vals_to_sc_params($module_slug, $vals) .']';
                break;    
            
                
            case 'pc_user_del' :
                $shortcode = '[pc-user-del-box '. self::vals_to_sc_params($module_slug, $vals) .']';
                break;  
                
                
            case 'pc_pvt_block' :
                $contents = $vals['pvt_block_txt'];
                unset($vals['pvt_block_txt']);
                
                $shortcode = '[pc-pvt-content '. self::vals_to_sc_params($module_slug, $vals) .']'. $contents .'[/pc-pvt-content]';
                break;      
                
                
            case 'pc_reg_form' :
                $shortcode = '[pc-registration-form '. self::vals_to_sc_params($module_slug, $vals) .']';
                break;  
            
                
            default :
                return $module_slug .' module not found';  
        }    
        
        //echo $shortcode;
        return do_shortcode($shortcode);
    }
    
    
    
    /* insert here custom actions upon initialization (eg. to create global variable containing galleries array) */
    private function custom_actions() {
        $GLOBALS['pvtcont_divi_icon_path'] = PC_DIR .'/builders_integration/divi_modules/icon.svg';
            
        // forms alignment array
        $GLOBALS['pvtcont_divi_forms_align'] = array(
            'center' 	=> esc_html__('Center', 'pc_ml'),
            'left'		=> esc_html__('Left', 'pc_ml'),
            'right'		=> esc_html__('Right', 'pc_ml'),
        ); 
    }
    
    
    
    

    ####################################################################################################
    ## Common methods
    
    
    function __construct() {
        // initialize modules
        add_action('divi_extensions_init', array($this, 'init_modules'));
        
        // ajax handlers
        foreach($this->modules as $key => $name) {
            add_action('wp_ajax_'. $key .'_for_divi', array($this, 'ajax_handler'));
        }
    }
    
    
    /* include divi integration files */
    public function init_modules() {   
        $this->custom_actions();
        
        foreach($this->modules as $module) {
            include_once(__DIR__ .'/divi_modules/'. $module .'/includes/register.php');
        }  
    } 
    
    
    
    public function ajax_handler() {
        if(!isset($_POST['nonce']) || !pc_static::verify_nonce($_POST['nonce'], 'lcwp_nonce')) {
            wp_die('Cheating?');
        };
        
        if(!isset($_POST['module'])) {
            die('module not found');    
        }
        
        wp_die(
            pc_static::wp_kses_ext(
                self::front_shortcode_render(pc_static::sanitize_val($_POST['module']), pc_static::sanitize_val($_POST['params']))
            )
        );
    }
    
    
    
    /* 
     * Static method compiling shortcode attributes from values array. Also strips out useless Divi values 
     * @param (array) $exception_indexes = array of extra indexes to save (eg. width and height) 
     */
    private static function vals_to_sc_params($module_slug, $vals, $exception_indexes = array()) {
       
        // strip useless parameters
        if(isset($GLOBALS[$module_slug .'_divi_field_indexes'])) {
            foreach($vals as $key => $val) {
                if(!in_array($key, $GLOBALS[$module_slug .'_divi_field_indexes']) && !in_array($key, (array)$exception_indexes)) {
                    unset($vals[$key]);    
                }
            }
        }

        // atts string creator
        $params = '';
        foreach($vals as $key => $val) {
            if($val === 'on' || $val === esc_html__('Yes', 'pc_ml')) {
                $val = 1;
            }
            elseif($val === 'off' || $val === esc_html__('No', 'pc_ml')) {
                $val = 0;
            }
            elseif($val === 'unset') {
                $val = '';
            }

            $params .= $key.'="'. esc_attr((string)$val) .'" ';
        }    
        
        return $params;
    }
}
new pc_divi_modules();








// constant to avoid useless Divi fields on module
if(!defined('LC_DIVI_DEF_OPTS_OVERRIDE')) {
    $indexes = array(
        'link_options',
        'admin_label',
        'background',
        'text',
        'fonts',
        'borders',
        'box_shadow',
        'margin_padding',
        'button',
        'filters',
        'text_shadow',
        'width', 
    );
    $to_return = array();

    foreach($indexes as $i) {
        $to_return[$i] = false;     
    }

    $to_return['width'] = array();
    $to_return['max_width'] = array(
        'use_max_width'        => false,
        'use_module_alignment' => false,
    );
    
    define('LC_DIVI_DEF_OPTS_OVERRIDE', serialize($to_return));
}


