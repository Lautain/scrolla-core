<?php
/**
 * Scrolla Core: vaste standaardwaarden en de overzichtspagina (alleen voor Scrolla-beheerders).
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Core_Settings {

	/** Vaste Scrolla-standaard. Bewust niet per site instelbaar. */
	const DEFAULTS = [
		'login_attempts'   => 5,
		'login_lock_min'   => 15,
		'login_long_after' => 3,
		'media_max'        => 2400,
		'media_quality'    => 82,
		'revisions'        => 10,
	];

	public static function get( string $key = '' ) {
		$s                    = self::DEFAULTS;
		$s['login_whitelist'] = (string) get_option( 'scrolla_login_whitelist', '' );
		return $key ? ( $s[ $key ] ?? null ) : $s;
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ], 99 );
	}

	public function menu(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			return;
		}
		add_menu_page( 'scrolla | digital agency | core', 'scrolla core', 'manage_options', 'scrolla-core', [ $this, 'render' ], self::icon(), 99 );
		add_submenu_page( 'scrolla-core', 'Overzicht', 'Overzicht', 'manage_options', 'scrolla-core', [ $this, 'render' ] );
	}

	public static function icon(): string {
		return 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 7 25 28"><path fill="#a7aaad" d="M10.6513 33.0211C4.68307 33.0211 0.392071 30.1677 0 25.2232H6.3167C6.5563 27.2271 8.03745 28.4251 10.5424 28.4251C12.9166 28.4251 14.3759 27.4232 14.3759 26.0074C14.3759 24.7876 13.2651 24.0688 11.3918 23.6985L7.36222 22.9144C2.89697 22.0431 0.522761 19.7778 0.522761 16.2056C0.522761 11.8493 4.291 8.97407 10.281 8.97407C16.2274 8.97407 20.1481 11.9582 20.2788 16.7501H14.3324C14.2888 14.8987 12.7205 13.5264 10.4552 13.5264C8.25527 13.5264 6.92659 14.5938 6.92659 15.966C6.92659 17.1422 7.95033 17.9046 9.71465 18.2749L14.071 19.1243C18.7105 20.0392 20.8451 22.0431 20.8451 25.4193C20.8451 30.0152 16.663 33.0211 10.6513 33.0211Z"/></svg>' );
	}

	/** Logo uit de huisstijl (inline, kleur via currentColor). */
	public static function logo(): string {
		return (string) file_get_contents( SCROLLA_CORE_DIR . 'assets/logo.svg' );
	}

	/** Paginakop in huisstijl: logo, paginanaam en (optioneel) badge. */
	public static function head( string $title, string $badge = '' ): void {
		printf( '<div class="sc-head"><span class="sc-logo">%s</span><h1>%s%s</h1></div>', self::logo(), esc_html( $title ), $badge ? ' <span class="sc-badge">' . esc_html( $badge ) . '</span>' : '' ); // phpcs:ignore
	}

	/** Gedeelde, compacte opmaak in de Scrolla-huisstijl (paars #231439, roze #EE4166, Inter Display). */
	public static function styles(): void {
		$f = plugins_url( 'assets/fonts/', SCROLLA_CORE_FILE );
		echo '<style>
		@font-face{font-family:"Inter Display";src:url(' . esc_url( $f ) . 'inter-display-medium.woff2) format("woff2");font-weight:400 500;font-display:swap}
		@font-face{font-family:"Inter Display";src:url(' . esc_url( $f ) . 'inter-display-semibold.woff2) format("woff2");font-weight:600;font-display:swap}
		@font-face{font-family:"Inter Display";src:url(' . esc_url( $f ) . 'inter-display-bold.woff2) format("woff2");font-weight:700;font-display:swap}
		.sc-wrap{--sc-paars:#231439;--sc-roze:#EE4166;--sc-roze-h:#D62E54;--sc-grijs:#F4F3F6;--sc-lijn:#E7E4EC;max-width:1080px;color:var(--sc-paars);font-family:"Inter Display",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-size:14px}
		.sc-wrap input,.sc-wrap textarea,.sc-wrap select,.sc-wrap button,.sc-wrap .button{font-family:inherit}
		.sc-head{display:flex;align-items:center;gap:14px;margin:6px 0 4px}.sc-logo{display:inline-flex;color:var(--sc-paars);padding-right:14px;border-right:1px solid var(--sc-lijn)}.sc-logo svg{height:26px;width:auto}
		.sc-wrap h1{display:flex;align-items:center;gap:8px;font-size:24px;font-weight:500;letter-spacing:-.01em;text-transform:lowercase;color:var(--sc-paars);margin:0;padding:0}
		.sc-head+.sc-intro,.sc-wrap>h1+.sc-intro{margin-top:6px}
		.sc-intro{color:rgba(35,20,57,.7);margin:0 0 16px;font-size:14px}
		.sc-badge{background:var(--sc-roze);color:#fff;border-radius:60px;padding:2px 9px;font-size:11px;font-weight:600;vertical-align:middle;letter-spacing:0;text-transform:none}
		.sc-card{background:#fff;border:1px solid var(--sc-lijn);border-radius:16px;padding:16px 20px;margin:0 0 12px}
		.sc-card h2{font-size:15px;font-weight:600;color:var(--sc-paars);margin:0 0 12px;padding:0}
		.sc-fields{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px 16px}
		.sc-field label{display:block;font-weight:600;font-size:12px;margin-bottom:4px;color:var(--sc-paars)}
		.sc-field input,.sc-field textarea,.sc-field select{width:100%;max-width:none;min-height:36px;font-size:14px;padding:4px 12px;border:1px solid var(--sc-lijn);border-radius:10px;background:var(--sc-grijs);color:var(--sc-paars);box-shadow:none}
		.sc-field input:focus,.sc-field textarea:focus,.sc-field select:focus{border-color:var(--sc-roze);background:#fff;box-shadow:0 0 0 1px var(--sc-roze);outline:0}
		.sc-field textarea{min-height:68px;padding:8px 12px}.sc-field.sc-wide{grid-column:1/-1}
		.sc-code{font-family:Menlo,Consolas,monospace;font-size:11px;color:rgba(35,20,57,.45);cursor:copy;margin-top:3px;display:inline-block}.sc-code:hover{color:var(--sc-roze)}
		.sc-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px 16px;margin:0}
		.sc-list li{margin:0;padding-left:22px;position:relative}.sc-list li:before{content:"\2713";position:absolute;left:0;color:var(--sc-roze);font-weight:700}
		.sc-muted{color:rgba(35,20,57,.55)}.sc-actions{display:flex;gap:10px;align-items:center;margin-top:10px}
		.sc-table{border:0;border-radius:10px;overflow:hidden}.sc-table td,.sc-table th{padding:8px 10px;color:var(--sc-paars)}.sc-table.striped>tbody>:nth-child(odd){background:var(--sc-grijs)}
		.sc-wrap .button{border-radius:60px;border:1px solid var(--sc-paars);color:var(--sc-paars);background:#fff;padding:0 16px;min-height:34px;line-height:32px;font-weight:600}
		.sc-wrap .button:hover,.sc-wrap .button:focus{border-color:var(--sc-roze);color:var(--sc-roze);background:#fff;box-shadow:none}
		.sc-wrap .button-small{min-height:26px;line-height:24px;padding:0 12px;font-size:12px}
		.sc-wrap .button-primary{background:var(--sc-roze);border-color:var(--sc-roze);color:#fff;padding:0 24px;min-height:40px;line-height:38px}
		.sc-wrap .button-primary:hover,.sc-wrap .button-primary:focus{background:var(--sc-roze-h);border-color:var(--sc-roze-h);color:#fff}
		.sc-wrap a{color:var(--sc-roze)}.sc-wrap a.button{color:var(--sc-paars)}.sc-wrap a.button-primary{color:#fff}
		.sc-wrap input[type=checkbox]:checked{background:var(--sc-roze);border-color:var(--sc-roze)}.sc-wrap input[type=checkbox]:checked:before{filter:brightness(0) invert(1)}
		.sc-wrap input[type=checkbox]:focus{border-color:var(--sc-roze);box-shadow:0 0 0 1px var(--sc-roze)}
		.sc-wrap details summary{cursor:pointer;font-weight:600}
		</style>';
	}

	public function render(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			wp_die( 'Geen toegang.' );
		}
		self::styles();
		$rel = get_site_transient( 'scrolla_core_release' );
		$new = is_array( $rel ) && ! empty( $rel['version'] ) && version_compare( $rel['version'], SCROLLA_CORE_VERSION, '>' );
		echo '<div class="wrap sc-wrap">';
		self::head( 'core', SCROLLA_CORE_VERSION );
		echo '<p class="sc-intro">De Scrolla-standaard voor deze website. Alles staat vast ingesteld; updates komen automatisch via GitHub.</p>';

		echo '<div class="sc-card"><h2>Updates</h2><p style="margin:0">';
		echo $new ? 'Nieuwe versie beschikbaar: <strong>' . esc_html( $rel['version'] ) . '</strong>. ' : 'Deze site draait de nieuwste bekende versie. ';
		echo '<span class="sc-muted">Sites controleren één keer per week.</span></p><div class="sc-actions"><a class="button" href="' . esc_url( admin_url( 'update-core.php?force-check=1' ) ) . '">Nu controleren</a></div></div>';

		$s = self::DEFAULTS;
		$items = [
			'Algemeen: bedrijfsgegevens als dynamic tags en <code>[scrolla veld="..."]</code>',
			'Opgeruimde beheeromgeving met menu-indeling Website / Instellingen',
			'Dupliceren van pagina\'s, berichten en berichttypes',
			'Inloggen met gebruikersnaam, algemene foutmelding',
			'Loginpogingen: ' . $s['login_attempts'] . ' pogingen, dan ' . $s['login_lock_min'] . ' min blokkade (na ' . $s['login_long_after'] . 'x: 24 uur)',
			'XML-RPC uit, gebruikers niet op te vragen, security-headers',
			'Gutenberg, reacties, feeds, emoji en overbodige head-tags uit',
			'Afbeeldingen max. ' . $s['media_max'] . 'px en webp (kwaliteit ' . $s['media_quality'] . ')',
			'Maximaal ' . $s['revisions'] . ' revisies',
			'Onderhoudspagina tijdens updates en onderhoudsknop in de toolbar',
			'Menu per rol (zie submenu)',
			'Importpunt voor de ontwikkeltool',
		];
		echo '<div class="sc-card"><h2>Actief op deze site</h2><ul class="sc-list">';
		foreach ( $items as $i ) {
			echo '<li>' . wp_kses_post( $i ) . '</li>';
		}
		echo '</ul></div>';

		if ( class_exists( 'Scrolla_Types' ) && Scrolla_Types::config() ) {
			echo '<div class="sc-card"><h2>Berichttypes van dit project</h2><table class="widefat striped sc-table"><tbody>';
			foreach ( Scrolla_Types::config() as $k => $t ) {
				$v = implode( ', ', array_map( fn( $f ) => $f['label'], $t['velden'] ) );
				printf( '<tr><td><span class="dashicons %s"></span> %s</td><td>/%s/</td><td class="sc-muted">%s</td></tr>', esc_attr( $t['icon'] ), esc_html( $t['meervoud'] ), esc_html( $t['slug'] ), esc_html( $v ?: 'geen extra velden' ) );
			}
			echo '</tbody></table><p class="sc-muted" style="margin:8px 0 0">Aangemaakt door de ontwikkeltool. Wijzigen gaat via Claude.</p></div>';
		}
		echo '</div>';
	}
}
