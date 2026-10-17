<?php
/**
 * Uninstall: removes the plugin's options, cron event and cached data, on every
 * site of a network.
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Cleans up the current site.
 */
function sportstatsapi_uninstall_site() {
	global $wpdb;
	delete_option( 'sportstatsapi_settings' );
	delete_option( 'sportstatsapi_key_state' );
	wp_clear_scheduled_hook( 'sportstatsapi_check_key' );
	// Transients with fixed names (also removed from a persistent object cache).
	delete_transient( 'sportstatsapi_competitions' );
	delete_transient( 'sportstatsapi_connect_' . md5( 'https://account.sportstatsapi.com/connect/wordpress' ) );
	// The others have hashed names (searches, teams, fallback, Connect state) and
	// expire on their own within 12 hours; those stored in the database go now.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_sportstatsapi_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_sportstatsapi_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $sportstatsapi_site_id ) {
		switch_to_blog( $sportstatsapi_site_id );
		sportstatsapi_uninstall_site();
		restore_current_blog();
	}
} else {
	sportstatsapi_uninstall_site();
}
