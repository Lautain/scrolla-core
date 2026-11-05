<?php
/**
 * Module Huisstijl: leest de branding van de site uit de Elementor-kit en Algemeen, zodat inlogpagina,
 * mails, onderhoudspagina en beheeromgeving automatisch in de stijl van de klant staan.
 *
 * Bronnen (in volgorde): Elementor-kit (logo, globale kleuren, globale fonts, knopradius), Algemeen (bedrijfsnaam),
 * WordPress (custom_logo, sitenaam). Ontbreekt iets, dan valt alles terug op neutraal.
 *
 * Per site bij te sturen zonder code: een globale kleur in de kit met de naam "Login achtergrond".
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Huisstijl {

	const NEUTRAAL = [
		'primair'    => '#1d1d1f',
		'secundair'  => '#50575e',
		'tekst'      => '#1d1d1f',
		'accent'     => '#2271b1',
		'achtergrond'=> '#f6f7f9',
	];

	private static ?array $cache = null;

	/** Alle huisstijlgegevens van deze site. */
	public static function get(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$kit   = self::kit();
		$kleur = self::NEUTRAAL;
		foreach ( (array) ( $kit['system_colors'] ?? [] ) as $c ) {
			$map = [ 'primary' => 'primair', 'secondary' => 'secundair', 'text' => 'tekst', 'accent' => 'accent' ];
			if ( isset( $map[ $c['_id'] ?? '' ] ) && self::is_kleur( $c['color'] ?? '' ) ) {
				$kleur[ $map[ $c['_id'] ] ] = $c['color'];
			}
		}
		$login_bg = '';
		foreach ( (array) ( $kit['custom_colors'] ?? [] ) as $c ) {
			if ( self::is_kleur( $c['color'] ?? '' ) && preg_match( '/login/i', (string) ( $c['title'] ?? '' ) ) ) {
				$login_bg = $c['color'];
			}
		}
		$kleur['achtergrond'] = $login_bg ?: self::mix( $kleur['primair'], '#ffffff', 0.94 );

		$font_kop  = '';
		$font_body = '';
		foreach ( (array) ( $kit['system_typography'] ?? [] ) as $t ) {
			$f = (string) ( $t['typography_font_family'] ?? '' );
			if ( 'primary' === ( $t['_id'] ?? '' ) ) {
				$font_kop = $f;
			} elseif ( 'text' === ( $t['_id'] ?? '' ) ) {
				$font_body = $f;
			}
		}

		$radius = $kit['button_border_radius'] ?? null;
		$radius = is_array( $radius ) && '' !== ( $radius['top'] ?? '' ) ? (float) $radius['top'] . ( $radius['unit'] ?? 'px' ) : '6px';

		$naam = class_exists( 'Scrolla_Algemeen' ) ? Scrolla_Algemeen::value( 'bedrijfsnaam' ) : '';

		self::$cache = [
			'naam'       => $naam ?: get_bloginfo( 'name' ),
			'logo'       => self::logo( $kit ),
			'kleur'      => $kleur,
			'op_primair' => self::contrast( $kleur['primair'] ),
			'font_kop'   => $font_kop,
			'font_body'  => $font_body ?: $font_kop,
			'radius'     => $radius,
		];
		return self::$cache;
	}

	/** CSS font-family met veilige terugval. */
	public static function font_stack( string $font ): string {
		$sys = 'system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif';
		return $font ? '"' . str_replace( '"', '', $font ) . '",' . $sys : $sys;
	}

	/** URL van het lokaal opgeslagen Google Font van Elementor, of leeg. */
	public static function font_css_url( string $font ): string {
		if ( ! $font ) {
			return '';
		}
		$up   = wp_get_upload_dir();
		$dir  = trailingslashit( $up['basedir'] ) . 'elementor/google-fonts/css/';
		$want = strtolower( preg_replace( '/[^a-z0-9]/i', '', $font ) );
		foreach ( (array) glob( $dir . '*.css' ) as $file ) {
			if ( strtolower( preg_replace( '/[^a-z0-9]/i', '', basename( $file, '.css' ) ) ) === $want ) {
				return trailingslashit( $up['baseurl'] ) . 'elementor/google-fonts/css/' . basename( $file ) . '?v=' . filemtime( $file );
			}
		}
		return '';
	}

	/** Instellingen van de actieve Elementor-kit. */
	private static function kit(): array {
		$id = (int) get_option( 'elementor_active_kit' );
		$s  = $id ? get_post_meta( $id, '_elementor_page_settings', true ) : [];
		return is_array( $s ) ? $s : [];
	}

	private static function logo( array $kit ): string {
		$id = (int) ( $kit['site_logo']['id'] ?? 0 );
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( ! $id ) {
			$url = (string) ( $kit['site_logo']['url'] ?? '' );
			return $url && ! str_contains( $url, 'placeholder' ) ? $url : '';
		}
		return (string) wp_get_attachment_image_url( $id, 'medium' );
	}

	private static function is_kleur( $c ): bool {
		return is_string( $c ) && (bool) preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c );
	}

	private static function rgb( string $hex ): array {
		$h = ltrim( $hex, '#' );
		if ( 3 === strlen( $h ) ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		return [ hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) ];
	}

	/** Meng kleur a met b; $t = aandeel b (0-1). */
	public static function mix( string $a, string $b, float $t ): string {
		[ $r1, $g1, $b1 ] = self::rgb( $a );
		[ $r2, $g2, $b2 ] = self::rgb( $b );
		return sprintf( '#%02x%02x%02x', round( $r1 + ( $r2 - $r1 ) * $t ), round( $g1 + ( $g2 - $g1 ) * $t ), round( $b1 + ( $b2 - $b1 ) * $t ) );
	}

	/** Wit of bijna-zwart, afhankelijk van wat het best leesbaar is op deze kleur. */
	public static function contrast( string $hex ): string {
		$lum = function ( array $rgb ): float {
			$c = array_map( function ( $v ) {
				$v /= 255;
				return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
			}, $rgb );
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		$l = $lum( self::rgb( $hex ) );
		return ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / 0.05 ) ? '#ffffff' : '#1d1d1f';
	}

	public function __construct() {
		add_action( 'admin_head', [ $this, 'admin_accent' ] );
	}

	/** Klantgebruikers zien de beheeromgeving met een accent in de huiskleur. Scrolla-beheerders houden standaard WordPress. */
	public function admin_accent(): void {
		if ( scrolla_is_scrolla_user() ) {
			return;
		}
		$h  = self::get();
		$p  = $h['kleur']['primair'];
		$op = $h['op_primair'];
		printf(
			'<style>#adminmenu li.current a.menu-top,#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,#adminmenu li.wp-has-current-submenu .wp-submenu .wp-submenu-head{background:%1$s;color:%2$s}#adminmenu li.wp-has-current-submenu div.wp-menu-image:before,#adminmenu li.current div.wp-menu-image:before{color:%2$s}#adminmenu .wp-submenu a:hover,#adminmenu .wp-submenu a:focus,#adminmenu li.menu-top:hover>a,#adminmenu li a:hover div.wp-menu-image:before{color:%3$s}.wp-core-ui .button-primary{background:%1$s;border-color:%1$s;color:%2$s}.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:%4$s;border-color:%4$s;color:%2$s}</style>',
			esc_attr( $p ),
			esc_attr( $op ),
			esc_attr( self::mix( $p, '#ffffff', 0.55 ) ),
			esc_attr( self::mix( $p, '#000000', 0.15 ) )
		);
	}
}

new Scrolla_Huisstijl();
