<?php
/**
 * Module SMTP: alle mail van de site via een SMTP-server, met testmail en mailog (laatste 200, max 30 dagen).
 * Het wachtwoord wordt versleuteld opgeslagen (op basis van de AUTH_KEY van de site).
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_SMTP {

	const OPTION = 'scrolla_smtp';
	const LOG    = 'scrolla_mail_log';

	public function __construct() {
		add_action( 'phpmailer_init', [ $this, 'mailer' ] );
		add_action( 'wp_mail_succeeded', fn( $m ) => $this->log( $m, '' ) );
		add_action( 'wp_mail_failed', fn( $e ) => $this->log( $e->get_error_data(), $e->get_error_message() ) );
		add_action( 'admin_menu', [ $this, 'menu' ], 103 );
		add_action( 'admin_post_scrolla_smtp_save', [ $this, 'save' ] );
	}

	private static function opt(): array {
		return wp_parse_args( (array) get_option( self::OPTION, [] ), [ 'host' => '', 'port' => 587, 'secure' => 'tls', 'user' => '', 'pass' => '', 'from' => '', 'from_name' => '' ] );
	}

	private static function crypt( string $v, bool $enc ): string {
		if ( '' === $v || ! function_exists( 'openssl_encrypt' ) ) {
			return $v;
		}
		$key = hash( 'sha256', defined( 'AUTH_KEY' ) ? AUTH_KEY : 'scrolla', true );
		if ( $enc ) {
			$iv = random_bytes( 16 );
			return base64_encode( $iv . openssl_encrypt( $v, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv ) );
		}
		$raw = base64_decode( $v, true );
		return $raw ? (string) openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) ) : '';
	}

	public function mailer( $phpmailer ): void {
		$o = self::opt();
		if ( ! $o['host'] ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $o['host'];
		$phpmailer->Port       = (int) $o['port'];
		$phpmailer->SMTPSecure = 'none' === $o['secure'] ? '' : $o['secure'];
		$phpmailer->SMTPAutoTLS = 'none' !== $o['secure'];
		if ( $o['user'] ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $o['user'];
			$phpmailer->Password = self::crypt( $o['pass'], false );
		}
		if ( $o['from'] ) {
			$phpmailer->setFrom( $o['from'], $o['from_name'] ?: get_bloginfo( 'name' ), false );
		}
	}

	private function log( $mail, string $error ): void {
		$log = array_filter( (array) get_option( self::LOG, [] ), fn( $r ) => $r['t'] > time() - 30 * DAY_IN_SECONDS );
		array_unshift( $log, [
			't'     => time(),
			'to'    => implode( ', ', (array) ( $mail['to'] ?? [] ) ),
			'sub'   => (string) ( $mail['subject'] ?? '' ),
			'error' => $error,
		] );
		update_option( self::LOG, array_slice( $log, 0, 200 ), false );
	}

	public function menu(): void {
		if ( scrolla_is_scrolla_user() ) {
			add_submenu_page( 'scrolla-core', 'SMTP en mailog', 'SMTP en mailog', 'manage_options', 'scrolla-smtp', [ $this, 'render' ] );
		}
	}

	public function render(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			wp_die( 'Geen toegang.' );
		}
		$o = self::opt();
		Scrolla_Core_Settings::styles();
		echo '<div class="wrap sc-wrap">'; Scrolla_Core_Settings::head( 'SMTP en mailog' ); echo '';
		if ( isset( $_GET['test'] ) ) {
			$ok = '1' === $_GET['test'];
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $ok ? 'success' : 'error', $ok ? 'Testmail verstuurd.' : 'Testmail mislukt, zie het mailog.' );
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'scrolla_smtp_save' );
		echo '<input type="hidden" name="action" value="scrolla_smtp_save"><div class="sc-card"><table class="form-table">';
		$row = fn( $l, $h ) => printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $l ), $h ); // phpcs:ignore
		$row( 'SMTP-server', sprintf( '<input type="text" name="host" value="%s" class="regular-text" placeholder="smtp.gmail.com">', esc_attr( $o['host'] ) ) );
		$row( 'Poort', sprintf( '<input name="port" type="number" value="%d" class="small-text">', (int) $o['port'] ) );
		$sel = '';
		foreach ( [ 'tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Geen' ] as $k => $l ) {
			$sel .= sprintf( '<option value="%s" %s>%s</option>', $k, selected( $o['secure'], $k, false ), $l );
		}
		$row( 'Versleuteling', '<select name="secure">' . $sel . '</select>' );
		$row( 'Gebruikersnaam', sprintf( '<input type="text" name="user" value="%s" class="regular-text" autocomplete="off">', esc_attr( $o['user'] ) ) );
		$row( 'Wachtwoord', '<input name="pass" type="password" value="" class="regular-text" autocomplete="new-password" placeholder="' . ( $o['pass'] ? '•••••••• (ongewijzigd laten)' : '' ) . '">' );
		$row( 'Afzender e-mail', sprintf( '<input type="email" name="from" value="%s" class="regular-text" placeholder="noreply@klantdomein.nl">', esc_attr( $o['from'] ) ) );
		$row( 'Afzender naam', sprintf( '<input type="text" name="from_name" value="%s" class="regular-text" placeholder="%s">', esc_attr( $o['from_name'] ), esc_attr( get_bloginfo( 'name' ) ) ) );
		$row( 'Testmail naar', '<input name="test_to" type="email" class="regular-text" placeholder="noreply@scrolla.nl"> <span class="sc-muted">Invullen = na opslaan een testmail sturen</span>' );
		echo '</table>';
		submit_button( 'Opslaan' );
		echo '</div></form><div class="sc-card"><h2>Mailog</h2><table class="widefat striped"><thead><tr><th>Tijd</th><th>Aan</th><th>Onderwerp</th><th>Status</th></tr></thead><tbody>';
		foreach ( (array) get_option( self::LOG, [] ) as $r ) {
			printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( wp_date( 'j M H:i', $r['t'] ) ), esc_html( $r['to'] ), esc_html( $r['sub'] ), $r['error'] ? '<span style="color:#d63638">' . esc_html( $r['error'] ) . '</span>' : 'Verstuurd' );
		}
		echo '</tbody></table></div></div>';
	}

	public function save(): void {
		if ( ! scrolla_is_scrolla_user() || ! check_admin_referer( 'scrolla_smtp_save' ) ) {
			wp_die( 'Geen toegang.' );
		}
		$o    = self::opt();
		$p    = wp_unslash( $_POST );
		$new  = [
			'host'      => sanitize_text_field( $p['host'] ?? '' ),
			'port'      => absint( $p['port'] ?? 587 ),
			'secure'    => in_array( $p['secure'] ?? '', [ 'tls', 'ssl', 'none' ], true ) ? $p['secure'] : 'tls',
			'user'      => sanitize_text_field( $p['user'] ?? '' ),
			'pass'      => '' !== ( $p['pass'] ?? '' ) ? self::crypt( (string) $p['pass'], true ) : $o['pass'],
			'from'      => sanitize_email( $p['from'] ?? '' ),
			'from_name' => sanitize_text_field( $p['from_name'] ?? '' ),
		];
		update_option( self::OPTION, $new, false );
		$q = '';
		if ( is_email( $p['test_to'] ?? '' ) ) {
			$ok = wp_mail( $p['test_to'], 'Testmail van ' . get_bloginfo( 'name' ), "Deze testmail is verstuurd via de SMTP-instellingen van scrolla core.\n\n" . home_url() );
			$q  = '&test=' . ( $ok ? '1' : '0' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=scrolla-smtp' . $q ) );
		exit;
	}
}

new Scrolla_SMTP();
