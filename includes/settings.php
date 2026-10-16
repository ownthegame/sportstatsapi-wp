<?php
/**
 * Settings → SportStatsAPI: the widget key, Connect, and the defaults.
 *
 * @package SportStatsAPI_Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * The account service URL that connects a site (see PLAN.md, "Connect").
 * Empty until the service offers it: the Connect button is then hidden.
 *
 * @return string
 */
function sportstatsapi_connect_url() {
	$url = defined( 'SPORTSTATSAPI_CONNECT_URL' ) ? SPORTSTATSAPI_CONNECT_URL : '';
	return (string) apply_filters( 'sportstatsapi_connect_url', $url );
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
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=sportstatsapi' ) ) . '">' . esc_html__( 'Settings', 'sportstatsapi-widgets' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SPORTSTATSAPI_DIR . 'sportstatsapi-widgets.php' ), 'sportstatsapi_action_links' );

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
		add_settings_error( 'sportstatsapi_settings', 'key', __( 'That is not a SportStatsAPI widget key (ssw_free_… or wk_live_…). The key was not saved.', 'sportstatsapi-widgets' ) );
		$key = sportstatsapi_settings()['key'];
	}
	$theme = isset( $in['theme'] ) ? sportstatsapi_clean( 'theme', $in['theme'] ) : '';
	return array(
		'key'      => $key,
		'theme'    => '' === $theme ? 'auto' : $theme,
		'accent'   => isset( $in['accent'] ) ? sportstatsapi_clean( 'accent', $in['accent'] ) : '',
		'lang'     => isset( $in['lang'] ) ? sportstatsapi_clean( 'lang', $in['lang'] ) : '',
		'fallback' => ! empty( $in['fallback'] ),
	);
}

/**
 * Connect, step 1 (admin-post.php?action=sportstatsapi_connect): to the account
 * service with this site, the way back and a one-time state.
 */
