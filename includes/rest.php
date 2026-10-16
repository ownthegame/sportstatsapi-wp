<?php
/**
 * Editor REST routes: competition search and the teams of a competition,
 * cached proxies of public SportStatsAPI data (for users who can edit posts).
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches public JSON, kept in a transient for 12 hours.
 *
 * @param string $url   URL.
 * @param string $cache Transient name.
 * @return array|null Null when it cannot be loaded.
 */
function sportstatsapi_remote_json( $url, $cache ) {
	$data = get_transient( $cache );
	if ( is_array( $data ) ) {
		return $data;
	}
	$res = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $data ) ) {
		return null;
	}
	set_transient( $cache, $data, 12 * HOUR_IN_SECONDS );
	return $data;
}

/**
 * Registers the routes.
 */
function sportstatsapi_rest_routes() {
	$can = function () {
		return current_user_can( 'edit_posts' );
	};
	register_rest_route(
		'sportstatsapi/v1',
		'/competitions',
		array(
			'methods'             => 'GET',
			'permission_callback' => $can,
			'callback'            => 'sportstatsapi_rest_competitions',
			'args'                => array(
				'search' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
	register_rest_route(
		'sportstatsapi/v1',
		'/teams',
		array(
			'methods'             => 'GET',
			'permission_callback' => $can,
			'callback'            => 'sportstatsapi_rest_teams',
			'args'                => array(
				'competition' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'sportstatsapi_rest_routes' );

/**
 * GET /sportstatsapi/v1/competitions?search=premier: up to 20 competitions
 * whose name, country or sport matches (free ones first); no search: the free ones.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function sportstatsapi_rest_competitions( WP_REST_Request $request ) {
	// [key, name, country, sport, free].
	$all = sportstatsapi_remote_json( SPORTSTATSAPI_SITE . '/widgets/competitions.json', 'sportstatsapi_competitions' );
	if ( null === $all ) {
		return new WP_Error( 'sportstatsapi_unavailable', __( 'SportStatsAPI cannot be reached right now.', 'sportstatsapi-widgets' ), array( 'status' => 502 ) );
	}
	$search = strtolower( trim( $request['search'] ) );
	$out    = array();
	foreach ( $all as $c ) {
		if ( ! is_array( $c ) || count( $c ) < 5 ) {
			continue;
		}
		if ( '' === $search ? $c[4] : false !== strpos( strtolower( $c[1] . ' ' . $c[2] . ' ' . $c[3] ), $search ) ) {
			$out[] = array(
				'key'     => (string) $c[0],
				'name'    => (string) $c[1],
				'country' => (string) $c[2],
				'sport'   => (string) $c[3],
				'free'    => (bool) $c[4],
			);
		}
	}
	usort(
		$out,
		function ( $a, $b ) {
			return (int) $b['free'] - (int) $a['free'];
		}
	);
	return rest_ensure_response( array_slice( $out, 0, 20 ) );
}

/**
 * GET /sportstatsapi/v1/teams?competition=england--premier-league: [{code, name}].
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function sportstatsapi_rest_teams( WP_REST_Request $request ) {
	$comp = sportstatsapi_clean( 'competition', $request['competition'] );
	if ( '' === $comp ) {
		return new WP_Error( 'sportstatsapi_competition', __( 'Unknown competition.', 'sportstatsapi-widgets' ), array( 'status' => 400 ) );
	}
	$data = sportstatsapi_remote_json( SPORTSTATSAPI_API . '/api/widget/v1/teams?c=' . rawurlencode( $comp ), 'sportstatsapi_teams_' . md5( $comp ) );
	if ( null === $data ) {
		return new WP_Error( 'sportstatsapi_unavailable', __( 'SportStatsAPI cannot be reached right now.', 'sportstatsapi-widgets' ), array( 'status' => 502 ) );
	}
	$out = array();
	foreach ( isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array() as $t ) {
		if ( ! empty( $t['code'] ) && ! empty( $t['name'] ) ) { // a team without our code cannot be embedded.
			$out[] = array(
				'code' => (string) $t['code'],
				'name' => (string) $t['name'],
			);
		}
	}
	return rest_ensure_response( $out );
}
