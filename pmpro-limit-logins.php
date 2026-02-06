<?php
/**
 * Plugin Name: Paid Memberships Pro - Limit Logins
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/limit-logins/
 * Description: Deter members from sharing login credentials: restrict simultaneous logins for the same user.
 * Version: 1.7
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-limit-logins
 * Domain Path: /languages
 */

define( 'PMPRO_LIMIT_LOGINS_VERSION', '1.6' );

// Start up the engine
class PMPro_Limit_Logins {
	/**
	 * This is our constructor
	 *
	 * @return PMPro_Limit_Logins
	 */
	public function __construct() {
		//support translations
		add_action( 'plugins_loaded', array( $this, 'textdomain' ) );

		//track logins
		add_action( 'wp_login', array( $this, 'login_track' ) );

		//bounce logins
		add_action( 'init', array( $this, 'login_flag' ), 10, 0 );

		//add action links to reset sessions
		add_filter( 'user_row_actions', array($this, 'user_row_actions' ), 10, 2 );
		
		//add check for resetting sessions
		add_action( 'admin_init', array( $this, 'reset_session' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		//JS checks
		add_action( 'wp_ajax_pmpro_limit_logins_check', array( $this, 'ajax_check' ) );
		add_action( 'wp_ajax_nopriv_pmpro_limit_logins_check', array( $this, 'ajax_check' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		// Show error message on login.
		add_action( 'login_head', array( $this, 'user_bounced_error' ) );

		// Deactivate WP Bounder.
		add_action( 'admin_init', array( $this, 'deactivate_wp_bouncer' ) );

		// Add settings page
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
	}
	
	public function wp_enqueue_scripts() {

		// Backwards compatibility for WP Bouncer if WP_BOUNCER_HEARTBEAT_CHECK is defined.
		if ( defined( 'WP_BOUNCER_HEARTBEAT_CHECK' ) && ! defined( 'PMPRO_LIMIT_LOGINS_HEARTBEAT_CHECK' ) ) {
			define( 'PMPRO_LIMIT_LOGINS_HEARTBEAT_CHECK', WP_BOUNCER_HEARTBEAT_CHECK );
		}
		
		// Check for PMPRO_LIMIT_LOGINS_HEARTBEAT_CHECK constant first
		if ( defined( 'PMPRO_LIMIT_LOGINS_HEARTBEAT_CHECK' ) 
			&& PMPRO_LIMIT_LOGINS_HEARTBEAT_CHECK == true 
			&& is_user_logged_in() ) {
			wp_enqueue_script(
				'pmpro_limit_logins', 
				plugins_url( 'js/pmpro-limit-logins.js', __FILE__ ),
				array( 'jquery' ),
				PMPRO_LIMIT_LOGINS_VERSION
			);
			/**
			 * Filter the timeout for the AJAX request.
			 * @deprecated 1.6 Use pmpro_limit_logins_ajax_timeout instead.
			 * @param int $timeout The timeout in milliseconds.
			 */
			$timeout = apply_filters_deprecated( 'wp_bouncer_ajax_timeout', array( 5000 ), '1.6', 'pmpro_limit_logins_ajax_timeout' );
			$timeout = apply_filters( 'pmpro_limit_logins_ajax_timeout', $timeout );
			wp_localize_script(
				'pmpro_limit_logins',
				'pmpro_limit_logins',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'pmpro_limit_logins_ajax_timeout' => $timeout,
				)
			);
		}
	}

	/**
	 * Support translations and tie into GlotPress
	 */
	public function textdomain() {
		load_plugin_textdomain( 'pmpro-limit-logins', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Get the URL to redirect dupe logins to
	 */
	public function get_redirect_url() {
		$default_url = esc_url( add_query_arg( 'bounced', '1', wp_login_url() ) );
		$url = get_option( 'pmpro_limit_logins_redirect_url', $default_url );
		
		/**
		 * Filter the URL to redirect to when a user is flagged for multiple logins.
		 * @deprecated 1.6 Use pmpro_limit_logins_redirect_url instead.
		 * @param string $url The URL to redirect to, includes query string. (?bounced=1)
		 */
		$url = apply_filters_deprecated( 'wp_bouncer_redirect_url', array( $url ), '1.6', 'pmpro_limit_logins_redirect_url' );
		$url = apply_filters( 'pmpro_limit_logins_redirect_url', $url );
		return $url;
	}

	/**
	 * Show error message on login page if bounced.
	 * @since 1.5
	 */
	public function user_bounced_error() {
		global $error;

		if( isset( $_REQUEST['bounced'] ) && '1' == $_REQUEST['bounced'] ) {
			$error  = esc_html__( 'There was an issue with your log in. Your user account has logged in recently from a different location.', 'pmpro-limit-logins' );
		}
  
	}
	
	/**
	 * helper function to get browser data at login
	 *
	 * @return PMPro_Limit_Logins
	 */
	private function browser_data() {
		// grab base user agent and parse out
	    $u_agent	= $_SERVER['HTTP_USER_AGENT'];
	    $bname		= 'Unknown';
	    $platform	= 'Unknown';
	    $version	= '';
	    $ub			= '';
		
	    // determine platform
	    if (preg_match('/linux/i', $u_agent))
	        $platform = 'linux';

	    if (preg_match('/macintosh|mac os x/i', $u_agent))
	        $platform = 'mac';

	    if (preg_match('/windows|win32/i', $u_agent))
	        $platform = 'windows';


	    // get browser info
	    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) {
	        $bname	= 'Internet Explorer';
	        $ub		= 'MSIE';
	    }

	    if(preg_match('/Firefox/i',$u_agent)) {
	        $bname	= 'Mozilla Firefox';
	        $ub		= 'Firefox';
	    }

	    if(preg_match('/Chrome/i',$u_agent)) {
	        $bname	= 'Google Chrome';
	        $ub		= 'Chrome';
	    }

		if(preg_match('/Safari/i',$u_agent) && !preg_match('/Chrome/i',$u_agent)) {
	        $bname	= 'Apple Safari';
	        $ub		= 'Safari';
	    }

	    if(preg_match('/Opera/i',$u_agent)) {
	        $bname	= 'Opera';
	        $ub		= 'Opera';
	    }

	    if(preg_match('/Netscape/i',$u_agent)) {
	        $bname	= 'Netscape';
	        $ub		= 'Netscape';
	    }

	    // finally get the correct version number
	    $known		= array('Version', $ub, 'other');
	    $pattern	= '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

	    if (!preg_match_all($pattern, $u_agent, $matches)) {
	        // we have no matching number just continue
	    }

	    // see how many we have
	    $i = count( $matches['browser'] );
	    if ($i != 1) {
	        //we will have two since we are not using 'other' argument yet
	        //see if version is before or after the name
	        if (strripos( $u_agent, 'Version' ) < strripos($u_agent,$ub)){
	            $version= $matches['version'][0];
	        }
	        else {
	            $version= $matches['version'][1];
	        }
	    }
	    else {
	        $version= $matches['version'][0];
	    }

	    // check if we have a number
	    if ($version == null || $version == '' )
	    	$version = '?';

	    return array(
	        'userAgent'	=> $u_agent,
	        'name'		=> $bname,
	        'version'	=> $version,
	        'platform'	=> $platform,
	        'pattern'	=> $pattern
	    );
	}
	
	/**
	 * redirect function for flagged logins
	 *
	 * @return PMPro_Limit_Logins
	 */
	public function flag_redirect() {		
		wp_redirect( $this->get_redirect_url() );
		exit();
	}

	/**
	 * run checks for a flagged login
	 *
	 * @return PMPro_Limit_Logins
	 */
	public function login_flag( $redirect = true ) {
		if(is_user_logged_in()) {	
			global $current_user;
			
			/**
			 * Ignore admins from being flagged.
			 * @deprecated 1.6 Use pmpro_limit_logins_ignore_admins instead.
			 * @param bool $ignored_admins True to ignore admins, false to flag them from multiple logins.
			 */
			$ignore_admins = get_option( 'pmpro_limit_logins_ignore_admins', true );
			$ignore_admins = apply_filters_deprecated( 'wp_bouncer_ignore_admins', array( $ignore_admins ), '1.6', 'pmpro_limit_logins_ignore_admins' );
			$ignore_admins = apply_filters( 'pmpro_limit_logins_ignore_admins', $ignore_admins );
			if( $ignore_admins && current_user_can("manage_options"))
				return false;
			
			//check the session ids
			$session_ids = get_transient("fakesessid_" . $current_user->user_login);			
			$old_session_ids = $session_ids;
						
			//make sure it's an array
			if(empty($session_ids))
				$session_ids = array();
			elseif(!is_array($session_ids))
				$session_ids = array($session_ids);

			/**
			 * Filter the number of simultaneous logins allowed.
			 * @deprecated 1.6 Use pmpro_limit_logins_number_simultaneous_logins instead.
			 * @param int $num_allowed The number of simultaneous logins allowed. Default 1.
			 */
			$num_allowed = get_option( 'pmpro_limit_logins_number_simultaneous_logins', 1 );
			$num_allowed = apply_filters_deprecated( 'wp_bouncer_number_simultaneous_logins', array( $num_allowed ), '1.6', 'pmpro_limit_logins_number_simultaneous_logins' );
			$num_allowed = apply_filters( 'pmpro_limit_logins_number_simultaneous_logins', $num_allowed );
			
			//0 means do nothing
			if(empty($num_allowed))
				return false;
						
			//if we have more than the num allowed, remove some from the top
			while(count($session_ids) > $num_allowed) {				
				unset($session_ids[0]);	//remove oldest id
				$session_ids = array_values($session_ids);	//fix array keys								
			}
			
			/**
			 * Filter the session ids to check for a flagged login.
			 * @deprecated 1.6 Use pmpro_limit_logins_session_ids instead.
			 * @param array $session_ids The session ids to check.
			 * @param array $old_session_ids The session ids before trimming.
			 * @param int $user_id The user ID of the current user.
			 */
			$session_ids = apply_filters_deprecated( 'wp_bouncer_session_ids', array( $session_ids, $old_session_ids, $current_user->ID ), '1.6', 'pmpro_limit_logins_session_ids' );
			$session_ids = apply_filters( 'pmpro_limit_logins_session_ids', $session_ids, $old_session_ids, $current_user->ID );
						
			/**
			 * Filter the session length for the transient. Defaults to 30 days.
			 * @deprecated 1.6 Use pmpro_limit_logins_session_length instead.
			 * @param int $session_length The session length in seconds.
			 * @param int $user_id The user ID of the current user.
			 */
			$session_days = get_option( 'pmpro_limit_logins_session_length', 30 );
			$session_length = 3600 * 24 * $session_days;
			$session_length = apply_filters_deprecated( 'wp_bouncer_session_length', array( $session_length, $current_user->ID ), '1.6', 'pmpro_limit_logins_session_length' );
			$session_length = apply_filters( 'pmpro_limit_logins_session_length', $session_length, $current_user->ID );
			set_transient("fakesessid_" . $current_user->user_login, $session_ids, $session_length);
						
			if(!empty($session_ids)) {			
				if(empty($_COOKIE['fakesessid']) || !in_array($_COOKIE['fakesessid'], $session_ids)) {
					
					/**
					 * Filter to allow for custom login flagging
					 * @deprecated 1.6 Use pmpro_limit_logins_login_flag instead.
					 * @param bool $logout True to log the user out, false to keep them logged in.
					 * @param array $session_ids The session ids to check.
					 */
					$logout = get_option( 'pmpro_limit_logins_trigger_logout', true );
					$logout = apply_filters_deprecated( 'wp_bouncer_login_flag', array( $logout, $session_ids ), '1.6', 'pmpro_limit_logins_login_flag' );
					$logout = apply_filters( 'pmpro_limit_logins_login_flag', $logout, $session_ids );
					
					if($logout) {
						//log user out
						wp_logout();
						
						//redirect
						if ( $redirect ) {
							$this->flag_redirect();
						}
					}
					
					return true;
				}
			}
		}
		
		// if we get here the login is not a dupe
		return false;
	}

	/**
	 * track and set session data at login
	 *
	 * @return PMPro_Limit_Logins
	 */
	public function login_track($user_login) {		
		// get browser data from current login
		$browser	= $this->browser_data();
				
		//generate a new session id
		$new_session_id = md5($browser['name'] . $browser['platform'] . $_SERVER['REMOTE_ADDR'] . time());
		
		//save it in a list in a transient
		$session_ids = get_transient("fakesessid_" . $user_login);
				
		if(empty($session_ids))
			$session_ids = array();
		elseif(!is_array($session_ids))
			$session_ids = array($session_ids);
				
		$session_ids[] = $new_session_id;			
				
		$session_days = get_option( 'pmpro_limit_logins_session_length', 30 );
		$session_length = 3600 * 24 * $session_days;
		set_transient("fakesessid_" . $user_login, $session_ids, $session_length);
		
		//and save it in a cookie
		$session_days = get_option( 'pmpro_limit_logins_session_length', 30 );
		$session_length = 3600 * 24 * $session_days;
		setcookie("fakesessid", $new_session_id, time()+$session_length, COOKIEPATH, COOKIE_DOMAIN, false);	
	}

	/**
	 * Add link to the user action links to reset sessions
	 *
	 * Use the pmpro_limit_logins_reset_sessions_cap to change the capability required to see this.
	 */	
	public function user_row_actions($actions, $user) {	
		/**
		 * Filter the capability required to reset sessions.
		 * @deprecated 1.6 Use pmpro_limit_logins_reset_sessions_cap instead.
		 * @param string $cap The capability required to reset sessions. Default 'edit_users'.
		 */
		$cap = get_option( 'pmpro_limit_logins_reset_permission_level', 'edit_users' );
		$cap = apply_filters_deprecated( 'wp_bouncer_reset_sessions_cap', array( $cap ), '1.6', 'pmpro_limit_logins_reset_sessions_cap' );
		$cap = apply_filters( 'pmpro_limit_logins_reset_sessions_cap', $cap );
		if(current_user_can($cap)) {
			$url = admin_url("users.php?pmproll=" . $user->ID);
			if(!empty($_REQUEST['s']))
				$url .= "&s=" . esc_attr($_REQUEST['s']);
			if(!empty($_REQUEST['paged']))
				$url .= "&paged=" . intval($_REQUEST['paged']);
			$url = wp_nonce_url($url, 'pmproll_' . $user->ID);
			$actions[] = '<a href="' . $url . '">Reset Sessions</a>';
		}
		
		return $actions;
	}
	
	/**
	 * Reset sessions. Runs on admin init. Checks for pmproll and nonce and resets sessions for that user.
	 */	
	public function reset_session() {
		if(!empty($_REQUEST['pmproll'])) {
			global $wpb_msg, $wpb_msgt;
			
			//get user id
			$user_id = intval($_REQUEST['pmproll']);
			$user = get_userdata($user_id);
						
			//no user?
			if(empty($user)) {
				//user not found error
				$wpb_msg = 'Could not reset sessions. User not found.';
				$wpb_msgt = 'error';
			} else {				
				//check nonce
				check_admin_referer( 'pmproll_'.$user_id);
				
				/**
				 * Filter the capability required to reset sessions.
				 * @deprecated 1.6 Use pmpro_limit_logins_reset_sessions_cap instead.
				 * @param string $cap The capability required to reset sessions. Default 'edit_users'.
				 */
				$cap = get_option( 'pmpro_limit_logins_reset_permission_level', 'edit_users' );
				$cap = apply_filters_deprecated( 'wp_bouncer_reset_sessions_cap', array( $cap ), '1.6', 'pmpro_limit_logins_reset_sessions_cap' );
				$cap = apply_filters( 'pmpro_limit_logins_reset_sessions_cap', $cap );
				if(!current_user_can($cap)) {
					//show error message
					$wpb_msg = 'You do not have permission to reset user sessions.';
					$wpb_msgt = 'error';
				} else {
					//all good, delete this user's sessions
					delete_transient('fakesessid_'. $user->user_login);				
					
					//show success message
					$wpb_msg = 'Sessions reset for ' . $user->user_login . '.';
					$wpb_msgt = 'updated';
				}
			}						
		}
	}
	
	/**
	 * Show any messages generated by Limit Logins.
	 */	
	public function admin_notices() {
		global $wpb_msg, $wpb_msgt;
		if(!empty($wpb_msg))
			echo "<div class=\"$wpb_msgt\"><p>$wpb_msg</p></div>"; 
	}
	
	/**
	 * Check login_flag via heartbeat API
	 */
	public function ajax_check() {
		$r = array();
		
		if( $this->login_flag( false ) ) {	
			$r['redirect_url'] = esc_url( $this->get_redirect_url() );
			$r['flagged'] = true;
		} else {
			$r['redirect_url'] = '';
			$r['flagged'] = false;
		}
		
		echo json_encode( $r );
		
		exit;
	}

	/*
	 * Deactivate WP Bouncer plugin if exists and is active
	 */
	public function deactivate_wp_bouncer() {

		// PLugin file path.
		$plugin = 'wp-bouncer/wp-bouncer.php';

		// Check if the plugin exists and is active.
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) && is_plugin_active( $plugin ) ) {
			// Deactivate the plugin.
			deactivate_plugins( $plugin );

			// Add an admin notice.
			add_action( 'admin_notices', function() use ( $plugin ) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p><?php esc_html_e( 'WP Bouncer has been deactivated', 'pmpro-limit-logins' ); ?></p>
				</div>
				<?php
			});
		}

	}

	/**
	 * Add settings page
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Limit Logins Settings', 'pmpro-limit-logins' ),
			__( 'PMPro Logins', 'pmpro-limit-logins' ),
			'manage_options',
			'pmpro-limit-logins-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'pmpro-limit-logins-settings-group', 'pmpro_limit_logins_number_simultaneous_logins' );
		register_setting( 'pmpro-limit-logins-settings-group', 'pmpro_limit_logins_redirect_url' );
		register_setting( 'pmpro-limit-logins-settings-group', 'pmpro_limit_logins_ignore_admins' );
		register_setting( 'pmpro-limit-logins-settings-group', 'pmpro_limit_logins_session_length' );
		register_setting( 'pmpro-limit-logins-settings-group', 'pmpro_limit_logins_trigger_logout' );
		register_setting( 'pmpro-limit-logins-settings-group', 'pmpro_limit_logins_reset_permission_level' );

		add_settings_section(
			'pmpro_limit_logins_general',
			__( 'General Settings', 'pmpro-limit-logins' ),
			array( $this, 'settings_section_callback' ),
			'pmpro-limit-logins-settings'
		);

		add_settings_field(
			'pmpro_limit_logins_number_simultaneous_logins',
			__( 'Simultaneous Logins', 'pmpro-limit-logins' ),
			array( $this, 'number_simultaneous_logins_callback' ),
			'pmpro-limit-logins-settings',
			'pmpro_limit_logins_general'
		);

		add_settings_field(
			'pmpro_limit_logins_redirect_url',
			__( 'Redirect URL', 'pmpro-limit-logins' ),
			array( $this, 'redirect_url_callback' ),
			'pmpro-limit-logins-settings',
			'pmpro_limit_logins_general'
		);

		add_settings_field(
			'pmpro_limit_logins_ignore_admins',
			__( 'Admins Ignore Login Limits', 'pmpro-limit-logins' ),
			array( $this, 'ignore_admins_callback' ),
			'pmpro-limit-logins-settings',
			'pmpro_limit_logins_general'
		);

		add_settings_field(
			'pmpro_limit_logins_session_length',
			__( 'Session Timeout Length', 'pmpro-limit-logins' ),
			array( $this, 'session_length_callback' ),
			'pmpro-limit-logins-settings',
			'pmpro_limit_logins_general'
		);

		add_settings_field(
			'pmpro_limit_logins_trigger_logout',
			__( 'Trigger Logout For Flagged Logins', 'pmpro-limit-logins' ),
			array( $this, 'trigger_logout_callback' ),
			'pmpro-limit-logins-settings',
			'pmpro_limit_logins_general'
		);

		add_settings_field(
			'pmpro_limit_logins_reset_permission_level',
			__( 'Reset User Session Permission Level', 'pmpro-limit-logins' ),
			array( $this, 'reset_permission_level_callback' ),
			'pmpro-limit-logins-settings',
			'pmpro_limit_logins_general'
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		?>
		<style>
			.form-table th {
				width: 250px;
			}
		</style>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'pmpro-limit-logins-settings-group' );
				do_settings_sections( 'pmpro-limit-logins-settings' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Settings section callback
	 */
	public function settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the settings for limiting simultaneous logins.', 'pmpro-limit-logins' ) . '</p>';
	}

