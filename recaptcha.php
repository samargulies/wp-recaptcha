<?php

require_once('wp-plugin.php');

if (!class_exists('reCAPTCHA')) {
    class reCAPTCHA extends WPPlugin {
        // member variables
        private $saved_error;
        
        // php 4 constructor
        function reCAPTCHA($options_name) {
            $args = func_get_args();
            call_user_func_array(array(&$this, "__construct"), $args);
        }
        
        // php 5 constructor
        function __construct($options_name) {
        
        	if ( ! function_exists( 'is_plugin_active_for_network' ) )
			   require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

            parent::__construct($options_name);
                        
            // require the recaptcha library
            $this->require_library();
            
            // register the hooks
            $this->register_actions();
            $this->register_filters();
        }
        
        function register_actions() {
            // load the plugin's textdomain for localization
            add_action('init', array(&$this, 'load_textdomain'));

            // styling
            add_action('wp_head', array(&$this, 'register_stylesheets'));
                        
            if ($this->options['show_in_registration'])
                add_action('login_head', array(&$this, 'registration_style'));
                
            // options            
            add_action('admin_init', array(&$this, 'register_default_options'));
            add_action('admin_init', array(&$this, 'register_settings_group'));

            // only register the hooks if the user wants recaptcha on the registration page
            if ($this->options['show_in_registration']) {
                // recaptcha form display
                if ($this->is_multi_blog())
                    add_action('signup_extra_fields', array(&$this, 'show_recaptcha_in_registration'));
                else
                    add_action('register_form', array(&$this, 'show_recaptcha_in_registration'));
            }

            // only register the hooks if the user wants recaptcha on the comments page
            if ($this->options['show_in_comments']) {
                add_filter('comment_form_field_comment', array(&$this, 'show_recaptcha_in_comments'));
                
                add_action('preprocess_comment', array(&$this, 'check_comment'), 0);

                // note any errors and save comment text in url if there was failed captcha response
                add_action('comment_post_redirect', array(&$this, 'relative_redirect'), 0, 2);
            }

            // administration (menus, pages, notifications, etc.)
            add_filter("plugin_action_links", array(&$this, 'show_settings_link'), 10, 2);

            add_action('admin_menu', array(&$this, 'add_settings_page'));
            //add_action('network_admin_menu', array(&$this, 'add_settings_page'));
            
            // admin notices
            add_action('admin_notices', array(&$this, 'missing_keys_notice'));
        }
        
        function register_filters() {
            // only register the hooks if the user wants recaptcha on the registration page
            if ($this->options['show_in_registration']) {
                // recaptcha validation
                if ($this->is_multi_blog())
                    add_filter('wpmu_validate_user_signup', array(&$this, 'validate_recaptcha_response_wpmu'));
                else
                    add_filter('registration_errors', array(&$this, 'validate_recaptcha_response'));
            }
        }
        
        function load_textdomain() {
            load_plugin_textdomain('recaptcha', false, 'languages');
        }
        
        // set the default options
        function register_default_options() {
            if ($this->options)
               return;
           
            $option_defaults = array();
           
            $old_options = WPPlugin::retrieve_options("recaptcha");
           
            if ($old_options) {
               $option_defaults['public_key'] = $old_options['pubkey']; // the public key for reCAPTCHA
               $option_defaults['private_key'] = $old_options['privkey']; // the private key for reCAPTCHA

               // placement
               $option_defaults['show_in_comments'] = $old_options['re_comments']; // whether or not to show reCAPTCHA on the comment post
               $option_defaults['show_in_registration'] = $old_options['re_registration']; // whether or not to show reCAPTCHA on the registration page

               // bypass levels
               $option_defaults['bypass_for_registered_users'] = ($old_options['re_bypass'] == "on") ? 1 : 0; // whether to skip reCAPTCHAs for registered users
               $option_defaults['minimum_bypass_level'] = $old_options['re_bypasslevel']; // who doesn't have to do the reCAPTCHA (should be a valid WordPress capability slug)

               if ($option_defaults['minimum_bypass_level'] == "level_10") {
                  $option_defaults['minimum_bypass_level'] = "activate_plugins";
               }

               // styling
               $option_defaults['comments_theme'] = $old_options['re_theme']; // the default theme for reCAPTCHA on the comment post
               $option_defaults['registration_theme'] = $old_options['re_theme_reg']; // the default theme for reCAPTCHA on the registration form
               $option_defaults['recaptcha_language'] = $old_options['re_lang']; // the default language for reCAPTCHA
               $option_defaults['xhtml_compliance'] = $old_options['re_xhtml']; // whether or not to be XHTML 1.0 Strict compliant
               $option_defaults['registration_tab_index'] = 30; // the default tabindex for reCAPTCHA

               // error handling
               $option_defaults['no_response_error'] = $old_options['error_blank']; // message for no CAPTCHA response
               $option_defaults['incorrect_response_error'] = $old_options['error_incorrect']; // message for incorrect CAPTCHA response
            }
           
            else {
               // keys
               $option_defaults['public_key'] = ''; // the public key for reCAPTCHA
               $option_defaults['private_key'] = ''; // the private key for reCAPTCHA

               // placement
               $option_defaults['show_in_comments'] = 1; // whether or not to show reCAPTCHA on the comment post
               $option_defaults['show_in_registration'] = 1; // whether or not to show reCAPTCHA on the registration page

               // bypass levels
               $option_defaults['bypass_for_registered_users'] = 1; // whether to skip reCAPTCHAs for registered users
               $option_defaults['minimum_bypass_level'] = 'read'; // who doesn't have to do the reCAPTCHA (should be a valid WordPress capability slug)

               // styling
               $option_defaults['comments_theme'] = 'red'; // the default theme for reCAPTCHA on the comment post
               $option_defaults['registration_theme'] = 'red'; // the default theme for reCAPTCHA on the registration form
               $option_defaults['recaptcha_language'] = 'en'; // the default language for reCAPTCHA
               $option_defaults['xhtml_compliance'] = 0; // whether or not to be XHTML 1.0 Strict compliant
               $option_defaults['registration_tab_index'] = 30; // the default tabindex for reCAPTCHA

               // error handling
               $option_defaults['no_response_error'] = '<strong>ERROR</strong>: Please fill in the reCAPTCHA form.'; // message for no CAPTCHA response
               $option_defaults['incorrect_response_error'] = '<strong>ERROR</strong>: That reCAPTCHA response was incorrect.'; // message for incorrect CAPTCHA response
            }
            
            // add the option based on what environment we're in
            WPPlugin::add_options($this->options_name, $option_defaults);
        }
        
        // require the recaptcha library
        function require_library() {
            require_once($this->path_to_plugin_directory() . '/recaptchalib.php');
        }
        
        // register the settings
        function register_settings_group() {
            register_setting("recaptcha_options_group", $this->options_name, array(&$this, 'validate_options'));
        }
        
        // todo: make unnecessary
        function register_stylesheets() {
        
        	if( ! comments_open() ) {
        		return;
        	}
        
            echo "<style type='text/css'>
             .recaptcha-error {
  			 	color: red;
			}
			#recaptcha_area #recaptcha_response_field {
				width: auto;
				height: auto;
				border: 1px solid gray;
				padding: 0;
				text-indent: 0;
				box-shadow: none;
				-webkit-border-radius: 0;
				-moz-border-radius: 0;
				border-radius: 0;
				-webkit-box-shadow: none;
				-moz-box-shadow: none;
				box-shadow: none;
				line-height: inherit;
			}
			#recaptcha_area .recaptcha_theme_red #recaptcha_response_field {
				border: 1px solid #CCA940;
			}
			#recaptcha_area label {
				line-height: inherit;
			}
			#recaptcha_table{
				direction: ltr;
            }
            </style>"; 
        }
        
        // stylesheet information
        // todo: this 'hack' isn't nice, try to figure out a workaround
        function registration_style() {
            $width = 0; // the width of the recaptcha form

            // every theme is 358 pixels wide except for the clean theme, so we have to programmatically handle that
            if ($this->options['registration_theme'] == 'clean')
                $width = 485;
            else
                $width = 360;

            echo <<<REGISTRATION
                <style type='text/css'>
                #login { width: {$width}px; }
                #reg_passmail{ margin-top: 10px; }
                #recaptcha_widget_div{ margin-bottom:10px; }
                </style>
REGISTRATION;
        }
        
        function recaptcha_enabled() {
            return ($this->options['show_in_comments'] || $this->options['show_in_registration']);
        }
        
        function keys_missing() {
            return (empty($this->options['public_key']) || empty($this->options['private_key']));
        }
        
        function create_error_notice($message, $anchor = '') {
            $options_url = admin_url('options-general.php?page=wp-recaptcha/recaptcha.php') . $anchor;
            $error_message = sprintf(__($message . ' <a href="%s" title="WP-reCAPTCHA Options">Fix this</a>', 'recaptcha'), $options_url);
            
            echo '<div class="error"><p><strong>' . $error_message . '</strong></p></div>';
        }
        
        function missing_keys_notice() {
            if ($this->recaptcha_enabled() && $this->keys_missing()) {
                $this->create_error_notice('You enabled reCAPTCHA, but some of the reCAPTCHA API Keys seem to be missing.');
            }
        }
        
        function validate_dropdown($array, $key, $value) {
            // make sure that the capability that was supplied is a valid capability from the drop-down list
            if (in_array($value, $array))
                return $value;
            else // if not, load the old value
                return $this->options[$key];
        }
        
        function validate_options($input) {
            // todo: make sure that 'incorrect_response_error' is not empty, prevent from being empty in the validation phase
            
            // trim the spaces out of the key, as they are usually present when copied and pasted
            // todo: keys seem to usually be 40 characters in length, verify and if confirmed, add to validation process
            $validated['public_key'] = trim($input['public_key']);
            $validated['private_key'] = trim($input['private_key']);
            
            $validated['show_in_comments'] = ($input['show_in_comments'] == 1 ? 1 : 0);
            $validated['bypass_for_registered_users'] = ($input['bypass_for_registered_users'] == 1 ? 1: 0);
            
            $capabilities = array ('read', 'edit_posts', 'publish_posts', 'moderate_comments', 'activate_plugins');
            $themes = array ('red', 'white', 'blackglass', 'clean');
            
            $recaptcha_languages = array ('en', 'nl', 'fr', 'de', 'pt', 'ru', 'es', 'tr');
            
            $validated['minimum_bypass_level'] = $this->validate_dropdown($capabilities, 'minimum_bypass_level', $input['minimum_bypass_level']);
            $validated['comments_theme'] = $this->validate_dropdown($themes, 'comments_theme', $input['comments_theme']);
            
            
            $validated['show_in_registration'] = ($input['show_in_registration'] == 1 ? 1 : 0);
            $validated['registration_theme'] = $this->validate_dropdown($themes, 'registration_theme', $input['registration_theme']);
            $validated['registration_tab_index'] = $input['registration_tab_index'] ? $input["registration_tab_index"] : 30; // use the intval filter
            
            $validated['recaptcha_language'] = $this->validate_dropdown($recaptcha_languages, 'recaptcha_language', $input['recaptcha_language']);
            $validated['xhtml_compliance'] = ($input['xhtml_compliance'] == 1 ? 1 : 0);
            
            $validated['no_response_error'] = $input['no_response_error'];
            $validated['incorrect_response_error'] = $input['incorrect_response_error'];
            
            return $validated;
        }
        
        // display recaptcha
        function show_recaptcha_in_registration($errors) {
        
        	$rerror = esc_html( $_GET['rerror'] );
        
            $format = <<<FORMAT
            <script type='text/javascript'>
            var RecaptchaOptions = { 
            	theme : '{$this->options['registration_theme']}', 
            	lang : '{$this->options['recaptcha_language']} 
            };
            </script>
FORMAT;

            // if it's for wordpress mu, show the errors
            if ($this->is_multi_blog()) {
                $error = $errors->get_error_message('captcha');
                echo '<label for="verification">Verification:</label>';
                echo ($error ? '<p class="error">'.$error.'</p>' : '');
                echo $format . $this->get_recaptcha_html($rerror, is_ssl());
            }
            
            // for regular wordpress
            else {
                echo $format . $this->get_recaptcha_html($rerror, is_ssl());
            }
        }
        
        function validate_recaptcha_response($errors) {
            // empty so throw the empty response error
            if (empty($_POST['recaptcha_response_field']) || $_POST['recaptcha_response_field'] == '') {
                $errors->add('blank_captcha', $this->options['no_response_error']);
                return $errors;
            }

            $response = recaptcha_check_answer($this->options['private_key'], $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);

            // response is bad, add incorrect response error
            if (!$response->is_valid)
                if ($response->error == 'incorrect-captcha-sol')
                    $errors->add('captcha_wrong', $this->options['incorrect_response_error']);

           return $errors;
        }
        
        function validate_recaptcha_response_wpmu($result) {
            // must make a check here, otherwise the wp-admin/user-new.php script will keep trying to call
            // this function despite not having called do_action('signup_extra_fields'), so the recaptcha
            // field was never shown. this way it won't validate if it's called in the admin interface
            
            if (!$this->is_authority()) {
                // blogname in 2.6, blog_id prior to that
                // todo: why is this done?
                if (isset($_POST['blog_id']) || isset($_POST['blogname']))
                    return $result;
                    
                // no text entered
                if (empty($_POST['recaptcha_response_field']) || $_POST['recaptcha_response_field'] == '') {
                    $result['errors']->add('blank_captcha', $this->options['no_response_error']);
                    return $result;
                }
                
                $response = recaptcha_check_answer($this->options['private_key'], $_SERVER['REMOTEADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);
                
                // response is bad, add incorrect response error
                // todo: why echo the error here? wpmu specific?
                if (!$response->is_valid)
                    if ($response->error == 'incorrect-captcha-sol') {
                        $result['errors']->add('captcha_wrong', $this->options['incorrect_response_error']);
                        echo '<div class="error">' . $this->options['incorrect_response_error'] . '</div>';
                    }
                    
                return $result;
            }
        }
        
        // utility methods
        function hash_comment($id) {  
            return wp_hash($this->options['private_key'] . $id);
          }
        
        function get_recaptcha_html($recaptcha_error, $use_ssl=false) {
            return recaptcha_get_html($this->options['public_key'], $recaptcha_error, $use_ssl, $this->options['xhtml_compliance']);
        }
        
        function show_recaptcha_in_comments( $comment_field ) {
            global $user_ID;
            
			$rerror = isset($_GET['rerror']) ? esc_html( $_GET['rerror'] ) : null;
			
            // set the minimum capability needed to skip the captcha if there is one
            if (isset($this->options['bypass_for_registered_users']) && $this->options['bypass_for_registered_users'] && $this->options['minimum_bypass_level'])
                $needed_capability = $this->options['minimum_bypass_level'];

            // skip the reCAPTCHA display if the minimum capability is met
            if ((isset($needed_capability) && $needed_capability && current_user_can($needed_capability)) || !$this->options['show_in_comments'])
                return;

            else {
 
                //modify the comment form for the reCAPTCHA widget
                $recaptcha_js_opts = <<<OPTS
                <script type='text/javascript'>
                    var RecaptchaOptions = { 
                    	theme : '{$this->options['comments_theme']}', 
                    	lang : '{$this->options['recaptcha_language']}'
                    };
                </script>
OPTS;
				echo $comment_field;
	
				// see if there is an unsaved comment to retreive after a failed
				// captcha response
				$this::revive_comment_text();
				
				// Did the user fail to match the CAPTCHA? If so, let them know
                if($rerror == 'incorrect-captcha-sol')
                    echo '<p class="recaptcha-error">' . $this->options['incorrect_response_error'] . "</p>";

				echo $recaptcha_js_opts;
                echo $this->get_recaptcha_html($rerror, is_ssl() );
           }
        }
        
        function check_comment($comment_data) {
            global $user_ID;
            
            if ($this->options['bypass_for_registered_users'] && $this->options['minimum_bypass_level'])
                $needed_capability = $this->options['minimum_bypass_level'];
            
            if ((isset($needed_capability) && current_user_can($needed_capability)) || !$this->options['show_in_comments'])
                return $comment_data;
            
            // do not check trackbacks/pingbacks
            if ($comment_data['comment_type'] == '') {
                $challenge = $_POST['recaptcha_challenge_field'];
                $response = $_POST['recaptcha_response_field'];
                
                $recaptcha_response = recaptcha_check_answer($this->options['private_key'], $_SERVER['REMOTE_ADDR'], $challenge, $response);
                
                if ($recaptcha_response->is_valid)
                    return $comment_data;
                    
                else {
                    $this->saved_error = $recaptcha_response->error;
                    
                    // mark comment as spam 
                    // (see http://codex.wordpress.org/Plugin_API/Filter_Reference/pre_comment_approved)
                    add_filter('pre_comment_approved', create_function('$a', 'return \'spam\';'));
                    return $comment_data;
                }
            }
            
            return $comment_data;
        }
        
        function relative_redirect($location, $comment) {
            if ($this->saved_error != '') {
                // replace #comment- at the end of $location with #commentform
                $location = substr($location, 0, strpos($location, '#')) . '#commentform';
                
                $location = add_query_arg( array(
                	'rcommentid' => $comment->comment_ID,
                    'rerror'	 => $this->saved_error,
                    'rchash'   	 => $this->hash_comment($comment->comment_ID),
                    'rnonce'  	 => wp_create_nonce('recaptcha')
                ), $location );
            }
            
            return $location;
        }
        
	    function revive_comment_text() {	            
			$comment_id = (int) $_REQUEST['rcommentid'];
			$comment_hash = esc_attr( $_REQUEST['rchash'] );
			$comment_hash = esc_attr( $_REQUEST['rchash'] );
			$comment_nonce = esc_attr( $_REQUEST['rnonce'] );
			
			if( ! wp_verify_nonce( $comment_nonce, 'recaptcha' ) )
				return;
			
			if ($comment_hash != $this->hash_comment($comment_id))
				return;
			$comment = get_comment($comment_id);
			
			if( $comment->comment_approved === 1 )
				return;
				
			$comment_content = preg_replace('/([\\/\(\)\+\;\'])/e','\'%\'.dechex(ord(\'$1\'))', $comment->comment_content);
            $comment_content = preg_replace('/\\r\\n/m', '\\\n', $comment_content);
				                                
			echo "
			<script type='text/javascript'>
				document.getElementById('comment').value = unescape('$comment_content');
			</script>
			";
			
			wp_delete_comment($comment->comment_ID);
		}
	    
        // todo: is this still needed?
        // this is used for the api keys url in the administration interface
        function blog_domain() {
            $uri = parse_url(get_option('siteurl'));
            return $uri['host'];
        }
        
        // add a settings link to the plugin in the plugin list
        function show_settings_link($links, $file) {
            if ($file == plugin_basename($this->path_to_plugin_directory() . '/wp-recaptcha.php')) {
               $settings_title = __('Settings for this Plugin', 'recaptcha');
               $settings = __('Settings', 'recaptcha');
               $settings_link = '<a href="options-general.php?page=wp-recaptcha/recaptcha.php" title="' . $settings_title . '">' . $settings . '</a>';
               array_unshift($links, $settings_link);
            }
            
            return $links;
        }
        
        // add the settings page
        function add_settings_page() {
        
			if( ! $this->is_authority() )
				return;
			
			if ( $this->environment == Environment::WordPressMS )  {
	        	
        		add_submenu_page('settings.php', 'WP-reCAPTCHA', 'WP-reCAPTCHA', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));
			
			} else {
            
            	add_options_page('WP-reCAPTCHA', 'WP-reCAPTCHA', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));
           
            }
        }
        
        // store the html in a separate file and use include on it
        function show_settings_page() {
            require_once( dirname(__FILE__) . '/settings.php');
        }
        
        function build_dropdown($name, $keyvalue, $checked_value) {
            echo '<select name="' . $name . '" id="' . $name . '">' . "\n";
            
            foreach ($keyvalue as $key => $value) {
                $checked = ($value == $checked_value) ? ' selected="selected" ' : '';
                
                echo '\t <option value="' . $value . '"' . $checked . ">$key</option> \n";
                $checked = NULL;
            }
            
            echo "</select> \n";
        }
        
        function capabilities_dropdown() {
            // define choices: Display text => permission slug
            $capabilities = array (
                __('all registered users', 'recaptcha') => 'read',
                __('edit posts', 'recaptcha') => 'edit_posts',
                __('publish posts', 'recaptcha') => 'publish_posts',
                __('moderate comments', 'recaptcha') => 'moderate_comments',
                __('activate plugins', 'recaptcha') => 'activate_plugins'
            );
            
            $this->build_dropdown('recaptcha_options[minimum_bypass_level]', $capabilities, $this->options['minimum_bypass_level']);
        }
        
        function theme_dropdown($which) {
            $themes = array (
                __('Red', 'recaptcha') => 'red',
                __('White', 'recaptcha') => 'white',
                __('Black Glass', 'recaptcha') => 'blackglass',
                __('Clean', 'recaptcha') => 'clean'
            );
            
            if ($which == 'comments')
                $this->build_dropdown('recaptcha_options[comments_theme]', $themes, $this->options['comments_theme']);
            else if ($which == 'registration')
                $this->build_dropdown('recaptcha_options[registration_theme]', $themes, $this->options['registration_theme']);
        }
        
        function recaptcha_language_dropdown() {
            $languages = array (
                __('English', 'recaptcha') => 'en',
                __('Dutch', 'recaptcha') => 'nl',
                __('French', 'recaptcha') => 'fr',
                __('German', 'recaptcha') => 'de',
                __('Portuguese', 'recaptcha') => 'pt',
                __('Russian', 'recaptcha') => 'ru',
                __('Spanish', 'recaptcha') => 'es',
                __('Turkish', 'recaptcha') => 'tr'
            );
            
            $this->build_dropdown('recaptcha_options[recaptcha_language]', $languages, $this->options['recaptcha_language']);
        }
    } // end class declaration
} // end of class exists clause

?>
