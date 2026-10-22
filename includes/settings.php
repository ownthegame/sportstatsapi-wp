<?php
/**
 * Settings → SportStatsAPI: the widget key, Connect, and the defaults.
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * The account service URL that connects a site (see PLAN.md, "Connect").
 * SPORTSTATSAPI_CONNECT_URL or the sportstatsapi_connect_url filter override it.
 *
 * @return string
 */
function sportstatsapi_connect_url() {
	$url = defined( 'SPORTSTATSAPI_CONNECT_URL' ) ? SPORTSTATSAPI_CONNECT_URL : 'https://account.sportstatsapi.com/connect/wordpress';
	return (string) apply_filters( 'sportstatsapi_connect_url', $url );
}

/**
 * Where the account service sends the key back: this settings page.
 *
 * @return string
 */
function sportstatsapi_connect_return() {
	return admin_url( 'options-general.php?page=sportstatsapi' );
}

/**
 * Why Connect cannot work here, '' when it can: the way back (the settings page)
 * must be https (http only on localhost) on the site's host and port, and the
 * account service must answer (checked at most once an hour; before the service
 * has the endpoint it answers 404).
 *
 * @return string The reason, for the settings page.
 */
function sportstatsapi_connect_problem() {
	$url  = sportstatsapi_connect_url();
	$ret  = wp_parse_url( sportstatsapi_connect_return() );
	$site = wp_parse_url( home_url( '/' ) );
	if ( '' === $url || empty( $ret['host'] ) || empty( $ret['scheme'] ) || empty( $site['host'] ) || empty( $site['scheme'] ) ) {
		return __( 'Connect is off on this site.', 'sportstatsapi-live-scores' );
	}
	$local = in_array( $ret['host'], array( 'localhost', '127.0.0.1' ), true );
	if ( 'https' !== $ret['scheme'] && ! ( 'http' === $ret['scheme'] && $local ) ) {
		return __( 'Connect needs your WordPress admin on HTTPS.', 'sportstatsapi-live-scores' );
	}
	$port = function ( $u ) {
		return isset( $u['port'] ) ? (int) $u['port'] : ( 'https' === $u['scheme'] ? 443 : 80 );
	};
	if ( strtolower( $ret['host'] ) !== strtolower( $site['host'] ) || $port( $ret ) !== $port( $site ) ) {
		return __( 'Connect needs your WordPress admin and your site on the same address (host and port).', 'sportstatsapi-live-scores' );
	}
	$cache = 'sportstatsapi_connect_' . md5( $url );
	$ok    = get_transient( $cache );
	if ( false === $ok ) {
		$res  = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$ok   = ( $code >= 200 && $code < 500 && 404 !== $code ) ? 'yes' : 'no';
		set_transient( $cache, $ok, HOUR_IN_SECONDS );
	}
	return 'yes' === $ok ? '' : __( 'The SportStatsAPI account service does not offer Connect right now.', 'sportstatsapi-live-scores' );
}

/**
 * Registers the option and the menu entry.
 */
