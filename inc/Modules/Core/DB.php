<?php
/**
 * DB module.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\Core;

use OneAccess\Contracts\Interfaces\Registrable;

/**
 * Class DB
 */
class DB implements Registrable {
	/**
	 * Global prefix.
	 *
	 * @todo need to replace with constant from main plugin file.
	 *
	 * @var string
	 */
	private const ONEACCESS = 'oneaccess_';

	/**
	 * DB version.
	 *
	 * @var string
	 */
	private const DB_VERSION = self::ONEACCESS . 'db_version';

	/**
	 * De-duplicated users table.
	 *
	 * @var string
	 */
	public const DEDUPLICATED_USERS_TABLE = self::ONEACCESS . 'deduplicated_users';

	/**
	 * Profile requests table.
	 *
	 * @var string
	 */
	public const PROFILE_REQUESTS_TABLE = self::ONEACCESS . 'profile_requests';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// oneaccess_add_deduplicated_users to add users to deduplicated users table.
		add_action( 'oneaccess_add_deduplicated_users', [ $this, 'add_deduplicated_users' ], 10, 1 );

		// create or update tables on plugin load.
		self::maybe_create_tables();
	}

	/**
	 * Check if tables need to be created or updated.
	 */
	public static function maybe_create_tables(): void {
		$current_version = ONEACCESS_VERSION;
		$db_version      = get_option( self::DB_VERSION, '0.0.0' );

		if ( ! version_compare( $db_version, $current_version, '<' ) ) {
			return;
		}

		self::create_deduplicated_users_table();
		self::create_profile_requests_table();

		update_option( self::DB_VERSION, $current_version, false );
	}

	/**
	 * Create database tables for storing de-duplicated users.
	 */
	public static function create_deduplicated_users_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::DEDUPLICATED_USERS_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		email VARCHAR(255) NOT NULL,
		first_name VARCHAR(100) NOT NULL,
		last_name VARCHAR(100) NOT NULL,
		sites_info JSON NOT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY email (email)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create table to store user profile requests changes.
	 */
	public static function create_profile_requests_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		request_data JSON NOT NULL,
		status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
		comment TEXT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add deduplicated users to the database.
	 *
	 * @param array<int, array<string, mixed>> $user_data Users data to be added.
	 */
	public static function add_deduplicated_users( array $user_data ): void {
		foreach ( $user_data as $user ) {
			$user_email = $user['email'] ?? '';
			if ( empty( $user_email ) ) {
				continue;
			}

			self::process_user( $user_email, $user );
		}
	}

	/**
	 * Process a single user for deduplication.
	 *
	 * @param string               $user_email User email.
	 * @param array<string, mixed> $user User data.
	 */
	private static function process_user( string $user_email, array $user ): void {
		global $wpdb;
		$table_name = self::get_table_name();

		$existing_user = self::get_existing_user( $user_email, $table_name );

		if ( $existing_user ) {
			self::update_existing_user( $existing_user, $user, $table_name );
		} else {
			self::insert_new_user( $user_email, $user, $table_name );
		}
	}

	/**
	 * Get the deduplicated users table name.
	 */
	private static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::DEDUPLICATED_USERS_TABLE;
	}

	/**
	 * Get existing user by email.
	 *
	 * @param string $user_email User email.
	 * @param string $table_name Table name.
	 *
	 * @return object|null
	 */
	private static function get_existing_user( string $user_email, string $table_name ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$user_email
			)
		);
	}

	/**
	 * Update existing user with new site info.
	 *
	 * @param object               $existing_user Existing user object.
	 * @param array<string, mixed> $user New user data.
	 * @param string               $table_name Table name.
	 */
	private static function update_existing_user( $existing_user, array $user, string $table_name ): void {
		$existing_sites_info = isset( $existing_user->sites_info ) ? self::decode_sites_info( $existing_user->sites_info ) : [];
		$new_site_info       = self::build_site_info( $user );
		$id                  = isset( $existing_user->id ) ? (int) $existing_user->id : 0;

		$site_exists = self::check_site_exists( $existing_sites_info, $new_site_info );

		if ( ! $site_exists ) {
			$existing_sites_info[] = $new_site_info;
		} else {
			$existing_sites_info = self::update_site_info( $existing_sites_info, $new_site_info );
		}

		self::save_updated_user( $id, $existing_sites_info, $table_name );
	}

	/**
	 * Decode sites info JSON.
	 *
	 * @param string $sites_info_json Sites info in JSON format.
	 *
	 * @return array<int, array<string, mixed>> Decoded sites info.
	 */
	private static function decode_sites_info( $sites_info_json ): array {
		$existing_sites_info = json_decode( $sites_info_json, true );
		if ( ! is_array( $existing_sites_info ) ) {
			$existing_sites_info = [];
		}
		return $existing_sites_info;
	}

	/**
	 * Build site info array from user data.
	 *
	 * @param array<string, mixed> $user User data.
	 *
	 * @return array{
	 *   site_name: string,
	 *   site_url: string,
	 *   user_id: mixed,
	 *   roles: mixed,
	 * } Site info array.
	 */
	private static function build_site_info( array $user ): array {
		return [
			'site_name' => $user['site_name'] ?? '',
			'site_url'  => $user['site_url'] ?? '',
			'user_id'   => $user['user_id'] ?? '',
			'roles'     => $user['roles'] ?? [],
		];
	}

	/**
	 * Check if site already exists in existing sites info.
	 *
	 * @param array<int, array<string, mixed>> $existing_sites_info Existing sites info.
	 * @param array<string, mixed>             $new_site_info New site info.
	 */
	private static function check_site_exists( array $existing_sites_info, array $new_site_info ): bool {
		foreach ( $existing_sites_info as $site_info ) {
			if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $new_site_info['site_url'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Update site info in existing sites info.
	 *
	 * @param array<int, array<string, mixed>> $existing_sites_info Existing sites info.
	 * @param array<string, mixed>             $new_site_info New site info.
	 *
	 * @return array<int, array<string, mixed>> Updated sites info.
	 */
	private static function update_site_info( array $existing_sites_info, array $new_site_info ): array {
		foreach ( $existing_sites_info as &$site_info ) {
			if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $new_site_info['site_url'] ) ) {
				$site_info['site_name'] = $new_site_info['site_name'];
				$site_info['user_id']   = $new_site_info['user_id'];
				$site_info['roles']     = $new_site_info['roles'];
				break;
			}
		}
		return $existing_sites_info;
	}

	/**
	 * Save updated user data to the database.
	 *
	 * @param int                              $user_id User ID.
	 * @param array<int, array<string, mixed>> $sites_info Updated sites info.
	 * @param string                           $table_name Table name.
	 */
	private static function save_updated_user( int $user_id, array $sites_info, string $table_name ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$table_name,
			[
				'sites_info' => wp_json_encode( $sites_info ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $user_id ],
			[
				'%s',
				'%s',
			],
			[ '%d' ]
		);
	}

	/**
	 * Insert new user into the database.
	 *
	 * @param string               $user_email User email.
	 * @param array<string, mixed> $user User data.
	 * @param string               $table_name Table name.
	 */
	private static function insert_new_user( string $user_email, array $user, string $table_name ): void {
		global $wpdb;

		$sites_info = [
			self::build_site_info( $user ),
		];

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$table_name,
			[
				'email'      => $user_email,
				'first_name' => $user['first_name'] ?? '',
				'last_name'  => $user['last_name'] ?? '',
				'sites_info' => wp_json_encode( $sites_info ),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}

	/**
	 * Add profile request to the database.
	 *
	 * @param int                  $user_id User ID for whom profile request is made.
	 * @param array<string, mixed> $request_data Profile request data.
	 * @param string               $status Status of the profile request.
	 */
	public static function add_profile_request( int $user_id, array $request_data, string $status = 'pending' ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$table_name,
			[
				'user_id'      => $user_id,
				'request_data' => wp_json_encode( $request_data ),
				'status'       => $status,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			],
			[
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}

	/**
	 * Update profile request status.
	 *
	 * @param int    $request_id Profile request ID.
	 * @param string $status New status of the profile request.
	 */
	public static function update_profile_request_status( int $request_id, string $status ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$table_name,
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $request_id ],
			[
				'%s',
				'%s',
			],
			[ '%d' ]
		);
	}

	/**
	 * Get pending profile requests by user ID.
	 *
	 * At a time at max only one pending request per user is allowed.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_pending_profile_request_by_user_id( int $user_id ): ?array {
		global $wpdb;
		$table_name = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$user_id,
				'pending'
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		// decode request_data.
		$row['request_data'] = json_decode( $row['request_data'], true );

		return $row;
	}

	/**
	 * Get rejected profile requests by user ID.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_rejected_profile_request_by_user_id( int $user_id ): ?array {
		global $wpdb;
		$table_name = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$user_id,
				'rejected'
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		// decode request_data.
		$row['request_data'] = json_decode( $row['request_data'], true );

		return $row;
	}

	/**
	 * Get the latest profile request by user ID.
	 *
	 * Returns the most recent request regardless of status (pending, rejected, or approved).
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_latest_profile_request_by_user_id( int $user_id ): ?array {
		global $wpdb;
		$table_name = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$user_id
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		// decode request_data.
		$row['request_data'] = json_decode( $row['request_data'], true );

		return $row;
	}

	/**
	 * Get profile request by request ID.
	 *
	 * @param int $request_id Request ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_profile_request_by_id( int $request_id ): ?array {
		global $wpdb;
		$table_name = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$request_id
			),
			ARRAY_A
		);
		if ( null === $row ) {
			return null;
		}

		// decode request_data.
		$row['request_data'] = json_decode( $row['request_data'], true );

		return $row;
	}

	/**
	 * Approve profile request by request ID.
	 *
	 * @param int $request_id Request ID.
	 */
	public static function approve_profile_request_by_id( int $request_id ): void {
		self::update_profile_request_status( $request_id, 'approved' );
	}

	/**
	 * Reject profile request by request ID.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $rejection_comment Rejection comment.
	 */
	public static function reject_profile_request_by_id( int $request_id, string $rejection_comment ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::PROFILE_REQUESTS_TABLE;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$table_name,
			[
				'status'     => 'rejected',
				'comment'    => $rejection_comment,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $request_id ],
			[
				'%s',
				'%s',
				'%s',
			],
			[ '%d' ]
		);
	}

	/**
	 * Delete user from deduplicated users table.
	 *
	 * @param string $email User email.
	 * @param string $site_url Site URL to remove from sites_info.
	 */
	public static function delete_user_from_deduplicated_users( string $email, string $site_url ): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix . self::DEDUPLICATED_USERS_TABLE;

		$response = false;

		// Get existing user record.
		$existing_user = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$email
			)
		);

		if ( $existing_user ) {
			// Decode existing sites_info.
			$existing_sites_info = json_decode( $existing_user->sites_info, true );
			if ( ! is_array( $existing_sites_info ) ) {
				$existing_sites_info = [];
			}

			// Filter out the site info to be removed.
			$updated_sites_info = array_filter(
				$existing_sites_info,
				static function ( $site_info ) use ( $site_url ) {
					return trailingslashit( $site_info['site_url'] ) !== trailingslashit( $site_url );
				}
			);

			if ( empty( $updated_sites_info ) ) {
				// If no sites left, delete the user record.
				$response = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
					$table_name,
					[ 'id' => $existing_user->id ],
					[ '%d' ]
				);
			} else {
				// Update the user record with updated sites_info.
				$response = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
					$table_name,
					[
						'sites_info' => wp_json_encode( array_values( $updated_sites_info ) ),
						'updated_at' => current_time( 'mysql' ),
					],
					[ 'id' => $existing_user->id ],
					[
						'%s',
						'%s',
					],
					[ '%d' ]
				);
			}
		}

		return $response;
	}

	/**
	 * Update user role in deduplicated users table.
	 *
	 * @param string $email User email.
	 * @param string $new_role New role to be assigned.
	 * @param string $site_url Site URL where role needs to be updated.
	 */
	public static function update_user_role_in_deduplicated_users( string $email, string $new_role, string $site_url ): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix . self::DEDUPLICATED_USERS_TABLE;

		$response = false;
		// Get existing user record.
		$existing_user = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$email
			)
		);

		if ( $existing_user ) {
			// Decode existing sites_info.
			$existing_sites_info = json_decode( $existing_user->sites_info, true );
			if ( ! is_array( $existing_sites_info ) ) {
				$existing_sites_info = [];
			}

			// Update the role for the specified site.
			foreach ( $existing_sites_info as &$site_info ) {
				if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $site_url ) ) {
					$site_info['roles'] = [ $new_role ];
					break;
				}
			}

			// Update the user record with updated sites_info.
			$response = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
				$table_name,
				[
					'sites_info' => wp_json_encode( $existing_sites_info ),
					'updated_at' => current_time( 'mysql' ),
				],
				[ 'id' => $existing_user->id ],
				[
					'%s',
					'%s',
				],
				[ '%d' ]
			);
		}

		return $response;
	}

	/**
	 * Add user to deduplicated users table.
	 *
	 * @param string             $email User email.
	 * @param string             $first_name User first name.
	 * @param string             $last_name User last name.
	 * @param string             $site_name Site name.
	 * @param string             $site_url Site URL.
	 * @param int                $user_id User ID on the site.
	 * @param array<int, string> $roles User roles on the site.
	 */
	public static function add_user_to_deduplicated_users(
		string $email,
		string $first_name,
		string $last_name,
		string $site_name,
		string $site_url,
		int $user_id,
		array $roles,
	): int|false {

		// get user by email.
		global $wpdb;
		$table_name = $wpdb->prefix . self::DEDUPLICATED_USERS_TABLE;

		$response = false;

		$user = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe usage.
				$email
			)
		);

		if ( $user ) {
			// user exists, update sites_info.
			$existing_sites_info = json_decode( $user->sites_info, true );
			if ( ! is_array( $existing_sites_info ) ) {
				$existing_sites_info = [];
			}

			// check if site already exists.
			$site_exists = false;
			foreach ( $existing_sites_info as $site_info ) {
				if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $site_url ) ) {
					$site_exists = true;
					break;
				}
			}

			if ( ! $site_exists ) {
				$existing_sites_info[] = [
					'site_name' => $site_name,
					'site_url'  => $site_url,
					'user_id'   => $user_id,
					'roles'     => $roles,
				];

				$response = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe usage.
					$table_name,
					[
						'first_name' => $first_name,
						'last_name'  => $last_name,
						'sites_info' => wp_json_encode( $existing_sites_info ),
						'updated_at' => current_time( 'mysql' ),
					],
					[ 'id' => $user->id ],
					[
						'%s',
						'%s',
						'%s',
						'%s',
					],
					[ '%d' ],
				);
			}
		}

		return $response;
	}
}
