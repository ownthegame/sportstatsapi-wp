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
 * GET /sportstatsapi/v1/competitions?search=premier: up to 20 competitions from
 * the API's search (/api/widget/v1/competitions, each query kept 10 minutes).
 * Before that endpoint is live (404): the full list, filtered here.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function sportstatsapi_rest_competitions( WP_REST_Request $request ) {
	$search = trim( $request['search'] );
	if ( '' === $search ) {
		return rest_ensure_response( array() );
	}
	$search = function_exists( 'mb_substr' ) ? mb_substr( $search, 0, 100 ) : substr( $search, 0, 100 );
	$cache  = 'sportstatsapi_cs_' . md5( strtolower( $search ) );
	$hit    = get_transient( $cache );
	if ( is_array( $hit ) ) {
		return rest_ensure_response( $hit );
	}
	$url  = add_query_arg(
		array(
			'q'     => rawurlencode( $search ),
			'limit' => 20,
		),
		SPORTSTATSAPI_API . '/api/widget/v1/competitions'
	);
	$res  = wp_remote_get( $url, array( 'timeout' => 8 ) );
	$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
	if ( 404 === $code ) {
		$out = sportstatsapi_search_full_list( $search );
	} elseif ( 200 === $code ) {
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$out  = array();
		foreach ( isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array() as $c ) {
			if ( ! empty( $c['key'] ) && ! empty( $c['name'] ) ) {
				$out[] = array(
					'key'     => (string) $c['key'],
					'name'    => (string) $c['name'],
					'country' => isset( $c['country'] ) ? (string) $c['country'] : '',
					'sport'   => isset( $c['sport'] ) ? (string) $c['sport'] : '',
					'free'    => isset( $c['free'] ) ? (bool) $c['free'] : null,
				);
			}
		}
	} else {
		$out = null;
	}
	if ( null === $out ) {
		return new WP_Error( 'sportstatsapi_unavailable', __( 'SportStatsAPI cannot be reached right now.', 'live-scores-league-tables' ), array( 'status' => 502 ) );
	}
	set_transient( $cache, $out, 10 * MINUTE_IN_SECONDS );
	return rest_ensure_response( $out );
}

/**
 * The fallback search: sportstatsapi.com/widgets/competitions.json (kept 12 hours),
 * name, country or sport containing the text, free ones first.
 *
 * @param string $search Search text.
 * @return array|null Null when the list cannot be loaded.
 */
function sportstatsapi_search_full_list( $search ) {
	// [key, name, country, sport, free].
	$all = sportstatsapi_remote_json( SPORTSTATSAPI_SITE . '/widgets/competitions.json', 'sportstatsapi_competitions' );
	if ( null === $all ) {
		return null;
	}
	$search = strtolower( $search );
	$out    = array();
	foreach ( $all as $c ) {
		if ( is_array( $c ) && count( $c ) >= 5 && false !== strpos( strtolower( $c[1] . ' ' . $c[2] . ' ' . $c[3] ), $search ) ) {
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
	return array_slice( $out, 0, 20 );
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
		return new WP_Error( 'sportstatsapi_competition', __( 'Unknown competition.', 'live-scores-league-tables' ), array( 'status' => 400 ) );
	}
	$data = sportstatsapi_remote_json( SPORTSTATSAPI_API . '/api/widget/v1/teams?c=' . rawurlencode( $comp ), 'sportstatsapi_teams_' . md5( $comp ) );
	if ( null === $data ) {
		return new WP_Error( 'sportstatsapi_unavailable', __( 'SportStatsAPI cannot be reached right now.', 'live-scores-league-tables' ), array( 'status' => 502 ) );
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