function sportstatsapi_admin_init() {
	register_setting(
		'sportstatsapi',
		'sportstatsapi_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sportstatsapi_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'sportstatsapi_admin_init' );

/**
 * Adds Settings → SportStatsAPI.
 */
function sportstatsapi_admin_menu() {
	add_options_page( 'SportStatsAPI', 'SportStatsAPI', 'manage_options', 'sportstatsapi', 'sportstatsapi_settings_page' );
}
add_action( 'admin_menu', 'sportstatsapi_admin_menu' );

/**
 * A Settings link on the Plugins screen.
 *
 * @param array $links Links.
 * @return array
 */
function sportstatsapi_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=sportstatsapi' ) ) . '">' . esc_html__( 'Settings', 'sportstatsapi-live-scores' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SPORTSTATSAPI_DIR . 'sportstatsapi-live-scores.php' ), 'sportstatsapi_action_links' );

/**
 * Sanitizes the settings form.
 *
 * @param mixed $in Submitted values.
 * @return array
 */
function sportstatsapi_sanitize_settings( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$key = isset( $in['key'] ) ? trim( sanitize_text_field( $in['key'] ) ) : '';
	if ( '' !== $key && '' === sportstatsapi_clean( 'key', $key ) ) {
		add_settings_error( 'sportstatsapi_settings', 'key', __( 'That is not a SportStatsAPI widget key (ssw_free_… or wk_live_…). The key was not saved.', 'sportstatsapi-live-scores' ) );
		$key = sportstatsapi_settings()['key'];
	}
	$theme  = isset( $in['theme'] ) ? sportstatsapi_clean( 'theme', $in['theme'] ) : '';
	$credit = ! empty( $in['credit'] );
	if ( 0 === strpos( $key, 'ssw_free_' ) && ! $credit ) {
		add_settings_error( 'sportstatsapi_settings', 'credit', __( 'Your free key is saved, but it needs your yes to the small "Data by SportStatsAPI" link: free widgets are hidden until you tick "Credit link".', 'sportstatsapi-live-scores' ), 'warning' );
	}
	return array(
		'key'      => $key,
		'theme'    => '' === $theme ? 'auto' : $theme,
		'accent'   => isset( $in['accent'] ) ? sportstatsapi_clean( 'accent', $in['accent'] ) : '',
		'lang'     => isset( $in['lang'] ) ? sportstatsapi_clean( 'lang', $in['lang'] ) : '',
		'fallback' => ! empty( $in['fallback'] ),
		'credit'   => $credit,
	);
}

/**
 * Connect, step 1 (admin-post.php?action=sportstatsapi_connect): to the account
 * service with this site, the way back and a one-time state.
 */
function sportstatsapi_connect_start() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'sportstatsapi-live-scores' ), 403 );
	}
	check_admin_referer( 'sportstatsapi_connect' );
	$problem = sportstatsapi_connect_problem();
	if ( '' !== $problem ) {
		wp_die( esc_html( $problem . ' ' . __( 'Paste your key instead.', 'sportstatsapi-live-scores' ) ) );
	}
	// The free key comes with the free plan's credit link: no yes, no Connect.
	if ( empty( $_POST['sportstatsapi_credit'] ) ) {
		wp_safe_redirect( add_query_arg( 'sportstatsapi', 'credit', sportstatsapi_connect_return() ) );
		exit;
	}
	// The yes is saved with the key when Connect comes back (step 2).
	$state = wp_generate_password( 32, false ); // [A-Za-z0-9].
	set_transient(
		'sportstatsapi_state_' . get_current_user_id(),
		array(
			'state'  => $state,
			'credit' => true,
		),
		15 * MINUTE_IN_SECONDS
	);
	$target = add_query_arg(
		array(
			'site'   => rawurlencode( home_url( '/' ) ),
			'return' => rawurlencode( sportstatsapi_connect_return() ),
			'state'  => $state,
		),
		sportstatsapi_connect_url()
	);
	// The account service is another host: wp_redirect, not wp_safe_redirect.
	wp_redirect( $target ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	exit;
}
add_action( 'admin_post_sportstatsapi_connect', 'sportstatsapi_connect_start' );

/**
 * Connect, step 2: the account service comes back to the settings page with
 * state and key, or state and error=denied (by name, among other parameters).
 * The state stands in for a nonce: made for this user in step 1, 15 minutes, once.
 */
