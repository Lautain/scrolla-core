<?php
/**
 * Module Beveiliging: aanvulling op de WP Toolkit-beveiliging van Plesk.
 * Inloggen via WP Toolkit (eenmalige link) loopt niet via het wachtwoordformulier en wordt nooit geblokkeerd.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Beveiliging {

	const LOG = 'scrolla_login_log';

	public function __construct() {
		// Loginpogingen beperken
		add_filter( 'authenticate', [ $this, 'check_lock' ], 30, 3 );
		add_action( 'wp_login_failed', [ $this, 'failed' ] );
		add_action( 'wp_login', [ $this, 'success' ], 10, 2 );
		add_action( 'admin_menu', [ $this, 'menu' ], 101 );
		add_action( 'admin_post_scrolla_unlock', [ $this, 'unlock' ] );
		add_action( 'admin_post_scrolla_whitelist', [ $this, 'save_whitelist' ] );

		// XML-RPC uit
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', function ( $h ) {
			unset( $h['X-Pingback'] );
			return $h;
		} );
		add_filter( 'pings_open', '__return_false' );

		// Gebruikers niet op te vragen
		add_action( 'template_redirect', [ $this, 'block_author' ], 1 );
		add_filter( 'rest_endpoints', [ $this, 'rest_users' ] );
		add_filter( 'oembed_response_data', function ( $d ) {
			unset( $d['author_name'], $d['author_url'] );
			return $d;
		} );
		add_filter( 'wp_sitemaps_add_provider', fn( $p, $name ) => 'users' === $name ? false : $p, 10, 2 );

		// Applicatiewachtwoorden uit
		add_filter( 'wp_is_application_passwords_available', '__return_false' );

		// Security-headers
		add_action( 'send_headers', [ $this, 'headers' ] );
		add_action( 'admin_init', [ $this, 'headers' ] );
		add_action( 'login_init', [ $this, 'headers' ] );
	}

	private function ip(): string {
		return preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	}

	private function whitelisted( string $ip ): bool {
		$list = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) Scrolla_Core_Settings::get( 'login_whitelist' ) ) ) );
		return in_array( $ip, $list, true );
	}

	public function check_lock( $user, $username, $password ) {
		$ip = $this->ip();
		if ( $ip && ! $this->whitelisted( $ip ) && ( $until = (int) get_transient( 'scrolla_lock_' . md5( $ip ) ) ) > time() ) {
			$min = max( 1, (int) ceil( ( $until - time() ) / 60 ) );
			return new WP_Error( 'scrolla_locked', sprintf( '<strong>Te veel mislukte pogingen.</strong> Probeer het over %d %s opnieuw, of gebruik "Wachtwoord vergeten".', $min, 1 === $min ? 'minuut' : 'minuten' ) );
		}
		return $user;
	}

	public function failed( $username ): void {
		$ip = $this->ip();
		if ( ! $ip || $this->whitelisted( $ip ) ) {
			return;
		}
		$s     = Scrolla_Core_Settings::get();
		$key   = md5( $ip );
		$tries = (int) get_transient( 'scrolla_tries_' . $key ) + 1;
		set_transient( 'scrolla_tries_' . $key, $tries, DAY_IN_SECONDS );
		$locked = 0;
		if ( $tries >= (int) $s['login_attempts'] ) {
			$locks  = (int) get_transient( 'scrolla_locks_' . $key ) + 1;
			set_transient( 'scrolla_locks_' . $key, $locks, DAY_IN_SECONDS );
			$locked = $locks >= (int) $s['login_long_after'] ? DAY_IN_SECONDS : (int) $s['login_lock_min'] * MINUTE_IN_SECONDS;
			set_transient( 'scrolla_lock_' . $key, time() + $locked, $locked );
			delete_transient( 'scrolla_tries_' . $key );
		}
		$log = (array) get_option( self::LOG, [] );
		array_unshift( $log, [ 't' => time(), 'ip' => $ip, 'user' => sanitize_user( (string) $username ), 'lock' => $locked ] );
		update_option( self::LOG, array_slice( $log, 0, 500 ), false );
	}

	public function success( $login, $user ): void {
		delete_transient( 'scrolla_tries_' . md5( $this->ip() ) );
	}

	public function menu(): void {
		if ( scrolla_is_scrolla_user() ) {
			add_submenu_page( 'scrolla-core', 'Loginpogingen', 'Loginpogingen', 'manage_options', 'scrolla-logins', [ $this, 'render' ] );
		}
	}

	public function render(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			wp_die( 'Geen toegang.' );
		}
		Scrolla_Core_Settings::styles();
		$log = (array) get_option( self::LOG, [] );
		echo '<div class="wrap sc-wrap">'; Scrolla_Core_Settings::head( 'loginpogingen' ); echo '<p class="sc-intro">Mislukte pogingen van de laatste tijd. Geblokkeerde IP-adressen zijn hier vrij te geven.</p>';
		echo '<div class="sc-card"><h2>Nooit blokkeren</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'scrolla_whitelist' );
		printf( '<input type="hidden" name="action" value="scrolla_whitelist"><div class="sc-field"><textarea name="whitelist" rows="2" placeholder="Eén IP-adres per regel">%s</textarea></div><div class="sc-actions"><button class="button">Opslaan</button><span class="sc-muted">Jouw IP: %s</span></div></form></div>', esc_textarea( (string) get_option( 'scrolla_login_whitelist', '' ) ), esc_html( $this->ip() ) );
		echo '<div class="sc-card"><table class="widefat striped sc-table"><thead><tr><th>Tijd</th><th>IP-adres</th><th>Gebruikersnaam</th><th>Blokkade</th><th></th></tr></thead><tbody>';
		foreach ( array_slice( $log, 0, 200 ) as $r ) {
			$active = (int) get_transient( 'scrolla_lock_' . md5( $r['ip'] ) ) > time();
			$btn    = $active ? '<a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=scrolla_unlock&ip=' . rawurlencode( $r['ip'] ) ), 'scrolla_unlock' ) ) . '">Vrijgeven</a>' : '';
			printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( wp_date( 'j M H:i', $r['t'] ) ), esc_html( $r['ip'] ), esc_html( $r['user'] ), $r['lock'] ? esc_html( human_time_diff( 0, $r['lock'] ) ) : '', $btn ); // phpcs:ignore
		}
		if ( ! $log ) {
			echo '<tr><td colspan="5" class="sc-muted">Nog geen mislukte pogingen.</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	public function save_whitelist(): void {
		if ( ! scrolla_is_scrolla_user() || ! check_admin_referer( 'scrolla_whitelist' ) ) {
			wp_die( 'Geen toegang.' );
		}
		update_option( 'scrolla_login_whitelist', sanitize_textarea_field( wp_unslash( $_POST['whitelist'] ?? '' ) ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=scrolla-logins' ) );
		exit;
	}

	public function unlock(): void {
		if ( ! scrolla_is_scrolla_user() || ! check_admin_referer( 'scrolla_unlock' ) ) {
			wp_die( 'Geen toegang.' );
		}
		$key = md5( sanitize_text_field( wp_unslash( $_GET['ip'] ?? '' ) ) );
		delete_transient( 'scrolla_lock_' . $key );
		delete_transient( 'scrolla_locks_' . $key );
		delete_transient( 'scrolla_tries_' . $key );
		wp_safe_redirect( admin_url( 'admin.php?page=scrolla-logins' ) );
		exit;
	}

	public function block_author(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		if ( isset( $_GET['author'] ) || is_author() ) { // phpcs:ignore
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public function rest_users( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			foreach ( array_keys( $endpoints ) as $route ) {
				if ( preg_match( '#^/wp/v2/users#', $route ) ) {
					unset( $endpoints[ $route ] );
				}
			}
		}
		return $endpoints;
	}

	public function headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), payment=(), usb=()' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}
}

new Scrolla_Beveiliging();
