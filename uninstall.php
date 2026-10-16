<?php
/**
 * Uninstall: removes the plugin's option and cached data.
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sportstatsapi_settings' );
delete_option( 'sportstatsapi_key_state' );
wp_clear_scheduled_hook( 'sportstatsapi_check_key' );
delete_transient( 'sportstatsapi_competitions' );

// The per-competition teams and fallback caches have hashed names.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_sportstatsapi_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_sportstatsapi_' ) . '%'
	)
);
