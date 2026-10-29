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
		add_menu_page( 'scrolla | digital agency | core', 'scrolla', 'manage_options', 'scrolla-core', [ $this, 'render' ], self::icon(), 99 );
		add_submenu_page( 'scrolla-core', 'Overzicht', 'Overzicht', 'manage_options', 'scrolla-core', [ $this, 'render' ] );
	}

	public static function icon(): string {
		return 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><text x="2" y="15" font-family="Arial" font-weight="bold" font-size="14" fill="#a7aaad">S!</text></svg>' );
	}

	/** Paginakop: standaard WordPress-titel met optionele badge. */
	public static function head( string $title, string $badge = '' ): void {
		printf( '<h1>%s%s</h1>', esc_html( $title ), $badge ? ' <span class="sc-badge">' . esc_html( $badge ) . '</span>' : '' ); // phpcs:ignore
	}

	/** Gedeelde opmaak: standaard WordPress, alleen compacte indeling. */
	public static function styles(): void {
		echo '<style>
		.sc-wrap{max-width:1080px}.sc-wrap h1{display:flex;align-items:center;gap:8px}
		.sc-intro{color:#646970;margin:0 0 12px}
		.sc-badge{background:#dcdcde;color:#1d2327;border-radius:3px;padding:1px 6px;font-size:11px;font-weight:600}
		.sc-card{background:#fff;border:1px solid #c3c4c7;box-shadow:0 1px 1px rgba(0,0,0,.04);padding:12px 16px;margin:0 0 12px}
		.sc-card h2{font-size:14px;margin:0 0 10px;padding:0}
		.sc-fields{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px 16px}
		.sc-field label{display:block;font-weight:600;margin-bottom:3px}
		.sc-field input,.sc-field textarea,.sc-field select{width:100%;max-width:none}
		.sc-field textarea{min-height:64px}.sc-field.sc-wide{grid-column:1/-1}
		.sc-code{font-family:Consolas,Monaco,monospace;font-size:11px;color:#8c8f94;cursor:copy;margin-top:2px;display:inline-block}.sc-code:hover{color:#2271b1}
		.sc-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:6px 16px;margin:0}
		.sc-list li{margin:0;padding-left:20px;position:relative}.sc-list li:before{content:"\\2713";position:absolute;left:0;color:#00a32a;font-weight:700}
		.sc-muted{color:#787c82}.sc-actions{display:flex;gap:8px;align-items:center;margin-top:8px}
		.sc-table td,.sc-table th{padding:6px 8px}
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
		self::head( 'scrolla', SCROLLA_CORE_VERSION );
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
			'Geteste updates: de stack werkt alleen bij naar versies die Claude eerst op de blueprint heeft getest',
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
