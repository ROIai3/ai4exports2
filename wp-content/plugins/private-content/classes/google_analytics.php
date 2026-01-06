<?php
/* NFPCF */

// GOOGLE ANALYTICS IMPLEMENTATION - USER TRACKING
if(!defined('ABSPATH')) {exit;}


class pvtcont_google_analytics {
	
	public $tid; // (string) google tracking ID (G-XXXXXXXXXX)
	public $gtm_id; // (string) Google Tag Manager ID (GTM-XXXXXXX)
    public $ga4_api_secret; // (string)
    

	public function __construct() {
        $analytics_version = 'ga4'; 
        $tid_option_name = 'pg_ga4_id';
        
		$this->tid = get_option($tid_option_name);
		if(empty($this->tid)) {
            return false;
        }
        
        if($analytics_version == 'ga4') {
            $this->gtm_id = get_option('pg_gtm_id');
            $this->ga4_api_secret = get_option('pg_ga4_api_secret');
            
            if(empty($this->gtm_id) || empty($this->ga4_api_secret)) {
                return false;
            }
        }

        
        // GA4 - user ID setup
        if(!is_admin() && $analytics_version == 'ga4') {
            if(get_option('pg_ga4_inject_js_code')) {
                add_action('wp_enqueue_scripts', array($this, 'enqueue_script'), 910);
            }
            
            add_action('wp_head', array($this, 'set_ga4_userid'), 1);
            add_action('wp_footer', array($this, 'add_gtm_footer_code'), 9999);
        }
	}

    
	
    
    /* get user ID in the analytics format */
    public static function get_ga_uid() {
        return (isset($GLOBALS['pc_user_id'])) ? 'pc-'.$GLOBALS['pc_user_id'] : '';  
    }
    
    
    
    
    
    
	
	////////////////////////////////////////////////////////////////
    ### ANALYTICS 4 ###
	
	
    public function enqueue_script() {
        wp_enqueue_script('pc-ga4', 'https://www.googletagmanager.com/gtag/js?id='. esc_attr($this->tid), 999, PC_VERS, true);   
    }
    
    
    
    /* GA4 - setup user ID parameter and eventually inject Google codes */
    public function set_ga4_userid() {
        if(empty($this->tid) || empty($this->gtm_id)) {
            return false;        
        }
        
        $inline_js = '
        (function() { 
            "use strict"; 
            
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                "pc_user_id" : '. ((isset($GLOBALS['pc_user_id'])) ? "'". absint(self::get_ga_uid()) ."'" : 'null') .'
            });';
               
            if(get_option('pg_ga4_inject_js_code')) {
                $inline_js .= '
                window.dataLayer = window.dataLayer || [];
                window.gtag = function(){dataLayer.push(arguments);}
                gtag("js", new Date());

                gtag("config", "'. esc_attr($this->tid) .'");

                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":
                new Date().getTime(),event:`gtm.js`});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!=`dataLayer`?`&l=`+l:"";j.async=true;j.src=
                `https://www.googletagmanager.com/gtm.js?id=`+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,`script`,`dataLayer`,`'. esc_attr($this->gtm_id) .'`);';
            }
            
            $inline_js .= '
            window.pc_ga4_event = function(event_name, params = {}, forced_uid = false) {
                const pc_uid = (forced_uid) ? `pc-`+ forced_uid : `'. esc_js(self::get_ga_uid()) .'`;
                
                if(!pc_uid) {
                    return false;    
                }
                else {
                    if(typeof(window.gtag) != `function`) {
                        console.error(`PrivateContent on ga4: gtag() function not found`);    
                    }
                    else {
                        params.user_id = pc_uid;
                        return gtag(`event`, event_name, params);
                    }
                }
            };
        })();';
        wp_add_inline_script('pc_frontend', $inline_js);
    }
    
    
    
    /* eventually inject Google Tag Manager footer code */
    public function add_gtm_footer_code() {
        if(get_option('pg_ga4_inject_js_code')) {
            ?>
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($this->gtm_id) ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <?php
        }
    }
    
    
    
	
    
    /* GA4 - create custom event */
    public function trigger_event($event_id, $params = array(), $forced_uid = false) {
        if(empty($this->tid) || empty($this->ga4_api_secret)) {
            return false;        
        }
        
        $ga4_args = array(	
            'client_id' => sanitize_text_field($_SERVER['HTTP_USER_AGENT']),
            'user_id'   => ($forced_uid) ? 'pc-'.$forced_uid : self::get_ga_uid(),
            'events'    => array(
                array(
                    'name'  => $event_id,
                    'params'=> $params
                ),
            ),
        );
        return wp_remote_post('https://www.google-analytics.com/mp/collect?measurement_id='. $this->tid .'&api_secret='. esc_attr($this->ga4_api_secret), 
            array('body' => json_encode($ga4_args))
        );
    } 
}


$GLOBALS['pvtcont_google_analytics'] = new pvtcont_google_analytics;