function sportstatsapi_connect_start() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'sportstatsapi-widgets' ), 403 );
	}
	check_admin_referer( 'sportstatsapi_connect' );
	$url = sportstatsapi_connect_url();
	if ( '' === $url ) {
		wp_die( esc_html__( 'Connect is not available yet: paste your key instead.', 'sportstatsapi-widgets' ) );
	}
	$state = wp_generate_password( 32, false );
	set_transient( 'sportstatsapi_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );
	$target = add_query_arg(
		array(
			'site'   => rawurlencode( home_url( '/' ) ),
			'return' => rawurlencode( admin_url( 'admin-post.php?action=sportstatsapi_connected' ) ),
			'state'  => $state,
		),
		$url
	);
	// The account service is another host: wp_redirect, not wp_safe_redirect.
	wp_redirect( $target ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	exit;
}
add_action( 'admin_post_sportstatsapi_connect', 'sportstatsapi_connect_start' );

/**
 * Connect, step 2 (admin-post.php?action=sportstatsapi_connected&state=&key=):
 * the account service sends the key back. The state stands in for a nonce: it
 * was made for this user in step 1 and works once.
 */
function sportstatsapi_connect_finish() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'sportstatsapi-widgets' ), 403 );
	}
	$name     = 'sportstatsapi_state_' . get_current_user_id();
	$expected = get_transient( $name );
	delete_transient( $name );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the one-time state is the check.
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	$key   = isset( $_GET['key'] ) ? sportstatsapi_clean( 'key', sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : '';
	// phpcs:enable
	$result = 'failed';
	if ( is_string( $expected ) && '' !== $state && hash_equals( $expected, $state ) && '' !== $key ) {
		$settings        = sportstatsapi_settings();
		$settings['key'] = $key;
		update_option( 'sportstatsapi_settings', $settings );
		$result = 'connected';
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=sportstatsapi&sportstatsapi=' . $result ) );
	exit;
}
add_action( 'admin_post_sportstatsapi_connected', 'sportstatsapi_connect_finish' );

/**
 * The settings page.
 */
function sportstatsapi_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = sportstatsapi_settings();
	$connect = sportstatsapi_connect_url();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$result = isset( $_GET['sportstatsapi'] ) && ! isset( $_GET['settings-updated'] ) ? sanitize_key( $_GET['sportstatsapi'] ) : '';
	$langs  = array(
		''   => __( 'The page’s language', 'sportstatsapi-widgets' ),
		'en' => 'English',
		'de' => 'Deutsch',
		'es' => 'Español',
		'it' => 'Italiano',
		'fr' => 'Français',
	);
	$themes = array(
		'auto'  => __( 'Auto (the visitor’s setting)', 'sportstatsapi-widgets' ),
		'light' => __( 'Light', 'sportstatsapi-widgets' ),
		'dark'  => __( 'Dark', 'sportstatsapi-widgets' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SportStatsAPI Widgets', 'sportstatsapi-widgets' ); ?></h1>
		<?php if ( 'connected' === $result ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Connected: your widget key is saved.', 'sportstatsapi-widgets' ); ?></p></div>
		<?php elseif ( 'failed' === $result ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Connect did not finish. Try again, or paste your key.', 'sportstatsapi-widgets' ); ?></p></div>
		<?php endif; ?>

		<p>
			<?php esc_html_e( 'Add widgets with the SportStatsAPI blocks in the editor, or with a shortcode:', 'sportstatsapi-widgets' ); ?>
			<code>[sportstatsapi widget="table" competition="england--premier-league"]</code>
		</p>
		<p>
			<?php esc_html_e( 'Live scores, tables, fixtures, the ticker and next match work without a key for the free competitions. A key from your SportStatsAPI account counts your free views; a paid key opens every competition and widget and removes the credit link.', 'sportstatsapi-widgets' ); ?>
		</p>

		<?php if ( '' !== $connect ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sportstatsapi_connect">
				<?php wp_nonce_field( 'sportstatsapi_connect' ); ?>
				<p>
					<?php submit_button( __( 'Connect with SportStatsAPI', 'sportstatsapi-widgets' ), 'secondary', 'sportstatsapi-connect', false ); ?>
					<span class="description"><?php esc_html_e( 'Opens your SportStatsAPI account and brings this site’s free widget key back here.', 'sportstatsapi-widgets' ); ?></span>
				</p>
			</form>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'sportstatsapi' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sportstatsapi-key"><?php esc_html_e( 'Widget key', 'sportstatsapi-widgets' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="sportstatsapi-key" name="sportstatsapi_settings[key]" value="<?php echo esc_attr( $s['key'] ); ?>" placeholder="ssw_free_… / wk_live_…" autocomplete="off">
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to the SportStatsAPI account page */
								esc_html__( 'Optional. Find it on your %s.', 'sportstatsapi-widgets' ),
								'<a href="https://account.sportstatsapi.com/account#widgets" target="_blank" rel="noopener">' . esc_html__( 'account page', 'sportstatsapi-widgets' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sportstatsapi-theme"><?php esc_html_e( 'Theme', 'sportstatsapi-widgets' ); ?></label></th>
					<td>
						<select id="sportstatsapi-theme" name="sportstatsapi_settings[theme]">
							<?php foreach ( $themes as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['theme'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sportstatsapi-accent"><?php esc_html_e( 'Accent colour', 'sportstatsapi-widgets' ); ?></label></th>
					<td>
						<input type="text" id="sportstatsapi-accent" name="sportstatsapi_settings[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>" placeholder="#2340F5" pattern="#[0-9a-fA-F]{6}" size="9" maxlength="7">
						<p class="description"><?php esc_html_e( 'Your brand colour as #RRGGBB. Applies with a paid key.', 'sportstatsapi-widgets' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sportstatsapi-lang"><?php esc_html_e( 'Language', 'sportstatsapi-widgets' ); ?></label></th>
					<td>
						<select id="sportstatsapi-lang" name="sportstatsapi_settings[lang]">
							<?php foreach ( $langs as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['lang'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Search engines', 'sportstatsapi-widgets' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sportstatsapi_settings[fallback]" value="1" <?php checked( $s['fallback'] ); ?>>
							<?php esc_html_e( 'Also print the data as plain HTML for visitors without JavaScript', 'sportstatsapi-widgets' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Your server loads each widget’s data from api.sportstatsapi.com and keeps it 10 minutes.', 'sportstatsapi-widgets' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
