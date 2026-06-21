<?php
/**
 * OneAccess
 *
 * @package           OneAccess
 * @author            rtCamp
 * @copyright         2025 rtCamp
 * @license           GPL-2.0-or-later
 *
 * Plugin Name:       OneAccess
 * Plugin URI:        https://github.com/rtCamp/oneaccess
 * Description:       Manage user accounts across multiple sites in a WordPress network.
 * Author:            rtCamp
 * Author URI:        https://rtcamp.com
 * Update URI:       https://github.com/rtCamp/oneaccess
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oneaccess
 * Domain Path:       /languages
 * Version:           1.1.3
 * Requires PHP:      8.2
 * Requires at least: 6.8
 * Tested up to:      6.9
 */

declare( strict_types = 1 );

namespace OneAccess;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * File path to the plugin's main file.
	 */
	define( 'ONEACCESS_FILE', __FILE__ );

	/**
	 * Version of the plugin.
	 */
	define( 'ONEACCESS_VERSION', '1.1.3' );

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

// If autoloader fails, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \OneAccess\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( '\OneAccess\Main' ) ) {
	\OneAccess\Main::instance();
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
