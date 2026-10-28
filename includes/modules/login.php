<?php
/**
 * Module Inloggen: alleen gebruikersnaam, algemene foutmelding, laatste login. De loginpagina zelf blijft standaard WordPress.
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
