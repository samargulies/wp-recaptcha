<?php

// just making sure the constant is defined
if (!defined('WP_CONTENT_DIR'))
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
 

if (!class_exists('Environment')) {
    class Environment {
        const WordPress = 1; // regular wordpress
        const WordPressMU = 2; // wordpress mu
        const WordPressMS = 3; // wordpress multi-site
    }
}

if (!class_exists('WPPlugin')) {
    abstract class WPPlugin {
        protected $environment; // what environment are we in
        protected $options_name; // the name of the options associated with this plugin
        
        protected $options;
        
        function WPPlugin($options_name) {
            $args = func_get_args();
            call_user_func_array(array(&$this, "__construct"), $args);
        }
        
        function __construct($options_name) {
            $this->environment = WPPlugin::determine_environment();
            $this->options_name = $options_name;
            
            $this->options = WPPlugin::retrieve_options($this->options_name);
        }
        
        // sub-classes determine what actions and filters to hook
        abstract protected function register_actions();
        abstract protected function register_filters();
        
        // environment checking
        static function determine_environment() {
            global $wpmu_version;
			
			/*
			
			Disable network-enabled environment to make every install site-by-site.
			In the future it may be possible to configure all settings network-wide and
			disable site-by-site configuration. Until then, we will only declare multisite.
            
            if ( is_plugin_active_for_network('wp-recaptcha/wp-recaptcha.php') )
            	return Environment::WordPressMS;
            */
            
            if ( is_multisite() )
                return Environment::WordPressMU;
                
            return Environment::WordPress;
        }
        
        static function path_to_plugin_directory() {
            return plugin_dir_path(__FILE__);
        }
        
        static function url_to_plugin_directory() {           
           return plugin_dir_url(__FILE__);
        }
        
        static function path_to_plugin( $file_path ) {
            return plugin_dir_path( basename($file_path) );
        }
        
        // options
        abstract protected function register_default_options();
        
        // option retrieval
        static function retrieve_options($options_name) {
            if( WPPlugin::determine_environment() == Environment::WordPressMS )
                return get_site_option($options_name);
            else
                return get_option($options_name);
        }
        
        static function remove_options($options_name) {
            if( WPPlugin::determine_environment() == Environment::WordPressMS )
                return delete_site_option($options_name);
            else
                return delete_option($options_name);
        }
        
        static function add_options($options_name, $options) {
            if( WPPlugin::determine_environment() == Environment::WordPressMS )
                return update_site_option($options_name, $options);
            else
                return update_option($options_name, $options);
        }
        
        protected function is_multi_blog() {
            return $this->environment != Environment::WordPress;
        }
        
        // calls the appropriate 'authority' checking function depending on the environment
        protected function is_authority() {
        
            if ($this->environment == Environment::WordPressMS) {
                return is_super_admin();
            } else {
            	return current_user_can('manage_options');
            }
        }
    }
}

?>
