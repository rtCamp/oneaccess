<?php
/**
 * Hooks related to governing site & brand sites.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\Core;

use OneAccess\Contracts\Interfaces\Registrable;
use OneAccess\Modules\Rest\Actions_Controller;
use OneAccess\Modules\Settings\Settings;

/**
 * Class Hooks
 *
 * Manages hooks for consumer sites to sync users with the governing site.
 */
class Hooks implements Registrable {
	/**
	 * Actions controller instance.
	 *
	 * @var \OneAccess\Modules\Rest\Actions_Controller
	 */
	private Actions_Controller $actions_controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->actions_controller = new Actions_Controller();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// Needed on both site types, so it must come before the consumer check below.
		add_filter( 'http_request_host_is_external', [ $this, 'allow_oneaccess_host' ], 10, 2 );

		// Early return if this is not a consumer site.
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		// Trigger bulk user deduplication when governing site is configured.
		add_action( 'oneaccess_governing_site_configured', [ $this, 'user_deduplication' ] );

		/**
		 * Send individual users to governing site when they are created.
		 *
		 * Using profile_update instead of user_register as usermeta might not be set during user_register.
		 *
		 * @see https://developer.wordpress.org/reference/hooks/user_register/#more-information
		 */
		add_action( 'profile_update', [ $this->actions_controller, 'send_single_user_for_deduplication' ], 99 );
	}

	/**
	 * Trigger user deduplication process.
	 *
	 * This will avoid return type issue with phpstan.
	 */
	public function user_deduplication(): void {
		$this->actions_controller->send_users_for_deduplication();
	}

	/**
	 * Allow outbound requests to the configured OneAccess sites.
	 *
	 * @internal Hook callback
	 *
	 * @param bool   $is_external Whether the host is considered external.
	 * @param string $host        Host name of the request.
	 */
	public function allow_oneaccess_host( $is_external, $host ): bool {
		if ( ! empty( $is_external ) ) {
			return true;
		}

		$urls   = array_column( Settings::get_shared_sites(), 'url' );
		$urls[] = (string) Settings::get_parent_site_url();

		foreach ( $urls as $url ) {
			if ( ! empty( $url ) && wp_parse_url( $url, PHP_URL_HOST ) === $host ) {
				return true;
			}
		}

		return false;
	}
}
