<?php
/**
 * The main plugin file.
 *
 * @package OneAccess
 */

declare( strict_types = 1 );

namespace OneAccess;

use OneAccess\Contracts\Traits\Singleton;

/**
 * Class - Main
 */
final class Main {
	use Singleton;

	/**
	 * Registrable classes are entrypoints that "hook" into WordPress.
	 * They should implement the Registrable interface.
	 *
	 * @var class-string<\OneAccess\Contracts\Interfaces\Registrable>[]
	 */
	private const REGISTRABLE_CLASSES = [
		Modules\Core\Assets::class,
		Modules\Core\DB::class,
		Modules\Core\Rest::class,
		Modules\Core\Hooks::class,
		Modules\Core\User_Roles::class,

		Modules\Settings\Admin::class,
		Modules\Settings\Settings::class,

		Modules\Rest\Actions_Controller::class,
		Modules\Rest\Basic_Options_Controller::class,
		Modules\Rest\Brand_Site_Controller::class,
		Modules\Rest\Governing_Site_Controller::class,

		Modules\User\Admin::class,
		Modules\User\Notice::class,
		Modules\User\Profile_Request::class,
	];

	/**
	 * {@inheritDoc}
	 */
	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Setup the plugin.
	 */
	private function setup(): void {
		// Ensure pretty permalinks are enabled.
		if ( ! $this->has_pretty_permalinks() ) {
			return;
		}

		// @todo - remove when submitting to .org, as this is handled by WordPress core.
		$this->load_textdomain();

		// Load the plugin classes.
		$this->load();

		// Do other stuff here like dep-checking, telemetry, etc.
	}

	/**
	 * Returns whether pretty permalinks are enabled.
	 *
	 * Will also render an admin notice if not enabled.
	 */
	private function has_pretty_permalinks(): bool {
		if ( ! empty( get_option( 'permalink_structure' ) ) ) {
			return true;
		}

		foreach ( [
			'admin_notices',
			'network_admin_notices',
		] as $hook ) {
			add_action(
				$hook,
				static function () {
					wp_admin_notice(
						sprintf(
						/* translators: 1: Plugin name */
							__( 'OneAccess: The plugin requires pretty permalinks to be enabled. Please go to <a href="%s">Permalink Settings</a> and enable an option other than <code>Plain</code>.', 'oneaccess' ),
							admin_url( 'options-permalink.php' ),
						),
						[
							'type'        => 'error',
							'dismissible' => false,
						]
					);
				}
			);
		}

		return false;
	}

	/**
	 * Load the plugin classes.
	 */
	private function load(): void {
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			$instance = new $class_name();
			$instance->register_hooks();
		}

		// Do other generalizable stuff here.
	}

	/**
	 * Load the plugin textdomain.
	 *
	 * @todo this should be removed before submitting to .org
	 */
	public function load_textdomain(): void {
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain(
					'oneaccess',
					false,
					dirname( (string) ONEACCESS_PLUGIN_BASENAME ) . '/languages/'
				);
			}
		);
	}
}
