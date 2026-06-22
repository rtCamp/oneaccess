<?php
/**
 * This is routes for Governing Site REST API.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\Rest;

use OneAccess\Modules\Core\DB;
use OneAccess\Modules\Settings\Settings;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class Governing_Site_Controller
 */
class Governing_Site_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/**
		 * Register a route to create user.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/new-users',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_user' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
					'args'                => [
						'username'  => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Username for the new user.', 'oneaccess' ),
						],
						'email'     => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Email address for the new user.', 'oneaccess' ),
						],
						'password'  => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Password for the new user.', 'oneaccess' ),
						],
						'full_name' => [
							'required'    => false,
							'type'        => 'string',
							'description' => __( 'Full name of the new user.', 'oneaccess' ),
						],
						'role'      => [
							'required'    => false,
							'type'        => 'string',
							'default'     => 'subscriber',
							'description' => __( 'Role for the new user.', 'oneaccess' ),
						],
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_users' ],
					'permission_callback' => [ self::class, 'permission_callback' ],
					'args'                => [
						'paged'        => [
							'required'          => false,
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						],
						'per_page'     => [
							'required'          => false,
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						],
						'search_query' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'role'         => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'site'         => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		/**
		 * Register a route to generate strong password.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/generate-strong-password',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'generate_strong_password' ],
				'permission_callback' => [ self::class, 'permission_callback' ],
			]
		);

		/**
		 * Register a route to create users for sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/create-user-for-sites',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_users_for_sites' ],
				'permission_callback' => [ self::class, 'permission_callback' ],
				'args'                => [
					'userdata' => [
						'required' => true,
						'type'     => 'object',
					],
					'sites'    => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);

		/**
		 * Register a route to update user roles for multiple sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/update-user-roles',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_user_roles_for_sites' ],
				'permission_callback' => [ self::class, 'permission_callback' ],
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'username' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'roles'    => [
						'required' => true,
						'type'     => 'object',
					],
				],
			]
		);

		/**
		 * Register a route to update user role.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/update-user',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_user' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'username' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'role'     => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		/**
		 * Register a route to add user to multiple sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/add-user-to-sites',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_user_to_sites' ],
				'permission_callback' => [ self::class, 'permission_callback' ],
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'username' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'fullName' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'password' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'sites'    => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);

		/**
		 * Register a route to approve user profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/approve-profile',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'approve_profile' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
				'args'                => [
					'user_id' => [
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'The user id of the user whose profile request is being approved.', 'oneaccess' ),
					],
				],
			]
		);

		/**
		 * Register a route to reject user profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/reject-profile',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'reject_profile' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
				'args'                => [
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
			]
		);

		/**
		 * Register a route to delete user from multiple sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/delete-user-from-sites',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_user_from_sites' ],
				'permission_callback' => [ self::class, 'permission_callback' ],
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'username' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'sites'    => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);

		/**
		 * Registe a route to delete user.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/delete-user',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_user' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
				'args'                => [
					'email'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
					'username' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Delete user.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function delete_user( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );

		if ( empty( $email ) || empty( $username ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Email and username are required.', 'oneaccess' ),
				],
				400
			);
		}

		// Get the user by email or login.
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $email );
		}
		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User not found.', 'oneaccess' ),
				],
				404
			);
		}

		// if function wp_delete_user is not available then include user.php file.
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		wp_delete_user( (int) $user->ID, 0 );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'User deleted successfully.', 'oneaccess' ),
			],
			200
		);
	}

	/**
	 * Delete user from multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function delete_user_from_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$sites    = $request->get_param( 'sites' );

		if ( empty( $email ) || empty( $username ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Email, username and sites are required.', 'oneaccess' ),
				],
				400
			);
		}

		// Validate sites.
		if ( ! is_array( $sites ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid sites data.', 'oneaccess' ),
				],
				400
			);
		}

		$response_data        = [];
		$oneaccess_sites_info = Settings::get_shared_sites();
		$processed_sites      = [];
		$error_log            = [];
		$user_delete_results  = [];

		foreach ( $sites as $site ) {

			// Skip duplicate or invalid sites.
			if ( empty( $site['site_url'] ) || in_array( $site['site_url'], $processed_sites, true ) ) {
				if ( ! empty( $site['site_url'] ) ) {
					$processed_sites[] = $site['site_url'];
				}
				continue;
			}

			$request_url = $site['site_url'] . '/wp-json/' . self::NAMESPACE . '/delete-user';
			$api_key     = $oneaccess_sites_info[ $site['site_url'] ]['api_key'] ?? '';
			$response    = wp_safe_remote_request(
				$request_url,
				[
					'method'  => 'DELETE',
					'body'    => [
						'username' => $username,
						'email'    => $email,
					],
					'headers' => [
						'X-OneAccess-Token' => $api_key,
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = [
					'site_name' => $site['site_url'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Error deleting user from site %s.', 'oneaccess' ),
						esc_html( $site['site_url'] ?? '' )
					),
				];
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				$error_log[] = [
					'site_name' => $site['site_url'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Failed to delete user from site %s.', 'oneaccess' ),
						esc_html( $site['site_url'] ?? '' )
					),
				];
				$error_log[] = $response;
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! $response_body['success'] ) {
				$error_log[] = [
					'site_name' => $site['site_url'] ?? '',
					'message'   => $response_body['message'] ?? __( 'Failed to delete user from site.', 'oneaccess' ),
				];
				continue;
			}

			$response_data[] = [
				'site'    => $site['site_url'],
				'message' => $response_body['message'] ?? __( 'User deleted successfully.', 'oneaccess' ),
			];

			// delete from deduplicated users table.
			$user_delete_results[] = DB::delete_user_from_deduplicated_users( $email, $site['site_url'] );
		}

		return new \WP_REST_Response(
			[
				'success' => count( $error_log ) === 0,
				'message' => count( $error_log ) === 0 ? __( 'User deleted from sites successfully.', 'oneaccess' ) : __( 'User could not be deleted from some sites.', 'oneaccess' ),
				'data'    => [
					'email'               => $email,
					'username'            => $username,
					'sites'               => $sites,
					'response_data'       => $response_data,
					'error_log'           => $error_log,
					'user_delete_results' => $user_delete_results,
				],
			],
			200
		);
	}

	/**
	 * Reject user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function reject_profile( WP_REST_Request $request ): \WP_REST_Response {
		$user_email        = sanitize_email( $request->get_param( 'user_email' ) );
		$rejection_comment = sanitize_text_field( $request->get_param( 'rejection_comment' ) );
		$request_id        = absint( $request->get_param( 'request_id' ) );

		if ( empty( $user_email ) || empty( $rejection_comment ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User email and rejection comment are required.', 'oneaccess' ),
				],
				400
			);
		}

		// Get the user by email.
		$user = get_user_by( 'email', $user_email );
		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User not found.', 'oneaccess' ),
				],
				404
			);
		}

		// get user profile request data.
		$profile_requests_data = DB::get_profile_request_by_id( $request_id );

		if ( ! $profile_requests_data || 'pending' !== $profile_requests_data['status'] ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'No pending profile request found for this user.', 'oneaccess' ),
				],
				404
			);
		}

		// Update the profile request status to rejected with comment.
		DB::reject_profile_request_by_id( $request_id, $rejection_comment );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Profile request rejected successfully.', 'oneaccess' ),
			],
			200
		);
	}

	/**
	 * Approve user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function approve_profile( \WP_REST_Request $request ): \WP_REST_Response {

		$user_id    = absint( $request->get_param( 'user_id' ) );
		$user_email = sanitize_email( $request->get_param( 'user_email' ) );
		$request_id = sanitize_text_field( $request->get_param( 'request_id' ) );

		if ( empty( $user_id ) || empty( $request_id ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User ID and request ID are required.', 'oneaccess' ),
					'data'    => [
						'user_id'    => $user_id,
						'request_id' => $request_id,
					],
				],
				400
			);
		}

		// Get the user by user_login.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			$user = get_user_by( 'email', $user_email );

			if ( ! $user ) {
				return new \WP_REST_Response(
					[
						'success' => false,
						'message' => __( 'User not found.', 'oneaccess' ),
					],
					404
				);
			}
		}

		// get user profile request data.
		$profile_requests_data = DB::get_profile_request_by_id( (int) $request_id );

		if ( ! $profile_requests_data || 'pending' !== $profile_requests_data['status'] ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'No pending profile request found for this user.', 'oneaccess' ),
				],
				404
			);
		}

		// Update the profile request status to approved.
		DB::approve_profile_request_by_id( (int) $request_id );

		// Update user meta with the new profile data.
		if ( ! empty( $profile_requests_data['request_data'] ) ) {
			$user_data = $profile_requests_data['request_data']['data'] ?? [];

			foreach ( $user_data as $key => $value ) {
				if ( 'email' === $key ) {
					wp_update_user(
						[
							'ID'         => $user->ID,
							'user_email' => $value['new'],
						]
					);
					continue;
				}

				if ( 'username' === $key ) {
					wp_update_user(
						[
							'ID'            => $user->ID,
							'user_nicename' => $value['new'],
						]
					);
					continue;
				}

				if ( 'url' === $key ) {
					wp_update_user(
						[
							'ID'       => $user->ID,
							'user_url' => $value['new'],
						]
					);
					continue;
				}

				wp_update_user(
					[
						'ID' => $user->ID,
						$key => $value['new'],
					]
				);
			}

			$user_metadata = $profile_requests_data['request_data']['metadata'] ?? [];

			foreach ( $user_metadata as $meta_key => $meta_value ) {
				update_user_meta( $user->ID, $meta_key, $meta_value['new'] );
			}
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Profile request approved successfully.', 'oneaccess' ),
			],
			200
		);
	}

	/**
	 * Add user to multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function add_user_to_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$email     = sanitize_email( $request->get_param( 'email' ) );
		$username  = sanitize_text_field( $request->get_param( 'username' ) );
		$full_name = sanitize_text_field( $request->get_param( 'fullName' ) );
		$password  = sanitize_text_field( $request->get_param( 'password' ) );
		$sites     = $request->get_param( 'sites' );

		if ( empty( $email ) || empty( $username ) || empty( $full_name ) || empty( $password ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Email, username, full name, password and sites are required.', 'oneaccess' ),
				],
				400
			);
		}

		// Validate sites.
		if ( ! is_array( $sites ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid sites data.', 'oneaccess' ),
				],
				400
			);
		}

		$response_data        = [];
		$oneaccess_sites_info = Settings::get_shared_sites();
		$processed_sites      = [];
		$error_log            = [];

		foreach ( $sites as $site_url ) {
			$url = ! empty( $site_url['url'] ) ? untrailingslashit( $site_url['url'] ) : '';
			if ( ! isset( $oneaccess_sites_info[ $url ] ) ) {
				$error_log[] = [
					'site_name' => $url ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Site %s not found in OneAccess sites.', 'oneaccess' ),
						esc_html( $url )
					),
				];
				continue;
			}

			// Skip duplicate or invalid sites.
			if ( empty( $site_url['url'] ) || in_array( $site_url['url'], $processed_sites, true ) ) {
				if ( ! empty( $site_url['url'] ) ) {
					$processed_sites[] = $site_url['url'];
				}
				continue;
			}

			$api_key     = $oneaccess_sites_info[ $url ]['api_key'] ?? '';
			$user_role   = $site_url['role'] ?? 'subscriber';
			$request_url = $site_url['url'] . '/wp-json/' . self::NAMESPACE . '/new-users';

			$response = wp_safe_remote_post(
				$request_url,
				[
					'method'  => 'POST',
					'body'    => [
						'username'  => $username,
						'email'     => $email,
						'password'  => $password,
						'full_name' => $full_name,
						'role'      => $user_role,
					],
					'headers' => [
						'X-OneAccess-Token' => $api_key,
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = [
					'site_name' => $site_url['url'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Error adding user to site %s.', 'oneaccess' ),
						esc_html( $site_url['url'] ?? '' )
					),
				];
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 201 !== $response_code ) {
				$error_log[] = [
					'site_name' => $site_url['url'] ?? '',
					'message'   => sprintf(
							/* translators: %s is the site URL */
						__( 'Failed to add user to site %s.', 'oneaccess' ),
						esc_html( $site_url['url'] ?? '' )
					),
				];
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $response_body['success'] ) {
				$error_log[] = [
					'site_name' => $site_url['url'] ?? '',
					'message'   => $response_body['message'] ?? __( 'Failed to add user to site.', 'oneaccess' ),
				];
				continue;
			}

			$response_data[] = [
				'site'    => $oneaccess_sites_info[ $site_url['url'] ]['name'] ?? $site_url['url'],
				'status'  => 'success',
				'message' => __( 'User added successfully.', 'oneaccess' ),
			];

			$first_name = '';
			$last_name  = '';

			// split full name into first and last name.
			$name_parts = explode( ' ', $full_name );

			$first_name = $name_parts[0] ?? '';
			$last_name  = isset( $name_parts[1] ) ? implode( ' ', array_slice( $name_parts, 1 ) ) : '';

			$user_id   = isset( $response_body['data']['user_id'] ) ? absint( $response_body['data']['user_id'] ) : 0;
			$user_role = isset( $response_body['data']['role'] ) ? sanitize_text_field( $response_body['data']['role'] ) : 'subscriber';

			// add user to deduplicated users table.
			DB::add_user_to_deduplicated_users(
				$email,
				$first_name,
				$last_name,
				$site_url['name'] ?? '',
				$site_url['url'] ?? '',
				$user_id,
				[ $user_role ],
			);
		}

		return new \WP_REST_Response(
			[
				'success' => count( $error_log ) === 0,
				'message' => count( $error_log ) === 0 ? __( 'User added to sites successfully.', 'oneaccess' ) : __( 'User could not be added to some sites.', 'oneaccess' ),
				'data'    => [
					'email'         => $email,
					'username'      => $username,
					'full_name'     => $full_name,
					'sites'         => $sites,
					'response_data' => $response_data,
					'error_log'     => $error_log,
				],
			],
			200
		);
	}

	/**
	 * Update user.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function update_user( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$role     = sanitize_text_field( $request->get_param( 'role' ) );

		if ( empty( $email ) || empty( $username ) || empty( $role ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Email, username and role are required.', 'oneaccess' ),
				],
				400
			);
		}

		// Update user role.
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User not found.', 'oneaccess' ),
				],
				404
			);
		}

		// get all user roles.
		$all_roles = wp_roles()->roles;
		if ( ! array_key_exists( $role, $all_roles ) ) {
			$role = 'subscriber';
		}

		// Update user role.
		wp_update_user(
			[
				'ID'   => $user->ID,
				'role' => $role,
			]
		);

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'User role updated successfully.', 'oneaccess' ),
			],
			200
		);
	}

	/**
	 * Update user roles for multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function update_user_roles_for_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$roles    = $request->get_param( 'roles' );

		if ( empty( $email ) || empty( $username ) || empty( $roles ) || ! is_array( $roles ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Email, username and roles are required.', 'oneaccess' ),
				],
				400
			);
		}

		$response_data        = [];
		$oneaccess_sites_info = Settings::get_shared_sites();
		$processed_sites      = [];
		$error_log            = [];
		$db_results           = [];

		foreach ( $roles as $key => $value ) {
			$site_key = untrailingslashit( $key );
			$site     = (array) ( $oneaccess_sites_info[ $site_key ] ?? [] );
			$site_url = ! empty( $site['url'] ) ? trailingslashit( $site['url'] ) : '';
			$api_key  = $site['api_key'] ?? '';
			$new_role = $value;

			// Skip duplicate or invalid sites.
			if ( empty( $site_url ) || in_array( $site_url, $processed_sites, true ) ) {
				continue;
			}
			$processed_sites[] = $site_url;

			$request_url = $site_url . '/wp-json/' . self::NAMESPACE . '/update-user';

			$response = wp_safe_remote_post(
				$request_url,
				[
					'method'  => 'POST',
					'body'    => [
						'username' => $username,
						'email'    => $email,
						'role'     => $new_role,
					],
					'headers' => [
						'X-OneAccess-Token' => $api_key,
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = [
					'site_name' => $site_url,
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Error updating user role on site %s.', 'oneaccess' ),
						esc_html( $site_url )
					),
				];
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $response_code ) {
				$error_log[] = [
					'site_name' => $site_url,
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Failed to update user role on site %s.', 'oneaccess' ),
						esc_html( $site_url )
					),
				];
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $response_body['success'] ) {
				$error_log[] = [
					'site_name' => $site_url,
					'message'   => $response_body['message'] ?? __( 'Failed to update user role on site.', 'oneaccess' ),
				];
				continue;
			}

			$response_data[] = [
				'site'    => $site_url,
				'status'  => 'success',
				'message' => __( 'User role updated successfully.', 'oneaccess' ),
			];

			// update role in deduplicated users table.
			$db_results[] = DB::update_user_role_in_deduplicated_users( $email, $new_role, $site_url );
		}

		return new \WP_REST_Response(
			[
				'success' => count( $error_log ) === 0,
				'message' => count( $error_log ) === 0 ? __( 'User roles updated successfully.', 'oneaccess' ) : __( 'User roles could not be updated on some sites.', 'oneaccess' ),
				'data'    => [
					'email'         => $email,
					'username'      => $username,
					'roles'         => $roles,
					'response_data' => $response_data,
					'error_log'     => $error_log,
					'db_results'    => $db_results,
				],
			],
			200
		);
	}

	/**
	 * Create a new user.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function create_user( \WP_REST_Request $request ): \WP_REST_Response {
		$username  = sanitize_user( $request->get_param( 'username' ) );
		$email     = sanitize_email( $request->get_param( 'email' ) );
		$password  = sanitize_text_field( $request->get_param( 'password' ) );
		$full_name = sanitize_text_field( $request->get_param( 'full_name' ) );
		$role      = sanitize_text_field( $request->get_param( 'role' ) );

		if ( empty( $username ) || empty( $email ) || empty( $password ) || empty( $full_name ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Username, email, full name and password are required.', 'oneaccess' ),
				],
				400
			);
		}

		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid email address.', 'oneaccess' ),
				],
				400
			);
		}

		// Check if user already exists.
		if ( username_exists( $username ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Username already exists, please user different username.', 'oneaccess' ),
				],
				400
			);
		}

		// Check if email already exists.
		if ( email_exists( $email ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Email already exists, please use a different email.', 'oneaccess' ),
				],
				400
			);
		}

		// Create the user.
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => $user_id->get_error_message(),
				],
				500
			);
		}

		// get all user roles.
		$all_roles = wp_roles()->roles;
		if ( ! array_key_exists( $role, $all_roles ) ) {
			$role = 'subscriber';
		}

		// Set the user's full name and role.
		wp_update_user(
			[
				'ID'            => $user_id,
				'display_name'  => $full_name,
				'user_nicename' => sanitize_title( $full_name ),
				'first_name'    => explode( ' ', $full_name )[0] ?: '',
				'last_name'     => explode( ' ', $full_name )[1] ?: '',
				'role'          => $role ?: 'subscriber',
			]
		);

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'User created successfully.', 'oneaccess' ),
				'data'    => [
					'user_id'   => $user_id,
					'username'  => $username,
					'email'     => $email,
					'full_name' => $full_name,
					'role'      => $role,
				],
			],
			201
		);
	}

	/**
	 * Get users.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function get_users( WP_REST_Request $request ): \WP_REST_Response {

		global $wpdb;

		$paged        = intval( $request->get_param( 'paged' ) ) ?: 1;
		$per_page     = intval( $request->get_param( 'per_page' ) ) ?: 20;
		$search_query = sanitize_text_field( $request->get_param( 'search_query' ) ) ?? '';
		$role         = sanitize_text_field( $request->get_param( 'role' ) ) ?? '';
		$site         = sanitize_text_field( $request->get_param( 'site' ) ) ?? '';

		// Calculate offset.
		$offset = ( $paged - 1 ) * $per_page;

		// Table name.
		$table_name = $wpdb->prefix . DB::DEDUPLICATED_USERS_TABLE;

		// Build WHERE conditions.
		$where_conditions = [];
		$prepare_values   = [];

		// Search query - search in email, first_name, last_name.
		if ( ! empty( $search_query ) ) {
			$where_conditions[] = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, " ", last_name) LIKE %s)';
			$search_like        = '%' . $wpdb->esc_like( $search_query ) . '%';
			$prepare_values[]   = $search_like;
			$prepare_values[]   = $search_like;
			$prepare_values[]   = $search_like;
			$prepare_values[]   = $search_like;
		}

		// Role filter - search within sites_info.
		if ( ! empty( $role ) ) {
			$where_conditions[] = "JSON_SEARCH(sites_info, 'one', %s, NULL, '$[*].roles[*]') IS NOT NULL";
			$prepare_values[]   = $role;
		}

		// Site filter - search by site_url or site_name in sites_info.
		if ( ! empty( $site ) ) {
			$where_conditions[] = "(JSON_SEARCH(sites_info, 'one', %s, NULL, '$[*].site_url') IS NOT NULL OR JSON_SEARCH(sites_info, 'one', %s, NULL, '$[*].site_name') IS NOT NULL)";
			$site_like          = '%' . $wpdb->esc_like( $site ) . '%';
			$prepare_values[]   = $site_like;
			$prepare_values[]   = $site_like;
		}

		// Combine WHERE conditions.
		$where_clause = '';
		if ( ! empty( $where_conditions ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
		}

		// Build count query.
		$count_query = "SELECT COUNT(*) FROM $table_name $where_clause";

		// Prepare count query if there are values.
		if ( ! empty( $prepare_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $prepare_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is prepared here.
		}

		$total_users = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query is prepared above.

		// Build main query.
		$query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";

		// Add pagination values to prepare.
		$prepare_values[] = $per_page;
		$prepare_values[] = $offset;

		// Prepare and execute query.
		$prepared_query = $wpdb->prepare( $query, $prepare_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is prepared here.
		$users          = $wpdb->get_results( $prepared_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- query is prepared above.

		// Process users data - decode sites_info.
		$processed_users = [];
		foreach ( $users as $user ) {
			$user_data = [
				'id'         => $user->id,
				'email'      => $user->email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
				'sites_info' => json_decode( $user->sites_info, true ),
				'created_at' => $user->created_at,
				'updated_at' => $user->updated_at,
			];

			/**
			 * Filter by role within sites_info.
			 */
			if ( ! empty( $role ) ) {
				$has_role = false;
				foreach ( $user_data['sites_info'] as $site_info ) {
					if ( isset( $site_info['roles'] ) && in_array( $role, $site_info['roles'], true ) ) {
						$has_role = true;
						break;
					}
				}
				if ( ! $has_role ) {
					continue;
				}
			}

			$processed_users[] = $user_data;
		}

		// get global oneaccess_sites variable.
		$oneaccess_sites = Settings::get_shared_sites();

		// Update processed users site names from oneaccess_sites.
		foreach ( $processed_users as $key => $user ) {
			// Loop through sites in reverse to safely unset.
			$site_keys = array_keys( $user['sites_info'] );

			foreach ( array_reverse( $site_keys ) as $site_key ) {
				$site_info = $user['sites_info'][ $site_key ];
				foreach ( $oneaccess_sites as $site ) {
					if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $site['url'] ) ) {
						$processed_users[ $key ]['sites_info'][ $site_key ]['site_name'] = $site['name'];
						break; // Found match, no need to continue.
					}
				}
			}
		}

		// Calculate total pages.
		$total_pages = ceil( $total_users / $per_page );

		return new \WP_REST_Response(
			[
				'success'    => true,
				'users'      => $processed_users,
				'pagination' => [
					'total_users'  => $total_users,
					'total_pages'  => $total_pages,
					'current_page' => $paged,
					'per_page'     => $per_page,
					'has_more'     => $paged < $total_pages,
				],
				'filters'    => [
					'search_query' => $search_query,
					'role'         => $role,
					'site'         => $site,
					'paged'        => $paged,
					'per_page'     => $per_page,
				],
			],
			200
		);
	}

	/**
	 * Generate a strong password.
	 */
	public function generate_strong_password(): \WP_REST_Response {

		$password = wp_generate_password( 32, true, true );

		// at random position add 0-9 any digit to make sure password always contains a digit.
		$random_position              = wp_rand( 0, 31 );
		$password[ $random_position ] = wp_rand( 0, 9 );

		return new \WP_REST_Response(
			[
				'success'  => true,
				'password' => $password,
			],
			200
		);
	}

	/**
	 * Create users for multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function create_users_for_sites( \WP_REST_Request $request ): \WP_REST_Response {

		$userdata = $request->get_param( 'userdata' );
		$sites    = $request->get_param( 'sites' );

		if ( empty( $userdata ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User data and sites are required.', 'oneaccess' ),
				],
				400
			);
		}

		// validate userdate.
		if ( ! is_array( $userdata ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid user data.', 'oneaccess' ),
				],
				400
			);
		}

		// validate sites.
		if ( ! is_array( $sites ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid sites data.', 'oneaccess' ),
				],
				400
			);
		}

		// validate userinfo.
		if ( ! isset( $userdata['username'] ) || empty( $userdata['username'] ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Username is required.', 'oneaccess' ),
				],
				400
			);
		}
		if ( ! isset( $userdata['email'] ) || empty( $userdata['email'] ) || ! is_email( $userdata['email'] ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Please enter valid email id.', 'oneaccess' ),
				],
				400
			);
		}
		if ( ! isset( $userdata['password'] ) || empty( $userdata['password'] ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Password is required.', 'oneaccess' ),
				],
				400
			);
		}
		if ( ! isset( $userdata['fullName'] ) || empty( $userdata['fullName'] ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Full name is required.', 'oneaccess' ),
				],
				400
			);
		}

		$oneaccess_sites_info = Settings::get_shared_sites();
		$processed_sites      = [];
		$response_data        = [];
		$error_log            = [];

		foreach ( $sites as $site ) {
			$site_url = ! empty( $site['url'] ) ? untrailingslashit( $site['url'] ) : '';

			// Skip duplicate or invalid sites.
			if ( empty( $site_url ) || in_array( $site_url, $processed_sites, true ) ) {
				continue;
			}
			$processed_sites[] = $site_url;

			$api_key   = $oneaccess_sites_info[ $site_url ]['api_key'] ?? '';
			$site_name = $oneaccess_sites_info[ $site_url ]['name'] ?? '';
			if ( empty( $api_key ) ) {
				$error_log[] = [
					'site_name' => $site_name,
					'message'   => sprintf(
						/* translators: %s is the site name */
						__( 'API key not found for site %s.', 'oneaccess' ),
						$site_name
					),
				];
				continue;
			}

			$request_url = $oneaccess_sites_info[ $site_url ]['url'] . '/wp-json/' . self::NAMESPACE . '/new-users';

			$response = wp_safe_remote_post(
				$request_url,
				[
					'method'  => 'POST',
					'body'    => [
						'username'  => $userdata['username'],
						'email'     => $userdata['email'],
						'password'  => $userdata['password'],
						'full_name' => $userdata['fullName'],
						'role'      => $userdata['role'] ?? 'subscriber',
					],
					'headers' => [
						'X-OneAccess-Token' => $api_key,
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = [
					'site_name' => $site_name,
					'message'   => sprintf(
						/* translators: %s is the site name */
						__( 'Error creating user on site %s.', 'oneaccess' ),
						$site_name
					),
				];
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $response_body['success'] ) ) {
				$error_log[] = [
					'site_name' => $site_name,
					'message'   => $response_body['message'] ?? sprintf(
						/* translators: %s is the site name */
						__( 'Failed to create user on site %s.', 'oneaccess' ),
						$site_name
					),
				];
				continue;
			}

			$response_data[] = [
				'site'    => $site_name,
				'status'  => 'success',
				'message' => __( 'User created successfully.', 'oneaccess' ),
			];
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'User creation process completed.', 'oneaccess' ),
				'data'    => [
					'response_data' => $response_data,
					'error_log'     => $error_log,
				],
			],
			200
		);
	}
}
