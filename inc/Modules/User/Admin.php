<?php
/**
 * Admin related functionality for User Module.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\User;

use OneAccess\Contracts\Interfaces\Registrable;
use OneAccess\Modules\Core\Assets;
use OneAccess\Modules\Core\DB;
use OneAccess\Modules\Core\User_Roles;
use OneAccess\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 *
	 * @todo need to replace globally with single source of truth.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'oneaccess';

	/**
	 * The screen ID for the settings page.
	 */
	public const SCREEN_ID = self::MENU_SLUG . '-settings';

	/**
	 * Path to the SVG logo for the menu.
	 *
	 * @todo Replace with actual logo.
	 * @var string
	 */
	private const SVG_LOGO_PATH = '';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_menu', [ $this, 'manage_users' ], 15 );
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 ); // 20 priority to make sure settings page respect its position.
		add_action( 'admin_menu', [ $this, 'remove_default_submenu' ], 999 );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
	}

	/**
	 * Add a settings page.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'OneAccess', 'oneaccess' ),
			__( 'OneAccess', 'oneaccess' ),
			'oneaccess_manage_settings',
			self::MENU_SLUG,
			'__return_null',
			self::SVG_LOGO_PATH,
			2
		);
	}

	/**
	 * Register the settings page.
	 */
	public function add_submenu(): void {

		// Add the settings submenu page.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'oneaccess' ),
			__( 'Settings', 'oneaccess' ),
			'oneaccess_manage_settings',
			self::SCREEN_ID,
			[ $this, 'screen_callback' ],
			999
		);
	}

	/**
	 * Add the Manage Users submenu page for governing sites.
	 */
	public function manage_users(): void {
		if ( ! Settings::is_governing_site() || empty( Settings::get_shared_sites() ) ) {
			return;
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Manage Users', 'oneaccess' ),
			'<span class="oneaccess-manage-user-page">' . __( 'Manage Users', 'oneaccess' ) . '</span>',
			'oneaccess_manage_settings',
			self::MENU_SLUG,
			[ $this, 'render_user_manager' ],
			2
		);
	}

	/**
	 * Render admin page
	 */
	public function render_user_manager(): void {
		?>
		<div class="wrap">
			<h1 class="oneaccess-heading"><?php esc_html_e( 'Manage Users', 'oneaccess' ); ?></h1>
			<div id="oneaccess-manage-user"></div>
		</div>
		<?php
	}

	/**
	 * Remove the default submenu added by WordPress.
	 */
	public function remove_default_submenu(): void {
		if ( Settings::is_governing_site() && ! empty( Settings::get_shared_sites() ) ) {
			return;
		}
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Admin page content callback.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'oneaccess' ); ?></h1>
			<div id="oneaccess-settings-page"></div>
		</div>
		<?php
	}

		/**
		 * Enqueue admin scripts.
		 *
		 * @param string $hook Current admin page hook.
		 */
	public function enqueue_scripts( string $hook ): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen ) {
			return;
		}

		if ( ( 'plugins.php' === $hook || str_contains( $hook, 'plugins' ) || str_contains( $hook, 'oneaccess' ) ) && 'plugins-network' !== $current_screen->id ) {
			// Enqueue the onboarding modal.
			$this->enqueue_onboarding_scripts();
		}

		if ( strpos( $hook, 'oneaccess-settings' ) !== false ) {
			$this->enqueue_settings_scripts();
		}

		if ( strpos( $hook, 'toplevel_page_oneaccess' ) !== false ) {
			$this->enqueue_manage_users_scripts();
		}

		if ( Settings::is_consumer_site() && strpos( $hook, 'users.php' ) !== false ) {
			wp_enqueue_style( Assets::ADMIN_USER_STYLES_HANDLE );
		}

		if ( Settings::is_consumer_site() && ( ( strpos( $hook, 'profile.php' ) !== false ) || ( strpos( $hook, 'user-edit.php' ) !== false ) ) ) {
			$this->enqueue_admin_user_scripts();
		}

		// @todo Move other scripts from Assets to here.
	}

	/**
	 * Enqueue the scripts and styles for the settings screen.
	 */
	public function enqueue_settings_scripts(): void {
		wp_localize_script(
			Assets::SETTINGS_SCRIPT_HANDLE,
			'OneAccessSettings',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( Assets::SETTINGS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::SETTINGS_SCRIPT_HANDLE );
	}

	/**
	 * Enqueue scripts and styles for the onboarding modal.
	 */
	private function enqueue_onboarding_scripts(): void {
		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return;
		}

		wp_localize_script(
			Assets::ONBOARDING_SCRIPT_HANDLE,
			'OneAccessOnboarding',
			[
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'setup_url' => admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ),
				'site_type' => Settings::get_site_type(), // @todo We can probably remove this.
			]
		);

		wp_enqueue_script( Assets::ONBOARDING_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ONBOARDING_SCRIPT_HANDLE );
	}

	/**
	 * Enqueue scripts and styles for the Manage Users screen.
	 */
	private function enqueue_manage_users_scripts(): void {

		$user_roles = wp_roles()->get_names();

			// remove network admin role from available roles.
		if ( isset( $user_roles[ User_Roles::NETWORK_ADMIN ] ) ) {
			unset( $user_roles[ User_Roles::NETWORK_ADMIN ] );
		}

			// add brand admin role if not exists.
		if ( ! isset( $user_roles[ User_Roles::BRAND_ADMIN ] ) ) {
			$user_roles[ User_Roles::BRAND_ADMIN ] = __( 'Brand Admin', 'oneaccess' );
		}

		wp_localize_script(
			Assets::MANAGE_USERS_SCRIPT_HANDLE,
			'OneAccess',
			array_merge(
				Assets::get_localized_data(),
				[
					'availableRoles' => $user_roles,
					'availableSites' => array_values( Settings::get_shared_sites() ),
				]
			)
		);

		wp_enqueue_script( Assets::MANAGE_USERS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::MANAGE_USERS_SCRIPT_HANDLE );
	}

	/**
	 * Enqueue scripts and styles for the admin user profile pages.
	 */
	private function enqueue_admin_user_scripts(): void {
		wp_enqueue_style( Assets::ADMIN_USER_STYLES_HANDLE );

		$current_user                  = isset( $_GET['user_id'] ) ? filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT ) : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is to know on which user profile page we are.      
		$get_user_profile_request_data = DB::get_latest_profile_request_by_user_id( (int) $current_user );
		$current_user_request          = is_array( $get_user_profile_request_data ) && ! empty( $get_user_profile_request_data ) ? $get_user_profile_request_data : null;

		wp_localize_script(
			Assets::USER_PROFILE_SCRIPT_HANDLE,
			'OneAccessProfile',
			array_merge(
				Assets::get_localized_data(),
				[
					'userId'  => $current_user,
					'request' => $current_user_request,
				]
			)
		);

		wp_enqueue_script( Assets::USER_PROFILE_SCRIPT_HANDLE );
	}
}