<?php
/**
 * This is to handle profile update requests.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\User;

use OneAccess\Contracts\Interfaces\Registrable;
use OneAccess\Modules\Core\DB;
use OneAccess\Modules\Core\User_Roles;
use OneAccess\Modules\Settings\Settings;

/**
 * Class Profile_Request
 */
class Profile_Request implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// early return if this is not a brand site.
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		// store personal profile update and edit user profile request.
		add_action( 'personal_options_update', [ $this, 'store_profile_update_request' ] );
		add_action( 'edit_user_profile_update', [ $this, 'store_profile_update_request' ] );

		// get current user.
		$current_user = wp_get_current_user();

		// check if its brand admin else return.
		if ( ! in_array( User_Roles::BRAND_ADMIN, $current_user->roles, true ) ) {
			return;
		}

		// add custom user column to indicate profile request status.
		add_filter( 'manage_users_columns', [ $this, 'add_profile_request_status_column' ] );

		// add custom user column content.
		add_filter( 'manage_users_custom_column', [ $this, 'render_profile_request_status_column' ], 10, 3 );
	}

	/**
	 * Store profile update request.
	 *
	 * @param int $user_id User ID.
	 */
	public function store_profile_update_request( int $user_id ): void {

		// Prevent multiple executions for the same request.
		static $processed = [];
		if ( isset( $processed[ $user_id ] ) ) {
			return;
		}
		$processed[ $user_id ] = true;

		// get profile request data.
		$profile_request_data = DB::get_pending_profile_request_by_user_id( $user_id );
		if ( ! is_array( $profile_request_data ) ) {
			$profile_request_data = [];
		}

		if ( ! empty( $profile_request_data ) ) {
			// if there is already a pending request, do not create a new one.
			return;
		}

		// get $_POST data and compare with existing user data & user meta.
		$post_data = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is to intercept profile update request before updating into DB.
		$user_data = get_userdata( $user_id );

		if ( ! $user_data ) {
			return;
		}

		$user_meta = get_user_meta( $user_id );

		$updated_metadata = [];
		$fields_to_check  = [
			'admin_color',
			'first_name',
			'last_name',
			'nickname',
			'facebook',
			'instagram',
			'linkedin',
			'myspace',
			'pinterest',
			'soundcloud',
			'tumblr',
			'wikipedia',
			'twitter',
			'youtube',
			'description',
		];

		foreach ( $fields_to_check as $field ) {
			if ( ! isset( $post_data[ $field ] ) ) {
				continue;
			}

			$post_user_data = $post_data[ $field ] ?? '';
			$user_meta_data = $user_meta[ $field ] ?? '';
			if ( is_array( $user_meta_data ) ) {
				$user_meta_data = $user_meta_data[0] ?? '';
			}
			if ( $post_user_data === $user_meta_data ) {
				continue;
			}

			$updated_metadata[ $field ] = [
				'old' => self::sanitize_user_fields( $field, isset( $user_meta[ $field ] ) ? $user_meta[ $field ][0] : '' ),
				'new' => self::sanitize_user_fields( $field, $post_data[ $field ] ),
			];
			$post_data[ $field ]        = $user_meta[ $field ][0] ?? '';
		}

		$updated_data = [];

		if ( isset( $post_data['display_name'] ) && $post_data['display_name'] !== $user_data->display_name ) {
			$updated_data['display_name'] = [
				'old' => self::sanitize_user_fields( 'display_name', $user_data->display_name ),
				'new' => self::sanitize_user_fields( 'display_name', $post_data['display_name'] ),
			];
			$post_data['display_name']    = $user_data->display_name;
		}
		if ( isset( $post_data['email'] ) && $post_data['email'] !== $user_data->user_email ) {
			$updated_data['email'] = [
				'old' => self::sanitize_user_fields( 'email', $user_data->user_email ),
				'new' => self::sanitize_user_fields( 'email', $post_data['email'] ),
			];
			$post_data['email']    = $user_data->user_email;
		}
		if ( isset( $post_data['url'] ) && $post_data['url'] !== $user_data->user_url ) {
			$updated_data['url'] = [
				'old' => self::sanitize_user_fields( 'url', $user_data->user_url ),
				'new' => self::sanitize_user_fields( 'url', $post_data['url'] ),
			];
			$post_data['url']    = $user_data->user_url;
		}
		// user nicename.
		if ( isset( $post_data['user_nicename'] ) && $post_data['user_nicename'] !== $user_data->user_nicename ) {
			$updated_data['user_nicename'] = [
				'old' => self::sanitize_user_fields( 'user_nicename', $user_data->user_nicename ),
				'new' => self::sanitize_user_fields( 'user_nicename', $post_data['user_nicename'] ),
			];
			$post_data['user_nicename']    = $user_data->user_nicename;
		}

		$_POST = $post_data;

		if ( empty( $updated_metadata ) && empty( $updated_data ) ) {
			return;
		}

		$requested_by = get_current_user_id();
		if ( $requested_by === $user_id ) {
			$requested_by = ( get_userdata( $requested_by )->display_name ?? 'Unknown' ) . esc_html( ' (Self)' );
		} else {
			$requested_by = ( get_userdata( $requested_by )->display_name ?? 'Unknown' ) . esc_html( ' (Brand Admin)' );
		}

		$profile_request_data[ $user_id ] = [
			'data'         => $updated_data,
			'user_name'    => get_userdata( $user_id )->display_name ?? get_userdata( $user_id )->user_nicename ?? __( 'Unknown', 'oneaccess' ),
			'user_email'   => get_userdata( $user_id )->user_email ?? 'Unknown',
			'metadata'     => $updated_metadata,
			'requested_by' => $requested_by,
			'status'       => __( 'pending', 'oneaccess' ),
			'user_login'   => get_userdata( $user_id )->user_login ?? 'Unknown',
			'requested_at' => current_time( 'mysql' ),
		];

		DB::add_profile_request( $user_id, $profile_request_data[ $user_id ], 'pending' );
	}

	/**
	 * Sanitize user fields based on field type.
	 *
	 * @param string $field Field name.
	 * @param string $value Field value.
	 *
	 * @return string Sanitized value.
	 */
	private static function sanitize_user_fields( string $field, string $value ): string {
		$sanitized_value = '';
		switch ( $field ) {

			// user email fields.
			case 'email':
				return sanitize_email( $value );

			// user name fields.
			case 'first_name':
			case 'last_name':
			case 'nickname':
			case 'user_nicename':
			case 'display_name':
				return sanitize_text_field( $value );

			// user url fields.
			case 'url':
			case 'website':
			case 'facebook':
			case 'instagram':
			case 'linkedin':
			case 'myspace':
			case 'pinterest':
			case 'soundcloud':
			case 'tumblr':
			case 'wikipedia':
			case 'twitter':
			case 'youtube':
			case 'github':
			case 'tiktok':
				$sanitized_value = esc_url_raw( $value );
				break;

			// user description field.
			case 'description':
			case 'biographical_info':
			case 'bio':
				$sanitized_value = sanitize_textarea_field( $value );
				break;

			// default case for any other fields.
			default:
				$sanitized_value = sanitize_text_field( $value );
				break;
		}
		return $sanitized_value;
	}

	/**
	 * Add custom user column to indicate profile request status.
	 *
	 * @param array<string, string> $columns Existing user columns.
	 *
	 * @return array<string, string> Modified user columns.
	 */
	public function add_profile_request_status_column( $columns ): array {
		// Add a new column for profile request status.
		$columns['profile_request_status'] = __( 'Profile Request Status', 'oneaccess' );
		return $columns;
	}

	/**
	 * Render the content for the profile request status column.
	 *
	 * @param string $value The current value of the column.
	 * @param string $column_name The name of the column.
	 * @param int    $user_id The ID of the user.
	 *
	 * @return string HTML content for the column.
	 */
	public function render_profile_request_status_column( $value, $column_name, $user_id ): string {
		if ( 'profile_request_status' === $column_name ) {
			// Get the user's profile request status.
			$profile_update_requests = DB::get_latest_profile_request_by_user_id( $user_id );
			if ( empty( $profile_update_requests ) ) {
				return '<span class="oneaccess-pill oneaccess-pill--no-request">' . __( 'No Request', 'oneaccess' ) . '</span>';
			}

			$status = $profile_update_requests['status'] ?? 'pending';
			switch ( $status ) {
				case 'rejected':
					return '<span class="oneaccess-pill oneaccess-pill--rejected">' . __( 'Rejected', 'oneaccess' ) . '</span>';
				case 'approved':
					return '<span class="oneaccess-pill oneaccess-pill--no-request">' . __( 'No Request', 'oneaccess' ) . '</span>';
				default:
					return '<span class="oneaccess-pill oneaccess-pill--pending">' . __( 'Pending', 'oneaccess' ) . '</span>';
			}
		}
		return $value;
	}
}
