<?php
/**
 * Module Onderhoud.
 * 1. Nette onderhoudspagina tijdens updates: Scrolla Core plaatst wp-content/maintenance.php (drop-in van WordPress),
 *    met naam, logo, kleuren en font van de site (Scrolla_Huisstijl) erin gebakken. WordPress toont die automatisch zolang een update loopt.
 * 2. Handmatige onderhoudsmodus: bezoekers zien dezelfde pagina (503), ingelogde gebruikers zien de site.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Onderhoud {

	const OPTION = 'scrolla_onderhoud';

	public static function html( string $name, string $logo ): string {
		$h     = class_exists( 'Scrolla_Huisstijl' ) ? Scrolla_Huisstijl::get() : null;
		$k     = $h ? $h['kleur'] : [ 'primair' => '#1d1d1f', 'tekst' => '#1d1d1f', 'secundair' => '#50575e', 'achtergrond' => '#f6f7f9' ];
		$font  = $h ? Scrolla_Huisstijl::font_stack( $h['font_body'] ) : 'system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif';
		$fkop  = $h ? Scrolla_Huisstijl::font_stack( $h['font_kop'] ) : $font;
		$fcss  = $h ? Scrolla_Huisstijl::font_css_url( $h['font_body'] ) : '';
		$fcss2 = $h && $h['font_kop'] !== $h['font_body'] ? Scrolla_Huisstijl::font_css_url( $h['font_kop'] ) : '';
		$links = ( $fcss ? '<link rel="stylesheet" href="' . esc_url( $fcss ) . '">' : '' ) . ( $fcss2 ? '<link rel="stylesheet" href="' . esc_url( $fcss2 ) . '">' : '' );
		$title = $name ? esc_html( $name ) : 'Website';
		$img   = $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . $title . '" class="logo">' : '<div class="naam">' . $title . '</div>';
		$bar   = $h ? Scrolla_Huisstijl::mix( $k['primair'], '#ffffff', 0.8 ) : '#e2e4e7';
		return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<meta http-equiv="refresh" content="60"><title>Even geduld | ' . $title . '</title>' . $links . '<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:' . $font . ';background:' . $k['achtergrond'] . ';color:' . $k['tekst'] . ';padding:24px}
.box{max-width:520px;text-align:center}.logo{max-width:220px;max-height:80px;margin-bottom:36px}.naam{font-family:' . $fkop . ';font-size:22px;font-weight:700;margin-bottom:36px}
h1{font-family:' . $fkop . ';font-size:clamp(26px,5vw,34px);line-height:1.15;margin:0 0 14px}p{font-size:17px;line-height:1.6;color:' . $k['secundair'] . ';margin:0 0 28px}
.bar{height:4px;background:' . $bar . ';border-radius:4px;overflow:hidden}.bar span{display:block;height:100%;width:40%;background:' . $k['primair'] . ';border-radius:4px;animation:l 1.6s ease-in-out infinite}
@keyframes l{0%{transform:translateX(-100%)}100%{transform:translateX(250%)}}small{display:block;margin-top:40px;color:' . $k['secundair'] . ';opacity:.7;font-size:13px}small a{color:inherit}
@media (prefers-reduced-motion:reduce){.bar span{animation:none;width:100%}}
</style></head><body><div class="box">' . $img . '<h1>We zijn de website even aan het bijwerken</h1><p>Over een paar minuten zijn we weer terug. Deze pagina ververst vanzelf.</p><div class="bar"><span></span></div>
<small>Website door <a href="https://www.scrolla.nl" rel="noopener">Scrolla!</a></small></div></body></html>';
	}

	private static function logo(): string {
		if ( class_exists( 'Scrolla_Huisstijl' ) ) {
			return Scrolla_Huisstijl::get()['logo'];
		}
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $id && class_exists( '\Elementor\Plugin' ) ) {
			$id = (int) ( get_option( 'elementor_active_kit' ) ? ( get_post_meta( (int) get_option( 'elementor_active_kit' ), '_elementor_page_settings', true )['site_logo']['id'] ?? 0 ) : 0 );
		}
		return $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
	}

	public function __construct() {
		add_action( 'admin_init', [ $this, 'dropin' ] );
		add_action( 'template_redirect', [ $this, 'manual' ], 0 );
		add_action( 'admin_bar_menu', [ $this, 'toolbar' ], 998 );
		add_action( 'admin_post_scrolla_onderhoud', [ $this, 'toggle' ] );
	}

	/** Schrijf/ververs de drop-in als naam, logo of versie veranderd is. */
	public function dropin(): void {
		$sig = md5( SCROLLA_CORE_VERSION . get_bloginfo( 'name' ) . self::logo() . ( class_exists( 'Scrolla_Huisstijl' ) ? wp_json_encode( Scrolla_Huisstijl::get() ) : '' ) );
		$file = WP_CONTENT_DIR . '/maintenance.php';
		if ( get_option( 'scrolla_onderhoud_sig' ) === $sig && file_exists( $file ) ) {
			return;
		}
		$html = self::html( get_bloginfo( 'name' ), self::logo() );
		$php  = "<?php\n// Gemaakt door Scrolla Core. Wordt getoond tijdens updates.\nhttp_response_code( 503 );\nheader( 'Retry-After: 60' );\nheader( 'Content-Type: text/html; charset=utf-8' );\n?>\n" . $html;
		if ( wp_is_writable( WP_CONTENT_DIR ) && false !== file_put_contents( $file, $php ) ) { // phpcs:ignore
			update_option( 'scrolla_onderhoud_sig', $sig, false );
		}
	}

	public function manual(): void {
		if ( ! get_option( self::OPTION ) || is_user_logged_in() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		status_header( 503 );
		header( 'Retry-After: 300' );
		nocache_headers();
		echo self::html( get_bloginfo( 'name' ), self::logo() ); // phpcs:ignore
		exit;
	}

	public function toolbar( WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$on = (bool) get_option( self::OPTION );
		$bar->add_node( [
			'id'     => 'scrolla-onderhoud',
			'parent' => 'top-secondary',
			'title'  => $on ? 'Onderhoudsmodus: AAN' : 'Onderhoud',
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=scrolla_onderhoud' ), 'scrolla_onderhoud' ),
			'meta'   => [ 'class' => $on ? 'scrolla-se-uit' : '', 'title' => $on ? 'Klik om de site weer open te zetten' : 'Klik om bezoekers een onderhoudspagina te tonen' ],
		] );
	}

	public function toggle(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'scrolla_onderhoud' ) ) {
			wp_die( 'Geen toegang.' );
		}
		update_option( self::OPTION, get_option( self::OPTION ) ? 0 : 1 );
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		}
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
}

new Scrolla_Onderhoud();
