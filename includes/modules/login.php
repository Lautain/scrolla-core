<?php
/**
 * Module Inloggen: alleen gebruikersnaam, algemene foutmelding, laatste login, en de inlogpagina in de huisstijl
 * van de site (logo, kleuren, font en knopradius uit de Elementor-kit via Scrolla_Huisstijl).
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Login {

	public function __construct() {
		add_filter( 'login_display_language_dropdown', '__return_false' );
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
		add_filter( 'gettext', [ $this, 'labels' ], 10, 3 );
		add_filter( 'login_errors', [ $this, 'generic_error' ] );
		add_action( 'wp_login', fn( $login, $user ) => update_user_meta( $user->ID, 'scrolla_last_login', time() ), 10, 2 );
		add_filter( 'manage_users_columns', fn( $c ) => $c + [ 'scrolla_last_login' => 'Laatste login' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'column' ], 10, 3 );
		add_filter( 'manage_users_sortable_columns', fn( $c ) => $c + [ 'scrolla_last_login' => 'scrolla_last_login' ] );
		add_action( 'pre_get_users', [ $this, 'sort' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'style' ] );
		add_filter( 'login_headerurl', fn() => home_url( '/' ) );
		add_filter( 'login_headertext', fn() => class_exists( 'Scrolla_Huisstijl' ) ? Scrolla_Huisstijl::get()['naam'] : get_bloginfo( 'name' ) );
		add_action( 'login_footer', [ $this, 'footer' ] );
	}

	/** Inlogpagina in de huisstijl. Alleen CSS; de werking van WordPress blijft ongewijzigd. */
	public function style(): void {
		if ( ! class_exists( 'Scrolla_Huisstijl' ) ) {
			return;
		}
		$h    = Scrolla_Huisstijl::get();
		$k    = $h['kleur'];
		$font = Scrolla_Huisstijl::font_css_url( $h['font_body'] );
		if ( $font ) {
			wp_enqueue_style( 'scrolla-login-font', $font, [], null );
		}
		if ( $h['font_kop'] && $h['font_kop'] !== $h['font_body'] && ( $f2 = Scrolla_Huisstijl::font_css_url( $h['font_kop'] ) ) ) {
			wp_enqueue_style( 'scrolla-login-font-kop', $f2, [], null );
		}
		$logo = $h['logo']
			? sprintf( 'background-image:url("%s");background-size:contain;background-position:center;width:100%%;max-width:240px;height:80px;', esc_url( $h['logo'] ) )
			: sprintf( 'background:none;text-indent:0;width:auto;height:auto;font-size:24px;font-weight:700;color:%s;font-family:%s;', esc_attr( $k['tekst'] ), Scrolla_Huisstijl::font_stack( $h['font_kop'] ) );
		$css = '
		body.login{background:' . $k['achtergrond'] . ';font-family:' . Scrolla_Huisstijl::font_stack( $h['font_body'] ) . ';color:' . $k['tekst'] . ';display:flex;flex-direction:column;justify-content:center;min-height:100vh}
		#login{width:min(380px,92vw);padding:0;margin:0 auto}
		.login h1{margin-bottom:28px}
		.login h1 a{' . $logo . 'margin:0 auto;outline:none;box-shadow:none}
		.login form{border:0;border-radius:calc(' . $h['radius'] . ' * 2);box-shadow:0 12px 40px rgba(0,0,0,.08);padding:32px 28px;margin-top:0}
		.login label{font-size:14px;font-weight:600;color:' . $k['tekst'] . '}
		.login input[type=text],.login input[type=password],.login input[type=email]{border:1px solid ' . Scrolla_Huisstijl::mix( $k['tekst'], '#ffffff', 0.8 ) . ';border-radius:' . $h['radius'] . ';padding:8px 12px;font-size:16px;box-shadow:none;transition:border-color .3s ease}
		.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus{border-color:' . $k['primair'] . ';box-shadow:0 0 0 1px ' . $k['primair'] . '}
		.login .button.wp-hide-pw .dashicons{color:' . $k['secundair'] . '}
		.wp-core-ui .button-primary{background:' . $k['primair'] . ';border-color:' . $k['primair'] . ';color:' . $h['op_primair'] . ';border-radius:' . $h['radius'] . ';padding:4px 20px;font-weight:600;text-shadow:none;box-shadow:none;transition:background .3s ease,border-color .3s ease}
		.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:' . Scrolla_Huisstijl::mix( $k['primair'], '#000000', 0.15 ) . ';border-color:' . Scrolla_Huisstijl::mix( $k['primair'], '#000000', 0.15 ) . ';color:' . $h['op_primair'] . ';box-shadow:none}
		.login input[type=checkbox]:checked::before{filter:none}.login input[type=checkbox]:focus{border-color:' . $k['primair'] . ';box-shadow:0 0 0 1px ' . $k['primair'] . '}
		.login #nav,.login #backtoblog{text-align:center;padding:0;margin:16px 0 0}
		.login #nav a,.login #backtoblog a,.login .privacy-policy-link{color:' . $k['secundair'] . ';transition:color .3s ease}
		.login #nav a:hover,.login #backtoblog a:hover,.login .privacy-policy-link:hover{color:' . $k['primair'] . '}
		.login .message,.login .notice,.login .success{border-radius:' . $h['radius'] . ';border-left-color:' . $k['primair'] . ';box-shadow:none}
		.login #login_error{border-left-color:#d63638}
		.sc-door{text-align:center;font-size:12px;color:' . $k['secundair'] . ';margin:32px 0 0}.sc-door a{color:inherit}
		';
		wp_register_style( 'scrolla-login', false, [], SCROLLA_CORE_VERSION );
		wp_enqueue_style( 'scrolla-login' );
		wp_add_inline_style( 'scrolla-login', preg_replace( '/\s+/', ' ', $css ) );
	}

	public function footer(): void {
		echo '<p class="sc-door">Website door <a href="https://www.scrolla.nl" target="_blank" rel="noopener">Scrolla!</a></p>';
	}

	public function labels( $t, $text, $domain ) {
		if ( 'default' === $domain && 'Username or Email Address' === $text ) {
			return 'Gebruikersnaam';
		}
		return $t;
	}

	public function generic_error( $error ) {
		global $errors;
		$codes = is_wp_error( $errors ) ? $errors->get_error_codes() : [];
		if ( array_intersect( $codes, [ 'scrolla_locked', 'expiredkey', 'invalidkey', 'empty_username', 'empty_password' ] ) ) {
			return $error;
		}
		if ( array_intersect( $codes, [ 'invalid_username', 'incorrect_password', 'invalid_email' ] ) ) {
			return '<strong>Fout:</strong> gebruikersnaam of wachtwoord onjuist. <a href="' . esc_url( wp_lostpassword_url() ) . '">Wachtwoord vergeten?</a>';
		}
		return $error;
	}

	public function column( $out, $col, $user_id ) {
		if ( 'scrolla_last_login' !== $col ) {
			return $out;
		}
		$t = (int) get_user_meta( $user_id, 'scrolla_last_login', true );
		return $t ? esc_html( sprintf( '%s geleden', human_time_diff( $t ) ) ) . '<br><span style="color:#787c82">' . esc_html( wp_date( 'j M Y H:i', $t ) ) . '</span>' : '<span style="color:#787c82">Nog niet</span>';
	}

	public function sort( $q ): void {
		if ( is_admin() && 'scrolla_last_login' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', 'scrolla_last_login' );
			$q->set( 'orderby', 'meta_value_num' );
		}
	}
}

new Scrolla_Login();
