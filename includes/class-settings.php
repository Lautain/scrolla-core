<?php
/**
 * Instellingenpagina van Scrolla Core. Alleen zichtbaar en bruikbaar voor Scrolla-beheerders.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Core_Settings {

	const OPTION = 'scrolla_core_settings';

	public static function defaults(): array {
		$mods = [];
		foreach ( scrolla_core_modules() as $k => $m ) {
			$mods[ $k ] = $m[1] ? 1 : 0;
		}
		return [
			'modules'          => $mods,
			'login_attempts'   => 5,
			'login_lock_min'   => 15,
			'login_long_after' => 3,
			'login_whitelist'  => '',
			'media_max'        => 2400,
			'media_quality'    => 82,
			'revisions'        => 10,
		];
	}

	public static function get( string $key = '' ) {
		$s = wp_parse_args( (array) get_option( self::OPTION, [] ), self::defaults() );
		$s['modules'] = wp_parse_args( (array) $s['modules'], self::defaults()['modules'] );
		return $key ? ( $s[ $key ] ?? null ) : $s;
	}

	public static function active_modules(): array {
		return array_keys( array_filter( self::get( 'modules' ) ) );
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ], 99 );
		add_action( 'admin_post_scrolla_core_save', [ $this, 'save' ] );
	}

	public function menu(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			return;
		}
		add_menu_page( 'Scrolla Core', 'Scrolla Core', 'manage_options', 'scrolla-core', [ $this, 'render' ], self::icon(), 81 );
		add_submenu_page( 'scrolla-core', 'Modules', 'Modules', 'manage_options', 'scrolla-core', [ $this, 'render' ] );
	}

	public static function icon(): string {
		return 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><text x="2" y="15" font-family="Arial" font-weight="bold" font-size="14" fill="#a7aaad">S!</text></svg>' );
	}

	/** Gedeelde opmaak voor alle Scrolla Core-schermen. */
	public static function styles(): void {
		echo '<style>
		.sc-wrap{max-width:1100px}.sc-wrap h1{display:flex;align-items:center;gap:10px}.sc-badge{background:#23052E;color:#fff;border-radius:4px;padding:2px 8px;font-size:12px}
		.sc-card{background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:20px 24px;margin:16px 0}
		.sc-card h2{margin-top:0}.sc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px}
		.sc-mod{border:1px solid #e2e4e7;border-radius:8px;padding:14px 16px;display:flex;gap:12px;align-items:flex-start}
		.sc-mod p{margin:4px 0 0;color:#50575e}.sc-mod strong{font-size:14px}
		.sc-switch{position:relative;width:38px;height:22px;flex:0 0 38px}.sc-switch input{opacity:0;width:0;height:0}
		.sc-switch span{position:absolute;inset:0;background:#c3c4c7;border-radius:22px;transition:.2s}.sc-switch span:before{content:"";position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
		.sc-switch input:checked+span{background:#23052E}.sc-switch input:checked+span:before{transform:translateX(16px)}
		.sc-table td,.sc-table th{padding:8px 10px}.sc-muted{color:#787c82}
		</style>';
	}

	public function render(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			wp_die( 'Geen toegang.' );
		}
		$s = self::get();
		self::styles();
		echo '<div class="wrap sc-wrap"><h1>Scrolla Core <span class="sc-badge">' . esc_html( SCROLLA_CORE_VERSION ) . '</span></h1>';
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Opgeslagen.</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'scrolla_core_save' );
		echo '<input type="hidden" name="action" value="scrolla_core_save">';
		echo '<div class="sc-card"><h2>Modules</h2><div class="sc-grid">';
		foreach ( scrolla_core_modules() as $k => $m ) {
			printf(
				'<label class="sc-mod"><span class="sc-switch"><input type="checkbox" name="modules[%1$s]" value="1" %2$s><span></span></span><span><strong>%3$s</strong><p>%4$s</p></span></label>',
				esc_attr( $k ), checked( ! empty( $s['modules'][ $k ] ), true, false ), esc_html( $m[2] ), esc_html( $m[3] )
			);
		}
		echo '</div></div>';

		echo '<div class="sc-card"><h2>Instellingen</h2><table class="form-table sc-table">';
		$this->num( 'login_attempts', 'Mislukte loginpogingen voor blokkade', $s, 'Daarna blokkade van het IP-adres.' );
		$this->num( 'login_lock_min', 'Blokkade (minuten)', $s );
		$this->num( 'login_long_after', 'Na zoveel blokkades: 24 uur', $s );
		printf( '<tr><th>Nooit blokkeren (IP per regel)</th><td><textarea name="login_whitelist" rows="3" cols="40">%s</textarea><p class="sc-muted">Jouw IP: %s</p></td></tr>', esc_textarea( $s['login_whitelist'] ), esc_html( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$this->num( 'media_max', 'Afbeeldingen maximaal (px)', $s, 'Langste zijde; grotere uploads worden verkleind.' );
		$this->num( 'media_quality', 'Webp-kwaliteit', $s, '82 is de beste balans tussen kwaliteit en grootte.' );
		$this->num( 'revisions', 'Maximaal aantal revisies', $s );
		echo '</table></div>';
		submit_button( 'Wijzigingen opslaan' );
		echo '</form></div>';
	}

	private function num( string $k, string $label, array $s, string $help = '' ): void {
		printf( '<tr><th>%s</th><td><input type="number" name="%s" value="%d" class="small-text">%s</td></tr>', esc_html( $label ), esc_attr( $k ), (int) $s[ $k ], $help ? '<p class="sc-muted">' . esc_html( $help ) . '</p>' : '' );
	}

	public function save(): void {
		if ( ! scrolla_is_scrolla_user() || ! check_admin_referer( 'scrolla_core_save' ) ) {
			wp_die( 'Geen toegang.' );
		}
		$s = self::get();
		foreach ( array_keys( scrolla_core_modules() ) as $k ) {
			$s['modules'][ $k ] = empty( $_POST['modules'][ $k ] ) ? 0 : 1;
		}
		foreach ( [ 'login_attempts', 'login_lock_min', 'login_long_after', 'media_max', 'media_quality', 'revisions' ] as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$s[ $k ] = max( 1, absint( $_POST[ $k ] ) );
			}
		}
		$s['media_quality']   = min( 100, $s['media_quality'] );
		$s['login_whitelist'] = sanitize_textarea_field( wp_unslash( $_POST['login_whitelist'] ?? '' ) );
		update_option( self::OPTION, $s );
		wp_safe_redirect( admin_url( 'admin.php?page=scrolla-core&saved=1' ) );
		exit;
	}
}
