<?php
/**
 * Widgets: definitions, markup, shortcode, blocks, script, server fallback.
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * The widgets: slug => kind (the embed's data-widget), team (none, optional,
 * required), the extra options it takes and whether it works without a plan.
 *
 * @return array<string, array<string, mixed>>
 */
function sportstatsapi_widgets() {
	return array(
		'table'      => array(
			'kind'    => 'table',
			'title'   => __( 'League table', 'sportstatsapi-widgets' ),
			'team'    => 'optional',
			'options' => array( 'size', 'rows' ),
			'free'    => true,
		),
		'scores'     => array(
			'kind'    => 'scores',
			'title'   => __( 'Live scores', 'sportstatsapi-widgets' ),
			'team'    => 'optional',
			'options' => array( 'round' ),
			'free'    => true,
		),
		'fixtures'   => array(
			'kind'    => 'fixtures',
			'title'   => __( 'Fixtures', 'sportstatsapi-widgets' ),
			'team'    => 'optional',
			'options' => array( 'view', 'country' ),
			'free'    => true,
		),
		'ticker'     => array(
			'kind'    => 'ticker',
			'title'   => __( 'Results ticker', 'sportstatsapi-widgets' ),
			'team'    => 'none',
			'options' => array(),
			'free'    => true,
		),
		'next-match' => array(
			'kind'    => 'next-match',
			'title'   => __( 'Next match', 'sportstatsapi-widgets' ),
			'team'    => 'required',
			'options' => array( 'country' ),
			'free'    => true,
		),
		'team-form'  => array(
			'kind'    => 'team-form',
			'title'   => __( 'Team form', 'sportstatsapi-widgets' ),
			'team'    => 'required',
			'options' => array(),
			'free'    => false,
		),
	);
}

/**
 * The plugin settings with defaults.
 *
 * @return array{key:string,theme:string,accent:string,lang:string,fallback:bool}
 */
function sportstatsapi_settings() {
	$s = get_option( 'sportstatsapi_settings', array() );
	return array(
		'key'      => isset( $s['key'] ) ? (string) $s['key'] : '',
		'theme'    => isset( $s['theme'] ) ? (string) $s['theme'] : 'auto',
		'accent'   => isset( $s['accent'] ) ? (string) $s['accent'] : '',
		'lang'     => isset( $s['lang'] ) ? (string) $s['lang'] : '',
		'fallback' => ! empty( $s['fallback'] ),
	);
}

/**
 * The key to put in widgets: the saved key, or '' while the API refuses it
 * (unknown, revoked, another website): free widgets then work without it.
 *
 * @return string
 */
function sportstatsapi_key() {
	$key = sportstatsapi_clean( 'key', sportstatsapi_settings()['key'] );
	return ( '' !== $key && '' !== sportstatsapi_key_error( $key ) ) ? '' : $key;
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
 * the last answer.
 */
function sportstatsapi_check_key() {
	$key = sportstatsapi_clean( 'key', sportstatsapi_settings()['key'] );
	if ( '' === $key ) {
		delete_option( 'sportstatsapi_key_state' );
		return;
	}
	$home = wp_parse_url( home_url() );
	$res  = wp_remote_get(
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
			'headers' => array( 'Origin' => $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) ),
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
	} elseif ( ! in_array( $error, array( 'UNKNOWN_KEY', 'WRONG_WEBSITE' ), true ) ) {
		return;
	}
	update_option(
		'sportstatsapi_key_state',
		array(
			'key'     => $key,
			'error'   => $error,
			'checked' => time(),
		),
		false
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
	$value = trim( (string) $value );
	$enums = array(
		'theme' => array( 'light', 'dark', 'auto' ),
		'lang'  => array( 'en', 'de', 'es', 'it', 'fr' ),
		'size'  => array( 'compact', 'standard', 'full' ),
		'view'  => array( 'round', 'date' ),
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
	if ( null === $attrs || empty( $attrs['competition'] ) ) {
		return current_user_can( 'edit_posts' )
			? '<p class="sportstatsapi-missing">' . esc_html__( 'SportStatsAPI: choose a widget and a competition.', 'sportstatsapi-widgets' ) . '</p>'
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
				'settings' => admin_url( 'options-general.php?page=sportstatsapi' ),
			)
		) . ';',
		'before'
	);
	wp_set_script_translations( 'sportstatsapi-editor', 'sportstatsapi-widgets' );

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
	if ( ! isset( $kinds[ $attrs['widget'] ] ) ) {
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
	// Only a paid key goes server-side (with the site as Origin): free widgets need none,
	// and a free key's views are counted in the browser.
	$headers = array();
	if ( isset( $attrs['key'] ) && 0 === strpos( $attrs['key'], 'wk_live_' ) ) {
		$query['key'] = $attrs['key'];
		$home         = wp_parse_url( home_url() );
		$headers      = array( 'Origin' => $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) );
	}
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
	$out = '';
	if ( ! empty( $d['table']['rows'] ) ) {
		$out .= '<table class="sportstatsapi-fallback"><thead><tr><th>#</th><th>' . esc_html__( 'Team', 'sportstatsapi-widgets' ) . '</th><th>' . esc_html__( 'P', 'sportstatsapi-widgets' ) . '</th><th>' . esc_html__( 'Pts', 'sportstatsapi-widgets' ) . '</th></tr></thead><tbody>';
		foreach ( $d['table']['rows'] as $r ) {
			$out .= '<tr><td>' . (int) $r['pos'] . '</td><td>' . esc_html( $r['team'] ) . '</td><td>' . (int) $r['played'] . '</td><td>' . (int) $r['points'] . '</td></tr>';
		}
		$out .= '</tbody></table>';
	}
	$matches = isset( $d['matches'] ) ? $d['matches'] : array();
	if ( ! empty( $d['groups'] ) ) {
		foreach ( $d['groups'] as $g ) {
			$matches = array_merge( $matches, isset( $g['matches'] ) ? $g['matches'] : array() );
		}
	}
	if ( ! empty( $d['club']['next'] ) ) {
		$matches[] = $d['club']['next'];
	}
	if ( $matches ) {
		$out .= '<ul class="sportstatsapi-fallback">';
		foreach ( $matches as $m ) {
			$score = ( ! empty( $m['played'] ) || ! empty( $m['live'] ) ) && isset( $m['home_score'], $m['away_score'] )
				? $m['home_score'] . '–' . $m['away_score']
				: 'v';
			$when  = empty( $m['kickoff'] ) ? '' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $m['kickoff'] ) );
			$text  = esc_html( $m['home'] . ' ' . $score . ' ' . $m['away'] ) . ( $when ? ' <small>' . esc_html( $when ) . '</small>' : '' );
			$out  .= '<li>' . ( empty( $m['url'] ) || '/' !== $m['url'][0] ? $text : '<a href="' . esc_url( SPORTSTATSAPI_SITE . $m['url'] ) . '">' . $text . '</a>' ) . '</li>';
		}
		$out .= '</ul>';
	}
	if ( '' !== $out && ! empty( $d['branding'] ) ) {
		$out .= '<p><a href="' . esc_url( SPORTSTATSAPI_SITE . '/' ) . '">' . esc_html__( 'Data by SportStatsAPI', 'sportstatsapi-widgets' ) . '</a></p>';
	}
	return $out;
}
