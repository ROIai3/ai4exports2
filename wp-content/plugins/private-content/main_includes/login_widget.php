<?php
// LOGIN WIDGET
if(!defined('ABSPATH')) {exit;}


class pvtcont_login_widget extends WP_Widget {
	
	function __construct() {
		$widget_ops = array('classname' => 'PrivateContentLogin', 'description' => esc_html__('Displays a login form for PrivateContent users', 'pc_ml'));
		parent::__construct('PrivateContentLogin', esc_html__('PrivateContent Login', 'pc_ml'), $widget_ops);
	}
   
   
	function form($instance) {
		$instance = wp_parse_args( (array)$instance);
		$title = (isset($instance['title'])) ? $instance['title'] : '';
		?>
		  <p>
			<label><?php esc_html_e('Title', 'pc_ml') ?>:</label> <br/>
			<input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')) ?>" name="<?php echo esc_attr($this->get_field_name('title')) ?>" type="text" value="<?php echo esc_attr($title) ?>" />
		  </p>
		<?php
	}
	
   
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		return $instance;
	}
	
   
	function widget($args, $instance) {
		global $wpdb;
		extract($args, EXTR_SKIP);
	 
		echo wp_kses_post($before_widget);
		$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
	 
        
        // WP 5.8 - legacy widget preview fix
        if(strpos(pc_static::curr_url(), 'widgets.php?') !== false && isset($_GET['legacy-widget-preview']) && !get_option('pg_disable_front_css')) {
            wp_enqueue_script('pc_frontend', PC_URL . '/js/frontend.min.js', 999, PC_VERS, true);	
            
            $style = get_option('pg_style', 'minimal');
            wp_enqueue_style('pc_frontend', PC_URL .'/css/frontend.min.css', 998, PC_VERS);

            pvtcont_inline_css();
        }
        
        
		if(!empty($title)) {
		  echo pc_static::wp_kses_ext($before_title . $title . $after_title);
			
			// switch if is logged or not
			$logged_user = pc_user_logged(array('username', 'name', 'surname'));
			
			if($logged_user) :
			?>
				<p><?php esc_html_e('Welcome', 'pc_ml') ?> <?php echo (empty($logged_user['name']) && empty($logged_user['surname'])) ? esc_html($logged_user['username']) : esc_html(ucfirst($logged_user['name']).' '.ucfirst($logged_user['surname'])); ?></p>
				
                <?php echo pc_static::wp_kses_ext(pc_logout_btn()) ?>
			<?php 
			else :
				
				$GLOBALS['pvtcont_login_widget'] = true;
				echo pc_static::wp_kses_ext(pc_login_form());
			endif;
		}
        
		echo pc_static::wp_kses_ext($after_widget);
	}
}


function pc_register_logform_widget() {
	register_widget('pvtcont_login_widget');
}
add_action('widgets_init', 'pc_register_logform_widget');

