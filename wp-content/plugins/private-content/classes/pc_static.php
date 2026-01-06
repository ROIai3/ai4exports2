<?php
// GENERIC PLUGIN STATIC METHODS
if(!defined('ABSPATH')) {exit;}


class pc_static {
    
    
    // include every PHP file found in a target folder
    public static function include_all($folder_path, $to_exclude = array()) {
        if(!file_exists($folder_path)) {
            return false;   
        }
        
        foreach(glob(trailingslashit($folder_path) .'*.php') as $filepath) {
            $to_skip = false;
            foreach($to_exclude as $te) {
                if(strpos($filepath, '/'. $te .'.') !== false) {
                    $to_skip = true;
                    break;
                }
            }
            
            if(!$to_skip) {
                include_once($filepath);
            }
        }
    }
    
    
    
    
    // To be used instead of wp_verify_nonce() to properly sanitize the value
    public static function verify_nonce($str, $key) {
        return (wp_verify_nonce(sanitize_text_field(wp_unslash($str)), $key)) ? true : false;   
    }
    
    
    
    
    // creating an escaping function similar to wp_kses_post but allowing <style> and <script>
    public static function wp_kses_ext($content) {
        if(empty($content)) {
            return $content;   
        }
        
        $allowed_tags = wp_kses_allowed_html('post');

        $allowed_tags['style'] = array(
            'type' => array()
        );
        $allowed_tags['script'] = array(
            'type' => array(),
            'src'  => array(),
            'async' => array(),
            'defer' => array(),
        );

        $allowed_tags['form'] = array(
            'action' => array(),
            'method' => array(),
            'enctype' => array(),
            'disabled' => array(),
            'readonly' => array(),
        );
        $allowed_tags['input'] = array(
            'type' => array(),
            'name' => array(),
            'value' => array(),
            'placeholder' => array(),
            'checked' => array(),
            'disabled' => array(),
            'autocomplete'=> array(),
            'readonly' => array(),
            'size' => array(),
        );
        $allowed_tags['select'] = array(
            'name' => array(),
            'multiple' => array(),
            'autocomplete'=> array(),
            'readonly' => array(),
            'size' => array(),
        );
        $allowed_tags['option'] = array(
            'value' => array(),
            'selected' => array(),
            'disabled' => array(),
        );
        $allowed_tags['textarea'] = array(
            'name' => array(),
            'placeholder' => array(),
            'rows' => array(),
            'cols' => array(),
            'autocomplete'=> array(),
            'readonly' => array(),
            'size' => array(),
            'disabled' => array(),
        );
        $allowed_tags['button'] = array(
            'type' => array(),
            'name' => array(),
            'value' => array(),
            'autocomplete'=> array(),
            'size' => array(),
            'disabled' => array(),
        );
        $allowed_tags['label'] = array(
            'for' => array(),
        );

        foreach ($allowed_tags as $tag => $attributes) {
            $allowed_tags[$tag]['class'] = array();
            $allowed_tags[$tag]['id'] = array();
            $allowed_tags[$tag]['data-*'] = true;
        }
        
        
        $sanitized = wp_kses(
            htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 
            $allowed_tags
        );
        return htmlspecialchars_decode($sanitized, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
    }

    
    
    
    // sanitize $_POST $_GET data (acts recursively) */
	public static function sanitize_val($data) {
        if(!is_array($data)) {
            return ($data != wp_strip_all_tags($data)) ? self::wp_kses_ext($data) : trim(_sanitize_text_fields($data, true));
        }
        
        $sanitized = array();
        foreach($data as $key => $val) {
            if(is_array($val)) {
                $sanitized[$key] = self::sanitize_val($val);   
            }
            else {
                $sanitized[$key] = ($val != wp_strip_all_tags($val)) ? self::wp_kses_ext($val) : trim(_sanitize_text_fields($val, true));
            }
        }
        
        return $sanitized;
	}
    
    
    
    // get the current URL
    public static function curr_url() {
        $pageURL = 'http';

        if((isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") || (function_exists('is_ssl') && is_ssl())) {
            $pageURL .= "s";
        }
        
        $host = (isset($_SERVER['HTTP_HOST'])) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : ''; 
        $uri = (isset($_SERVER["REQUEST_URI"])) ? filter_var(wp_unslash($_SERVER["REQUEST_URI"]), FILTER_SANITIZE_URL) : ''; 
        
        $pageURL .= "://" . $host . $uri;
        return $pageURL;
    }
    
    

    // get file extension from a filename
    public static function stringToExt($string) {
        $pos = strrpos($string, '.');
        $ext = strtolower(substr($string,$pos));
        return $ext;	
    }
    
    
    
    // bytes to human readable format
    public static function human_filesize($bytes, $decimals = 2) {
        $size = array('Bytes','KB','MB','GB','TB','PB','EB','ZB','YB');

        $factor = floor((strlen($bytes) - 1) / 3);
        if($factor < 0) {
            $factor = 0;    
        }

        $val = sprintf("%.{$decimals}f", $bytes / pow(1024, $factor));

        // remove precise values
        if($decimals) {
            if(!(int)substr($val, ($decimals * -1))) {
                $arr = explode('.', $val);
                $val = $arr[0];
            }
        }

        return $val .' '. $size[$factor];
    }
    
    
    
    // calculate elapsed time
    public static function elapsed_time($date) {
        // PHP <5.3 fix
        if(!method_exists('DateTime','getTimestamp')) {
            include_once(PC_DIR . '/classes/datetime_getimestamp_fix.php');

            $dt = new pvtcont_DateTime($date);
            $timestamp = $dt->getTimestamp();	
        }
        else {	
            $dt = new DateTime($date);
            $timestamp = $dt->getTimestamp();
        }

        // calculate difference between server time and given timestamp
        $timestamp = current_time('timestamp') - $timestamp;

        //if no time was passed return 0 seconds
        if ($timestamp < 1){
            return '1 '. esc_html__('second', 'pc_ml');
        }

        //create multi-array with seconds and define values
        $values = array(
            12*30*24*60*60  =>  'year',
            30*24*60*60     =>  'month',
            24*60*60        =>  'day',
            60*60           =>  'hour',
            60              =>  'minute',
            1               =>  'second'
        );

        //loop over the array
        foreach ($values as $secs => $point){

            //check if timestamp is equal or bigger the array value
            $divRes = $timestamp / $secs;
            if ($divRes >= 1){

                //if timestamp is bigger, round the divided value and return it
                $res = round($divRes);

                // translatable strings
                switch($point) {
                    case 'year' : $txt = ($res > 1) ? esc_html__('years', 'pc_ml') : esc_html__('year', 'pc_ml'); break; 
                    case 'month': $txt = ($res > 1) ? esc_html__('months', 'pc_ml') : esc_html__('month', 'pc_ml'); break;
                    case 'day'  : $txt = ($res > 1) ? esc_html__('days', 'pc_ml') : esc_html__('day', 'pc_ml'); break;	
                    case 'hour' : $txt = ($res > 1) ? esc_html__('hours', 'pc_ml') : esc_html__('hour', 'pc_ml'); break;	
                    case'minute': $txt = ($res > 1) ? esc_html__('minutes', 'pc_ml') : esc_html__('minute', 'pc_ml'); break;	
                    case'second': $txt = ($res > 1) ? esc_html__('seconds', 'pc_ml') : esc_html__('second', 'pc_ml'); break;	
                }
                return $res. ' ' .$txt;
            }
        }
    }
    
    
    
    /* returning login cookie exiration time (in website timezone): considering remember me, short-lived mode and allowng filters */
    public static function login_cookie_duration($remember_me = false) {
        $short_lived        = get_option('pg_no_cookie_login');
        $basic_cookie_time  = ($short_lived) ? 60 * 10 : 60 * 60; // 10 or 60 minutes
        $cookie_time        = (!empty($remember_me)) ? (3600 * 24 * 14) : $basic_cookie_time; // 2 weeks (WP remember me timing) or what stated before
        
        // PC-FILTER - allow login cookie duration customization - passes "remember_me" and "short_lived" flags 
        $duration = (int)apply_filters('pc_login_cookie_duration', $cookie_time, $remember_me, $short_lived);
        
        return (int)gmdate('U') + $duration;
    }
    
    
    
    /* shortcut method to set cookies with right params */
    public static function setcookie($name, $value, $expiration) { 
        $secure = (is_ssl() && 'https' === wp_parse_url(get_option('home'), PHP_URL_SCHEME));
        setcookie($name, $value, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
    }
    
    
    
    /* 
     * encrypt/decrypt a number to not be directly recognizable on frontend 
     * encrypting, returns a base64 string
     */
    public static function encrypt_number($number) {
        $clean_domain = str_replace(array('http://', 'https://', 'http://www.', 'https://www.', 'www.'), '', strtolower(site_url()));
        $clean_domain = substr(preg_replace("/[^A-Za-z0-9]/", '', $clean_domain), 0, 6);
        $mystery_num = (int)strrev(base_convert($clean_domain, 36, 10));
        
        return strrev(base64_encode((int)$number + $mystery_num));
    }
    public static function decrypt_number($string) {
        $clean_domain = str_replace(array('http://', 'https://', 'http://www.', 'https://www.', 'www.'), '', strtolower(site_url()));
        $clean_domain = substr(preg_replace("/[^A-Za-z0-9]/", '', $clean_domain), 0, 6);
        $mystery_num = (int)strrev(base_convert($clean_domain, 36, 10));
        
        return (int)base64_decode(strrev($string)) - $mystery_num;
    }
    
    
    
    /* 
     * compress/decompress a string (if the function is available)
     * encrypting, returns a base64 string
     */
    public static function compress_data($data) {
        $str = maybe_serialize($data);
		if(function_exists('gzcompress') && function_exists('gzuncompress')) {
			$str = gzcompress($str, 9);
		}
		
		return base64_encode($str);	
    }
    public static function decompress_data($data) {
        $string = base64_decode($data);
		if(function_exists('gzcompress') && function_exists('gzuncompress') && !empty($string)) {
			$string = gzuncompress($string);
		}
		
		return maybe_unserialize($string);
    }
    
    
    
    /* manage URL attrbutes (add/edit/remove)
     * 
     * @param (string) $action = add || edit || remove
     * @param (string|array) $param_name = string or array containing one or more parameters to affect
     * @param (mixed) $val = new parameter value (using parameters array, values must match to them). Ignore if using "remove"
     * @param (string) $url = URL to manage. Ignore to use current URL
     *
     * @return (string) resulting URL
     */
    public static function man_url_attr($action, $param_name, $val = false, $url = false) {
        if(!$url) {
            $url = self::curr_url();    
        }
        
        // sanitize
        if(!is_array($param_name)) {
            $param_name = array($param_name);    
        }
        if($action != 'delete' && is_array($param_name) && !is_array($val)) {
            if(count($param_name) === 1) {
                $val = array($val);    
            }
            else {
                // trigger_error( esc_html($param_name) .' and '. esc_html($val) .' arrays must have the same values number'); // debug
                return $url;
            }
        }
        
        
        // elaborate URL
        $raw_arr = explode('?', $url);

        $base = $raw_arr[0];
        $params = array();

        if(count($raw_arr) > 1) {
            $raw_params = explode('&', $raw_arr[1]);

            foreach($raw_params as $part) {
                $arr = explode('=', $part);

                if(count($arr) == 1) {
                    $params[ $arr[0] ]	= false;
                } else {
                    $params[ $arr[0] ]	= $arr[1];	
                }
            }
        }

        ####	

        if($action == 'add' || $action == 'edit') {
            
            $a = 0;
            foreach($param_name as $pn) {
                $params[ $pn ] = urlencode( $val[$a] );
                $a++;
            }
        }

        elseif($action == 'remove' && count($raw_arr) > 1) {
            
            foreach($param_name as $pn) {
                if(isset($params[ $pn ])) {
                    unset( $params[ $pn ] );	
                }
            }
        }

        ####

        if(count($params)) {
            $to_merge = array();

            foreach($params as $name => $pval) {
                $to_merge[] = ($pval !== false) ? $name.'='.$pval : $name; 	
            }

            return $base .'?'. implode('&', $to_merge);
        }
        else{
            return $base;
        }
    }
    
    
    
    // get WP pages list - id => title
    public static function get_pages() {
        $pages = array();

        foreach(get_pages() as $pag) {
            $pages[ $pag->ID ] = $pag->post_title;	
        }

        return $pages;	
    }
    
    
    
    // get lightbox instances - id => note
    public static function get_lb_instances() {
        $inst = array();

        // if isset global variable from settings saving
        if(isset($GLOBALS['pvtcont_lb_data'])) {
            foreach($GLOBALS['pvtcont_lb_data'] as $lb_id => $lb_data) {
                $inst[ $lb_id ] = $lb_data['note'];		
            }
        }
        else {
            if(isset($GLOBALS['pvtcont_cached_lb_list'])) {
                return $GLOBALS['pvtcont_cached_lb_list'];
            }
            
            pc_lightboxes_ct(); // be sure tax is registered
            $lb_instances = get_terms(array(
                'taxonomy'   => 'pc_lightboxes',
                'hide_empty' => 0,
                'order'      => 'ASC',
            ));
            
            if(!is_array($lb_instances)) {
                return $inst;   
            }
            
            foreach($lb_instances as $i) {
                $name = ($i->name == '|||pclbft|||') ? $i->term_id : $i->name;
                $inst[ $i->term_id ] = $name;	
            }
                
            $GLOBALS['pvtcont_cached_lb_list'] = $inst;
        }

        return $inst;	
    }
    
    
    
    // enqueue lightbox instance to be loaded in page's footer
    public static function enqueue_lb($lightbox_id) {
        if(!isset($GLOBALS['pvtcont_queued_lb'])) {
            $GLOBALS['pvtcont_queued_lb'] = array();
        }

        if(is_numeric($lightbox_id) && !in_array($lightbox_id, $GLOBALS['pvtcont_queued_lb'])) {
            $GLOBALS['pvtcont_queued_lb'][] = $lightbox_id;
            return true;		
        }

        return false;	
    }
    
    
    
    // get all the custom post types
    public static function get_cpt() {
        $args = array(
            'public'   => true,
            'publicly_queryable' => true,
            '_builtin' => false
        );
        
        $cpt_obj = get_post_types($args, 'objects');
        $cpt = array();
        
        foreach($cpt_obj as $id => $obj) {
            if($id == 'pg_user_page') {
                continue;    
            }
            $cpt[$id] = $obj->labels->name;	
        }
        
        if(is_plugin_active('media-grid/media-grid.php') || is_plugin_active('media-grid-bundle/mg_bundle.php')) {
            $cpt['mg_items'] = 'Media Grid Items';    
        }
        return $cpt;
    }
    
    
    
    
    // get all the custom taxonomies
    public static function get_ct() {
        $args = array(
            'public' => true,
            '_builtin' => false
        );
        
        $ct_obj = get_taxonomies($args, 'objects');
        $ct = array();
        
        foreach($ct_obj as $id => $obj) {
            $ct[$id] = $obj->labels->name;	
        }
        return $ct;	
    }
    
    
    
    // get affected post types
    public static function affected_pt() {
        if(isset($GLOBALS['pvtcont_affected_pt'])) {
            return $GLOBALS['pvtcont_affected_pt']; // cache	
        }

        $rpt = array('post','page');	
        
        /* NFPCF */
        $cpt = get_option('pg_extend_cpt'); 
        if(is_array($cpt)) {
            foreach($cpt as $pt) {
                if(is_admin() || post_type_exists($pt)) { // frontend check - be sure CPT are registered
                    $rpt[] = $pt;	
                }
            }
        }

        // PC-FILTER - allow manual CPT integration - passes already affected CPT slugs array
        $rpt = apply_filters('pvtcont_affected_pt', $rpt);

        $GLOBALS['pvtcont_affected_pt'] = $rpt;
        return $rpt;
    }
    
    
    
    // get affected  taxonomies
    public static function affected_tax() {
        if(isset($GLOBALS['pvtcont_affected_tax'])) {
            return $GLOBALS['pvtcont_affected_tax']; // cache	
        }

        $tax = array('category');	
        
        /* NFPCF */
        $cts = get_option('pg_extend_ct'); 
        if(is_array($cts)) {
            foreach($cts as $ct) {
                if(is_admin() || taxonomy_exists($ct)) { // frontend check - be sure taxonomies are registered
                    $tax[] = $ct;	
                }
            }
        }

        // PC-FILTER - allow manual taxonomies integration - passes already affected taxonomies slug array
        $tax = apply_filters('pvtcont_affected_tax', $tax);

        $GLOBALS['pvtcont_affected_tax'] = $tax;
        return $tax;
    }
    
    
    
    // associative array of user categories (id => name) 
    // $escape_no_reg = escape ones prevented from registration
    public static function user_cats($escape_no_reg = false) {
        $cache_name = ($escape_no_reg) ? 'pc_user_cats_nr_static' : 'pc_user_cats_static';
        
        if(isset($GLOBALS[$cache_name])) {
            return $GLOBALS[$cache_name];
        }
        
        $user_categories = get_terms(array(
            'taxonomy'   => 'pg_user_categories',
            'orderby'    => 'name',
            'hide_empty' => 0,
        ));	
        $cats = array();

        if (!is_wp_error($user_categories)) {
            foreach($user_categories as $ucat) {
                if($escape_no_reg && pc_static::retrocomp_get_term_meta($ucat->term_id, 'pg_ucat_no_registration', "pg_ucat_". $ucat->term_id ."_no_registration")) {
                    continue;
                }
                
                // WPML - Polylang compatibility
                if(function_exists('icl_t')){
                    $cat_name = icl_t('PrivateContent Categories', 'Category #'.$ucat->term_id, $ucat->name);
                }
                else if(function_exists('pll__')){
                    $cat_name = pll__($ucat->name);
                } else {
                    $cat_name = $ucat->name;
                }

                $cats[$ucat->term_id] = $cat_name;	
            }
        }
        
        $GLOBALS[$cache_name] = $cats;
        return $cats;
    }
    
    
    
    /* create restriction options array
     *
     * @param (bool) $bulk_opts - whether to add "all/unlogged" options or not
     * @param (bool) $apply_filter - whether to apply filter to add new options
     * @param (array) $remove - specific values to discard
     *
     * @return (array) associative array of subjects (eg. pc_cats) and having opts array as val
     */
    public static function restr_opts_arr($bulk_opts = true, $apply_filter = true, $remove = array()) {
        $opts = array();

        // pvtContent cats
        $opts['pc_cats'] = array(
            'name' => esc_html__('User Categories', 'pc_ml'),
            'opts' => array()
        );

        if($bulk_opts) {
            $opts['pc_cats']['opts'] = array(
                'all'       => esc_html__('Any logged user', 'pc_ml'),
                'unlogged'  => esc_html__('Unlogged Users', 'pc_ml'),
            );	
        }

        foreach(self::user_cats() as $ucat_id => $ucat_name) {
            $opts['pc_cats']['opts'][$ucat_id] = $ucat_name;	
        }

        // PC-FILTER - add custom values in user categories dropdown to manage restrictions - passes PC opts array and the ones to exclude - structure must comply
        if($apply_filter) {
            $opts = apply_filters('pc_user_cat_dd_opts', $opts, $remove);
        }

        // selective removal
        if(!empty($remove) && is_array($remove)) {
            foreach($opts as $subj => $data) {
                foreach($data['opts'] as $key => $name) {

                    if(in_array($key, $remove)) {
                        unset( $opts[$subj]['opts'][$key] );	
                    }
                }
            }
        }
        return $opts;	
    }
    
    
    
    /* Same as self::restr_opts_arr() but returns a simple (value=>label) array */
    public static function onlyvals_restr_opts_arr($bulk_opts = true, $apply_filter = true, $remove = array()) {
        $vals = array();
        
        foreach(self::restr_opts_arr($bulk_opts, $apply_filter, $remove) as $data) {
            $vals += $data['opts'];
        }
        return $vals;
    }
    
    
    
    /* get user categories dropdown options
     *
     * @param (string|array) $sel - selected value
     * @param (bool) $bulk_opts - whether to add "all/unlogged" options or not
     * @param (bool) $apply_filter - whether to apply filter to add new options
     * @param (array) $remove - specific values to discard
     */
    public static function user_cat_dd_opts($sel = false, $bulk_opts = true, $apply_filter = true, $remove = array()) {
        $opts = self::restr_opts_arr($bulk_opts, $apply_filter, $remove);

        //// setup code
        $code = '';
        foreach($opts as $data) {
            if($apply_filter && count($opts) > 1) {
                $code .= '<optgroup label="'. $data['name'] .'">';
            }

            foreach($data['opts'] as $opt_id => $opt_name) {
                switch($opt_id) {
                    case 'all' 		: $class = 'class="pc_all_field"'; break;
                    case 'unlogged' : $class = 'class="pc_unl_field"'; break;
                    default 		: $class = ''; break; 	
                }

                if(is_array($sel)) {
                    $sel_attr = (in_array($opt_id, $sel)) ? 'selected="selected"' : '';	
                } else {
                    $sel_attr = ($opt_id == $sel) ? 'selected="selected"' : '';	
                }

                $code .= '<option value="'. $opt_id .'" '.$sel_attr.' '.$class.'>'. $opt_name .'</option>';	
            }

            if($apply_filter && count($opts) > 1) {$code .= '</optgroup>';}
        }

        return $code;
    }
    
    
    
    // get term object knowing only its ID
    public static function term_obj_from_term_id($term_id) {
        global $wpdb;
        $tax = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.taxonomy FROM $wpdb->term_taxonomy AS t WHERE t.term_id = %s LIMIT 1", 
                $term_id
            )
        );

        return (!empty($tax)) ? get_term_by('id', $term_id, $tax) : false;
    }
    
    
    
    // align forms (since v6)
    public static function form_align($form_code, $align = 'center') {

        // exception for widget login form
        if(isset($GLOBALS['pvtcont_login_widget'])) {
            unset($GLOBALS['pvtcont_login_widget']);
            return $form_code;	
        }

        return '<div class="pc_aligned_form pc_falign_'. $align .'">'. $form_code .'</div>';	
    }
    
    
    
    // stripslashes for options inserted
    public static function strip_opts($fdata) {
        if(!is_array($fdata)) {
            return false;
        }

        foreach($fdata as $key=>$val) {
            if(!is_array($val)) {
                $fdata[$key] = stripslashes($val);
            }
            else {
                $fdata[$key] = array();
                foreach($val as $arr_val) {$fdata[$key][] = stripslashes($arr_val);}
            }
        }

        return $fdata;
    }
    
    
    
    // users list - pagination block
    public static function users_list_pag_block($curr_pag, $per_page, $tot_users) {
        global $pc_users;
        
        if(!$tot_users) {
            return '';    
        }
        
        $param_name = 'pagenum';
        $tot_pages = ceil((int)$tot_users / $per_page);
        
        if($tot_pages < 2) {
            return '';    
        }
        
        $prev_dis_class = ($curr_pag < 2) ? 'disabled' : '';
        $next_dis_class = ($curr_pag == $tot_pages) ? 'disabled' : ''; 
        
        $first_url  = (!$prev_dis_class) ? self::man_url_attr('remove', $param_name) : ''; 
        $prev_url   = (!$prev_dis_class) ? self::man_url_attr('edit', $param_name, ($curr_pag - 1)) : '';   
        $next_url   = (!$next_dis_class) ? self::man_url_attr('edit', $param_name, ($curr_pag + 1)) : ''; 
        $last_url   = (!$next_dis_class) ? self::man_url_attr('edit', $param_name, $tot_pages) : '';   

        $code = '
        <div class="tablenav-pages">
            <span class="pagination-links">';
        
                if($curr_pag < 2) {
                    $code .= '
                    <span class="tablenav-pages-navspan button disabled" title="'. esc_attr__('First page', 'pc_ml') .'">«</span>
                    <span class="tablenav-pages-navspan button disabled" title="'. esc_attr__('Previous page', 'pc_ml') .'">‹</span>';
                }
                else {
                    $code .= '
                    <a class="first-page button" href="'. esc_attr($first_url) .'" title="'. esc_attr__('First page', 'pc_ml') .'">
                        <span class="screen-reader-text">'. esc_html__('First page', 'pc_ml') .'</span>
                        <span aria-hidden="true">«</span>
                    </a>
                    <a class="prev-page button" href="'. esc_attr($prev_url) .'" title="'. esc_attr__('Previous page', 'pc_ml') .'">
                        <span class="screen-reader-text">'. esc_html__('Previous page', 'pc_ml') .'</span>
                        <span aria-hidden="true">‹</span>
                    </a>';
                }

                $code .= '
                <span class="paging-input">
                    <label class="screen-reader-text">'. esc_html__('Current Page', 'pc_ml') .'</label>
                    <input class="current-page pc_ulist_pagenum_input" type="text" name="pagenum" value="'. $curr_pag .'" size="1" data-tot-pag="'. $tot_pages .'" autocomplete="off" />
                    <span class="tablenav-paging-text"> '. esc_html__('of', 'pc_ml') .' <span class="total-pages">'. $tot_pages .'</span></span>
                </span>';

                if($curr_pag == $tot_pages) {
                    $code .= '
                    <span class="tablenav-pages-navspan button disabled" title="'. esc_attr__('Next page', 'pc_ml') .'">›</span>
                    <span class="tablenav-pages-navspan button disabled" title="'. esc_attr__('Last page', 'pc_ml') .'">»</span>';        
                }
                else {
                    $code .= '
                    <a class="next-page button" href="'. esc_attr($next_url) .'" title="'. esc_attr__('Next page', 'pc_ml') .'">
                        <span class="screen-reader-text">'. esc_html__('Next page', 'pc_ml') .'></span>
                        <span aria-hidden="true">›</span>
                    </a>
                    <a class="last-page button" href="'. esc_attr($last_url) .'" title="'. esc_attr__('Last page', 'pc_ml') .'">
                        <span class="screen-reader-text">'. esc_html__('Last page', 'pc_ml') .'></span>
                        <span aria-hidden="true">»</span>
                    </a>';
                }
        
        $code .= '
            </span>
        </div>';
        
        return $code;
    }
    
    
    
    // get default users list columns array
    public static function default_ulist_cols() {
        return array(
            'name' => array(
                'name' 		=> (get_option('pg_use_first_last_name')) ? esc_html__('First name', 'pc_ml') : esc_html__('Name', 'pc_ml'),
                'sortable' 	=> true,
                'is_date'   => false,
            ),
            'surname' => array(
                'name' 		=> (get_option('pg_use_first_last_name')) ? esc_html__('Last name', 'pc_ml') : esc_html__('Surname', 'pc_ml'),
                'sortable' 	=> true,
                'is_date'   => false,
            ),
            'email' => array(
                'name' 		=> esc_html__('E-mail', 'pc_ml'),
                'sortable' 	=> true,
                'is_date'   => false,
            ),
            'tel' => array(
                'name' 		=> esc_html__('Telephone', 'pc_ml'),
                'sortable' 	=> true,
                'width'		=> '120px',

                'is_date'   => false,
            ),
            'categories' => array(
                'name' 		=> esc_html__('Categories', 'pc_ml'),
                'sortable' 	=> false,
                'is_date'   => false,
            ),
            'insert_date' => array(
                'name' 		=> esc_html__('Registered', 'pc_ml'),
                'sortable' 	=> true,
                'width'		=> '152px',
                'is_date'   => true,
            ),
            'last_access' => array(
                'name' 		=> esc_html__('Last access', 'pc_ml'),
                'sortable' 	=> true,
                'width'		=> '110px',
                'is_date'   => true,
            )
        );    
    }
    
    
    
    /*
     * retrieve user-chosen users list columns
     * @param (bool) $get_all - whether to get all potential columns, even if not checked to be shown in users list 
     */
    public static function get_ulist_columns($get_all = false) {
        $columns = self::default_ulist_cols();

        // PC-FILTER - additional fields for users list - must comply with initial structure
        $columns = apply_filters('pc_users_list_table_fields', $columns);
        
        $user_choices = self::get_wp_user_ulist_columns();
        if(!is_array($user_choices) || empty($user_choices)) {
            return $columns;
        }
        
        
        // re-arrange basing on user preferences
        $arranged = array();
        foreach($user_choices as $uc) {
            
            if(!is_array($uc) || !isset($uc['id'])) {
                continue;    
            }
            
            // fix "name" index used to avoid interferences in pc_ulist_man_columns()
            $uc_id = ($uc['id'] == 'pc_name') ? 'name' : $uc['id'];

            if(!isset($columns[$uc_id])) {
                continue;    
            }
            
            if($uc['checked'] || $get_all) {
                $arranged[ $uc_id ] = $columns[ $uc_id ];
            }
            unset($columns[ $uc_id ]);
        }
        
        return array_merge($arranged, $columns);
    }
    
    
    
    // retrieve user preferences for field columns in users list, applying a filter
    public static function get_wp_user_ulist_columns() {
        $def_columns = self::default_ulist_cols();
        $def_columns = array('pc_name' => $def_columns['name']) + $def_columns;
        unset($def_columns['name']);
        
        $user_choices = get_user_meta(get_current_user_id(), 'pc_ulist_columns', true);

        // no choices - setup default scheme
        if(empty($user_choices)) {
            $user_choices = array();
            
            foreach(array_keys($def_columns) as $dc_slug) {
                $user_choices[] = array(
                    'id' => $dc_slug,
                    'checked' => 1,
                );
            }  
        }
        
        // PC-FILTER - additional fields for users list - must comply with initial structure
        return (array)apply_filters('pc_wp_user_ulist_columns', $user_choices);
    }
    
    
    
    // returns HTML code for user categories, in users list page
    public static function ulist_user_cats_td($data) {
        if(!is_array($data)) {
            $data = unserialize($data);
        }
        $terms = get_terms(array(
            'taxonomy'   => 'pg_user_categories',
            'include'    => $data,
            'orderby'    => 'none',
            'hide_empty' => false,
        ));
        
        // catch translations
        $all_translated = pc_static::user_cats();
        
        $code = '';
        foreach($terms as $t) {
            $name = (isset($all_translated[$t->term_id])) ? $all_translated[$t->term_id] : $t->name;
            $code .= '<span data-cat-id="'. $t->term_id .'">'. $name .'</span>';    
        }
        
        return $code;
    }
    
    
    
    // micro helper function to know if GET field exists
    public static function get_param_exists($param_name) {
        return (isset($_GET[$param_name]) && !empty($_GET[$param_name])) ? true : false;	
    }
    
    
    
    // WPML & Polylang integration - given a page ID, searches a translation. If not found, return original value
    public static function wpml_translated_pag_id($obj_id){

        // WPML
        if(function_exists('icl_object_id')) {
            $trans_val = icl_object_id($obj_id, 'page', true);
            if($trans_val && get_post_status($trans_val) == 'publish') {
                return $trans_val;
            }
        } 	

        // polylang
        if(function_exists('pll_get_post')) {
            $trans_val = pll_get_post($obj_id);
            if($trans_val && get_post_status($trans_val) == 'publish') {
                return $trans_val;
            }
        } 	

        return $obj_id;
    }
    
    
    
    // hex color to RGBA
    public static function hex2rgba($hex, $alpha) {
        // if is RGB or transparent - return it
        $three_chars_pattern = '/^#[a-f0-9]{3}$/i';
        $six_chars_pattern = '/^#[a-f0-9]{6}$/i';
        
        if(empty($hex) || $hex == 'transparent' || (!preg_match($three_chars_pattern, $hex) && !preg_match($six_chars_pattern, $hex))) {
            return $hex;
        }

        $hex = str_replace("#", "", $hex);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        $rgb = array($r, $g, $b);
        $rgb = 'rgb('. implode(",", $rgb) .')';

        $rgba = str_replace(array('rgb', ')'), array('rgba', ', '.$alpha.')'), $rgb);
        return $rgba;	
    }
    
     

    // given a settings option name, echoes (or returns it) getting the default value from defined settings
    public static function get_opt_for_css($opt_name, $echo = true, $recursive_array = false) {
        if(isset($GLOBALS['pvtcont_get_opt_for_css_cache']) && isset($GLOBALS['pvtcont_get_opt_for_css_cache'][$opt_name])) {
            $val = $GLOBALS['pvtcont_get_opt_for_css_cache'][$opt_name];   
        }
        else {
            if(!isset($GLOBALS['pvtcont_get_opt_for_css_cache'])) {
                $GLOBALS['pvtcont_get_opt_for_css_cache'] = array();        
            }
            
            if($recursive_array) {
                $array = $recursive_array;    
            }
            else {
                include_once(PC_DIR .'/settings/structure.php');
                global $pc_settings_structure;
                $array = $pc_settings_structure;
            }


            // search
            $result = false;

            if(isset($array[$opt_name])) {
                $result = $array[$opt_name];    
            }
            else {
                foreach($array as $key => $val) {
                    if(is_array($val) && !isset($val['type'])) { // skip fields definition arrays
                        $result = self::get_opt_for_css($opt_name, $echo, $val);  

                        if($result) {
                            break;    
                        }
                    }
                }
            }

            // fallback
            if(!$result) {
                $result = array('def' => '');
            }

            $def = (isset($result['def'])) ? $result['def'] : false; 
            $val = get_option($opt_name, $def);
            
            $GLOBALS['pvtcont_get_opt_for_css_cache'][$opt_name] = $val;
        }
        
        if($echo && !$recursive_array) {
            echo esc_attr($val);    
        } else {
            return $val;
        }
    }
    
    
    
    /* 
     * Create dynamic frontend CSS
     * @param (bool) $skip_if_file_exists - true to not act if customm CSS file already exists
     */
    public static function create_custom_style($skip_if_file_exists = false) {	
        global $wp_filesystem;
        $filepath = PC_DIR.'/css/custom.css';
        
        if(empty($wp_filesystem)) {
            require_once (ABSPATH .'/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        
        $versioning_key = 'pc_dynamic_css_versioning';
        $versioning = array(
            'pc' => PC_VERS
        );
        $versioning = apply_filters($versioning_key, $versioning);
        ksort($versioning);
        
        
        if($skip_if_file_exists && $wp_filesystem->exists($filepath) && md5(json_encode($versioning)) == get_option($versioning_key)) {
            return true;   
        }
        
        $css = self::custom_css_less_parser();
        if(trim($css)) {
            
            $versioning_pre = array();
            foreach($versioning as $subj => $ver) {
                $versioning_pre[] = $subj .' > '.$ver;   
            }
            $versioning_pre = '/* '. implode(' | ', $versioning_pre) .' */
';
            
            if(!$wp_filesystem->put_contents($filepath, $versioning_pre.$css)) {
                update_option('pg_inline_css', 1, false);	
                $error = true;
            }
            else {
                update_option($versioning_key, md5(json_encode($versioning)), false);
                update_option('pc_dynamic_scripts_id', md5($css));	
            }
        }
        else {
            if($wp_filesystem->exists($filepath))	{
                wp_delete_file($filepath);
            }
        }

        return (isset($error)) ? false : true;
    }
    
    
    
    /* Be sure... well read the function name */
    public static function be_sure_dynamic_css_exists() {
        if(!get_option('pg_inline_css')) {
            self::create_custom_style(true);
        }
    }
    
    
    
    /* For whatever reason WP returns every value as a single index array using get_term_meta() or get_post_meta() to fetch every meta - fix it */
    public static function fix_wp_get_all_meta($data, $prepare_for_json = false) {
        $fixed = array();
        foreach($data as $key => $val) {
            $fixed[$key] = (is_array($val) && count($val) === 1 && isset($val[0])) ? $val[0] : $val;
            if($prepare_for_json) {
                $fixed[$key] = self::stringify_for_json($fixed[$key]);
            }
        }
        return $fixed;
    }
    
    
    
    /* passing whatever data, returns a string, resdy to be executed by json_encode() */
    public static function stringify_for_json($data) {
        if(empty($data) && !is_array($data)) {
            return $data;
        }
        
        $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
        $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
        
         // do not double serialize
        if(!is_serialized($data)) {
            $data = maybe_serialize($data);
        }
        return str_replace($escapers, $replacements, $data);
    }
    
    
    
    // addons list database
    public static function addons_db($addon = false) {
        $addons = array(
            'pcud' => array(
                'name' => 'User Data',
                'descr'	=> 'User Data add-on boosts PrivateContent plugin allowing you to create and use unlimited fields to record more informations from your users',
                'link'	=> 'https://charon.lcweb.it/bff73641?ref=pc_addons_adv',
                'path'	=> 'private-content-user-data/pc_user_data.php'
            ),

            'pcfm' => array(
                'name' 	=> 'Files Manager',
                'descr'	=> 'Unlimited upload fields and restricted files for PrivateContent users. Google Analytics tracking and six different file view layouts to use',
                'link'	=> 'https://charon.lcweb.it/46223884?ref=pc_addons_adv',
                'path'	=> 'private-content-files-manager/pc_files_manager.php'
            ),

            'pcpp' => array(
                'name' 	=> 'Premium Plans',
                'descr'	=> 'Turn PrivateContent into a true premium membership platform. The add-on takes advantage of WooCommerce systems to handle payments and set subscription time limits',
                'link'	=> 'https://charon.lcweb.it/7937f589?ref=pc_addons_adv',
                'path'	=> 'private-content-premium-plans/pc_premium_plans.php'
            ),

            'pcma' => array(
                'name' => 'Mail Actions',
                'descr'	=> 'Manages PrivateContent e-mail operations: e-mail address validation, password retrieval, MailPoet + Mailchimp sync and much more. Finally a true e-mail marketing campaign engine to run your newsletters!',
                'link'	=> 'https://charon.lcweb.it/b1f9f472?ref=pc_addons_adv',
                'path'	=> 'private-content-mail-actions/pc_mail_actions.php'
            ),	
            
            'pcua' => array(
                'name' => 'User Activities',
                'descr'	=> 'A powerful and easy-to-use solution to track PrivateContent users interaction on your website. Includes also a scheduled e-mail report and PDF export engine!',
                'link'	=> 'https://charon.lcweb.it/f2ce52ae?ref=pc_addons_adv',
                'path'	=> 'private-content-user-activities/pc_user_activities.php'
            ),
        );	
        return (!$addon || !isset($addons[$addon])) ? $addons : $addons[$addon]; 	
    }
    
    
    
    // returns an array of add-ons not enabled yet 
    public static function addons_not_installed() {
        $found = array();

        foreach(self::addons_db() as $id => $data) {
            if(!is_plugin_active( $data['path'] )) {
                $found[] = $id;	
            }
        }

        return $found;
    }
    
    
    
    // retrieve term meta value considering the old WP option storing system - automatically moves data to the new storing system
    public static function retrocomp_get_term_meta($term_id, $meta_key, $old_key, $default_val = false) {
        $val = get_term_meta($term_id, $meta_key, true);
       
        if($val === false) {
            $val = get_option($old_key, $default_val);
            delete_option($old_key);
            update_term_meta($term_id, $meta_key, $val); 
        }
        
        return $val;
    }
    

    
    // FontAwesome v4 class retrocompatibility
    public static function fontawesome_v4_retrocomp($class) {
        if(!empty($class) && strpos($class, ' ') === false) {
            $class = 'fas '. $class;    
        }
        
        return esc_attr($class);
    }
    
    
    
    // font-awesome icon picker - hidden lightbox code
    public static function fa_icon_picker_code($no_icon_text, $form_wrap = false) {
        include_once(PC_DIR .'/classes/lc_fontAwesome_helper.php');

        try{
            return '
            <div id="pc_icons_list" class="pc_displaynone">
                '. lc_fontawesome_helper::html_list(array(
                    'extra_class'   => 'pc_lb_icon_picker',
                    'form_wrap'     => $form_wrap,

                    'labels'        => array(
                        '🔍 '. esc_html__('Search icons ..', 'pc_ml'), 
                        esc_html__('All categories', 'pc_ml'), 
                        esc_html__('Solid', 'pc_ml'), 
                        esc_html__('Regular', 'pc_ml'), 
                        esc_html__('Brands', 'pc_ml'),
                        esc_html__('no icon', 'pc_ml'),
                        esc_html__('.. no icons found ..', 'pc_ml'),
                    )
                )) .'
            </div>';
        }
        catch(Exception $e) {
            echo 'fa_icon_picker_code ERROR - '. wp_json_encode($e);    
        }
    }


    
    // font-awesome icon picker - javascript code - direct print
    public static function fa_icon_picker_js($selector) {
        wp_enqueue_script('thickbox');
        wp_enqueue_style('thickbox');
        $prefix = 'pc';
        
        $code = '
        let $sel_type_opt = false;
            
        // launch lightbox
        $(document).on(`click`, `.'. esc_js($selector) .' i`, function() {
            $sel_type_opt = $(this);

            let sel_val = $sel_type_opt.attr(`class`).trim();
            if(sel_val) {
                sel_val = `.`+ sel_val.replace(` `, `.`);    
            }


            tb_show("'. esc_html__('Icons picker', 'pc_ml') .'", `#TB_inline?inlineId='. esc_js($prefix) .'_icons_list`);
            setTimeout(function() {
                $(`#TB_window`).addClass(`'. esc_js($prefix) .'_icon_picker_lb`)
                $(`input[name="lcfah-search"]`).val(``);

                // reset search
                $(`select[name="lcfah-style"] option`).removeAttr(`selected`);
                $(`select[name="lcfah-style"]`).each(function() {
                    const event = new Event(`change`);
                    this.dispatchEvent(event);
                });


                // show selected value
                const $sel_obj = (sel_val) ? $(`.'. esc_js($prefix) .'_icon_picker_lb `+sel_val).parent() : $(`.lcfah-no-icon`); 

                $(`.'. esc_js($prefix) .'_icon_picker_lb .'. esc_js($prefix) .'_lb_icon_selected`).removeClass(`'. esc_js($prefix) .'_lb_icon_selected`);
                $sel_obj.addClass(`'. esc_js($prefix) .'_lb_icon_selected`);
            }, 10);
        });

        // select icon
        $(document).on("click", ".'. esc_js($prefix) .'_icon_picker_lb .lcfah-list li:not(.lcfah-no-results)", function() {
            const val = ($(this).hasClass(`lcfah-no-icon`)) ? `` : $(this).find(`i`).attr(`class`);

            $sel_type_opt.parent().find(`input`).val(val);
            $sel_type_opt.attr(`class`, val);

            tb_remove();
            $sel_type_opt = false;
        });';
        
        return $code;
    }
    
    
    
    /* LESS-like CSS prefixer */
    public static function getPrefixedCss($css,$prefix) {
        # Wipe all block comments
        $css = preg_replace('!/\*.*?\*/!s', '', $css);

        $parts = explode('}', $css);
        $keyframeStarted = false;
        $mediaQueryStarted = false;

        foreach($parts as &$part) {
            $part = trim($part); # Wht not trim immediately .. ?
            if(empty($part)) {
                $keyframeStarted = false;
                continue;
            }
            else { # This else is also required
                $partDetails = explode('{', $part);

                if (strpos($part, 'keyframes') !== false) {
                    $keyframeStarted = true;
                    continue;
                }

                if($keyframeStarted) {
                    continue;
                }

                if(substr_count($part, "{")==2) {
                    $mediaQuery = $partDetails[0]."{";
                    $partDetails[0] = $partDetails[1];
                    $mediaQueryStarted = true;
                }

                $subParts = explode(',', $partDetails[0]);
                foreach($subParts as &$subPart) {
                    if(trim($subPart)==="@font-face") continue;
                    else $subPart = $prefix . ' ' . trim($subPart);
                }

                if(substr_count($part,"{")==2) {
                    $part = $mediaQuery."\n".implode(', ', $subParts)."{".$partDetails[2];
                }
                elseif(empty($part[0]) && $mediaQueryStarted) {
                    $mediaQueryStarted = false;
                    $part = implode(', ', $subParts)."{".$partDetails[2]."}\n"; //finish media query
                }
                else {
                    if(isset($partDetails[1]))
                    {   # Sometimes, without this check,
                        # there is an error-notice, we don't need that..
                        $part = implode(', ', $subParts)."{".$partDetails[1];
                    }
                }

                unset($partDetails, $mediaQuery, $subParts); # Kill those three ..
            }   unset($part); # Kill this one as well
        }

        # Finish with the whole new prefixed string/file in one line
        return(preg_replace('/\s+/',' ',implode("} ", $parts)));
    }



    // handles custom CSS written in LESS and returns a CSS string
    public static function custom_css_less_parser() {
        ob_start();
        include_once(PC_DIR .'/main_includes/custom_style.php');

        $css = ob_get_clean();
        if(!trim($css)) { 
            return '';    
        }

        // Divi fix
        if(class_exists('ET_Builder_Module')) {
            $css .= self::getPrefixedCss($css, '#et-boc .et-l');
        }

        return $css;
    }
    
    
}