	/**
	 * Number of simultaneous logins field callback
	 */
	public function number_simultaneous_logins_callback() {
		$value = get_option( 'pmpro_limit_logins_number_simultaneous_logins', 1 );
		echo '<input type="number" id="pmpro_limit_logins_number_simultaneous_logins" name="pmpro_limit_logins_number_simultaneous_logins" value="' . esc_attr( $value ) . '" min="1" max="10" />';
		echo '<p class="description">' . esc_html__( 'Number of simultaneous logins allowed per user. Set to 0 to disable login limiting.', 'pmpro-limit-logins' ) . '</p>';
	}

	/**
	 * Redirect URL field callback
	 */
	public function redirect_url_callback() {
		$value = get_option( 'pmpro_limit_logins_redirect_url', esc_url( add_query_arg( 'bounced', '1', wp_login_url() ) ) );
		echo '<input type="url" id="pmpro_limit_logins_redirect_url" name="pmpro_limit_logins_redirect_url" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'URL to redirect users when they reach their login limit.', 'pmpro-limit-logins' ) . '</p>';
	}

	/**
	 * Ignore admins field callback
	 */
	public function ignore_admins_callback() {
		$value = get_option( 'pmpro_limit_logins_ignore_admins', true );
		echo '<input type="checkbox" id="pmpro_limit_logins_ignore_admins" name="pmpro_limit_logins_ignore_admins" value="1" ' . checked( 1, $value, false ) . ' />';
		echo '<label for="pmpro_limit_logins_ignore_admins">' . esc_html__( 'Check this to allow administrators to bypass login limits', 'pmpro-limit-logins' ) . '</label>';
	}

