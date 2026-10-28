<?php
/**
 * Module Inloggen: Scrolla-loginpagina, alleen gebruikersnaam, algemene foutmelding, laatste login.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Login {

	public function __construct() {
		add_action( 'login_enqueue_scripts', [ $this, 'style' ] );
		add_filter( 'login_headerurl', fn() => 'https://www.scrolla.nl' );
		add_filter( 'login_headertext', fn() => 'Scrolla!' );
		add_filter( 'login_display_language_dropdown', '__return_false' );
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
		add_filter( 'gettext', [ $this, 'labels' ], 10, 3 );
		add_filter( 'login_errors', [ $this, 'generic_error' ] );
		add_action( 'wp_login', fn( $login, $user ) => update_user_meta( $user->ID, 'scrolla_last_login', time() ), 10, 2 );
		add_filter( 'manage_users_columns', fn( $c ) => $c + [ 'scrolla_last_login' => 'Laatste login' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'column' ], 10, 3 );
		add_filter( 'manage_users_sortable_columns', fn( $c ) => $c + [ 'scrolla_last_login' => 'scrolla_last_login' ] );
		add_action( 'pre_get_users', [ $this, 'sort' ] );
	}

	public function style(): void {
		$logo = apply_filters( 'scrolla_core_login_logo', 'https://www.scrolla.nl/wp-content/uploads/2024/03/Logo-Scrolla.svg' );
		?>
		<style>
		html,body.login{min-height:100%;margin:0;background:#21112d!important}
		body.login{display:flex;align-items:center;justify-content:center}
		body.login #login{width:360px;padding:0;margin:0}
		body.login h1.wp-login-logo a,body.login h1 a{background-image:url('<?php echo esc_url( $logo ); ?>')!important;background-size:contain!important;background-position:center!important;background-repeat:no-repeat!important;width:280px!important;height:72px!important;margin:0 auto 28px!important;-webkit-mask:none!important;mask:none!important}
		body.login #loginform,body.login #lostpasswordform,body.login #resetpassform{background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;margin-top:0}
		body.login label,body.login .forgetmenot label{color:#e9e3ee!important}
		body.login input[type=text],body.login input[type=password],body.login input[type=email]{background:#2d1a3b!important;border:1px solid #4a3159!important;color:#fff!important;border-radius:8px!important;padding:8px 12px!important;font-size:16px!important;box-shadow:none!important}
		body.login input[type=text]:focus,body.login input[type=password]:focus{border-color:#ee4166!important;box-shadow:0 0 0 1px #ee4166!important}
		body.login .wp-hide-pw,body.login .wp-hide-pw .dashicons{color:#cbb9d6!important}
		body.login #wp-submit,body.login .button-primary{background:#ee4166!important;border-color:#ee4166!important;color:#fff!important;border-radius:8px!important;padding:4px 22px!important;font-weight:600;transition:.3s ease}
		body.login #wp-submit:hover,body.login .button-primary:hover,body.login #wp-submit:focus{background:#d72f55!important;border-color:#d72f55!important}
		body.login #nav{text-align:center}body.login #nav a,body.login #backtoblog a{color:#cbb9d6!important}body.login #backtoblog{display:none}
		body.login .wp-tooltip__toggle{color:#cbb9d6!important}
		body.login .message,body.login .notice,body.login #login_error{border-radius:8px;border-left-color:#ee4166!important}
		body.login .privacy-policy-page-link,body.login .language-switcher{display:none}
		</style>
		<?php
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
