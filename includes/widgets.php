<?php
/**
 * Widgets: definitions, markup, shortcode, blocks, script, server fallback.
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * The widgets, free ones first (the service's 19 widget kinds). Paid ones need a
 * paid plan key; the match widgets take a team (its live, next or last match) or
 * a match code.
 *
 * @return array<string, array<string, mixed>>
 */
function sportstatsapi_widgets() {
	// slug => kind (the embed's data-widget), title, team (none, optional, required), options, free.
	$list = array(
		'table'        => array( 'table', __( 'League table', 'sportstatsapi-live-scores' ), 'optional', array( 'size', 'rows' ), true ),
		'scores'       => array( 'scores', __( 'Live scores', 'sportstatsapi-live-scores' ), 'optional', array( 'round' ), true ),
		'fixtures'     => array( 'fixtures', __( 'Fixtures', 'sportstatsapi-live-scores' ), 'optional', array( 'view', 'country' ), true ),
		'ticker'       => array( 'ticker', __( 'Results ticker', 'sportstatsapi-live-scores' ), 'none', array( 'leagues' ), true ),
		'next-match'   => array( 'next-match', __( 'Next match', 'sportstatsapi-live-scores' ), 'required', array( 'country' ), true ),
		'calendar'     => array( 'calendar', __( 'Team calendar', 'sportstatsapi-live-scores' ), 'required', array(), true ),
		'match-centre' => array( 'match-centre', __( 'Match centre', 'sportstatsapi-live-scores' ), 'optional', array( 'match' ), false ),
		'match-facts'  => array( 'match-facts', __( 'Match facts', 'sportstatsapi-live-scores' ), 'none', array( 'style', 'langs' ), false ),
		'preview'      => array( 'preview', __( 'Match preview', 'sportstatsapi-live-scores' ), 'optional', array( 'match' ), false ),
		'head-to-head' => array( 'head-to-head', __( 'Head to head', 'sportstatsapi-live-scores' ), 'optional', array( 'match' ), false ),
		'box-score'    => array( 'box-score', __( 'Box score', 'sportstatsapi-live-scores' ), 'optional', array( 'match' ), false ),
		'team-form'    => array( 'team-form', __( 'Team form', 'sportstatsapi-live-scores' ), 'required', array(), false ),
		'club-season'  => array( 'club-season', __( 'Club season', 'sportstatsapi-live-scores' ), 'required', array(), false ),
		'squad'        => array( 'squad', __( 'Squad', 'sportstatsapi-live-scores' ), 'required', array(), false ),
		'injuries'     => array( 'injuries', __( 'Injuries and suspensions', 'sportstatsapi-live-scores' ), 'optional', array(), false ),
		'race'         => array( 'race', __( 'Season race', 'sportstatsapi-live-scores' ), 'optional', array( 'teams' ), false ),
		'top-players'  => array( 'top-players', __( 'Top players', 'sportstatsapi-live-scores' ), 'none', array( 'stat', 'rows' ), false ),
		'player'       => array( 'player', __( 'Player card', 'sportstatsapi-live-scores' ), 'required', array( 'player' ), false ),
		'tennis'       => array( 'tennis', __( 'Tennis', 'sportstatsapi-live-scores' ), 'none', array( 'player', 'tournament' ), false ),
	);
	$out  = array();
	foreach ( $list as $slug => $w ) {
		$out[ $slug ] = array(
			'kind'    => $w[0],
			'title'   => $w[1],
			'team'    => $w[2],
			'options' => $w[3],
			'free'    => $w[4],
		);
	}
	return $out;
}

/**
 * The plugin settings with defaults.
 *
 * @return array{key:string,theme:string,accent:string,lang:string,fallback:bool,credit:bool}
 */
function sportstatsapi_settings() {
	$s = get_option( 'sportstatsapi_settings', array() );
	return array(
		'key'      => isset( $s['key'] ) ? (string) $s['key'] : '',
		'theme'    => isset( $s['theme'] ) ? (string) $s['theme'] : 'auto',
		'accent'   => isset( $s['accent'] ) ? (string) $s['accent'] : '',
		'lang'     => isset( $s['lang'] ) ? (string) $s['lang'] : '',
		'fallback' => ! empty( $s['fallback'] ),
		'credit'   => ! empty( $s['credit'] ),
	);
}

/**
 * The key to put in widgets: the saved key, or '' when there is none or the API
 * refuses it (unknown, revoked, another website). Without a key nothing is shown.
 * A key not checked yet (the API could not be reached) counts as working.
 *
 * @return string
 */
