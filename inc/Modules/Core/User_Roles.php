<?php
/**
 * User Roles module.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\Core;

use OneAccess\Contracts\Interfaces\Registrable;
use OneAccess\Modules\Settings\Settings;

/**
 * Class User_Roles
 */
class User_Roles implements Registrable {
	/**
	 * Prefix for custom roles.
	 *
	 * @todo need to replace globally with single source of truth.
	 *
	 * @var string
	 */
	private const PREFIX       = 'oneaccess_';
	public const BRAND_ADMIN   = self::PREFIX . 'brand_admin';
	public const NETWORK_ADMIN = self::PREFIX . 'network_admin';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// modify user caps to core user roles.
		add_filter( 'user_has_cap', [ $this, 'modify_user_caps' ], 10, 1 );

		// create roles when plugin is loaded.
		self::create_brand_admin_role();
		self::create_network_admin_role();
	}

	/**
	 * Modify user capabilities based on the site type.
	 *
	 * @param array<string, bool|mixed> $allcaps All capabilities for the user.
	 *
	 * @return array<string, bool|mixed> Modified capabilities.
	 */
	public function modify_user_caps( $allcaps ): array {
		// if this is branch site.
		if ( ! Settings::is_consumer_site() ) {
			return $allcaps;
		}
		// remove user creation, deletion and promotion caps.
		$caps_to_remove = [
			'create_users',
			'delete_users',
			'promote_users',
		];

		foreach ( $caps_to_remove as $cap ) {
			if ( ! isset( $allcaps[ $cap ] ) ) {
				continue;
			}

			unset( $allcaps[ $cap ] );
		}

		$current_user = wp_get_current_user();

		if ( ! in_array( self::BRAND_ADMIN, $current_user->roles, true ) ) {
			$allcaps['edit_users'] = false;
		}

		return $allcaps;
	}

	/**
	 * Create the brand admin role.
	 */
	public static function create_network_admin_role(): void {

		if ( ! Settings::is_governing_site() ) {
			return;
		}

		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role ) {
			return;
		}

		// check if role already exists.
		if ( get_role( self::NETWORK_ADMIN ) ) {
			return;
		}

		$network_admin = add_role( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.custom_role_add_role -- all sites are not VIP sites.
			self::NETWORK_ADMIN,
			__( 'Network Admin', 'oneaccess' ),
			$admin_role->capabilities
		);

		if ( ! $network_admin ) {
			return;
		}

		// Add additional capabilities to the network admin role.
		$additional_caps = [
			'oneaccess_manage_requests',
			'oneaccess_manage_settings',
			'oneaccess_manage_sites',
			'oneaccess_manage_users',
		];

		foreach ( $additional_caps as $cap ) {
			$network_admin->add_cap( $cap );
		}
	}

	/**
	 * Create the brand admin role.
	 */
	public static function create_brand_admin_role(): void {
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		$admin_role = get_role( 'administrator' );

		if ( ! $admin_role ) {
			return;
		}

		// check if role already exists.
		if ( get_role( self::BRAND_ADMIN ) ) {
			return;
		}

		$brand_admin = add_role( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.custom_role_add_role -- all sites are not VIP sites.
			self::BRAND_ADMIN,
			__( 'Brand Admin', 'oneaccess' ),
			$admin_role->capabilities
		);

		if ( ! $brand_admin ) {
			return;
		}

		$additional_caps = [
			'oneaccess_manage_settings',
		];

		foreach ( $additional_caps as $cap ) {
			$brand_admin->add_cap( $cap );
		}
	}

	/**
	 * Remove roles and change user roles to administrator.
	 */
	public static function remove_roles_and_change_user_roles(): void {
		// remove network_admin role if it exists.
		if ( get_role( self::NETWORK_ADMIN ) ) {

			// before removing the role change all network_admin users to administrator role.
			$network_admin_users = get_users( [ 'role' => self::NETWORK_ADMIN ] );
			foreach ( $network_admin_users as $user ) {
				$user->set_role( 'administrator' );
			}

			remove_role( self::NETWORK_ADMIN );
		}

		// remove brand_admin role if it exists.
		if ( ! get_role( self::BRAND_ADMIN ) ) {
			return;
		}

		// before removing the role change all brand_admin users to administrator role.
		$brand_admin_users = get_users( [ 'role' => self::BRAND_ADMIN ] );
		foreach ( $brand_admin_users as $user ) {
			$user->set_role( 'administrator' );
		}

		remove_role( self::BRAND_ADMIN );
	}

	/**
	 * Update user role on plugin activation.
	 */
	public static function update_user_role_on_activation(): void {
		$current_user = wp_get_current_user();
		if ( Settings::is_consumer_site() && in_array( 'administrator', (array) $current_user->roles, true ) ) {
			$current_user->add_role( self::BRAND_ADMIN );
		}
		if ( ! Settings::is_governing_site() || ! in_array( 'administrator', (array) $current_user->roles, true ) ) {
			return;
		}

		$current_user->add_role( self::NETWORK_ADMIN );
	}
}
