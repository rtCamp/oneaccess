<?php
/**
 * Settings class.
 * This class handles the settings page for the OneAccess plugin,
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\Settings;

use OneAccess\Contracts\Interfaces\Registrable;

/**
 * Class Settings
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
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', [ $this, 'inject_site_selection_modal' ] );
		add_filter( 'plugin_action_links_' . ONEACCESS_PLUGIN_BASENAME, [ $this, 'add_action_links' ], 2 );
		add_filter( 'admin_body_class', [ $this, 'add_body_classes' ] );
	}

	/**
	 * Inject site selection modal into the admin footer.
	 */
	public function inject_site_selection_modal(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}

		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return;
		}

		?>
		<div class="wrap">
			<div id="oneaccess-site-selection-modal" class="oneaccess-modal"></div>
		</div>
		<?php
	}

	/**
	 * Add action links to the settings on the plugins page.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( $links ): array {
		// Defense against other plugins.
		if ( ! is_array( $links ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Expected an array.', 'oneaccess' ), '1.0.0' );

			$links = [];
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) ),
			__( 'Settings', 'oneaccess' )
		);

		return $links;
	}

	/**
	 * Add body classes for the admin area.
	 *
	 * @param string $classes Existing body classes.
	 */
	public function add_body_classes( $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// Cast to string in case it's null.
		$classes = $this->add_body_class_for_modal( (string) $classes, $current_screen );
		$classes = $this->add_body_class_for_missing_sites( (string) $classes, $current_screen );

		return $classes;
	}

	/**
	 * Add body class if the modal is going to be shown.
	 *
	 * @param string     $classes        Existing body classes.
	 * @param \WP_Screen $current_screen Current screen object.
	 */
	private function add_body_class_for_modal( string $classes, \WP_Screen $current_screen ): string {
		if ( 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return $classes;
		}

		// Add oneaccess-site-selection-modal class to body.
		$classes .= ' oneaccess-site-selection-modal ';
		return $classes;
	}

	/**
	 * Add body class for missing sites.
	 *
	 * @param string     $classes Existing body classes.
	 * @param \WP_Screen $current_screen Current screen object.
	 */
	private function add_body_class_for_missing_sites( string $classes, \WP_Screen $current_screen ): string { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- Required by WordPress hook signature.
		// Bail if the shared sites are already set.
		$shared_sites = Settings::get_shared_sites();
		if ( ! empty( $shared_sites ) ) {
			return $classes;
		}

		$classes .= ' oneaccess-missing-brand-sites ';
		return $classes;
	}
}