	/**
	 * Session length field callback
	 */
	public function session_length_callback() {
		$value = get_option( 'pmpro_limit_logins_session_length', 30 );
		echo '<input type="number" id="pmpro_limit_logins_session_length" name="pmpro_limit_logins_session_length" value="' . esc_attr( $value ) . '" min="1" max="365" />';
		echo '<p class="description">' . esc_html__( 'Number of days before a session expires. Default is 30 days.', 'pmpro-limit-logins' ) . '</p>';
	}

	/**
	 * Trigger logout field callback
	 */
	public function trigger_logout_callback() {
		$value = get_option( 'pmpro_limit_logins_trigger_logout', true );
		echo '<input type="checkbox" id="pmpro_limit_logins_trigger_logout" name="pmpro_limit_logins_trigger_logout" value="1" ' . checked( 1, $value, false ) . ' />';
		echo '<label for="pmpro_limit_logins_trigger_logout">' . esc_html__( 'Check this to automatically log out users when they reach their login limit', 'pmpro-limit-logins' ) . '</label>';
	}

	/**
	 * Reset permission level field callback
	 */
	public function reset_permission_level_callback() {
		$value = get_option( 'pmpro_limit_logins_reset_permission_level', 'edit_users' );
		$capabilities = array(
			'manage_options' => __( 'Administrator', 'pmpro-limit-logins' ),
			'edit_users' => __( 'User Editor', 'pmpro-limit-logins' ),
			'list_users' => __( 'User Lister', 'pmpro-limit-logins' ),
		);
		
		echo '<select id="pmpro_limit_logins_reset_permission_level" name="pmpro_limit_logins_reset_permission_level">';
		foreach ( $capabilities as $cap => $label ) {
			echo '<option value="' . esc_attr( $cap ) . '" ' . selected( $cap, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Minimum user capability required to reset user sessions.', 'pmpro-limit-logins' ) . '</p>';
	}
} // End of class
$PMPro_Limit_Logins = new PMPro_Limit_Logins();
