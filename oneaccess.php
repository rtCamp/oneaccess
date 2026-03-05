<?php
/**
 * Plugin Name: OneAccess
 * Description: OneAccess is plugin to manage user accounts across multiple sites in a WordPress.
 * Author: Utsav Patel, rtCamp
 * Author URI: https://rtcamp.com
 * Plugin URI: https://github.com/rtCamp/OneAccess/
 * Update URI: https://github.com/rtCamp/OneAccess/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: oneaccess
 * Domain Path: /languages
 * Version: 1.1.2
 * Requires PHP: 8.0
 * Requires at least: 6.8
 * Tested up to: 6.9
 *
 * @package OneAccess
 */

namespace OneAccess;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit();

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * Version of the plugin.
	 */
	define( 'ONEACCESS_VERSION', '1.1.2' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'ONEACCESS_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'ONEACCESS_URL', plugin_dir_url( __FILE__ ) );

	/**
	 * Plugin basename.
	 */
	define( 'ONEACCESS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

constants();

// If autoloader failed, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \OneAccess\Autoloader::autoload() ) {
	return;
}

/**
 * Load plugin.
 */
if ( class_exists( 'OneAccess\Main' ) ) {
	add_action(
		'plugins_loaded',
		'\OneAccess\load_plugin'
	);
}

/**
 * Load OneAccess plugin functionality.
 *
 * @return void
 */
function load_plugin(): void {
	\OneAccess\Main::instance();

	//phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- @todo remove before submitting to .org.
	load_plugin_textdomain( 'oneaccess', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * Activation hook.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		if ( ! class_exists( '\OneAccess\Modules\Core\User_Roles' ) ) {
			return;
		}

		// Update user role on activation.
		\OneAccess\Modules\Core\User_Roles::update_user_role_on_activation();
	}
);
