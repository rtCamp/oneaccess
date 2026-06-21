<?php
/**
 * This is routes for brand sites REST API endpoints.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\Rest;

use OneAccess\Modules\Settings\Settings;

/**
 * Class Brand_Site_Controller
 */
class Brand_Site_Controller extends Abstract_REST_Controller {
	private const TIME_ZONE = 'timezone_string';

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/**
		 * Register a route to get user profile request data across all brand sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/all-profile-requests',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'all_profile_requests' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
				],
			]
		);

		/**
		 * Register a route to approve profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/approve-profile-request',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'approve_profile_request' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
					'args'                => [
						'site_name'  => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The name of the site where the profile request is made.', 'oneaccess' ),
						],
						'user_email' => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The email of the user whose profile request is being approved.', 'oneaccess' ),
						],
						'user_id'    => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The ID of the user whose profile request is being approved.', 'oneaccess' ),
						],
					],
				],
			]
		);

		/**
		 * Register a route to reject profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/reject-profile-request',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'reject_profile_request' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
					'args'                => [
						'site_name'         => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The name of the site where the profile request is made.', 'oneaccess' ),
						],
						'user_email'        => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The email of the user whose profile request is being rejected.', 'oneaccess' ),
						],
						'rejection_comment' => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The comment explaining why the profile request is being rejected.', 'oneaccess' ),
						],
					],
				],
			]
		);
	}

	/**
	 * Reject user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function reject_profile_request( \WP_REST_Request $request ): \WP_REST_Response {
		$site_name         = sanitize_text_field( $request->get_param( 'site_name' ) );
		$user_email        = sanitize_email( $request->get_param( 'user_email' ) );
		$rejection_comment = sanitize_text_field( $request->get_param( 'rejection_comment' ) );
		$request_id        = sanitize_text_field( $request->get_param( 'request_id' ) );

		if ( empty( $site_name ) || empty( $user_email ) || empty( $rejection_comment ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Site name, user email, and rejection comment are required.', 'oneaccess' ),
				],
				400
			);
		}

		// Get the site URL and API key from the global oneaccess_sites.
		$oneaccess_sites = Settings::get_shared_site_by_name( $site_name );
		$site_url        = $oneaccess_sites['url'] ?? '';
		$api_key         = $oneaccess_sites['api_key'] ?? '';

		$response = wp_safe_remote_post(
			$site_url . '/wp-json/' . self::NAMESPACE . '/reject-profile',
			[
				'headers' => [
					'X-OneAccess-Token' => $api_key,
				],
				'body'    => [
					'user_email'        => $user_email,
					'rejection_comment' => $rejection_comment,
					'request_id'        => $request_id,
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Failed to reject profile request.', 'oneaccess' ),
				],
				500
			);
		}

		return new \WP_REST_Response(
			[
				'success'           => true,
				'message'           => __( 'Profile request rejected successfully.', 'oneaccess' ),
				'site_name'         => $site_name,
				'user_email'        => $user_email,
				'rejection_comment' => $rejection_comment,
			],
			200
		);
	}

	/**
	 * Approve user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function approve_profile_request( \WP_REST_Request $request ): \WP_REST_Response {
		$site_name  = sanitize_text_field( $request->get_param( 'site_name' ) );
		$user_email = sanitize_email( $request->get_param( 'user_email' ) );
		$user_id    = absint( $request->get_param( 'user_id' ) );
		$request_id = sanitize_text_field( $request->get_param( 'request_id' ) );

		if ( empty( $site_name ) || empty( $user_email ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Site name and user email are required.', 'oneaccess' ),
				],
				400
			);
		}

		// Get the site URL and API key from the global oneaccess_sites.
		$oneaccess_sites = Settings::get_shared_site_by_name( $site_name );
		$site_url        = $oneaccess_sites['url'] ?? '';
		$api_key         = $oneaccess_sites['api_key'] ?? '';

		$reponse = wp_safe_remote_post(
			$site_url . '/wp-json/' . self::NAMESPACE . '/approve-profile',
			[
				'headers' => [
					'X-OneAccess-Token' => $api_key,
				],
				'body'    => [
					'user_id'    => $user_id,
					'user_email' => $user_email,
					'request_id' => $request_id,
				],
			]
		);

		if ( is_wp_error( $reponse ) || 200 !== wp_remote_retrieve_response_code( $reponse ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Failed to approve profile request.', 'oneaccess' ),
				],
				500
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Profile request approved successfully.', 'oneaccess' ),
				'user_id' => $user_id,
			],
			200
		);
	}

	/**
	 * Get user profile request data across all brand sites.
	 */
	public function all_profile_requests(): \WP_REST_Response {
		// get all sites data.
		$oneaccess_sites_info = Settings::get_shared_sites();

		$all_profile_requests = [];
		$fetched_sites        = [];
		$pending_requests     = 0;

		foreach ( $oneaccess_sites_info as $site ) {
			if ( in_array( $site['url'], $fetched_sites, true ) ) {
				continue; // Skip if already fetched.
			}
			$fetched_sites[] = $site['url'];
			$timezone        = get_option( self::TIME_ZONE ) ?? 'UTC';
			$request_url     = $site['url'] . '/wp-json/' . self::NAMESPACE . '/profile-requests?' . wp_date( 'd-m-Y H:i:s', null, $timezone );
			$api_key         = $site['api_key'] ?? '';

			$response = wp_safe_remote_get(
				$request_url,
				[
					'headers' => [
						'X-OneAccess-Token' => $api_key,
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );
			if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
				continue;
			}

			// add site name to each data.
			foreach ( $data['data'] as &$request ) {
				$request['site_name'] = $site['name'] ?? 'Unknown Site';
				if ( ! isset( $request['status'] ) || 'pending' !== $request['status'] ) {
					continue;
				}

				++$pending_requests;
			}

			$all_profile_requests = array_merge( $all_profile_requests, $data['data'] );
		}

		// sort all profile requests by requested_at time.
		usort(
			$all_profile_requests,
			static function ( $a, $b ) {
				return strtotime( $b['requested_at'] ) - strtotime( $a['requested_at'] );
			}
		);

		return new \WP_REST_Response(
			[
				'success'          => true,
				'profile_requests' => $all_profile_requests,
				'count'            => $pending_requests,
			],
			200
		);
	}
}