function sportstatsapi_key() {
	$key   = sportstatsapi_clean( 'key', sportstatsapi_settings()['key'] );
	$error = '' === $key ? '' : sportstatsapi_key_error( $key );
	// VIEW_LIMIT keeps the key: the widget itself tells visitors the free views are used up.
	return ( '' !== $error && 'VIEW_LIMIT' !== $error ) ? '' : $key;
}

/**
 * The site's origin (scheme://host[:port]) for the Origin header, '' when unknown.
 *
 * @return string
 */
function sportstatsapi_origin() {
	$home = wp_parse_url( home_url() );
	if ( empty( $home['scheme'] ) || empty( $home['host'] ) ) {
		return '';
	}
	return $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
}

/**
 * The API's error code for this key at the last check ('' when it worked or was not checked).
 *
 * @param string $key Widget key.
 * @return string
 */
function sportstatsapi_key_error( $key ) {
	$st = get_option( 'sportstatsapi_key_state' );
	return ( is_array( $st ) && isset( $st['key'], $st['error'] ) && $st['key'] === $key ) ? (string) $st['error'] : '';
}

/**
 * Asks the API whether the saved key works (after a save, and daily by WP-Cron),
 * with a free widget the key must open. Network errors and rate limits keep
 * the last answer. Without a key the daily check stops.
 */
