<?php
/**
 * Plugin Name:       SportStatsAPI Widgets
 * Plugin URI:        https://sportstatsapi.com/widgets/
 * Description:       League tables, live scores, fixtures and more from SportStatsAPI, as blocks and shortcodes.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            SportStatsAPI
 * Author URI:        https://sportstatsapi.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sportstatsapi-widgets
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
