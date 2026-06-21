<?php
/**
 * This will be executed when the plugin is uninstalled via the WordPress admin.
 *
 * @package OneAccess
 */

declare( strict_types = 1 );

namespace OneAccess;

// Only uninstall if called by WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// We use local constants so this plugin can be uninstalled even if the autoloader is corrupted or missing.
const PLUGIN_PREFIX = 'oneaccess_';

/**
 * Uninstalls the plugin. If multisite, uninstalls from all sites.
 */
function run_uninstaller(): void {
	if ( ! is_multisite() ) {
		uninstall();
		return;
	}

	delete_network_plugin_data();

	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) ?: [];

	foreach ( $site_ids as $site_id ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- The state doesn't matter during uninstall.
		if ( ! switch_to_blog( (int) $site_id ) ) {
			continue;
		}

		uninstall();
		restore_current_blog();
	}
}

/**
 * The (site-specific) uninstall function.
 */
function uninstall(): void {
	remove_user_roles_caps();
	delete_plugin_data();
}

/**
 * Delete multisite network plugin data.
 */
function delete_network_plugin_data(): void {
	$options = [
		PLUGIN_PREFIX . 'multisite_governing_site',
	];

	foreach ( $options as $option ) {
		delete_site_option( $option );
	}
}

/**
 * Deletes meta, options, transients, etc.
 */
function delete_plugin_data(): void {

	// list of actions to be cleared on uninstall.
	$actions_to_clear = [
		PLUGIN_PREFIX . 'governing_site_configured',
		PLUGIN_PREFIX . 'add_deduplicated_users',
	];

	// Clear scheduled actions.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		foreach ( $actions_to_clear as $action ) {
			as_unschedule_all_actions( $action );
		}
	}

	// Options to clean up.
	$options = [
		PLUGIN_PREFIX . 'child_site_api_key',
		PLUGIN_PREFIX . 'consumer_api_key',
		PLUGIN_PREFIX . 'db_version',
		PLUGIN_PREFIX . 'governing_site_url',
		PLUGIN_PREFIX . 'new_users',
		PLUGIN_PREFIX . 'parent_site_url',
		PLUGIN_PREFIX . 'profile_update_requests',
		PLUGIN_PREFIX . 'shared_sites',
		PLUGIN_PREFIX . 'site_type',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Drop custom tables created by the OneAccess.
	$tables_to_drop = [
		PLUGIN_PREFIX . 'deduplicated_users',
		PLUGIN_PREFIX . 'profile_requests',
	];

	global $wpdb;
	foreach ( $tables_to_drop as $table ) {
		$full_table_name = $wpdb->prefix . $table;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $full_table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this is to drop table on uninstall
	}
}

/**
 * Remove custom roles and capabilities from users.
 */
function remove_user_roles_caps(): void {
	// remove custom roles and capabilities from users.
	$roles = [ PLUGIN_PREFIX . 'brand_admin', PLUGIN_PREFIX . 'network_admin' ];

	$capabilities = [
		PLUGIN_PREFIX . 'manage_requests',
		PLUGIN_PREFIX . 'manage_settings',
		PLUGIN_PREFIX . 'manage_sites',
		PLUGIN_PREFIX . 'manage_users',
	];

	// get all users with custom roles & remove the role and capabilities.
	foreach ( $roles as $role ) {
		$users = get_users( [ 'role' => $role ] );
		foreach ( $users as $user ) {
			if ( ! in_array( $role, (array) $user->roles, true ) ) {
				continue;
			}

			foreach ( $capabilities as $cap ) {
				// remove capability if user has it.
				if ( ! $user->has_cap( $cap ) ) {
					continue;
				}

				$user->remove_cap( $cap );
			}
			$user->remove_role( $role );
		}

		// Finally remove the role itself.
		remove_role( $role );
	}
}

// Run the uninstaller.
run_uninstaller();