function sportstatsapi_connect_finish() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the one-time state is the check.
	if ( ! isset( $_GET['page'], $_GET['state'] ) || 'sportstatsapi' !== $_GET['page'] || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
	$key   = isset( $_GET['key'] ) ? sportstatsapi_clean( 'key', sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : '';
	$error = isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';
	// phpcs:enable
	$name     = 'sportstatsapi_state_' . get_current_user_id();
	$expected = get_transient( $name );
	delete_transient( $name );
	$result = 'failed';
	if ( is_array( $expected ) && isset( $expected['state'] ) && is_string( $expected['state'] ) && '' !== $state && hash_equals( $expected['state'], $state ) ) {
		if ( 'denied' === $error ) {
			$result = 'denied';
		} elseif ( '' !== $key ) {
			$settings           = sportstatsapi_settings();
			$settings['key']    = $key;
			$settings['credit'] = ! empty( $expected['credit'] );
			update_option( 'sportstatsapi_settings', $settings );
			$result = 'connected';
		}
	}
	wp_safe_redirect( add_query_arg( 'sportstatsapi', $result, sportstatsapi_connect_return() ) );
	exit;
}
add_action( 'admin_init', 'sportstatsapi_connect_finish' );

/**
 * The key notices that apply now: kind => [ state, text ]. The state names the
 * condition (key and error code), so a dismissed notice comes back when it changes.
 *
 * @return array
 */
function sportstatsapi_key_notices() {
	$key = sportstatsapi_clean( 'key', sportstatsapi_settings()['key'] );
	if ( '' === $key ) {
		return array();
	}
	$error = sportstatsapi_key_error( $key );
	$out   = array();
	if ( 'WRONG_WEBSITE' === $error ) {
		$text = __( 'SportStatsAPI: your key isn’t set up for this website, so widgets are hidden on your site. Add this site on your account page, or connect again.', 'sportstatsapi-live-scores' );
	} elseif ( 'UNKNOWN_KEY' === $error ) {
		$text = __( 'SportStatsAPI: your key isn’t recognised, so widgets are hidden on your site. Connect again or paste a working key.', 'sportstatsapi-live-scores' );
	} elseif ( 'VIEW_LIMIT' === $error ) {
		$text = __( 'SportStatsAPI: your free key reached 30,000 views this month: widgets show no data until the month ends. Upgrade: https://sportstatsapi.com/pricing/', 'sportstatsapi-live-scores' );
	}
	if ( isset( $text ) ) {
		$out['key'] = array( md5( $key . '|' . $error ), $text );
	}
	if ( 0 === strpos( $key, 'ssw_free_' ) && ! sportstatsapi_settings()['credit'] ) {
		$out['credit'] = array( md5( $key ), __( 'SportStatsAPI: your free key needs your yes to the small "Data by SportStatsAPI" link; free widgets are hidden until you tick it in Settings.', 'sportstatsapi-live-scores' ) );
	}
	return $out;
}

/**
 * Key notices on the dashboard, the Plugins screen and the settings page while
 * the API refuses the saved key, the free views are used up, or a free key waits
 * for the yes to the credit link. Dismissing one hides it for that user until its
 * condition changes; the settings page always shows the current state.
 */
function sportstatsapi_key_notice() {
	$screen = get_current_screen();
	if ( ! current_user_can( 'manage_options' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'settings_page_sportstatsapi' ), true ) ) {
		return;
	}
	$settings  = 'settings_page_sportstatsapi' === $screen->id;
	$dismissed = array_filter( (array) get_user_meta( get_current_user_id(), 'sportstatsapi_dismissed', true ) );
	$shown     = false;
	foreach ( sportstatsapi_key_notices() as $kind => $notice ) {
		if ( ! $settings && isset( $dismissed[ $kind ] ) && $dismissed[ $kind ] === $notice[0] ) {
			continue;
		}
		printf(
			'<div class="notice notice-warning%s" data-sportstatsapi-notice="%s"><p>%s <a href="%s">%s</a></p></div>',
			$settings ? '' : ' is-dismissible',
			esc_attr( $kind ),
			esc_html( $notice[1] ),
			esc_url( sportstatsapi_connect_return() ),
			esc_html__( 'Settings', 'sportstatsapi-live-scores' )
		);
		$shown = true;
	}
	if ( $shown && ! $settings ) {
		wp_add_inline_script(
			'common',
			sprintf(
				'jQuery(document).on("click","[data-sportstatsapi-notice] .notice-dismiss",function(){jQuery.post(ajaxurl,{action:"sportstatsapi_dismiss",kind:jQuery(this).closest("[data-sportstatsapi-notice]").data("sportstatsapiNotice"),_ajax_nonce:%s});});',
				wp_json_encode( wp_create_nonce( 'sportstatsapi_dismiss' ) )
			)
		);
	}
}
add_action( 'admin_notices', 'sportstatsapi_key_notice' );