function sportstatsapi_check_key() {
	$key = sportstatsapi_clean( 'key', sportstatsapi_settings()['key'] );
	if ( '' === $key ) {
		delete_option( 'sportstatsapi_key_state' );
		wp_clear_scheduled_hook( 'sportstatsapi_check_key' );
		return;
	}
	$origin = sportstatsapi_origin();
	$res    = wp_remote_get(
		add_query_arg(
			array(
				'w'   => 'table',
				'c'   => 'england--premier-league',
				'key' => rawurlencode( $key ),
			),
			SPORTSTATSAPI_API . '/api/widget/v1/data'
		),
		array(
			'timeout' => 8,
			'headers' => '' === $origin ? array() : array( 'Origin' => $origin ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return;
	}
	$code  = wp_remote_retrieve_response_code( $res );
	$body  = json_decode( wp_remote_retrieve_body( $res ), true );
	$error = isset( $body['error']['code'] ) ? (string) $body['error']['code'] : '';
	if ( 200 === $code ) {
		$error = '';
	} elseif ( ! in_array( $error, array( 'UNKNOWN_KEY', 'WRONG_WEBSITE', 'VIEW_LIMIT' ), true ) ) {
		return;
	}
	update_option(
		'sportstatsapi_key_state',
		array(
			'key'     => $key,
			'error'   => $error,
			'checked' => time(),
		),
		true // Small, and read on every page with a widget.
	);
}
add_action( 'sportstatsapi_check_key', 'sportstatsapi_check_key' );
add_action( 'add_option_sportstatsapi_settings', 'sportstatsapi_check_key' );
add_action( 'update_option_sportstatsapi_settings', 'sportstatsapi_check_key' );

/**
 * Validates one value as the embed script does; '' when it does not pass.
 *
 * @param string $name  Option name.
 * @param mixed  $value Raw value.
 * @return string
 */
function sportstatsapi_clean( $name, $value ) {
	if ( is_bool( $value ) ) {
		$value = $value ? '1' : '';
	}
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$value = trim( (string) $value );
	$enums = array(
		'theme' => array( 'light', 'dark', 'auto' ),
		'lang'  => array( 'en', 'de', 'es', 'it', 'fr' ),
		'size'  => array( 'compact', 'standard', 'full' ),
		'view'  => array( 'round', 'date' ),
		'stat'  => array( 'goals', 'assists', 'cards', 'rating' ),
		'style' => array( 'one' ),
	);
	if ( isset( $enums[ $name ] ) ) {
		return in_array( $value, $enums[ $name ], true ) ? $value : '';
	}
	switch ( $name ) {
		case 'competition':
			return preg_match( '/^[a-z0-9-]{1,120}$/', $value ) ? $value : '';
		case 'team':
			return preg_match( "/^[A-Za-z0-9 .'-]{1,80}$/", $value ) ? $value : '';
		case 'country':
			return preg_match( "/^[A-Za-z][A-Za-z .'-]{1,39}$/", $value ) ? $value : '';
		case 'accent':
			return (string) sanitize_hex_color( $value );
		case 'key':
			return preg_match( '/^(ssw_free_|wk_live_)[A-Za-z0-9_-]{1,55}$/', $value ) ? $value : '';
		case 'rows':
			return ( (int) $value >= 1 && (int) $value <= 40 ) ? (string) (int) $value : '';
		case 'round':
			return ( (int) $value >= 1 && (int) $value <= 99 ) ? (string) (int) $value : '';
		case 'radius':
			return ( '' !== $value && is_numeric( $value ) && (int) $value >= 0 && (int) $value <= 20 ) ? (string) (int) $value : '';
		case 'leagues':
			return substr( preg_replace( '/[^a-z0-9,-]/', '', $value ), 0, 400 );
		case 'teams':
			return substr( preg_replace( "/[^A-Za-z0-9 ,.'-]/", '', $value ), 0, 300 );
		case 'match':
			return preg_match( '#^[A-Za-z0-9/-]{1,400}$#', $value ) ? $value : '';
		case 'player':
		case 'tournament':
			return preg_match( '/^[a-z0-9-]{1,80}$/', $value ) ? $value : '';
		case 'langs':
			return in_array( $value, array( '1', 'true', 'yes' ), true ) ? 'true' : '';
		case 'font':
			return in_array( $value, array( '1', 'true', 'inherit', 'yes' ), true ) ? 'inherit' : '';
	}
	return '';
}

/**
 * The data-* attributes of one widget: the block or shortcode values over the
 * plugin defaults, validated, empty ones left out.
 *
 * @param string $slug Widget slug.
 * @param array  $in   Block attributes or shortcode atts.
 * @return array<string, string>|null Null for an unknown widget.
 */
function sportstatsapi_attributes( $slug, array $in ) {
	$widgets = sportstatsapi_widgets();
	if ( ! isset( $widgets[ $slug ] ) ) {
		return null;
	}
	$def = $widgets[ $slug ];
	$set = sportstatsapi_settings();

	$names = array_merge( array( 'competition', 'lang', 'theme', 'accent', 'font', 'radius' ), $def['options'] );
	if ( 'none' !== $def['team'] ) {
		$names[] = 'team';
	}
	$out = array( 'widget' => $def['kind'] );
	foreach ( $names as $name ) {
		$v = isset( $in[ $name ] ) ? sportstatsapi_clean( $name, $in[ $name ] ) : '';
		if ( '' === $v && in_array( $name, array( 'lang', 'theme', 'accent' ), true ) ) {
			$v = sportstatsapi_clean( $name, $set[ $name ] );
		}
		if ( '' !== $v ) {
			$out[ $name ] = $v;
		}
	}
	$key = sportstatsapi_key();
	if ( '' !== $key ) {
		$out['key'] = $key;
	}
	return $out;
}

/**
 * The markup of one widget, and the embed script queued for the page.
 *
 * @param string $slug Widget slug.
 * @param array  $in   Block attributes or shortcode atts.
 * @return string
 */
function sportstatsapi_render( $slug, array $in ) {
	$attrs = sportstatsapi_attributes( $slug, $in );
	$admin = current_user_can( 'edit_posts' );
	if ( null === $attrs || empty( $attrs['competition'] ) ) {
		return $admin
			? '<p class="sportstatsapi-missing">' . esc_html__( 'SportStatsAPI: choose a widget and a competition.', 'sportstatsapi-live-scores' ) . '</p>'
			: '';
	}
	// Connect is required: no key, no widget (editors see why).
	if ( empty( $attrs['key'] ) ) {
		return $admin
			? '<p class="sportstatsapi-missing"><a href="' . esc_url( admin_url( 'options-general.php?page=sportstatsapi' ) ) . '">' . esc_html__( 'Connect SportStatsAPI to show this widget', 'sportstatsapi-live-scores' ) . '</a></p>'
			: '';
	}
	// A free key needs the site owner's yes to the credit link the free plan shows (settings).
	if ( 0 === strpos( $attrs['key'], 'ssw_free_' ) && ! sportstatsapi_settings()['credit'] ) {
		return $admin
			? '<p class="sportstatsapi-missing"><a href="' . esc_url( admin_url( 'options-general.php?page=sportstatsapi' ) ) . '">' . esc_html__( 'Free widgets show a small "Data by SportStatsAPI" link. Agree to it in the settings to show this widget.', 'sportstatsapi-live-scores' ) . '</a></p>'
			: '';
	}
	wp_enqueue_script( 'sportstatsapi-embed' );

	$html = '<div class="sportstats-widget"';
	foreach ( $attrs as $name => $value ) {
		$html .= ' data-' . $name . '="' . esc_attr( $value ) . '"';
	}
	$html .= '></div>';
	if ( sportstatsapi_settings()['fallback'] ) {
		$html .= sportstatsapi_fallback( $attrs );
	}
	return $html;
}

/**
 * Registers the embed script (enqueued by the first widget on a page).
 */
function sportstatsapi_register_script() {
	// The version is the API's own (it caches by it): no ?ver from WordPress.
	wp_register_script(
		'sportstatsapi-embed',
		SPORTSTATSAPI_API . '/api/widget/v1/embed.js',
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		array(
			'in_footer' => true,
			'strategy'  => 'async',
		)
	);
}
add_action( 'init', 'sportstatsapi_register_script' );

/**
 * Shortcode [sportstatsapi widget="table" competition="england--premier-league" ...].
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function sportstatsapi_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'widget'      => 'table',
			'competition' => '',
			'team'        => '',
			'theme'       => '',
			'lang'        => '',
			'accent'      => '',
			'rows'        => '',
			'size'        => '',
			'view'        => '',
			'country'     => '',
			'round'       => '',
			'font'        => '',
			'radius'      => '',
			'leagues'     => '',
			'teams'       => '',
			'stat'        => '',
			'match'       => '',
			'player'      => '',
			'tournament'  => '',
			'style'       => '',
			'langs'       => '',
		),
		$atts,
		'sportstatsapi'
	);
	return sportstatsapi_render( sanitize_key( $atts['widget'] ), $atts );
}
add_shortcode( 'sportstatsapi', 'sportstatsapi_shortcode' );

/**
 * Registers the blocks (dynamic: rendered by sportstatsapi_render) and the
 * editor script they share.
 */
function sportstatsapi_register_blocks() {
	wp_register_script(
		'sportstatsapi-editor',
		SPORTSTATSAPI_URL . 'assets/editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data' ),
		SPORTSTATSAPI_VERSION,
		true
	);
	$set     = sportstatsapi_settings();
	$widgets = array();
	foreach ( sportstatsapi_widgets() as $slug => $def ) {
		$widgets[ $slug ] = array(
			'kind'    => $def['kind'],
			'team'    => $def['team'],
			'options' => $def['options'],
			'free'    => $def['free'],
		);
	}
	wp_add_inline_script(
		'sportstatsapi-editor',
		'window.sportstatsapiEditor = ' . wp_json_encode(
			array(
				'embed'    => SPORTSTATSAPI_API . '/api/widget/v1/embed.js',
				'widgets'  => $widgets,
				'defaults' => array(
					'theme'  => $set['theme'],
					'lang'   => $set['lang'],
					'accent' => $set['accent'],
				),
				'key'      => sportstatsapi_key(),
				'credit'   => $set['credit'],
				'settings' => admin_url( 'options-general.php?page=sportstatsapi' ),
			)
		) . ';',
		'before'
	);
	wp_set_script_translations( 'sportstatsapi-editor', 'sportstatsapi-live-scores' );

	foreach ( array_keys( sportstatsapi_widgets() ) as $slug ) {
		register_block_type(
			SPORTSTATSAPI_DIR . 'blocks/' . $slug,
			array(
				'render_callback' => function ( $attributes ) use ( $slug ) {
					$html = sportstatsapi_render( $slug, $attributes );
					return '' === $html ? '' : '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
				},
			)
		);
	}
}
add_action( 'init', 'sportstatsapi_register_blocks' );

/**
 * The "SportStatsAPI" inserter category, first in the list.
 *
 * @param array $categories Block categories.
 * @return array
 */
function sportstatsapi_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'sportstatsapi',
			'title' => 'SportStatsAPI',
		)
	);
	return $categories;
}
add_filter( 'block_categories_all', 'sportstatsapi_block_category' );

