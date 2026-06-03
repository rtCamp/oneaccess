<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneAccess
 */

declare( strict_types = 1 );

namespace OneAccess;

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Multisite loop for uninstalling from all sites.
 */
function multisite_uninstall(): void {
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
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
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
		'oneaccess_multisite_governing_site',
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
		'oneaccess_governing_site_configured',
		'oneaccess_add_deduplicated_users',
	];

	// Clear scheduled actions.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		foreach ( $actions_to_clear as $action ) {
			as_unschedule_all_actions( $action );
		}
	}

	// Options to clean up.
	$options = [
		'oneaccess_child_site_api_key',
		'oneaccess_consumer_api_key',
		'oneaccess_db_version',
		'oneaccess_governing_site_url',
		'oneaccess_new_users',
		'oneaccess_parent_site_url',
		'oneaccess_profile_update_requests',
		'oneaccess_shared_sites',
		'oneaccess_site_type',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Drop custom tables created by the OneAccess.
	$tables_to_drop = [
		'oneaccess_deduplicated_users',
		'oneaccess_profile_requests',
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
	$roles = [ 'oneaccess_brand_admin', 'oneaccess_network_admin' ];

	$capabilities = [
		'oneaccess_manage_requests',
		'oneaccess_manage_settings',
		'oneaccess_manage_sites',
		'oneaccess_manage_users',
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
multisite_uninstall();