/**
 * AJAX: the user dismissed a key notice; remember its current condition.
 */
function sportstatsapi_dismiss_notice() {
	check_ajax_referer( 'sportstatsapi_dismiss' );
	$kind    = isset( $_POST['kind'] ) ? sanitize_key( $_POST['kind'] ) : '';
	$notices = sportstatsapi_key_notices();
	if ( ! current_user_can( 'manage_options' ) || ! isset( $notices[ $kind ] ) ) {
		wp_send_json_error( null, 400 );
	}
	$dismissed          = array_filter( (array) get_user_meta( get_current_user_id(), 'sportstatsapi_dismissed', true ) );
	$dismissed[ $kind ] = $notices[ $kind ][0];
	update_user_meta( get_current_user_id(), 'sportstatsapi_dismissed', $dismissed );
	wp_send_json_success();
}
add_action( 'wp_ajax_sportstatsapi_dismiss', 'sportstatsapi_dismiss_notice' );

/**
 * The settings page.
 */
function sportstatsapi_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = sportstatsapi_settings();
	$problem = sportstatsapi_connect_problem();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$result = isset( $_GET['sportstatsapi'] ) && ! isset( $_GET['settings-updated'] ) ? sanitize_key( $_GET['sportstatsapi'] ) : '';
	$langs  = array(
		''   => __( 'The page’s language', 'sportstatsapi-live-scores' ),
		'en' => 'English',
		'de' => 'Deutsch',
		'es' => 'Español',
		'it' => 'Italiano',
		'fr' => 'Français',
	);
	$themes = array(
		'auto'  => __( 'Auto (the visitor’s setting)', 'sportstatsapi-live-scores' ),
		'light' => __( 'Light', 'sportstatsapi-live-scores' ),
		'dark'  => __( 'Dark', 'sportstatsapi-live-scores' ),
	);
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:10px"><img src="<?php echo esc_url( SPORTSTATSAPI_URL . 'assets/icon.svg' ); ?>" width="32" height="32" alt="" style="border-radius:7px"> <?php esc_html_e( 'SportStatsAPI Live Scores & League Tables', 'sportstatsapi-live-scores' ); ?></h1>
		<?php if ( 'connected' === $result ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Connected: your widget key is saved.', 'sportstatsapi-live-scores' ); ?></p></div>
		<?php elseif ( 'denied' === $result ) : ?>
			<div class="notice notice-info"><p><?php esc_html_e( 'Connect was cancelled. Nothing changed.', 'sportstatsapi-live-scores' ); ?></p></div>
		<?php elseif ( 'credit' === $result ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'To connect, agree that free widgets show a small "Data by SportStatsAPI" link.', 'sportstatsapi-live-scores' ); ?></p></div>
		<?php elseif ( 'failed' === $result ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Connect did not finish. Try again, or paste your key.', 'sportstatsapi-live-scores' ); ?></p></div>
		<?php endif; ?>

		<p>
			<?php esc_html_e( 'Add widgets with the SportStatsAPI blocks in the editor, or with a shortcode:', 'sportstatsapi-live-scores' ); ?>
			<code>[sportstatsapi widget="table" competition="england--premier-league"]</code>
		</p>
		<p>
			<?php esc_html_e( 'Widgets show on your site once it is connected: Connect creates a free SportStatsAPI key (live scores, tables, fixtures, ticker, next match and calendar for the free competitions, 25,000 views a month, with a small "Data by SportStatsAPI" link in each widget). A paid key opens all 19 widgets and every competition, and removes the credit link.', 'sportstatsapi-live-scores' ); ?>
		</p>

		<?php if ( '' === $problem ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sportstatsapi_connect">
				<?php wp_nonce_field( 'sportstatsapi_connect' ); ?>
				<p>
					<label>
						<input type="checkbox" name="sportstatsapi_credit" value="1" required <?php checked( $s['credit'] ); ?>>
						<?php esc_html_e( 'Yes, free widgets may show a small "Data by SportStatsAPI" link (required for the free key).', 'sportstatsapi-live-scores' ); ?>
					</label>
				</p>
				<p>
					<?php submit_button( __( 'Connect with SportStatsAPI', 'sportstatsapi-live-scores' ), 'secondary', 'sportstatsapi-connect', false ); ?>
					<span class="description"><?php esc_html_e( 'Opens your SportStatsAPI account and brings this site’s free widget key back here.', 'sportstatsapi-live-scores' ); ?></span>
				</p>
			</form>
		<?php else : ?>
			<p class="description"><?php echo esc_html( $problem . ' ' . __( 'Paste the widget key from your SportStatsAPI account below.', 'sportstatsapi-live-scores' ) ); ?></p>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'sportstatsapi' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sportstatsapi-key"><?php esc_html_e( 'Widget key', 'sportstatsapi-live-scores' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="sportstatsapi-key" name="sportstatsapi_settings[key]" value="<?php echo esc_attr( $s['key'] ); ?>" placeholder="ssw_free_… / wk_live_…" autocomplete="off">
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to the SportStatsAPI account page */
								esc_html__( 'Required to show widgets. Connect fills it in, or copy it from your %s. The key is visible in your page source, like any embed code; it only works for widgets and for your website’s address.', 'sportstatsapi-live-scores' ),
								'<a href="https://account.sportstatsapi.com/account#widgets" target="_blank" rel="noopener">' . esc_html__( 'account page', 'sportstatsapi-live-scores' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Credit link', 'sportstatsapi-live-scores' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sportstatsapi_settings[credit]" value="1" <?php checked( $s['credit'] ); ?>>
							<?php esc_html_e( 'Yes, free widgets may show a small "Data by SportStatsAPI" link', 'sportstatsapi-live-scores' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Required for a free key: without it free widgets are not shown. Paid keys show no link.', 'sportstatsapi-live-scores' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sportstatsapi-theme"><?php esc_html_e( 'Theme', 'sportstatsapi-live-scores' ); ?></label></th>
					<td>
						<select id="sportstatsapi-theme" name="sportstatsapi_settings[theme]">
							<?php foreach ( $themes as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['theme'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sportstatsapi-accent"><?php esc_html_e( 'Accent colour', 'sportstatsapi-live-scores' ); ?></label></th>
					<td>
						<input type="text" id="sportstatsapi-accent" name="sportstatsapi_settings[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>" placeholder="#2340F5" pattern="#[0-9a-fA-F]{6}" size="9" maxlength="7">
						<p class="description"><?php esc_html_e( 'Your brand colour as #RRGGBB. Applies with a paid key.', 'sportstatsapi-live-scores' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sportstatsapi-lang"><?php esc_html_e( 'Language', 'sportstatsapi-live-scores' ); ?></label></th>
					<td>
						<select id="sportstatsapi-lang" name="sportstatsapi_settings[lang]">
							<?php foreach ( $langs as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['lang'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Search engines', 'sportstatsapi-live-scores' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sportstatsapi_settings[fallback]" value="1" <?php checked( $s['fallback'] ); ?>>
							<?php esc_html_e( 'Also print the data as plain HTML for visitors without JavaScript', 'sportstatsapi-live-scores' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Paid key only. Your server loads each widget’s data from api.sportstatsapi.com and keeps it 10 minutes.', 'sportstatsapi-live-scores' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