/**
 * Server fallback: the widget's data as plain HTML in <noscript>, for search
 * engines and visitors without JavaScript. Cached 10 minutes per widget.
 *
 * @param array<string, string> $attrs Validated widget attributes.
 * @return string
 */
function sportstatsapi_fallback( array $attrs ) {
	$kinds = array(
		'table'      => 'table',
		'scores'     => 'scores',
		'fixtures'   => 'fixtures',
		'ticker'     => 'ticker',
		'next-match' => 'nextmatch',
	);
	// Paid keys only: a free key's views are counted by the script in the visitor's
	// browser, so a server fetch must not use it.
	if ( ! isset( $kinds[ $attrs['widget'] ], $attrs['key'] ) || 0 !== strpos( $attrs['key'], 'wk_live_' ) ) {
		return '';
	}
	$query = array(
		'w'    => $kinds[ $attrs['widget'] ],
		'c'    => $attrs['competition'],
		'lang' => isset( $attrs['lang'] ) ? $attrs['lang'] : 'en',
	);
	foreach ( array( 'team', 'rows', 'round', 'view', 'country' ) as $name ) {
		if ( isset( $attrs[ $name ] ) ) {
			$query[ $name ] = $attrs[ $name ];
		}
	}
	$query['key'] = $attrs['key'];
	$origin       = sportstatsapi_origin();
	$headers      = '' === $origin ? array() : array( 'Origin' => $origin );

	$url   = add_query_arg( array_map( 'rawurlencode', $query ), SPORTSTATSAPI_API . '/api/widget/v1/data' );
	$cache = 'sportstatsapi_fb_' . md5( $url );
	$html  = get_transient( $cache );
	if ( false === $html ) {
		$html = '';
		$res  = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => $headers,
			)
		);
		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( is_array( $body ) && ! empty( $body['data'] ) ) {
				$html = sportstatsapi_fallback_html( $body['data'] );
			}
		}
		// A failure is kept 2 minutes, so a down API is not asked on every page view.
		set_transient( $cache, $html, '' === $html ? 2 * MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	}
	return '' === $html ? '' : '<noscript>' . $html . '</noscript>';
}

