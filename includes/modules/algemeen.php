<?php
/**
 * Module Algemeen: bedrijfsgegevens op één plek (vervanger van het tabje "Algemeen" uit ASE).
 * Te gebruiken als Elementor dynamic tag (groep "Scrolla") en als shortcode [scrolla veld="telefoon"].
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Algemeen {

	const OPTION = 'scrolla_algemeen';

	/** Vaste velden: sleutel => [label, type, groep] */
	public static function fields(): array {
		$f = [
			'bedrijfsnaam'   => [ 'Bedrijfsnaam', 'text', 'Bedrijf' ],
			'adres'          => [ 'Straat en huisnummer', 'text', 'Bedrijf' ],
			'postcode'       => [ 'Postcode', 'text', 'Bedrijf' ],
			'plaats'         => [ 'Plaats', 'text', 'Bedrijf' ],
			'telefoon'       => [ 'Telefoon', 'tel', 'Contact' ],
			'whatsapp'       => [ 'WhatsApp', 'tel', 'Contact' ],
			'email'          => [ 'E-mailadres', 'email', 'Contact' ],
			'kvk'            => [ 'KvK-nummer', 'text', 'Bedrijf' ],
			'btw'            => [ 'Btw-nummer', 'text', 'Bedrijf' ],
			'iban'           => [ 'IBAN', 'text', 'Bedrijf' ],
			'openingstijden' => [ 'Openingstijden', 'textarea', 'Contact' ],
			'routelink'      => [ 'Route (Google Maps-link)', 'url', 'Contact' ],
			'facebook'       => [ 'Facebook', 'url', 'Socials' ],
			'instagram'      => [ 'Instagram', 'url', 'Socials' ],
			'linkedin'       => [ 'LinkedIn', 'url', 'Socials' ],
			'youtube'        => [ 'YouTube', 'url', 'Socials' ],
			'tiktok'         => [ 'TikTok', 'url', 'Socials' ],
			'x'              => [ 'X (Twitter)', 'url', 'Socials' ],
			'pinterest'      => [ 'Pinterest', 'url', 'Socials' ],
		];
		foreach ( (array) get_option( self::OPTION . '_extra', [] ) as $key => $label ) {
			$f[ $key ] = [ $label, 'text', 'Extra' ];
		}
		return $f;
	}

	public static function value( string $key, bool $link = false ): string {
		$v      = (string) ( get_option( self::OPTION, [] )[ $key ] ?? '' );
		$fields = self::fields();
		if ( ! $link || '' === $v || ! isset( $fields[ $key ] ) ) {
			return $v;
		}
		switch ( $fields[ $key ][1] ) {
			case 'tel':
				$num = preg_replace( '/[^0-9+]/', '', $v );
				if ( 'whatsapp' === $key ) {
					return 'https://wa.me/' . ltrim( preg_replace( '/^0/', '31', $num ), '+' );
				}
				return 'tel:' . $num;
			case 'email':
				return 'mailto:' . antispambot( $v );
			default:
				return $v;
		}
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_scrolla_algemeen_save', [ $this, 'save' ] );
		add_shortcode( 'scrolla', [ $this, 'shortcode' ] );
		add_action( 'elementor/dynamic_tags/register', [ $this, 'register_tags' ] );
	}

	public function menu(): void {
		add_menu_page( 'Algemeen', 'Algemeen', 'edit_pages', 'scrolla-algemeen', [ $this, 'render' ], 'dashicons-building', 3 );
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( 'Geen toegang.' );
		}
		$vals = (array) get_option( self::OPTION, [] );
		Scrolla_Core_Settings::styles();
		echo '<div class="wrap sc-wrap">'; Scrolla_Core_Settings::head( 'algemeen' ); echo '<p class="sc-intro">Deze gegevens worden op de hele website gebruikt, zoals in de header, footer en op de contactpagina. Pas je ze hier aan, dan zijn ze overal bijgewerkt.</p>';
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible sc-keep"><p>Opgeslagen.</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'scrolla_algemeen_save' );
		echo '<input type="hidden" name="action" value="scrolla_algemeen_save">';
		$groups = [];
		foreach ( self::fields() as $k => $f ) {
			$groups[ $f[2] ][ $k ] = $f;
		}
		$scrolla = scrolla_is_scrolla_user();
		foreach ( $groups as $g => $fields ) {
			echo '<div class="sc-card"><h2>' . esc_html( $g ) . '</h2><div class="sc-fields">';
			foreach ( $fields as $k => $f ) {
				$v    = $vals[ $k ] ?? '';
				$id   = 'sc-' . $k;
				$type = [ 'url' => 'url', 'email' => 'email', 'tel' => 'tel' ][ $f[1] ] ?? 'text';
				$in   = 'textarea' === $f[1]
					? sprintf( '<textarea id="%s" name="v[%s]" rows="3">%s</textarea>', esc_attr( $id ), esc_attr( $k ), esc_textarea( $v ) )
					: sprintf( '<input id="%s" type="%s" name="v[%s]" value="%s">', esc_attr( $id ), $type, esc_attr( $k ), esc_attr( $v ) );
				$code = $scrolla ? sprintf( '<span class="sc-code" title="Klik om te kopiëren" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.textContent)">[scrolla veld="%s"]</span>', esc_html( $k ) ) : '';
				printf( '<div class="sc-field%s"><label for="%s">%s</label>%s%s</div>', 'textarea' === $f[1] ? ' sc-wide' : '', esc_attr( $id ), esc_html( $f[0] ), $in, $code ); // phpcs:ignore
			}
			echo '</div></div>';
		}
		if ( $scrolla ) {
			$extra = (array) get_option( self::OPTION . '_extra', [] );
			$lines = implode( "\n", array_map( fn( $k, $l ) => "$k|$l", array_keys( $extra ), $extra ) );
			echo '<div class="sc-card"><h2>Extra velden <span class="sc-badge">alleen Scrolla</span></h2><div class="sc-field sc-wide">';
			printf( '<textarea name="extra" rows="2" placeholder="sleutel|Label, bijv. vestiging2|Tweede vestiging">%s</textarea></div></div>', esc_textarea( $lines ) );
		}
		submit_button( 'Opslaan', 'primary', 'submit', false );
		echo '</form></div>';
	}

	public function save(): void {
		if ( ! current_user_can( 'edit_pages' ) || ! check_admin_referer( 'scrolla_algemeen_save' ) ) {
			wp_die( 'Geen toegang.' );
		}
		if ( scrolla_is_scrolla_user() && isset( $_POST['extra'] ) ) {
			$extra = [];
			foreach ( preg_split( '/\r?\n/', wp_unslash( $_POST['extra'] ) ) as $line ) {
				$p = array_map( 'trim', explode( '|', $line, 2 ) );
				if ( count( $p ) === 2 && $p[0] !== '' ) {
					$extra[ sanitize_key( $p[0] ) ] = sanitize_text_field( $p[1] );
				}
			}
			update_option( self::OPTION . '_extra', $extra, false );
		}
		$out = [];
		foreach ( self::fields() as $k => $f ) {
			$raw = wp_unslash( $_POST['v'][ $k ] ?? '' );
			$out[ $k ] = match ( $f[1] ) {
				'url'      => esc_url_raw( $raw ),
				'email'    => sanitize_email( $raw ),
				'textarea' => sanitize_textarea_field( $raw ),
				default    => sanitize_text_field( $raw ),
			};
		}
		update_option( self::OPTION, $out );
		wp_safe_redirect( admin_url( 'admin.php?page=scrolla-algemeen&saved=1' ) );
		exit;
	}

	public function shortcode( $atts ): string {
		$a = shortcode_atts( [ 'veld' => '', 'link' => '' ], $atts );
		$v = self::value( $a['veld'], (bool) $a['link'] );
		return 'openingstijden' === $a['veld'] ? nl2br( esc_html( $v ) ) : esc_html( $v );
	}

	public function register_tags( $manager ): void {
		$manager->register_group( 'scrolla', [ 'title' => 'Scrolla' ] );
		require_once __DIR__ . '/elementor-tags.php';
		$manager->register( new Scrolla_Tag_Algemeen_Tekst() );
		$manager->register( new Scrolla_Tag_Algemeen_Link() );
	}
}

new Scrolla_Algemeen();
