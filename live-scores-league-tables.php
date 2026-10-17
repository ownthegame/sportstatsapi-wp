<?php
/**
 * Plugin Name:       Live Scores & League Tables
 * Plugin URI:        https://sportstatsapi.com/widgets/
 * Description:       Live scores, league tables, fixtures, a results ticker, next match and team calendar widgets from SportStatsAPI, as blocks and shortcodes.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            SportStatsAPI
 * Author URI:        https://sportstatsapi.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       live-scores-league-tables
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'ABSPATH' ) || exit;

define( 'SPORTSTATSAPI_VERSION', '0.1.0' );
define( 'SPORTSTATSAPI_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPORTSTATSAPI_URL', plugin_dir_url( __FILE__ ) );
define( 'SPORTSTATSAPI_API', 'https://api.sportstatsapi.com' );
define( 'SPORTSTATSAPI_SITE', 'https://sportstatsapi.com' );

require SPORTSTATSAPI_DIR . 'includes/widgets.php';
require SPORTSTATSAPI_DIR . 'includes/rest.php';
if ( is_admin() ) {
	require SPORTSTATSAPI_DIR . 'includes/settings.php';
}

/**
 * Schedules the daily key check on every site that runs the plugin (activation
 * alone reaches only the current site of a network).
 */
function sportstatsapi_schedule_check() {
	if ( ! wp_next_scheduled( 'sportstatsapi_check_key' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'sportstatsapi_check_key' );
	}
}
add_action( 'init', 'sportstatsapi_schedule_check' );

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'sportstatsapi_check_key' );
	}
);