/**
 * Plain HTML of the widget data: a table, or a list of matches.
 *
 * @param array $d The data of /api/widget/v1/data.
 * @return string
 */
function sportstatsapi_fallback_html( array $d ) {
	$out  = '';
	$rows = isset( $d['table']['rows'] ) && is_array( $d['table']['rows'] ) ? $d['table']['rows'] : array();
	$body = '';
	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) || ! isset( $r['pos'], $r['team'], $r['played'], $r['points'] ) || ! is_scalar( $r['team'] ) ) {
			continue;
		}
		$body .= '<tr><td>' . (int) $r['pos'] . '</td><td>' . esc_html( (string) $r['team'] ) . '</td><td>' . (int) $r['played'] . '</td><td>' . (int) $r['points'] . '</td></tr>';
	}
	if ( '' !== $body ) {
		$out .= '<table class="sportstatsapi-fallback"><thead><tr><th>#</th><th>' . esc_html__( 'Team', 'sportstatsapi-live-scores' ) . '</th><th>' . esc_html__( 'P', 'sportstatsapi-live-scores' ) . '</th><th>' . esc_html__( 'Pts', 'sportstatsapi-live-scores' ) . '</th></tr></thead><tbody>' . $body . '</tbody></table>';
	}
	$matches = isset( $d['matches'] ) && is_array( $d['matches'] ) ? $d['matches'] : array();
	if ( ! empty( $d['groups'] ) && is_array( $d['groups'] ) ) {
		foreach ( $d['groups'] as $g ) {
			if ( isset( $g['matches'] ) && is_array( $g['matches'] ) ) {
				$matches = array_merge( $matches, $g['matches'] );
			}
		}
	}
	if ( ! empty( $d['club']['next'] ) ) {
		$matches[] = $d['club']['next'];
	}
	$items = '';
	foreach ( $matches as $m ) {
		if ( ! is_array( $m ) || ! isset( $m['home'], $m['away'] ) || ! is_scalar( $m['home'] ) || ! is_scalar( $m['away'] ) ) {
			continue;
		}
		$score = ( ! empty( $m['played'] ) || ! empty( $m['live'] ) ) && isset( $m['home_score'], $m['away_score'] ) && is_scalar( $m['home_score'] ) && is_scalar( $m['away_score'] )
			? $m['home_score'] . '–' . $m['away_score']
			: 'v';
		$time  = ! empty( $m['kickoff'] ) && is_string( $m['kickoff'] ) ? strtotime( $m['kickoff'] ) : false;
		$when  = false === $time ? '' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
		// Plain text: the fallback adds no links.
		$items .= '<li>' . esc_html( $m['home'] . ' ' . $score . ' ' . $m['away'] ) . ( $when ? ' <small>' . esc_html( $when ) . '</small>' : '' ) . '</li>';
	}
	if ( '' !== $items ) {
		$out .= '<ul class="sportstatsapi-fallback">' . $items . '</ul>';
	}
	return $out;
}
