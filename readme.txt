=== Paid Memberships Pro - Limit Logins ===
Contributors: strangerstudios
Website Link: https://www.paidmembershipspro.com/add-ons/limit-logins/
Tags: login, security, membership, firewall, protection
Requires at least: 5.4
Tested up to: 6.7
Stable tag: 1.6
Requires PHP: 7.4
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Deter members from sharing login credentials: restrict simultaneous logins for the same user.

== Description ==

Limit Logins restricts the number of simultaneous logins for the same WordPress user account. The plugin's goal is to deter people from sharing their login credentials for your site, which is especially important for a paid membership, premium content, or eLearning site.

For more information please visit https://www.paidmembershipspro.com/add-ons/limit-logins.

== Installation ==

1. Upload the `pmpro-limit-logins` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-limit-logins/issues

== Changelog ==
= 1.6 - 2025-01-20 =
* REFACTOR: Renamed to Paid Memberships Pro - Limit Logins
* ENHANCEMENT: New filters added to match new name, previous filters maintained for backward compatibility. Please replace `wp_bouncer_` with `pmpro_limit_logins_` in code. 
* ENHANCEMENT: Automatically deactivate WP Bouncer if active.

= 1.5.1 - 2023-01-30 =
* ENHANCEMENT: Added filter `wp_bouncer_ajax_timeout` to adjust timeout (default 5000).
* ENHANCEMENT: Added support for translations.
* BUG FIX: Removed unused login warning file and screenshot from the SVN repository that is not used in this plugin.
* BUG FIX: Fixed misspelled constant for plugin version and usage in JS file load.

= 1.5 - 2021-06-02 =
* ENHANCEMENT: Removed the login-warning.php file. Instead, we redirect to the wp-login.php page and show a message.
* BUG FIX: Adjusted URLs to be https and adjusted meta tags to be be noindex/nofollow.

= 1.4.1 - 2020-01-01 =
* BUG FIX: Fixed issue where users were not redirected to the warning page when logged out.

= 1.4 - 2019-01-16 =
* BUG FIX: Fixed issue with how things were stored in transients. (Thanks, zackdn on GitHub)
* FEATURE: Added JavaScript to bounce users in case the PHP bouncer is not running (e.g. when using page caching). To enable this, add `define( 'WP_BOUNCER_HEARTBEAT_CHECK', true );` to your wp-config.php (without the backticks).

= 1.3.1 =
* Fixed a typo.
* Tested up to WP 4.8

= 1.3 =
* Added a user action link (hover over a user on the users.php page in the dashboard) to reset all sessions for a user.
* Added wp_bouncer_session_ids hook to filter session ids when saving them. Passes $session_ids, $old_session_ids (before any were removed/bounced), and the current user's ID as parameters.
* Added wp_bouncer_session_length hook to filter how long the session ids transients are set. This way, you can time the transients to expire at a specific time of day. Note that the transient is saved on every page load, so if you set it to 5 minutes, it's going to push it out 5 minutes on every page load. You should try to set it to (the number of seconds until midnight) or something like that.

= 1.2 =
* Fixed some typos in the variables used to generate the session ids.
* The fakesessid_{user_login} transients are now storing arrays of session ids. This allowed for multiple (but limited) sessions per user if wanted.
* Added wp_bouncer_ignore_admins filter, if returning false even admins will be bounced.
* Added wp_bouncer_redirect_url filter, which can be used to change the URL redirected to after being bounced.
* Added wp_bouncer_number_simultaneous_logins filter, which can be set to limit logins to a number other than 1. 0 means unlimited logins.
* Added wp_bouncer_login_flag in case you want to hook in and do something right before bouncing (or potentially stop the bouncing).

= 1.1 =
* Admin accounts (specifically users with "manage_options" capability) are excluded from bounces. This will eventually be a setting once we setup a settings page.
* Readme changes.

= 1.0.1 =
* Fixed bug with how transients were being set and get.
* Removed code in track_login that made sure you were logging in from login page. This will allow wp bouncer to kick in when logging in via wp_signon, etc.
* Moved redirect url to a class property. Will eventually add a settings page for this and any other setting/configuration value.

= 1.0 =
* First release!
