<?php
/**
 * This is routes for Settings options.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\Rest;

use OneAccess\Modules\Core\User_Roles;
use OneAccess\Modules\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Basic_Options_Controller
 */
class Basic_Options_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/**
		 * Register a route to get site type and set site type.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/site-type',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_site_type' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
					'args'                => [
						'site_type' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		/**
		 * Register a route for health-check.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/health-check',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'health_check' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
			]
		);

		/**
		 * Register a route to get api key option.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/secret-key',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_secret_key' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'regenerate_secret_key' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
				],
			]
		);

		/**
		 * Register a route to manage governing site.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/governing-site',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_governing_site' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove_governing_site' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
				],
			],
		);
	}

	/**
	 * Set the site type.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_site_type( WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$site_type = sanitize_text_field( $request->get_param( 'site_type' ) );
		$success   = update_option( Settings::OPTION_SITE_TYPE, $site_type, false );

		// Create user roles based on site type.
		User_Roles::create_brand_admin_role();
		User_Roles::create_network_admin_role();

		// Get current user once.
		$current_user = wp_get_current_user();
		$user_roles   = (array) $current_user->roles;

		// If it's a brand site, add brand admin role.
		if ( Settings::is_consumer_site() ) {
			if ( ! in_array( User_Roles::BRAND_ADMIN, $user_roles, true ) ) {
				$current_user->add_role( User_Roles::BRAND_ADMIN );
			}

			// Remove network admin role if exists.
			if ( in_array( User_Roles::NETWORK_ADMIN, $user_roles, true ) ) {
				$current_user->remove_role( User_Roles::NETWORK_ADMIN );
			}
		}

		// If it's a governing site, add network admin role.
		if ( Settings::is_governing_site() ) {
			if ( ! in_array( User_Roles::NETWORK_ADMIN, $user_roles, true ) ) {
				$current_user->add_role( User_Roles::NETWORK_ADMIN );
			}

			// Remove brand admin role if exists.
			if ( in_array( User_Roles::BRAND_ADMIN, $user_roles, true ) ) {
				$current_user->remove_role( User_Roles::BRAND_ADMIN );
			}
		}

		return rest_ensure_response(
			[
				'success'   => $success,
				'site_type' => $site_type,
			]
		);
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function health_check(): WP_REST_Response|\WP_Error {

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Health check passed successfully.', 'oneaccess' ),
			]
		);
	}

	/**
	 * Get governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_governing_site(): WP_REST_Response|\WP_Error {
		$governing_site_url = Settings::get_parent_site_url();

		return rest_ensure_response(
			[
				'success'            => true,
				'governing_site_url' => $governing_site_url,
			]
		);
	}

	/**
	 * Remove governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_governing_site(): WP_REST_Response|\WP_Error {
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Governing site removed successfully.', 'oneaccess' ),
			]
		);
	}

	/**
	 * Get the secret key.
	 */
	public function get_secret_key(): \WP_REST_Response|\WP_Error {
		$secret_key = Settings::get_api_key();

		return new \WP_REST_Response(
			[
				'success'    => true,
				'secret_key' => $secret_key,
			]
		);
	}

	/**
	 * Regenerate the secret key.
	 */
	public function regenerate_secret_key(): \WP_REST_Response|\WP_Error {

		$regenerated_key = Settings::regenerate_api_key();

		return new \WP_REST_Response(
			[
				'success'    => true,
				'message'    => __( 'Secret key regenerated successfully.', 'oneaccess' ),
				'secret_key' => $regenerated_key,
			]
		);
	}
}
